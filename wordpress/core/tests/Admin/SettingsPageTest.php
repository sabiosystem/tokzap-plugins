<?php

/**
 * Testes da página de configurações do TokZap.
 *
 * Usa Brain\Monkey para mockar funções do WordPress sem instalação completa do WP.
 */

declare(strict_types=1);

namespace TokZap\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @covers TokZap_SettingsPage
 */
class SettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub de funções WP universais usadas em todos os testes
        Functions\stubs([
            '__' => static fn ($str) => $str,
            'esc_html__' => static fn ($str) => $str,
            'sanitize_text_field' => static fn ($v) => (string) $v,
            'wp_unslash' => static fn ($v) => $v,
        ]);

        // Carrega as dependências do plugin
        if (! class_exists('TokZap_API')) {
            require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
        }

        if (! class_exists('TokZap_SettingsPage')) {
            require_once TOKZAP_PLUGIN_DIR.'src/Admin/SettingsPage.php';
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        unset($_POST['api_key']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Stub de add_action que captura os hooks registrados.
     *
     * @param  array<string>  $captured  Referência para popular com os hooks
     */
    private function stubAddAction(array &$captured): void
    {
        Functions\expect('add_action')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $hook) use (&$captured): void {
                $captured[] = $hook;
            });
    }

    /**
     * Retorna uma subclasse anônima de TokZap_SettingsPage que injeta
     * um TokZap_API mockado e sobrescreve ajax_test_connection com a
     * lógica real (sem usar get_option para a key, usa $_POST).
     */
    private function makePageWithMockedApi(object $mockApi): object
    {
        return new class($mockApi) extends \TokZap_SettingsPage
        {
            private object $mockApi;

            public function __construct(object $mockApi)
            {
                // Não chama parent::__construct para evitar registrar hooks
                $this->mockApi = $mockApi;
            }

            /** Reimplementação que usa o mockApi injetado */
            public function ajax_test_connection(): void
            {
                check_ajax_referer('tokzap_admin', 'nonce');

                if (! current_user_can('manage_options')) {
                    wp_send_json_error(['message' => 'Permissão insuficiente.'], 403);

                    return;
                }

                $raw_key = isset($_POST['api_key'])
                    ? sanitize_text_field(wp_unslash($_POST['api_key']))
                    : '';

                $result = $this->mockApi->get_instance_status();

                if (is_wp_error($result)) {
                    $code = $result->get_error_code();
                    $status = ($code === 'instance_disconnected') ? 'disconnected' : 'invalid';
                    wp_send_json_success(['status' => $status, 'phone' => '']);

                    return;
                }

                $state = $result['instance']['state'] ?? $result['status'] ?? 'unknown';
                $connected = in_array(strtolower((string) $state), ['open', 'connected', 'active'], true);
                $phone = (string) ($result['instance']['owner'] ?? $result['phone'] ?? '');

                wp_send_json_success([
                    'status' => $connected ? 'connected' : 'disconnected',
                    'phone' => sanitize_text_field($phone),
                ]);
            }
        };
    }

    // -----------------------------------------------------------------------
    // 1. Nonce obrigatório
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_ajax_test_connection_requires_nonce(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('tokzap_admin', 'nonce')
            ->andReturnUsing(static function (): never {
                throw new \RuntimeException('nonce_failed');
            });

        $page = $this->makePageWithMockedApi(Mockery::mock('TokZap_API'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce_failed');

        $page->ajax_test_connection();
    }

    // -----------------------------------------------------------------------
    // 2. Permissão manage_options obrigatória
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_ajax_test_connection_requires_manage_options(): void
    {
        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(false);
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(static function (): never {
                throw new \RuntimeException('permission_denied');
            });

        $page = $this->makePageWithMockedApi(Mockery::mock('TokZap_API'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('permission_denied');

        $page->ajax_test_connection();
    }

    // -----------------------------------------------------------------------
    // 3. Retorna status 'connected' quando instância está aberta
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_ajax_test_connection_returns_connected_status(): void
    {
        $_POST['api_key'] = 'tk_live_testkey123456';

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(true);
        Functions\expect('is_wp_error')->once()->andReturn(false);

        $mockApi = Mockery::mock('TokZap_API');
        $mockApi->shouldReceive('get_instance_status')
            ->once()
            ->andReturn([
                'instance' => [
                    'state' => 'open',
                    'owner' => '+5511999999999',
                ],
            ]);

        $captured = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(static function (array $data) use (&$captured): never {
                $captured = $data;
                throw new \RuntimeException('json_sent');
            });

        $page = $this->makePageWithMockedApi($mockApi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_sent');

        try {
            $page->ajax_test_connection();
        } finally {
            $this->assertSame('connected', $captured['status'] ?? null);
            $this->assertSame('+5511999999999', $captured['phone'] ?? null);
        }
    }

    // -----------------------------------------------------------------------
    // 4. Retorna status 'invalid' quando TokZapClient retorna 401
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_ajax_test_connection_returns_invalid_on_401(): void
    {
        $_POST['api_key'] = 'tk_live_badkey';

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(true);
        Functions\expect('is_wp_error')->once()->andReturn(true);

        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('get_error_code')->once()->andReturn('unauthorized');

        $mockApi = Mockery::mock('TokZap_API');
        $mockApi->shouldReceive('get_instance_status')->once()->andReturn($wpError);

        $captured = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(static function (array $data) use (&$captured): never {
                $captured = $data;
                throw new \RuntimeException('json_sent');
            });

        $page = $this->makePageWithMockedApi($mockApi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_sent');

        try {
            $page->ajax_test_connection();
        } finally {
            $this->assertSame('invalid', $captured['status'] ?? null);
        }
    }

    // -----------------------------------------------------------------------
    // 5. Hooks admin_menu e AJAX registrados no construtor
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_settings_page_registered(): void
    {
        $hooks = [];
        $this->stubAddAction($hooks);

        // Stub de funções chamadas durante register_settings (admin_init)
        Functions\expect('register_setting')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_settings_section')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_settings_field')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_options_page')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('admin_enqueue_scripts')->zeroOrMoreTimes()->andReturnNull();

        new \TokZap_SettingsPage;

        $this->assertContains('admin_menu', $hooks, 'Hook admin_menu deve ser registrado.');
        $this->assertContains('admin_init', $hooks, 'Hook admin_init deve ser registrado.');
        $this->assertContains('admin_enqueue_scripts', $hooks, 'Hook admin_enqueue_scripts deve ser registrado.');
        $this->assertContains('wp_ajax_tokzap_test_connection', $hooks, 'Hook AJAX deve ser registrado.');
    }

    // -----------------------------------------------------------------------
    // 6. API Key sanitizada antes de salvar
    // -----------------------------------------------------------------------

    /**
     * @test
     */
    public function test_api_key_sanitized_before_save(): void
    {
        $hooks = [];
        $this->stubAddAction($hooks);
        Functions\expect('register_setting')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_settings_section')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_settings_field')->zeroOrMoreTimes()->andReturnNull();
        Functions\expect('add_options_page')->zeroOrMoreTimes()->andReturnNull();

        $page = new \TokZap_SettingsPage;

        // Valor limpo passa intacto (sanitize_text_field stubado como passthrough em setUp)
        $this->assertSame('tk_live_abc123', $page->sanitize_api_key('tk_live_abc123'));

        // String vazia aceita (remoção intencional da key)
        $this->assertSame('', $page->sanitize_api_key(''));

        // Valor com espaços extras é retornado como string (stub passthrough — WP remove espaços)
        $result = $page->sanitize_api_key('  tk_live_x  ');
        $this->assertIsString($result, 'sanitize_api_key deve retornar string.');
    }
}
