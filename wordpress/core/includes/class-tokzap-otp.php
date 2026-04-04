<?php

/**
 * Lógica de envio e verificação de OTP via API TokZap.
 */
defined('ABSPATH') || exit;

/**
 * Classe TokZap_OTP
 *
 * Encapsula as operações de envio e verificação de OTP
 * delegando as chamadas HTTP ao TokZap_API.
 */
class TokZap_OTP
{
    /**
     * Cliente da API TokZap.
     */
    private TokZap_API $api;

    /**
     * Inicializa o cliente de OTP.
     */
    public function __construct()
    {
        $this->api = new TokZap_API;
    }

    /**
     * Envia um OTP para o número de telefone informado.
     *
     * @param  string  $phone  Número no formato E.164 sem "+", ex: 5511999999999
     * @return true|WP_Error Retorna true em sucesso ou WP_Error em falha
     */
    public function send(string $phone): bool|WP_Error
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) < 10) {
            return new WP_Error('invalid_phone', __('Número de telefone inválido.', 'tokzap'));
        }

        $result = $this->api->post('/otp/send', ['phone' => $phone]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['success'])) {
            $message = isset($result['message']) ? $result['message'] : __('Falha ao enviar OTP.', 'tokzap');

            return new WP_Error('send_failed', $message);
        }

        return true;
    }

    /**
     * Verifica um código OTP informado pelo usuário.
     *
     * @param  string  $phone  Número no formato E.164 sem "+"
     * @param  string  $code  Código de 6 dígitos
     * @return true|WP_Error Retorna true se verificado ou WP_Error em falha
     */
    public function verify(string $phone, string $code): bool|WP_Error
    {
        $phone = preg_replace('/\D/', '', $phone);
        $code = preg_replace('/\D/', '', $code);

        if (strlen($phone) < 10) {
            return new WP_Error('invalid_phone', __('Número de telefone inválido.', 'tokzap'));
        }

        if (strlen($code) !== 6) {
            return new WP_Error('invalid_code', __('O código deve ter 6 dígitos.', 'tokzap'));
        }

        $result = $this->api->post('/otp/verify', [
            'phone' => $phone,
            'code' => $code,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['success']) || empty($result['verified'])) {
            return new WP_Error('invalid_otp', __('Código inválido ou expirado.', 'tokzap'));
        }

        return true;
    }
}
