<?php

namespace TokZap\Shortcodes;

use TokZap\Api\TokZapClient;

defined('ABSPATH') || exit;

/**
 * Shortcode [tokzap_otp_form] — formulário de verificação OTP via WhatsApp.
 *
 * Renderiza um formulário de três passos (telefone → código → sucesso) e
 * expõe os handlers AJAX tokzap_send_otp e tokzap_verify_otp.
 */
class OtpFormShortcode
{
    /**
     * Client injetado para testes — substituído em produção por null (cria instância nova).
     */
    private static ?TokZapClient $testClient = null;

    /**
     * Injeta um TokZapClient para uso nos testes.
     * Nunca chamar em produção.
     */
    public static function injectClient(TokZapClient $client): void
    {
        self::$testClient = $client;
    }

    /**
     * Remove o client injetado (cleanup de teste).
     */
    public static function clearClient(): void
    {
        self::$testClient = null;
    }

    /**
     * Retorna o client a usar: injetado (testes) ou novo (produção).
     */
    private static function getClient(): TokZapClient
    {
        return self::$testClient ?? new TokZapClient;
    }

    /**
     * Registra o shortcode no WordPress.
     */
    public function register(): void
    {
        add_shortcode('tokzap_otp_form', [self::class, 'render']);
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    /**
     * Renderiza o HTML do formulário OTP de três passos.
     *
     * @param  array<string, string>|string  $atts  Atributos do shortcode
     * @return string HTML gerado
     */
    public static function render(array|string $atts): string
    {
        $atts = shortcode_atts([
            'redirect_url' => '',
            'button_text' => __('Enviar código', 'tokzap'),
            'verify_text' => __('Verificar', 'tokzap'),
            'placeholder' => __('(11) 9 0000-0000', 'tokzap'),
            'title' => __('Verificação via WhatsApp', 'tokzap'),
            'show_title' => 'yes',
        ], $atts, 'tokzap_otp_form');

        $uid = 'tokzap-'.substr(uniqid('', true), -8);
        $redirect = esc_url((string) $atts['redirect_url']);
        $button_text = esc_html((string) $atts['button_text']);
        $verify_text = esc_html((string) $atts['verify_text']);
        $placeholder = esc_attr((string) $atts['placeholder']);
        $title = esc_html((string) $atts['title']);
        $show_title = $atts['show_title'] === 'yes';
        $show_branding = (bool) get_option('tokzap_show_branding', true);

        ob_start();
        ?>
<div class="tokzap-form-wrap"
     id="<?php echo esc_attr($uid); ?>"
     data-redirect="<?php echo esc_attr($redirect); ?>">

    <?php /* Passo 1: telefone */ ?>
    <div class="tokzap-step tokzap-step-phone">
        <?php if ($show_title) { ?>
            <h3 class="tokzap-title"><?php echo $title; ?></h3>
        <?php } ?>

        <div class="tokzap-field">
            <label for="<?php echo esc_attr($uid); ?>-phone">
                <?php esc_html_e('WhatsApp', 'tokzap'); ?>
            </label>
            <input type="tel"
                   id="<?php echo esc_attr($uid); ?>-phone"
                   class="tokzap-phone-input"
                   placeholder="<?php echo $placeholder; ?>"
                   maxlength="20"
                   autocomplete="tel"/>
        </div>

        <button type="button" class="tokzap-btn tokzap-btn-send">
            <?php echo $button_text; ?>
        </button>

        <div class="tokzap-message" aria-live="polite"></div>
    </div>

    <?php /* Passo 2: código OTP */ ?>
    <div class="tokzap-step tokzap-step-code" style="display:none">
        <p class="tokzap-info">
            <?php esc_html_e('Código enviado para', 'tokzap'); ?>
            <strong class="tokzap-phone-display"></strong>
            <button type="button" class="tokzap-btn-link tokzap-btn-change">
                <?php esc_html_e('Trocar número', 'tokzap'); ?>
            </button>
        </p>

        <div class="tokzap-code-inputs" role="group"
             aria-label="<?php esc_attr_e('Código de verificação', 'tokzap'); ?>">
            <?php for ($i = 0; $i < 6; $i++) { ?>
            <input type="text"
                   inputmode="numeric"
                   maxlength="1"
                   class="tokzap-digit"
                   data-index="<?php echo $i; ?>"
                   autocomplete="<?php echo $i === 0 ? 'one-time-code' : 'off'; ?>"/>
            <?php } ?>
        </div>

        <button type="button" class="tokzap-btn tokzap-btn-verify">
            <?php echo $verify_text; ?>
        </button>

        <div class="tokzap-message" aria-live="polite"></div>

        <div class="tokzap-resend">
            <span class="tokzap-countdown">
                <?php esc_html_e('Reenviar em', 'tokzap'); ?>
                <span class="tokzap-timer">60</span>s
            </span>
            <button type="button"
                    class="tokzap-btn-link tokzap-btn-resend"
                    style="display:none">
                <?php esc_html_e('Reenviar código', 'tokzap'); ?>
            </button>
        </div>
    </div>

    <?php /* Passo 3: sucesso */ ?>
    <div class="tokzap-step tokzap-step-success" style="display:none">
        <div class="tokzap-success-icon">✓</div>
        <p><?php esc_html_e('Número verificado com sucesso!', 'tokzap'); ?></p>
    </div>

    <?php if ($show_branding) { ?>
    <p class="tokzap-branding">
        <?php esc_html_e('Verificação segura por', 'tokzap'); ?>
        <a href="https://tokzap.com" target="_blank" rel="noopener">TokZap</a>
    </p>
    <?php } ?>

</div>
        <?php
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // AJAX handlers
    // -----------------------------------------------------------------------

    /**
     * Handler AJAX: envia OTP para o telefone informado.
     *
     * Aplica validação, rate limiting e delega o envio ao TokZapClient.
     */
    public static function ajaxSend(): void
    {
        check_ajax_referer('tokzap_frontend', 'nonce');

        $raw_phone = isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '';
        $phone = preg_replace('/\D/', '', (string) $raw_phone);

        if (strlen($phone) < 10 || strlen($phone) > 13) {
            wp_send_json_error(['message' => __('Número inválido.', 'tokzap')]);

            return;
        }

        // Rate limiting: máximo 3 envios por IP+telefone por hora
        $ip = self::getClientIp();
        $rate_key = 'tokzap_rate_'.md5($ip.'_'.$phone);
        $attempts = (int) get_transient($rate_key);

        if ($attempts >= 3) {
            wp_send_json_error([
                'message' => __('Muitas tentativas. Aguarde 1 hora para tentar novamente.', 'tokzap'),
                'code' => 'too_many_requests',
            ]);

            return;
        }

        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

        $result = self::getClient()->sendOtp($phone);

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'daily_limit_reached') {
                wp_send_json_error([
                    'message' => __('Limite diário atingido.', 'tokzap'),
                    'upgrade_url' => 'https://tokzap.com/billing',
                    'limit' => true,
                ]);

                return;
            }

            wp_send_json_error([
                'message' => __('Erro ao conectar. Tente novamente.', 'tokzap'),
            ]);

            return;
        }

        wp_send_json_success(['message' => __('Código enviado!', 'tokzap')]);
    }

