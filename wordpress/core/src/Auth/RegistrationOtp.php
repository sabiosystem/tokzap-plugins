<?php

namespace TokZap\Auth;

use TokZap\Api\TokZapClient;

defined('ABSPATH') || exit;

/**
 * Verificação de WhatsApp no registro de novos usuários.
 *
 * Adiciona um campo de telefone no formulário de cadastro nativo do WordPress,
 * exige que o número seja verificado via OTP antes de criar a conta e
 * persiste o telefone verificado nos metadados do novo usuário.
 */
class RegistrationOtp
{
    private const TRANSIENT_TTL = 600; // 10 minutos

    /**
     * Registra os três hooks necessários.
     */
    public function register(): void
    {
        add_action('register_form', [$this, 'renderPhoneField'], 10);
        add_filter('registration_errors', [$this, 'validateRegistration'], 10, 3);
        add_action('user_register', [$this, 'savePhoneMeta'], 10);
        add_action('wp_ajax_nopriv_tokzap_reg_verify_otp', [$this, 'ajaxRegVerify']);
    }

    // -----------------------------------------------------------------------
    // Hook: register_form
    // -----------------------------------------------------------------------

    /**
     * Renderiza o campo de telefone e os inputs OTP no formulário de cadastro.
     */
    public function renderPhoneField(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $phone = isset($_POST['tokzap_phone'])
            ? esc_attr(sanitize_text_field(wp_unslash($_POST['tokzap_phone'])))
            : '';
        $nonce = wp_create_nonce('tokzap_frontend');
        ?>
        <div class="tokzap-reg-field">
            <label for="tokzap_phone">
                <?php esc_html_e('WhatsApp', 'tokzap'); ?>
                <span class="required" aria-hidden="true">*</span>
            </label>
            <input type="tel"
                   name="tokzap_phone"
                   id="tokzap_phone"
                   class="input"
                   value="<?php echo $phone; ?>"
                   placeholder="(11) 9 0000-0000"
                   autocomplete="tel"/>

            <div id="tokzap-reg-status" aria-live="polite"></div>

            <div id="tokzap-reg-send-wrap" style="margin-top:6px">
                <button type="button"
                        id="tokzap-reg-send"
                        class="button"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php esc_html_e('Enviar código de verificação', 'tokzap'); ?>
                </button>
            </div>

            <div id="tokzap-reg-otp" style="display:none;margin-top:10px">
                <div class="tokzap-code-inputs" role="group"
                     aria-label="<?php esc_attr_e('Código de verificação', 'tokzap'); ?>">
                    <?php for ($i = 0; $i < 6; $i++) { ?>
                    <input type="text"
                           inputmode="numeric"
                           maxlength="1"
                           class="tokzap-digit"
                           data-index="<?php echo $i; ?>"
                           style="width:38px;height:44px;text-align:center;font-size:1.2rem;margin:0 3px"/>
                    <?php } ?>
                </div>
                <button type="button"
                        id="tokzap-reg-verify"
                        class="button"
                        style="margin-top:8px"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php esc_html_e('Verificar', 'tokzap'); ?>
                </button>
            </div>

            <input type="hidden"
                   name="tokzap_phone_verified"
                   id="tokzap_phone_verified"
                   value="0"/>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // Hook: registration_errors
    // -----------------------------------------------------------------------

    /**
     * Valida o campo de telefone e a verificação OTP antes de criar o usuário.
     *
     * @param  \WP_Error  $errors  Objeto de erros do WP
     */
    public function validateRegistration(\WP_Error $errors, string $sanitized_user_login, string $user_email): \WP_Error
    {
        if (! $this->isEnabled()) {
            return $errors;
        }

        $raw_phone = isset($_POST['tokzap_phone']) ? wp_unslash($_POST['tokzap_phone']) : '';
        $phone = preg_replace('/\D/', '', sanitize_text_field((string) $raw_phone));
        $verified_raw = isset($_POST['tokzap_phone_verified']) ? wp_unslash($_POST['tokzap_phone_verified']) : '0';
        $verified = sanitize_text_field((string) $verified_raw);

        if (empty($phone)) {
            $errors->add(
                'tokzap_phone_required',
                __('<strong>Erro</strong>: O número de WhatsApp é obrigatório.', 'tokzap')
            );

            return $errors;
        }

        // Verificar via transient (prova server-side) E via campo hidden
        $transient = get_transient('tokzap_reg_'.$phone);
        if ($verified !== '1' || ! $transient) {
            $errors->add(
                'tokzap_phone_unverified',
                __('<strong>Erro</strong>: Verifique seu número de WhatsApp antes de continuar.', 'tokzap')
            );
        }

        return $errors;
    }

    // -----------------------------------------------------------------------
    // Hook: user_register
    // -----------------------------------------------------------------------

    /**
     * Persiste o telefone verificado nos metadados do novo usuário.
     *
     * @param  int  $user_id  ID do usuário recém-criado
     */
    public function savePhoneMeta(int $user_id): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $raw_phone = isset($_POST['tokzap_phone']) ? wp_unslash($_POST['tokzap_phone']) : '';
        $phone = preg_replace('/\D/', '', sanitize_text_field((string) $raw_phone));

        if (empty($phone)) {
            return;
        }

        update_user_meta($user_id, 'tokzap_whatsapp_phone', $phone);
        update_user_meta($user_id, 'tokzap_whatsapp_verified', $phone);
    }

    // -----------------------------------------------------------------------
    // AJAX: tokzap_reg_verify_otp (somente nopriv — usuários não logados)
    // -----------------------------------------------------------------------

    /**
     * Verifica o OTP durante o registro, sem gravar user_meta ainda
     * (o usuário ainda não existe). Armazena prova de verificação em transient.
     */
    public function ajaxRegVerify(): void
    {
        check_ajax_referer('tokzap_frontend', 'nonce');

        $raw_phone = isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '';
        $raw_code = isset($_POST['code']) ? wp_unslash($_POST['code']) : '';

        $phone = preg_replace('/\D/', '', sanitize_text_field((string) $raw_phone));
        $code = preg_replace('/\D/', '', sanitize_text_field((string) $raw_code));

        if (strlen($phone) < 10 || strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Código incorreto.', 'tokzap')]);

            return;
        }

        $client = new TokZapClient;
        $result = $client->verifyOtp($phone, $code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => __('Código incorreto.', 'tokzap')]);

            return;
        }

        // Gravar prova de verificação (server-side) por 10 minutos
        set_transient('tokzap_reg_'.$phone, '1', self::TRANSIENT_TTL);

        wp_send_json_success([
            'verified' => true,
            'message' => __('Número verificado!', 'tokzap'),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Verifica se a verificação no registro está habilitada nas configurações.
     */
    private function isEnabled(): bool
    {
        return (bool) get_option('tokzap_registration_otp', false);
    }
}
