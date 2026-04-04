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