    /**
     * Handler AJAX: verifica o código OTP informado pelo usuário.
     *
     * Em caso de sucesso, salva o telefone verificado no user_meta (se logado)
     * e retorna a URL de redirecionamento configurada no shortcode.
     */
    public static function ajaxVerify(): void
    {
        check_ajax_referer('tokzap_frontend', 'nonce');

        $raw_phone = isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '';
        $raw_code = isset($_POST['code']) ? wp_unslash($_POST['code']) : '';
        $redirect = isset($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : '';

        $phone = preg_replace('/\D/', '', (string) $raw_phone);
        $code = preg_replace('/\D/', '', (string) $raw_code);

        if (strlen($phone) < 10 || strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Código incorreto.', 'tokzap')]);

            return;
        }

        $result = self::getClient()->verifyOtp($phone, $code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => __('Código incorreto.', 'tokzap')]);

            return;
        }

        // Salvar telefone verificado se o usuário estiver logado
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            update_user_meta($user_id, 'tokzap_whatsapp_verified', $phone);
        }

        wp_send_json_success([
            'verified' => true,
            'redirect' => $redirect,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Retorna o IP real do cliente respeitando proxies comuns.
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (! empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // X-Forwarded-For pode conter lista — usar o primeiro
                $parts = explode(',', $ip);

                return trim($parts[0]);
            }
        }

        return '0.0.0.0';
    }
}
