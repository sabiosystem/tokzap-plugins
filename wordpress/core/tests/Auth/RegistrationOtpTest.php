<?php

/**
 * Testes do RegistrationOtp (OTP no formulário de registro do WordPress).
 */

declare(strict_types=1);

namespace TokZap\Tests\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use TokZap\Api\TokZapClient;
use TokZap\Auth\RegistrationOtp;

/**
 * @covers \TokZap\Auth\RegistrationOtp
 */
class RegistrationOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            '__' => static fn ($str) => $str,
            'esc_html__' => static fn ($str) => $str,
            'esc_html_e' => null,
            'esc_attr_e' => null,
            'esc_attr' => static fn ($v) => (string) $v,
            'esc_html' => static fn ($v) => (string) $v,
            'sanitize_text_field' => static fn ($v) => (string) $v,
            'wp_unslash' => static fn ($v) => $v,
            'wp_create_nonce' => static fn ($action) => 'test_nonce_reg',
        ]);

        // Carrega dependências
        if (! class_exists('TokZap_API')) {
            require_once TOKZAP_PLUGIN_DIR.'includes/class-tokzap-api.php';
        }

        if (! class_exists(TokZapClient::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Api/TokZapClient.php';
        }

        if (! class_exists(RegistrationOtp::class)) {
            require_once TOKZAP_PLUGIN_DIR.'src/Auth/RegistrationOtp.php';
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        unset($_POST['tokzap_phone'], $_POST['tokzap_phone_verified'], $_POST['phone'], $_POST['code'], $_POST['nonce']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // 1. register() — hooks registrados
    // -----------------------------------------------------------------------

    /** @test */
    public function test_register_hooks_are_set(): void
    {
        Functions\expect('get_option')
            ->zeroOrMoreTimes()
            ->andReturn(false);

        $actions = [];

        Functions\expect('add_action')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $hook) use (&$actions): void {
                $actions[] = $hook;
            });

        Functions\expect('add_filter')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (): void {});

        (new RegistrationOtp)->register();

        $this->assertContains('register_form', $actions);
        $this->assertContains('user_register', $actions);
        $this->assertContains('wp_ajax_nopriv_tokzap_reg_verify_otp', $actions);
    }

    // -----------------------------------------------------------------------
    // 2. validateRegistration() — erro quando telefone vazio
    // -----------------------------------------------------------------------

    /** @test */
    public function test_validate_registration_requires_phone(): void
    {
        $_POST['tokzap_phone'] = '';
        $_POST['tokzap_phone_verified'] = '0';

        Functions\expect('get_option')
            ->with('tokzap_registration_otp', false)
            ->andReturn(true);

        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('add')
            ->once()
            ->with('tokzap_phone_required', Mockery::type('string'));

        Functions\expect('is_wp_error')->zeroOrMoreTimes()->andReturn(false);

        $reg = new RegistrationOtp;
        $result = $reg->validateRegistration($wpError, 'user', 'user@example.com');

        $this->assertSame($wpError, $result);
    }

    // -----------------------------------------------------------------------
    // 3. validateRegistration() — erro quando telefone não verificado
    // -----------------------------------------------------------------------

    /** @test */
    public function test_validate_registration_requires_verified_phone(): void
    {
        $_POST['tokzap_phone'] = '11999990000';
        $_POST['tokzap_phone_verified'] = '0'; // não verificado

        Functions\expect('get_option')
            ->with('tokzap_registration_otp', false)
            ->andReturn(true);

        Functions\expect('get_transient')
            ->once()
            ->andReturn(false); // sem transient = não verificado

        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('add')
            ->once()
            ->with('tokzap_phone_unverified', Mockery::type('string'));

        $reg = new RegistrationOtp;
        $result = $reg->validateRegistration($wpError, 'user', 'user@example.com');

        $this->assertSame($wpError, $result);
    }

    // -----------------------------------------------------------------------
    // 4. savePhoneMeta() — persiste telefone no user_meta
    // -----------------------------------------------------------------------

    /** @test */
    public function test_save_phone_meta_persists_phone(): void
    {
        $_POST['tokzap_phone'] = '(11) 9 9999-0000'; // com máscara — deve salvar só dígitos

        Functions\expect('get_option')
            ->with('tokzap_registration_otp', false)
            ->andReturn(true);

        $calls = [];
        Functions\expect('update_user_meta')
            ->twice()
            ->andReturnUsing(static function (int $uid, string $key, string $val) use (&$calls): bool {
                $calls[] = [$uid, $key, $val];

                return true;
            });

        $reg = new RegistrationOtp;
        $reg->savePhoneMeta(99);

        $this->assertCount(2, $calls, 'update_user_meta deve ser chamado duas vezes.');
        $this->assertSame([99, 'tokzap_whatsapp_phone', '11999990000'], $calls[0]);
        $this->assertSame([99, 'tokzap_whatsapp_verified', '11999990000'], $calls[1]);
    }
}
