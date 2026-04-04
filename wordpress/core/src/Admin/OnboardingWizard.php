<?php

/**
 * Wizard de onboarding simplificado do plugin TokZap.
 *
 * Exibe admin notices contextuais para guiar o usuário na configuração inicial.
 * Passo 1: aviso para configurar a API Key (enquanto não estiver salva).
 * Passo 2: confirmação de sucesso após testar a conexão (via AJAX dismiss).
 */
defined('ABSPATH') || exit;

/**
 * Gerencia o onboarding de dois passos via admin_notices.
 */
class TokZap_OnboardingWizard
{
    /**
     * Registra os hooks de onboarding.
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'maybe_render_notice']);
        add_action('wp_ajax_tokzap_dismiss_onboarding', [$this, 'ajax_dismiss']);
    }

    /**
     * Exibe o notice correto conforme o estado de configuração.
     *
     * Não exibe nada fora das telas de configurações do TokZap.
     */
    public function maybe_render_notice(): void
    {
        // Só exibe na página de settings do TokZap
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || $screen->id !== 'settings_page_tokzap-settings') {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        // Onboarding já concluído — não exibe nada
        if (get_option('tokzap_onboarding_done') === '1') {
            return;
        }

        $api_key = (string) get_option('tokzap_api_key', '');

        if ($api_key === '') {
            $this->render_step_1();
        } else {
            $this->render_step_2();
        }
    }

    /**
     * Renderiza o passo 1: convida o usuário a configurar a API Key.
     */
    private function render_step_1(): void
    {
        ?>
        <div class="notice notice-info is-dismissible tokzap-onboarding-notice" id="tokzap-onboarding-step-1">
            <p>
                <strong>👋 <?php esc_html_e('Bem-vindo ao TokZap!', 'tokzap'); ?></strong>
                <?php esc_html_e('Configure sua API Key para começar a enviar verificações via WhatsApp.', 'tokzap'); ?>
                <a href="#tokzap-api-key-field" class="tokzap-onboarding-cta">
                    <?php esc_html_e('Configurar agora', 'tokzap'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Renderiza o passo 2: confirma configuração bem-sucedida e oferece dismiss.
     */
    private function render_step_2(): void
    {
        ?>
        <div class="notice notice-success tokzap-onboarding-notice" id="tokzap-onboarding-step-2">
            <p>
                <strong>✅ <?php esc_html_e('TokZap configurado!', 'tokzap'); ?></strong>
                <?php esc_html_e('Adicione o shortcode', 'tokzap'); ?>
                <code>[tokzap_otp_form]</code>
                <?php esc_html_e('em qualquer página para começar a verificar usuários.', 'tokzap'); ?>
                &nbsp;
                <button
                    type="button"
                    class="button button-small"
                    id="tokzap-onboarding-done"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('tokzap_onboarding')); ?>"
                >
                    <?php esc_html_e('Entendido', 'tokzap'); ?>
                </button>
            </p>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('tokzap-onboarding-done');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var notice = document.getElementById('tokzap-onboarding-step-2');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (notice) notice.style.display = 'none';
                };
                xhr.send('action=tokzap_dismiss_onboarding&nonce=' + encodeURIComponent(btn.dataset.nonce));
            });
        })();
        </script>
        <?php
    }

    /**
     * Handler AJAX: marca o onboarding como concluído.
     */
    public function ajax_dismiss(): void
    {
        check_ajax_referer('tokzap_onboarding', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error([], 403);
        }

        update_option('tokzap_onboarding_done', '1');
        wp_send_json_success();
    }
}
