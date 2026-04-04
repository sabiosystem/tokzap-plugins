<?php

/**
 * Cliente HTTP para a API TokZap.
 *
 * Encapsula todas as chamadas à API REST do TokZap usando wp_remote_post/get,
 * mantendo a API Key segura e centralizada.
 */
defined('ABSPATH') || exit;

/**
 * Classe TokZap_API
 *
 * Responsável por autenticar e executar requisições HTTP à API TokZap.
 */
class TokZap_API
{
    /**
     * API Key do TokZap (formato: tk_live_xxxxxxxxxxxxxxxxxxxx).
     */
    private string $api_key;

    /**
     * Inicializa o cliente com a API Key fornecida ou a salva nas configurações.
     *
     * Aceitar a key como parâmetro permite testar a conexão antes de salvar,
     * sem alterar a opção atual do banco de dados.
     *
     * @param  string|null  $apiKey  API Key a usar; usa get_option se null.
     */
    public function __construct(?string $apiKey = null)
    {
        $this->api_key = $apiKey ?? (string) get_option('tokzap_api_key', '');
    }

    /**
     * Monta os headers padrão para todas as requisições.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Executa uma requisição POST para um endpoint da API.
     *
     * @param  string  $endpoint  Caminho relativo, ex: /otp/send
     * @param  array<string, mixed>  $body  Dados a enviar como JSON
     * @return array<string, mixed>|WP_Error Dados decodificados ou WP_Error
     */
    public function post(string $endpoint, array $body): array|WP_Error
    {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API Key não configurada. Acesse Configurações → TokZap.', 'tokzap'));
        }

        $response = wp_remote_post(TOKZAP_API_BASE.$endpoint, [
            'headers' => $this->headers(),
            'body' => wp_json_encode($body),
            'timeout' => 15,
        ]);

        return $this->parse_response($response);
    }

    /**
     * Executa uma requisição GET para um endpoint da API.
     *
     * @param  string  $endpoint  Caminho relativo, ex: /instance/status
     * @return array<string, mixed>|WP_Error Dados decodificados ou WP_Error
     */
    public function get(string $endpoint): array|WP_Error
    {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API Key não configurada. Acesse Configurações → TokZap.', 'tokzap'));
        }

        $response = wp_remote_get(TOKZAP_API_BASE.$endpoint, [
            'headers' => $this->headers(),
            'timeout' => 15,
        ]);

        return $this->parse_response($response);
    }

    /**
     * Processa a resposta HTTP e retorna os dados ou um WP_Error.
     *
     * @param  array<mixed>|WP_Error  $response  Resultado de wp_remote_*
     * @return array<string, mixed>|WP_Error
     */
    private function parse_response(array|WP_Error $response): array|WP_Error
    {
        if (is_wp_error($response)) {
            return new WP_Error('request_failed', __('Falha na requisição à API TokZap: ', 'tokzap').$response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Resposta inválida da API TokZap.', 'tokzap'));
        }

        if ($status_code === 429) {
            $message = isset($data['message']) ? $data['message'] : __('Limite diário de OTPs atingido.', 'tokzap');

            return new WP_Error('daily_limit_reached', $message);
        }

        if ($status_code === 401) {
            return new WP_Error('unauthorized', __('API Key inválida ou revogada.', 'tokzap'));
        }

        if ($status_code === 503) {
            return new WP_Error('instance_disconnected', __('Instância WhatsApp desconectada. Reconecte no painel TokZap.', 'tokzap'));
        }

        if ($status_code < 200 || $status_code >= 300) {
            $message = isset($data['message']) ? $data['message'] : __('Erro desconhecido na API TokZap.', 'tokzap');

            return new WP_Error('api_error', $message);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Consulta o status da instância WhatsApp vinculada à API Key.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_instance_status(): array|WP_Error
    {
        return $this->get('/instance/status');
    }
}
