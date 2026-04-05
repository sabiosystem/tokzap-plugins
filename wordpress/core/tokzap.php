<?php

use TokZap\Auth\LoginOtp;
use TokZap\Auth\RegistrationOtp;
use TokZap\Shortcodes\OtpFormShortcode;

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

// Auto-update via GitHub Releases (sabiosystem/tokzap-plugins)
require_once TOKZAP_PLUGIN_DIR.'vendor/autoload.php';

add_action('init', function () {
    if (! class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        return;
    }

    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/sabiosystem/tokzap-plugins/',
        __FILE__,
        'tokzap'
    );

    // Usar GitHub Releases (não branch) — busca o asset tokzap-wordpress-*.zip
    $updateChecker->getVcsApi()->enableReleaseAssets('/tokzap-wordpress-.*\.zip$/');
});

// Dependências legadas (mantidas para compatibilidade)
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-otp.php';
require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-shortcode.php';

// Admin
require_once TOKZAP_PLUGIN_DIR.'src/Admin/SettingsPage.php';
require_once TOKZAP_PLUGIN_DIR.'src/Admin/OnboardingWizard.php';

// Namespaced: API client, shortcode, autenticação
require_once TOKZAP_PLUGIN_DIR.'src/Api/TokZapClient.php';
require_once TOKZAP_PLUGIN_DIR.'src/Shortcodes/OtpFormShortcode.php';
require_once TOKZAP_PLUGIN_DIR.'src/Auth/LoginOtp.php';
require_once TOKZAP_PLUGIN_DIR.'src/Auth/RegistrationOtp.php';

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

        // Frontend: assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Shortcode OTP (namespaced) — AJAX: send + verify
        $shortcode = new OtpFormShortcode;
        $shortcode->register();

        add_action('wp_ajax_tokzap_send_otp', [OtpFormShortcode::class, 'ajaxSend']);
        add_action('wp_ajax_nopriv_tokzap_send_otp', [OtpFormShortcode::class, 'ajaxSend']);
        add_action('wp_ajax_tokzap_verify_otp', [OtpFormShortcode::class, 'ajaxVerify']);
        add_action('wp_ajax_nopriv_tokzap_verify_otp', [OtpFormShortcode::class, 'ajaxVerify']);

        // 2FA no login (namespaced)
        (new LoginOtp)->register();

        // OTP no registro de novos usuários (namespaced)
        (new RegistrationOtp)->register();
    }

    /**
     * Registra scripts e estilos no frontend (widget e shortcode).
     */
    public function enqueue_frontend_assets(): void
    {
        wp_enqueue_style(
            'tokzap-form',
            TOKZAP_PLUGIN_URL.'assets/css/form.css',
            [],
            TOKZAP_VERSION
        );

        wp_enqueue_script(
            'tokzap-frontend',
            TOKZAP_PLUGIN_URL.'assets/js/frontend.js',
            ['jquery'],
            TOKZAP_VERSION,
            true
        );

        wp_localize_script('tokzap-frontend', 'tokzapFrontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tokzap_frontend'),
            'i18n' => [
                'sending' => __('Enviando...', 'tokzap'),
                'verifying' => __('Verificando...', 'tokzap'),
                'invalidPhone' => __('Digite um número válido com DDD.', 'tokzap'),
                'invalidCode' => __('Digite os 6 dígitos do código.', 'tokzap'),
            ],
        ]);
    }
}

TokZap_Plugin::instance();
