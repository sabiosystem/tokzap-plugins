<?php

namespace TokZap\Auth;

use TokZap\Api\TokZapClient;
use TokZap\Shortcodes\OtpFormShortcode;

defined('ABSPATH') || exit;

/**
 * Verificação em duas etapas (2FA) via WhatsApp no login do WordPress.
 *
 * Estratégia:
 * 1. wp_authenticate_user — intercepta após validação de senha.
 *    Se 2FA estiver ativo e o usuário tiver WhatsApp verificado,
 *    gera um token temporário, seta cookie e retorna WP_Error para
 *    interromper o login normal.
 * 2. login_message — se o cookie 2FA estiver presente, renderiza
 *    o formulário OTP inline na tela de login.
 * 3. AJAX tokzap_2fa_complete — valida o OTP, seta o cookie de auth
 *    do WP e retorna URL de redirecionamento.
 */
class LoginOtp
{
    private const COOKIE_NAME = 'tokzap_2fa_token';

    private const TRANSIENT_TTL = 300;  // 5 minutos

    private const MAX_ATTEMPTS = 5;

    /**
     * Registra todos os hooks necessários.
     */
    public function register(): void
    {
        add_filter('wp_authenticate_user', [$this, 'handleAuthentication'], 10, 2);
        add_filter('login_message', [$this, 'handleLoginMessage'], 10);
        add_action('wp_ajax_nopriv_tokzap_2fa_complete', [$this, 'ajax2faComplete']);
    }

    // -----------------------------------------------------------------------
    // Hook: wp_authenticate_user
    // -----------------------------------------------------------------------

    /**
     * Intercepta o login após validação da senha.
     *
     * Retorna WP_Error para suspender o login e iniciar o fluxo 2FA,
     * ou retorna $user sem modificação se 2FA não se aplica.
     */
    public function handleAuthentication(\WP_User|\WP_Error $user, string $password): \WP_User|\WP_Error
    {
        // Propagar erros anteriores (ex: senha errada)
        if (is_wp_error($user)) {
            return $user;
        }

        // 2FA desabilitado nas configurações
        if (! $this->is2faEnabled()) {
            return $user;
        }

        $phone = get_user_meta($user->ID, 'tokzap_whatsapp_verified', true);

        // Usuário sem WhatsApp verificado
        if (empty($phone)) {
            // Se 2FA for opcional, deixa passar
            if (! $this->is2faRequired()) {
                return $user;
            }

            // 2FA obrigatório mas sem telefone: bloqueia com mensagem amigável
            return new \WP_Error(
                'tokzap_2fa_no_phone',
                __('Verifique seu WhatsApp em seu perfil antes de fazer login com 2FA ativo.', 'tokzap')
            );
        }

        // Gerar token e armazenar estado 2FA em transient
        $token = wp_generate_password(32, false);
        set_transient('tokzap_2fa_'.$token, [
            'user_id' => $user->ID,
            'phone' => (string) $phone,
            'remember' => false,
        ], self::TRANSIENT_TTL);

        // Cookie com o token (sem expiry = session cookie)
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => 0,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

        return new \WP_Error('tokzap_2fa_required', 'otp_pending');
    }

    // -----------------------------------------------------------------------
    // Hook: login_message
    // -----------------------------------------------------------------------

    /**
     * Injeta o formulário OTP na tela de login quando 2FA está pendente.
     *
     * @param  string  $message  Mensagem atual da tela de login
     * @return string Mensagem com o formulário OTP appended (se aplicável)
     */
    public function handleLoginMessage(string $message): string
    {
        if (! $this->has2faPendingCookie()) {
            return $message;
        }

        // Enqueue assets do frontend na tela de login
        add_action('login_enqueue_scripts', function () {
            wp_enqueue_style('tokzap-form', TOKZAP_PLUGIN_URL.'assets/css/form.css', [], TOKZAP_VERSION);
            wp_enqueue_script('tokzap-frontend', TOKZAP_PLUGIN_URL.'assets/js/frontend.js', ['jquery'], TOKZAP_VERSION, true);
            wp_localize_script('tokzap-frontend', 'tokzapFrontend', $this->frontendL10n());
        });

        ob_start();
        ?>
        <div class="tokzap-2fa-wrap" style="margin-bottom:20px">
            <p style="font-size:13px;color:#444;margin-bottom:8px">
                <?php esc_html_e('Digite o código enviado ao seu WhatsApp para continuar.', 'tokzap'); ?>
            </p>
            <?php
            // Renderizar o formulário OTP — sem redirect (login_url é o default)
            echo OtpFormShortcode::render([
                'show_title' => 'no',
                'redirect_url' => '',
                'button_text' => __('Enviar código', 'tokzap'),
                'verify_text' => __('Verificar e entrar', 'tokzap'),
            ]);
        ?>
        </div>
        <?php
        $form = (string) ob_get_clean();

        return $message.$form;
    }

