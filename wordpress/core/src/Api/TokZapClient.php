<?php

namespace TokZap\Api;

defined('ABSPATH') || exit;

/**
 * Cliente HTTP namespaced para a API TokZap.
 *
 * Encapsula envio de OTP, verificação e status de instância,
 * delegando as chamadas HTTP à classe legada TokZap_API.
 */
class TokZapClient
{
    private \TokZap_API $api;

    /**
     * @param  string|null  $apiKey  API Key a usar; usa get_option se null.
     */
    public function __construct(?string $apiKey = null)
    {
        $this->api = new \TokZap_API($apiKey);
    }

    /**
     * Envia um OTP para o número de telefone informado.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function sendOtp(string $phone): array|\WP_Error
    {
        return $this->api->post('/otp/send', ['phone' => $phone]);
    }

    /**
     * Verifica um código OTP para o número informado.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function verifyOtp(string $phone, string $code): array|\WP_Error
    {
        return $this->api->post('/otp/verify', ['phone' => $phone, 'code' => $code]);
    }

    /**
     * Retorna o status da instância WhatsApp vinculada à API Key.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function getInstanceStatus(): array|\WP_Error
    {
        return $this->api->get_instance_status();
    }
}
