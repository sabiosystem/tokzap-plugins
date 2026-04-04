<?php

/**
 * Shortcode [tokzap_verify] para verificação OTP via WhatsApp.
 */
defined('ABSPATH') || exit;

/**
 * Classe TokZap_Shortcode
 *
 * Registra e renderiza o shortcode [tokzap_verify] que exibe o formulário
 * de verificação OTP do TokZap em qualquer página ou post do WordPress.
 *
 * Uso:
 *   [tokzap_verify]
 *   [tokzap_verify on_success="redirect" redirect_url="/obrigado"]
 *   [tokzap_verify on_success="message" success_message="Obrigado por verificar!"]
 *   [tokzap_verify phone_field="billing_phone"]
 */
class TokZap_Shortcode
{
    /**
     * Registra o shortcode no WordPress.
     */
    public static function init(): void
    {
        add_shortcode('tokzap_verify', [__CLASS__, 'render']);
    }

    /**
     * Renderiza o shortcode e retorna o HTML do formulário.
     *
     * @param  array<string, string>|string  $atts  Atributos do shortcode
     * @return string HTML do formulário de verificação
     */
    public static function render(array|string $atts): string
    {
        $atts = shortcode_atts(
            [
                'phone_field' => '',
                'on_success' => 'message',
                'redirect_url' => '',
                'success_message' => __('Número verificado com sucesso!', 'tokzap'),
            ],
            $atts,
            'tokzap_verify'
        );

        $redirect_url = ! empty($atts['redirect_url'])
            ? esc_url($atts['redirect_url'])
            : '';

        $success_message = esc_html($atts['success_message']);
        $on_success = in_array($atts['on_success'], ['redirect', 'message'], true)
            ? $atts['on_success']
            : 'message';

        $nonce = wp_create_nonce('tokzap_otp');

        ob_start();
        include TOKZAP_PLUGIN_DIR.'templates/verify-form.php';

        return (string) ob_get_clean();
    }
}
