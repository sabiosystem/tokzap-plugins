<?php
/**
 * Plugin Name: TokZap WhatsApp OTP
 * Plugin URI:  https://tokzap.com
 * Description: Autenticação OTP via WhatsApp. Adicione verificação de identidade ao seu site WordPress em minutos.
 * Version:     1.0.0
 * Author:      TokZap
 * Author URI:  https://tokzap.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tokzap
 * Domain Path: /languages
 */
defined('ABSPATH') || exit;

define('TOKZAP_VERSION', '1.0.0');
define('TOKZAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOKZAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TOKZAP_API_BASE', 'https://api.tokzap.com/v1');

require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-otp.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-shortcode.php';

/**
 * Classe principal do plugin TokZap.
 */
class TokZap_Plugin
{
    /**
     * Instância única (singleton).
     */
    private static ?TokZap_Plugin $instance = null;

    /**
     * Retorna a instância única.
     */
    public static function instance(): TokZap_Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Inicializa hooks do plugin.
     */
    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_tokzap_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_tokzap_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_tokzap_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_nopriv_tokzap_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_tokzap_test_connection', [$this, 'ajax_test_connection']);

        TokZap_Shortcode::init();
    }

    /**
     * Registra a página de configurações no painel do WordPress.
     */
    public function register_settings_page(): void
    {
        add_options_page(
            __('TokZap WhatsApp OTP', 'tokzap'),
            __('TokZap', 'tokzap'),
            'manage_options',
            'tokzap',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registra os campos de configuração na API Settings do WordPress.
     */
    public function register_settings(): void
    {
        register_setting('tokzap_settings', 'tokzap_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        add_settings_section(
            'tokzap_main',
            __('Configurações', 'tokzap'),
            '__return_false',
            'tokzap'
        );

        add_settings_field(
            'tokzap_api_key',
            __('API Key', 'tokzap'),
            [$this, 'render_api_key_field'],
            'tokzap',
            'tokzap_main'
        );
    }

    /**
     * Renderiza o campo de API Key na página de configurações.
     */
    public function render_api_key_field(): void
    {
        $api_key = get_option('tokzap_api_key', '');
        $masked = $api_key ? substr($api_key, 0, 12).str_repeat('*', max(0, strlen($api_key) - 12)) : '';
        ?>
        <input
            type="password"
            name="tokzap_api_key"
            id="tokzap_api_key"
            value="<?php echo esc_attr($api_key); ?>"
            class="regular-text"
            placeholder="tk_live_xxxxxxxxxxxxxxxxxxxx"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e('Obtenha sua API Key em', 'tokzap'); ?>
            <a href="https://tokzap.com/api-keys" target="_blank">tokzap.com/api-keys</a>.
        </p>
        <?php
    }

    /**
     * Renderiza a página de configurações completa.
     */
    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>
                <span style="color:#39b54a;">&#9679;</span>
                <?php esc_html_e('TokZap WhatsApp OTP', 'tokzap'); ?>
            </h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('tokzap_settings');
        do_settings_sections('tokzap');
        submit_button(__('Salvar', 'tokzap'));
        ?>
            </form>

            <hr />

            <h2><?php esc_html_e('Testar conexão', 'tokzap'); ?></h2>
            <p><?php esc_html_e('Verifique se a API Key está correta consultando o status da instância.', 'tokzap'); ?></p>
            <button
                type="button"
                id="tokzap-test-btn"
                class="button button-secondary"
            >
                <?php esc_html_e('Testar conexão', 'tokzap'); ?>
            </button>
            <span id="tokzap-test-result" style="margin-left:12px; font-weight:600;"></span>

            <hr />

            <h2><?php esc_html_e('Uso do shortcode', 'tokzap'); ?></h2>
            <p><?php esc_html_e('Insira o shortcode abaixo em qualquer página ou post:', 'tokzap'); ?></p>
            <code>[tokzap_verify]</code>
            <p><?php esc_html_e('Com opções:', 'tokzap'); ?></p>
            <code>[tokzap_verify on_success="redirect" redirect_url="/obrigado"]</code>
        </div>

        <script>
        document.getElementById('tokzap-test-btn').addEventListener('click', function() {
            var btn    = this;
            var result = document.getElementById('tokzap-test-result');
            btn.disabled = true;
            result.textContent = '<?php esc_html_e('Verificando…', 'tokzap'); ?>';
            result.style.color = '#666';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=tokzap_test_connection&nonce=<?php echo esc_js(wp_create_nonce('tokzap_test')); ?>'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    result.textContent = '✅ ' + data.data.message;
                    result.style.color = '#39b54a';
                } else {
                    result.textContent = '❌ ' + (data.data ? data.data.message : '<?php esc_html_e('Falha na conexão.', 'tokzap'); ?>');
                    result.style.color = '#dc2626';
                }
            })
            .catch(function() {
                result.textContent = '❌ <?php esc_html_e('Erro de rede.', 'tokzap'); ?>';
                result.style.color = '#dc2626';
            })
            .finally(function() {
                btn.disabled = false;
            });
        });
        </script>
        <?php
    }

    /**
     * Registra scripts e estilos no frontend.
     */
    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'tokzap-widget',
            TOKZAP_PLUGIN_URL.'assets/tokzap-widget.css',
            [],
            TOKZAP_VERSION
        );

        wp_enqueue_script(
            'tokzap-widget',
            TOKZAP_PLUGIN_URL.'assets/tokzap-widget.js',
            [],
            TOKZAP_VERSION,
            true
        );

        wp_localize_script('tokzap-widget', 'tokzapSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tokzap_otp'),
        ]);
    }

    /**
     * Registra scripts no painel administrativo.
     *
     * @param  string  $hook  Nome da página admin atual.
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'settings_page_tokzap') {
            return;
        }

        // Nenhum asset extra necessário — o inline script da página basta.
    }

    /**
     * Handler AJAX: envia OTP para um número de telefone.
     */
    public function ajax_send_otp(): void
    {
        check_ajax_referer('tokzap_otp', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (empty($phone)) {
            wp_send_json_error(['message' => __('Número de telefone é obrigatório.', 'tokzap')]);
        }

        $otp = new TokZap_OTP;
        $result = $otp->send($phone);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Código enviado via WhatsApp.', 'tokzap')]);
    }

    /**
     * Handler AJAX: verifica o código OTP informado.
     */
    public function ajax_verify_otp(): void
    {
        check_ajax_referer('tokzap_otp', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        if (empty($phone) || empty($code)) {
            wp_send_json_error(['message' => __('Telefone e código são obrigatórios.', 'tokzap')]);
        }

        $otp = new TokZap_OTP;
        $result = $otp->verify($phone, $code);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['verified' => true]);
    }

    /**
     * Handler AJAX: testa a conexão com a API TokZap.
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('tokzap_test', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'tokzap')]);
        }

        $api = new TokZap_API;
        $result = $api->get_instance_status();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $status = isset($result['status']) ? $result['status'] : 'unknown';
        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: status da instância */
                __('Conexão OK — instância %s.', 'tokzap'),
                $status
            ),
        ]);
    }
}

TokZap_Plugin::instance();
