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

// Dependências
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-otp.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-shortcode.php';

// Admin
require_once TOKZAP_PLUGIN_DIR.'src/Admin/SettingsPage.php';
require_once TOKZAP_PLUGIN_DIR.'src/Admin/OnboardingWizard.php';

/**
 * Classe principal do plugin TokZap.
 *
 * Responsável pelo bootstrap: inicializa as classes de admin e os
 * handlers AJAX do frontend. A lógica de configurações e onboarding
 * fica encapsulada em TokZap_SettingsPage e TokZap_OnboardingWizard.
 */
class TokZap_Plugin
{
    /**
     * Instância única (singleton).
     */
    private static ?TokZap_Plugin $instance = null;

    /**
     * Retorna a instância única do plugin.
     */
    public static function instance(): TokZap_Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Inicializa todas as classes e hooks do plugin.
     */
    private function __construct()
    {
        // Admin: settings + onboarding
        if (is_admin()) {
            new TokZap_SettingsPage;
            new TokZap_OnboardingWizard;
        }

        // Frontend: assets e AJAX para o widget/shortcode
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_tokzap_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_tokzap_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_tokzap_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_nopriv_tokzap_verify_otp', [$this, 'ajax_verify_otp']);

        TokZap_Shortcode::init();
    }

    /**
     * Registra scripts e estilos no frontend (widget e shortcode).
     */
    public function enqueue_frontend_assets(): void
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
}

TokZap_Plugin::instance();
