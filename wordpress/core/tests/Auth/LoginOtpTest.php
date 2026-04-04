<?php

/**
 * Testes do LoginOtp (2FA via WhatsApp no login do WordPress).
 */

declare(strict_types=1);

namespace TokZap\Tests\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use TokZap\Api\TokZapClient;
use TokZap\Auth\LoginOtp;
use TokZap\Shortcodes\OtpFormShortcode;

/**
 * @covers \TokZap\Auth\LoginOtp
 */
class LoginOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            '__' => static fn ($str) => $str,
            'esc_html__' => static fn ($str) => $str,
            'esc_html_e' => null,
            'sanitize_text_field' => static fn ($v) => (string) $v,
            'wp_unslash' => static fn ($v) => $v,
        ]);

        // Carrega dependências
        if (! class_exists('TokZap_API')) {
            require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
        }

        if (! class_exists(TokZapClient::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Api/TokZapClient.php';
        }

        if (! class_exists(LoginOtp::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Auth/LoginOtp.php';
        }

        // Shortcode é necessário para handleLoginMessage
        if (! class_exists(OtpFormShortcode::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Shortcodes/OtpFormShortcode.php';
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        unset($_COOKIE['tokzap_2fa_token'], $_POST['code']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // 1. register() — hooks registrados
    // -----------------------------------------------------------------------

    /** @test */
    public function test_register_hooks_are_set(): void
    {
        $filters = [];
        $actions = [];

        Functions\expect('add_filter')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $hook) use (&$filters): void {
                $filters[] = $hook;
            });

        Functions\expect('add_action')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $hook) use (&$actions): void {
                $actions[] = $hook;
            });

        (new LoginOtp)->register();

        $this->assertContains('wp_authenticate_user', $filters);
        $this->assertContains('login_message', $filters);
        $this->assertContains('wp_ajax_nopriv_tokzap_2fa_complete', $actions);
    }

    // -----------------------------------------------------------------------
    // 2. handleAuthentication() — propaga WP_Error anterior
    // -----------------------------------------------------------------------

    /** @test */
    public function test_handle_authentication_propagates_existing_wp_error(): void
    {
        Functions\expect('add_filter')->zeroOrMoreTimes();
        Functions\expect('add_action')->zeroOrMoreTimes();

        $existingError = Mockery::mock('WP_Error');
        Functions\expect('is_wp_error')->once()->with($existingError)->andReturn(true);

        $loginOtp = new LoginOtp;
        $result = $loginOtp->handleAuthentication($existingError, 'password123');

        $this->assertSame($existingError, $result);
    }

    // -----------------------------------------------------------------------
    // 3. handleAuthentication() — bypassa quando 2FA está desabilitado
    // -----------------------------------------------------------------------

    /** @test */
    public function test_handle_authentication_bypasses_when_2fa_disabled(): void
    {
        Functions\expect('add_filter')->zeroOrMoreTimes();
        Functions\expect('add_action')->zeroOrMoreTimes();

        $user = Mockery::mock('WP_User');
        $user->ID = 1;

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('get_option')
            ->once()
            ->with('tokzap_2fa_enabled', false)
            ->andReturn(false);

        $loginOtp = new LoginOtp;
        $result = $loginOtp->handleAuthentication($user, 'password');

        $this->assertSame($user, $result, 'Deve retornar o usuário sem modificação quando 2FA está desativado.');
    }

    // -----------------------------------------------------------------------
    // 4. handleAuthentication() — inicia fluxo 2FA quando habilitado
    // -----------------------------------------------------------------------

    /** @test */
    public function test_handle_authentication_initiates_2fa_flow(): void
    {
        Functions\expect('add_filter')->zeroOrMoreTimes();
        Functions\expect('add_action')->zeroOrMoreTimes();

        $user = Mockery::mock('WP_User');
        $user->ID = 42;

        Functions\expect('is_wp_error')->once()->andReturn(false);
        Functions\expect('get_option')
            ->with('tokzap_2fa_enabled', false)->andReturn(true);
        Functions\expect('get_user_meta')
            ->once()
            ->with(42, 'tokzap_whatsapp_verified', true)
            ->andReturn('5511999990000');
        Functions\expect('wp_generate_password')
            ->once()
            ->andReturn('generatedtoken1234567890123456');
        Functions\expect('set_transient')->once()->andReturn(true);
        Functions\expect('is_ssl')->once()->andReturn(false);
        // COOKIEPATH e COOKIE_DOMAIN são constantes PHP definidas no bootstrap

        $loginOtp = new LoginOtp;
        $result = $loginOtp->handleAuthentication($user, 'password');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('tokzap_2fa_required', $result->code);
    }
}
