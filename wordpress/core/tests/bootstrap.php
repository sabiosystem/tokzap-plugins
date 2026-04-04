<?php

/**
 * Bootstrap para os testes do plugin TokZap.
 *
 * Usa Brain\Monkey para mockar funções do WordPress sem precisar de
 * uma instalação completa do WP. Cada test case deve chamar
 * Brain\Monkey\setUp() / tearDown() via parent::setUp().
 */

require_once __DIR__.'/../vendor/autoload.php';

// Stub de ABSPATH para permitir o require dos arquivos do plugin
if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp/');
}

if (! defined('TOKZAP_VERSION')) {
    define('TOKZAP_VERSION', '1.0.0');
}

if (! defined('TOKZAP_PLUGIN_DIR')) {
    define('TOKZAP_PLUGIN_DIR', dirname(__DIR__).'/');
}

if (! defined('TOKZAP_PLUGIN_URL')) {
    define('TOKZAP_PLUGIN_URL', 'https://example.com/wp-content/plugins/tokzap/');
}

if (! defined('TOKZAP_API_BASE')) {
    define('TOKZAP_API_BASE', 'https://api.tokzap.com/v1');
}

// Constantes de cookie do WordPress
if (! defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}

if (! defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', 'example.com');
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// Stub mínimo de WP_Error (antes que Mockery crie uma versão sem propriedades)
if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public string $code;

        public string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}
