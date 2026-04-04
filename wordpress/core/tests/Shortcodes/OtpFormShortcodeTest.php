<?php

/**
 * Testes do OtpFormShortcode.
 *
 * Cobre: render, ajaxSend (rate limiting, daily limit, sucesso) e ajaxVerify.
 */

declare(strict_types=1);

namespace TokZap\Tests\Shortcodes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use TokZap\Api\TokZapClient;
use TokZap\Shortcodes\OtpFormShortcode;

/**
 * @covers \TokZap\Shortcodes\OtpFormShortcode
 */
class OtpFormShortcodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            '__' => static fn ($str) => $str,
            'esc_html__' => static fn ($str) => $str,
            'esc_attr_e' => static fn ($str) => $str,
            'esc_html_e' => null,
            'esc_attr' => static fn ($v) => (string) $v,
            'esc_html' => static fn ($v) => (string) $v,
            'esc_url' => static fn ($v) => (string) $v,
            'esc_url_raw' => static fn ($v) => (string) $v,
            'sanitize_text_field' => static fn ($v) => (string) $v,
            'wp_unslash' => static fn ($v) => $v,
            'admin_url' => static fn ($path = '') => 'https://example.com/wp-admin/'.$path,
            'wp_create_nonce' => static fn ($action) => 'test_nonce',
            'shortcode_atts' => static function (array $defaults, $atts, string $shortcode = ''): array {
                if (! is_array($atts)) {
                    return $defaults;
                }

                return array_merge($defaults, array_intersect_key($atts, $defaults));
            },
        ]);

        // Carrega dependências
        if (! class_exists('TokZap_API')) {
            require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
        }

        if (! class_exists(TokZapClient::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Api/TokZapClient.php';
        }

        if (! class_exists(OtpFormShortcode::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Shortcodes/OtpFormShortcode.php';
        }
    }

    protected function tearDown(): void
    {
        OtpFormShortcode::clearClient();
        Monkey\tearDown();
        Mockery::close();
        unset($_POST['phone'], $_POST['code'], $_POST['redirect'], $_POST['nonce']);
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // 1. render() — HTML contém elementos essenciais
    // -----------------------------------------------------------------------

    /** @test */
    public function test_render_returns_html_with_form_elements(): void
    {
        Functions\expect('get_option')
            ->zeroOrMoreTimes()
            ->andReturn(false);

        $html = OtpFormShortcode::render([]);

        $this->assertStringContainsString('tokzap-form-wrap', $html);
        $this->assertStringContainsString('tokzap-step-phone', $html);
        $this->assertStringContainsString('tokzap-step-code', $html);
        $this->assertStringContainsString('tokzap-step-success', $html);
        $this->assertStringContainsString('tokzap-digit', $html);
    }

    // -----------------------------------------------------------------------
    // 2. render() — branding exibido quando opção está ativa
    // -----------------------------------------------------------------------

    /** @test */
    public function test_render_shows_branding_when_enabled(): void
    {
        Functions\expect('get_option')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static fn ($key, $default = null) => $key === 'tokzap_show_branding' ? true : $default);

        $html = OtpFormShortcode::render([]);

        $this->assertStringContainsString('tokzap-branding', $html);
        $this->assertStringContainsString('tokzap.com', $html);
    }

    // -----------------------------------------------------------------------
    // 3. ajaxSend() — nonce obrigatório
    // -----------------------------------------------------------------------

    /** @test */
    public function test_ajax_send_requires_nonce(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('tokzap_frontend', 'nonce')
            ->andReturnUsing(static function (): never {
                throw new \RuntimeException('nonce_failed');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce_failed');

        OtpFormShortcode::ajaxSend();
    }

    // -----------------------------------------------------------------------
    // 4. ajaxSend() — rate limit bloqueia após 3 tentativas
    // -----------------------------------------------------------------------

    /** @test */
    public function test_ajax_send_blocks_after_rate_limit(): void
    {
        $_POST['phone'] = '11999990000';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('get_transient')->once()->andReturn(3); // já no limite
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(static function (array $data): never {
                throw new \RuntimeException('rate_limited');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate_limited');

        OtpFormShortcode::ajaxSend();
    }

    // -----------------------------------------------------------------------
    // 5. ajaxSend() — OTP enviado com sucesso
    // -----------------------------------------------------------------------

    /** @test */
    public function test_ajax_send_returns_success(): void
    {
        $_POST['phone'] = '11999990000';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('get_transient')->once()->andReturn(0);
        Functions\expect('set_transient')->once()->andReturn(true);
        Functions\expect('is_wp_error')->once()->andReturn(false);

        $mockClient = Mockery::mock(TokZapClient::class);
        $mockClient->shouldReceive('sendOtp')
            ->once()
            ->with('11999990000')
            ->andReturn(['success' => true]);

        OtpFormShortcode::injectClient($mockClient);

        $captured = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(static function (array $data) use (&$captured): never {
                $captured = $data;
                throw new \RuntimeException('json_sent');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_sent');

        try {
            OtpFormShortcode::ajaxSend();
        } finally {
            $this->assertArrayHasKey('message', $captured ?? []);
        }
    }

    // -----------------------------------------------------------------------
    // 6. ajaxVerify() — código correto retorna verified=true
    // -----------------------------------------------------------------------

    /** @test */
    public function test_ajax_verify_returns_verified_on_success(): void
    {
        $_POST['phone'] = '11999990000';
        $_POST['code'] = '123456';
        $_POST['redirect'] = '';

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('get_current_user_id')->once()->andReturn(0);

        $mockClient = Mockery::mock(TokZapClient::class);
        $mockClient->shouldReceive('verifyOtp')
            ->once()
            ->with('11999990000', '123456')
            ->andReturn(['verified' => true]);

        OtpFormShortcode::injectClient($mockClient);

        $captured = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(static function (array $data) use (&$captured): never {
                $captured = $data;
                throw new \RuntimeException('json_sent');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_sent');

        try {
            OtpFormShortcode::ajaxVerify();
        } finally {
            $this->assertTrue($captured['verified'] ?? false);
        }
    }
}