    // -----------------------------------------------------------------------
    // AJAX: tokzap_2fa_complete
    // -----------------------------------------------------------------------

    /**
     * Completa o fluxo 2FA: valida OTP, autentica o usuário no WP e
     * retorna a URL de redirecionamento pós-login.
     */
    public function ajax2faComplete(): void
    {
        check_ajax_referer('tokzap_frontend', 'nonce');

        // Ler token do cookie
        $token = isset($_COOKIE[self::COOKIE_NAME])
            ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]))
            : '';

        if (empty($token)) {
            wp_send_json_error(['message' => __('Sessão expirada. Faça login novamente.', 'tokzap')]);

            return;
        }

        $state = get_transient('tokzap_2fa_'.$token);

        if (! $state || ! is_array($state)) {
            wp_send_json_error(['message' => __('Sessão expirada. Faça login novamente.', 'tokzap')]);

            return;
        }

        $raw_code = isset($_POST['code']) ? wp_unslash($_POST['code']) : '';
        $code = preg_replace('/\D/', '', (string) $raw_code);

        if (strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Digite os 6 dígitos do código.', 'tokzap')]);

            return;
        }

        // Controle de tentativas
        $attempts_key = 'tokzap_2fa_attempts_'.$token;
        $attempts = (int) get_transient($attempts_key);

        if ($attempts >= self::MAX_ATTEMPTS) {
            delete_transient('tokzap_2fa_'.$token);
            wp_send_json_error([
                'message' => __('Máximo de tentativas excedido. Faça login novamente.', 'tokzap'),
                'force_logout' => true,
            ]);

            return;
        }

        $result = (new TokZapClient)->verifyOtp((string) $state['phone'], $code);

        if (is_wp_error($result)) {
            set_transient($attempts_key, $attempts + 1, self::TRANSIENT_TTL);
            wp_send_json_error(['message' => __('Código incorreto.', 'tokzap')]);

            return;
        }

        // Sucesso: autenticar o usuário e limpar estado 2FA
        delete_transient('tokzap_2fa_'.$token);
        delete_transient($attempts_key);

        $remember = (bool) ($state['remember'] ?? false);
        wp_set_auth_cookie((int) $state['user_id'], $remember);

        $redirect = admin_url();
        $user = get_userdata((int) $state['user_id']);
        if ($user && ! user_can($user, 'edit_posts')) {
            $redirect = home_url('/');
        }

        wp_send_json_success(['redirect' => $redirect]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Verifica se o 2FA está habilitado nas configurações do plugin.
     */
    private function is2faEnabled(): bool
    {
        return (bool) get_option('tokzap_2fa_enabled', false);
    }

    /**
     * Verifica se o 2FA é obrigatório para todos os usuários.
     */
    private function is2faRequired(): bool
    {
        return (bool) get_option('tokzap_2fa_required', false);
    }

    /**
     * Verifica se há um cookie de 2FA pendente com transient válido.
     */
    private function has2faPendingCookie(): bool
    {
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        $token = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));

        return (bool) get_transient('tokzap_2fa_'.$token);
    }

    /**
     * Retorna o array de strings i18n para o script frontend.
     *
     * @return array<string, mixed>
     */
    private function frontendL10n(): array
    {
        return [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tokzap_frontend'),
            'i18n' => [
                'sending' => __('Enviando...', 'tokzap'),
                'verifying' => __('Verificando...', 'tokzap'),
                'invalidPhone' => __('Digite um número válido com DDD.', 'tokzap'),
                'invalidCode' => __('Digite os 6 dígitos do código.', 'tokzap'),
            ],
        ];
    }
}
