<?php

/**
 * Página de configurações do TokZap no painel WordPress.
 *
 * Registra o submenu em Configurações > TokZap, todos os campos via
 * Settings API nativa e o handler AJAX para teste de conexão em tempo real.
 */
defined('ABSPATH') || exit;

/**
 * Gerencia a página de configurações do plugin TokZap.
 */
class TokZap_SettingsPage
{
    /**
     * Registra todos os hooks necessários.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_tokzap_test_connection', [$this, 'ajax_test_connection']);
    }

    // -----------------------------------------------------------------------
    // Menu
    // -----------------------------------------------------------------------

    /**
     * Registra o submenu em Configurações > TokZap.
     */
    public function register_menu(): void
    {
        add_options_page(
            __('TokZap WhatsApp OTP', 'tokzap'),
            __('TokZap', 'tokzap'),
            'manage_options',
            'tokzap-settings',
            [$this, 'render_page']
        );
    }

    // -----------------------------------------------------------------------
    // Settings API
    // -----------------------------------------------------------------------

    /**
     * Registra todas as opções, seções e campos via Settings API.
     */
    public function register_settings(): void
    {
        // --- Opções ---
        register_setting('tokzap_settings', 'tokzap_api_key', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => '',
        ]);

        register_setting('tokzap_settings', 'tokzap_2fa_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => static fn ($v) => (bool) absint($v),
            'default' => false,
        ]);

        register_setting('tokzap_settings', 'tokzap_2fa_required', [
            'type' => 'boolean',
            'sanitize_callback' => static fn ($v) => (bool) absint($v),
            'default' => false,
        ]);

        register_setting('tokzap_settings', 'tokzap_registration_otp', [
            'type' => 'boolean',
            'sanitize_callback' => static fn ($v) => (bool) absint($v),
            'default' => false,
        ]);

        register_setting('tokzap_settings', 'tokzap_custom_message', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);

        register_setting('tokzap_settings', 'tokzap_remove_branding', [
            'type' => 'boolean',
            'sanitize_callback' => static fn ($v) => (bool) absint($v),
            'default' => false,
        ]);

        // --- Seção 1: Conexão ---
        add_settings_section(
            'tokzap_section_api',
            __('Conexão com a API', 'tokzap'),
            '__return_false',
            'tokzap-settings'
        );

        add_settings_field(
            'tokzap_api_key',
            __('API Key', 'tokzap'),
            [$this, 'render_api_key_field'],
            'tokzap-settings',
            'tokzap_section_api'
        );

        // --- Seção 2: 2FA ---
        add_settings_section(
            'tokzap_section_2fa',
            __('Login com WhatsApp (2FA)', 'tokzap'),
            '__return_false',
            'tokzap-settings'
        );

        add_settings_field(
            'tokzap_2fa_enabled',
            __('Verificação em duas etapas', 'tokzap'),
            [$this, 'render_2fa_enabled_field'],
            'tokzap-settings',
            'tokzap_section_2fa'
        );

        add_settings_field(
            'tokzap_2fa_required',
            __('Tornar obrigatório para todos', 'tokzap'),
            [$this, 'render_2fa_required_field'],
            'tokzap-settings',
            'tokzap_section_2fa'
        );

        // --- Seção 3: Cadastro ---
        add_settings_section(
            'tokzap_section_registration',
            __('Cadastro com verificação', 'tokzap'),
            '__return_false',
            'tokzap-settings'
        );

        add_settings_field(
            'tokzap_registration_otp',
            __('Verificação no registro', 'tokzap'),
            [$this, 'render_registration_field'],
            'tokzap-settings',
            'tokzap_section_registration'
        );

        // --- Seção 4: Personalização ---
        add_settings_section(
            'tokzap_section_customization',
            __('Personalização', 'tokzap'),
            '__return_false',
            'tokzap-settings'
        );

        add_settings_field(
            'tokzap_custom_message',
            __('Mensagem personalizada do OTP', 'tokzap'),
            [$this, 'render_custom_message_field'],
            'tokzap-settings',
            'tokzap_section_customization'
        );

        add_settings_field(
            'tokzap_remove_branding',
            __('Remover marca TokZap', 'tokzap'),
            [$this, 'render_remove_branding_field'],
            'tokzap-settings',
            'tokzap_section_customization'
        );
    }

    /**
     * Sanitiza a API Key: strip tags, trim, verifica formato tk_live_.
     */
    public function sanitize_api_key(string $value): string
    {
        $clean = sanitize_text_field($value);

        // Aceita string vazia (remoção intencional da key)
        if ($clean === '') {
            return '';
        }

        return $clean;
    }

    // -----------------------------------------------------------------------
    // Render fields
    // -----------------------------------------------------------------------

    /**
     * Renderiza o campo de API Key com máscara e botão de teste.
     */
    public function render_api_key_field(): void
    {
        $saved = (string) get_option('tokzap_api_key', '');
        // Mostrar os primeiros 8 chars + máscara se já configurada
        $display = $saved !== ''
            ? substr($saved, 0, 8).str_repeat('•', max(0, strlen($saved) - 8))
            : '';
        ?>
        <div class="tokzap-api-key-wrap">
            <input
                type="password"
                name="tokzap_api_key"
                id="tokzap_api_key"
                value="<?php echo esc_attr($saved); ?>"
                class="regular-text"
                placeholder="tk_live_xxxxxxxxxxxxxxxxxxxx"
                autocomplete="new-password"
                spellcheck="false"
            />
            <?php if ($display !== '') { ?>
                <p class="tokzap-key-preview">
                    <?php esc_html_e('Configurada:', 'tokzap'); ?>
                    <code><?php echo esc_html($display); ?></code>
                </p>
            <?php } ?>
            <p class="description">
                <?php esc_html_e('Obtenha sua API Key em', 'tokzap'); ?>
                <a href="https://tokzap.com/api-keys" target="_blank" rel="noopener">
                    tokzap.com/api-keys
                </a>.
            </p>

            <button
                type="button"
                id="tokzap-test-btn"
                class="button button-secondary tokzap-test-btn"
            >
                <?php esc_html_e('Testar conexão', 'tokzap'); ?>
            </button>

            <div id="tokzap-connection-status" class="tokzap-connection-status" aria-live="polite"></div>
        </div>
        <?php
    }

    /**
     * Renderiza o checkbox de habilitação do 2FA.
     */
    public function render_2fa_enabled_field(): void
    {
        $checked = (bool) get_option('tokzap_2fa_enabled', false);
        ?>
        <label for="tokzap_2fa_enabled">
            <input
                type="checkbox"
                name="tokzap_2fa_enabled"
                id="tokzap_2fa_enabled"
                value="1"
                <?php checked($checked); ?>
            />
            <?php esc_html_e('Habilitar verificação em duas etapas', 'tokzap'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Após digitar a senha, o usuário recebe um código no WhatsApp antes de acessar o painel.', 'tokzap'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza o checkbox de 2FA obrigatório (depende do 2FA habilitado).
     */
    public function render_2fa_required_field(): void
    {
        $enabled = (bool) get_option('tokzap_2fa_enabled', false);
        $checked = (bool) get_option('tokzap_2fa_required', false);
        ?>
        <label for="tokzap_2fa_required">
            <input
                type="checkbox"
                name="tokzap_2fa_required"
                id="tokzap_2fa_required"
                value="1"
                <?php checked($checked); ?>
                <?php disabled(! $enabled); ?>
            />
            <?php esc_html_e('Tornar obrigatório para todos', 'tokzap'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Se desmarcado, o usuário pode pular a verificação.', 'tokzap'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza o checkbox de OTP obrigatório no registro.
     */
    public function render_registration_field(): void
    {
        $checked = (bool) get_option('tokzap_registration_otp', false);
        ?>
        <label for="tokzap_registration_otp">
            <input
                type="checkbox"
                name="tokzap_registration_otp"
                id="tokzap_registration_otp"
                value="1"
                <?php checked($checked); ?>
            />
            <?php esc_html_e('Exigir verificação de WhatsApp no registro', 'tokzap'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Adiciona campo de telefone no formulário de cadastro e exige verificação antes de criar a conta.', 'tokzap'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza a textarea de mensagem personalizada (bloqueada no plano Free).
     */
    public function render_custom_message_field(): void
    {
        $value = (string) get_option('tokzap_custom_message', '');
        $is_free = $this->is_free_plan();
        ?>
        <textarea
            name="tokzap_custom_message"
            id="tokzap_custom_message"
            rows="3"
            cols="50"
            class="large-text<?php echo $is_free ? ' tokzap-locked' : ''; ?>"
            placeholder="<?php esc_attr_e('Seu código de verificação é: {{code}}'."\n".'Enviado por [Sua Empresa]', 'tokzap'); ?>"
            <?php disabled($is_free); ?>
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Use {{code}} para inserir o código OTP na mensagem.', 'tokzap'); ?>
        </p>
        <?php if ($is_free) { ?>
            <p class="tokzap-upgrade-notice">
                <?php esc_html_e('Disponível a partir do plano Starter.', 'tokzap'); ?>
                <a href="https://tokzap.com/billing" target="_blank" rel="noopener">
                    <?php esc_html_e('Fazer upgrade →', 'tokzap'); ?>
                </a>
            </p>
        <?php } ?>
        <?php
    }

    /**
     * Renderiza o checkbox de remoção de branding (bloqueado no plano Free).
     */
    public function render_remove_branding_field(): void
    {
        $checked = (bool) get_option('tokzap_remove_branding', false);
        $is_free = $this->is_free_plan();
        ?>
        <label for="tokzap_remove_branding" class="<?php echo $is_free ? 'tokzap-locked' : ''; ?>">
            <input
                type="checkbox"
                name="tokzap_remove_branding"
                id="tokzap_remove_branding"
                value="1"
                <?php checked($checked); ?>
                <?php disabled($is_free); ?>
            />
            <?php esc_html_e('Remover marca TokZap do formulário', 'tokzap'); ?>
        </label>
        <?php if ($is_free) { ?>
            <p class="tokzap-upgrade-notice">
                <?php esc_html_e('Disponível a partir do plano Starter.', 'tokzap'); ?>
                <a href="https://tokzap.com/billing" target="_blank" rel="noopener">
                    <?php esc_html_e('Fazer upgrade →', 'tokzap'); ?>
                </a>
            </p>
        <?php } ?>
        <?php
    }

    // -----------------------------------------------------------------------
    // Assets
    // -----------------------------------------------------------------------

    /**
     * Registra CSS e JS do admin apenas na tela de configurações do TokZap.
     *
     * @param  string  $hook  Identificador da página admin atual.
     */
    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'settings_page_tokzap-settings') {
            return;
        }

        wp_enqueue_style(
            'tokzap-admin',
            TOKZAP_PLUGIN_URL.'assets/css/admin.css',
            [],
            TOKZAP_VERSION
        );

        wp_enqueue_script(
            'tokzap-admin',
            TOKZAP_PLUGIN_URL.'assets/js/admin.js',
            ['jquery'],
            TOKZAP_VERSION,
            true
        );

        wp_localize_script('tokzap-admin', 'tokzapAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tokzap_admin'),
            'i18n' => [
                'testing' => __('Testando...', 'tokzap'),
                'test' => __('Testar conexão', 'tokzap'),
                'connected' => __('Conectado', 'tokzap'),
                'disconnected' => __('Instância desconectada', 'tokzap'),
                'invalid' => __('API Key inválida', 'tokzap'),
                'empty_key' => __('Digite a API Key antes de testar.', 'tokzap'),
                'network_error' => __('Erro de rede. Tente novamente.', 'tokzap'),
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Render page
    // -----------------------------------------------------------------------

    /**
     * Renderiza a página completa de configurações com layout duas colunas.
     */
    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $plan = $this->current_plan_label();
        $is_free = $this->is_free_plan();
        ?>
        <div class="wrap tokzap-settings-wrap" id="tokzap-settings-wrap">

            <?php /* Header */ ?>
            <div class="tokzap-header">
                <div class="tokzap-logo">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="14" cy="14" r="14" fill="#39b54a"/>
                        <path d="M8 14.5L12.5 19L20 10" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="tokzap-logo-text">TokZap</span>
                </div>
                <span class="tokzap-plan-badge tokzap-plan-<?php echo esc_attr(strtolower(get_option('tokzap_plan', 'free'))); ?>">
                    <?php echo esc_html($plan); ?>
                </span>
            </div>

            <div class="tokzap-settings-layout">

                <?php /* Coluna principal — formulário */ ?>
                <div class="tokzap-settings-main">
                    <form method="post" action="options.php" id="tokzap-settings-form">
                        <?php settings_fields('tokzap_settings'); ?>

                        <?php /* Seção 1 — Conexão */ ?>
                        <div class="tokzap-section">
                            <h2 class="tokzap-section-title">
                                <?php esc_html_e('Conexão com a API', 'tokzap'); ?>
                            </h2>
                            <table class="form-table" role="presentation">
                                <?php do_settings_fields('tokzap-settings', 'tokzap_section_api'); ?>
                            </table>
                        </div>

                        <?php /* Seção 2 — 2FA */ ?>
                        <div class="tokzap-section">
                            <h2 class="tokzap-section-title">
                                <?php esc_html_e('Login com WhatsApp (2FA)', 'tokzap'); ?>
                            </h2>
                            <table class="form-table" role="presentation">
                                <?php do_settings_fields('tokzap-settings', 'tokzap_section_2fa'); ?>
                            </table>
                        </div>

                        <?php /* Seção 3 — Cadastro */ ?>
                        <div class="tokzap-section">
                            <h2 class="tokzap-section-title">
                                <?php esc_html_e('Cadastro com verificação', 'tokzap'); ?>
                            </h2>
                            <table class="form-table" role="presentation">
                                <?php do_settings_fields('tokzap-settings', 'tokzap_section_registration'); ?>
                            </table>
                        </div>

                        <?php /* Seção 4 — Personalização */ ?>
                        <div class="tokzap-section">
                            <h2 class="tokzap-section-title">
                                <?php esc_html_e('Personalização', 'tokzap'); ?>
                            </h2>
                            <table class="form-table" role="presentation">
                                <?php do_settings_fields('tokzap-settings', 'tokzap_section_customization'); ?>
                            </table>
                        </div>

                        <?php submit_button(__('Salvar configurações', 'tokzap')); ?>
                    </form>
                </div>

                <?php /* Sidebar */ ?>
                <aside class="tokzap-settings-sidebar">

                    <div class="tokzap-card">
                        <h3><?php esc_html_e('Documentação', 'tokzap'); ?></h3>
                        <p><?php esc_html_e('Guias de instalação, shortcodes e integração com WooCommerce.', 'tokzap'); ?></p>
                        <a href="https://tokzap.com/docs" target="_blank" rel="noopener" class="button button-secondary tokzap-card-link">
                            <?php esc_html_e('Ver documentação →', 'tokzap'); ?>
                        </a>
                    </div>

                    <div class="tokzap-card">
                        <h3><?php esc_html_e('Suporte', 'tokzap'); ?></h3>
                        <p><?php esc_html_e('Dúvidas ou problemas? Nossa equipe está pronta para ajudar.', 'tokzap'); ?></p>
                        <a href="https://tokzap.com/support" target="_blank" rel="noopener" class="button button-secondary tokzap-card-link">
                            <?php esc_html_e('Abrir suporte →', 'tokzap'); ?>
                        </a>
                    </div>

                    <div class="tokzap-card">
                        <h3><?php esc_html_e('Shortcode', 'tokzap'); ?></h3>
                        <p><?php esc_html_e('Adicione o formulário de verificação em qualquer página:', 'tokzap'); ?></p>
                        <div class="tokzap-shortcode-wrap">
                            <code id="tokzap-shortcode-text">[tokzap_otp_form]</code>
                            <button
                                type="button"
                                id="tokzap-copy-shortcode"
                                class="button button-small"
                                aria-label="<?php esc_attr_e('Copiar shortcode', 'tokzap'); ?>"
                            >
                                <?php esc_html_e('Copiar', 'tokzap'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if ($is_free) { ?>
                    <div class="tokzap-card tokzap-card-upgrade">
                        <h3><?php esc_html_e('Faça o upgrade', 'tokzap'); ?></h3>
                        <p>
                            <?php esc_html_e('Remova a marca TokZap, personalize a mensagem OTP e desbloqueie envios ilimitados.', 'tokzap'); ?>
                        </p>
                        <a
                            href="https://tokzap.com/billing"
                            target="_blank"
                            rel="noopener"
                            class="button button-primary tokzap-card-link"
                        >
                            <?php esc_html_e('Ver planos →', 'tokzap'); ?>
                        </a>
                    </div>
                    <?php } ?>

                </aside>

            </div><!-- .tokzap-settings-layout -->
        </div><!-- .tokzap-settings-wrap -->
        <?php
    }

    // -----------------------------------------------------------------------
    // AJAX
    // -----------------------------------------------------------------------

    /**
     * Handler AJAX para teste de conexão em tempo real.
     *
     * Instancia TokZap_API com a key enviada no POST (não a salva),
     * permitindo testar antes de salvar o formulário.
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('tokzap_admin', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão insuficiente.', 'tokzap')], 403);
        }

        $raw_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $api_key = $raw_key !== '' ? $raw_key : (string) get_option('tokzap_api_key', '');

        if (empty($api_key)) {
            wp_send_json_success([
                'status' => 'invalid',
                'phone' => '',
            ]);

            return;
        }

        $api = new TokZap_API($api_key);
        $result = $api->get_instance_status();

        if (is_wp_error($result)) {
            $status = match ($result->get_error_code()) {
                'unauthorized' => 'invalid',
                'instance_disconnected' => 'disconnected',
                'no_api_key' => 'invalid',
                default => 'invalid',
            };

            wp_send_json_success([
                'status' => $status,
                'phone' => '',
            ]);

            return;
        }

        // A API retorna estado da instância — mapear para connected/disconnected
        $instance_status = $result['instance']['state'] ?? $result['status'] ?? 'unknown';
        $connected_states = ['open', 'connected', 'active'];

        $status = in_array(strtolower((string) $instance_status), $connected_states, true)
            ? 'connected'
            : 'disconnected';

        $phone = $result['instance']['owner'] ?? $result['phone'] ?? '';

        wp_send_json_success([
            'status' => $status,
            'phone' => sanitize_text_field((string) $phone),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Retorna true se o usuário está no plano Free.
     */
    private function is_free_plan(): bool
    {
        $plan = (string) get_option('tokzap_plan', 'free');

        return $plan === '' || $plan === 'free';
    }

    /**
     * Retorna o label legível do plano atual.
     */
    private function current_plan_label(): string
    {
        $plan = (string) get_option('tokzap_plan', 'free');

        return match ($plan) {
            'starter' => 'Starter',
            'basic' => 'Basic',
            'pro' => 'Pro',
            'business' => 'Business',
            default => 'Free',
        };
    }
}
