<?php

namespace TokZap\Auth;

use TokZap\Api\TokZapClient;

defined('ABSPATH') || exit;

/**
 * Verificação em duas etapas (2FA) via WhatsApp no login do WordPress.
 *
 * Fluxo:
 * 1. wp_authenticate_user — intercepta após senha correta, envia OTP automaticamente,
 *    armazena estado em transient, seta cookie e retorna WP_Error.
 * 2. login_message — injeta um modal popup de tela cheia com o campo de 6 dígitos.
 * 3. AJAX tokzap_2fa_complete — valida OTP, chama wp_set_auth_cookie e redireciona.
 */
class LoginOtp
{
    private const COOKIE_NAME = 'tokzap_2fa_token';

    private const TRANSIENT_TTL = 300;

    private const MAX_ATTEMPTS = 5;

    public function register(): void
    {
        add_filter('wp_authenticate_user', [$this, 'handleAuthentication'], 10, 2);
        add_filter('login_message', [$this, 'handleLoginMessage'], 10);
        add_action('wp_ajax_nopriv_tokzap_2fa_complete', [$this, 'ajax2faComplete']);
        add_action('wp_ajax_nopriv_tokzap_2fa_cancel', [$this, 'ajax2faCancel']);
    }

    // -----------------------------------------------------------------------
    // Hook: wp_authenticate_user
    // -----------------------------------------------------------------------

    public function handleAuthentication(\WP_User|\WP_Error $user, string $password): \WP_User|\WP_Error
    {
        if (is_wp_error($user)) {
            return $user;
        }

        if (! $this->is2faEnabled()) {
            return $user;
        }

        $phone = (string) get_user_meta($user->ID, 'tokzap_whatsapp_verified', true);

        if (empty($phone)) {
            if (! $this->is2faRequired()) {
                return $user;
            }
            return new \WP_Error(
                'tokzap_2fa_no_phone',
                __('Verifique seu WhatsApp no perfil antes de fazer login.', 'tokzap')
            );
        }

        // Enviar OTP automaticamente para o WhatsApp do usuário
        $sendResult = (new TokZapClient)->sendOtp($phone);
        if (is_wp_error($sendResult)) {
            return new \WP_Error(
                'tokzap_2fa_send_failed',
                __('Não foi possível enviar o código WhatsApp. Tente novamente.', 'tokzap')
            );
        }

        $token = wp_generate_password(32, false);
        set_transient('tokzap_2fa_'.$token, [
            'user_id' => $user->ID,
            'phone'   => $phone,
            'remember'=> false,
        ], self::TRANSIENT_TTL);

        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires'  => 0,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        // Atualizar $_COOKIE imediatamente para que handleLoginMessage
        // detecte o token ainda nesta mesma requisição (sem precisar de F5).
        $_COOKIE[self::COOKIE_NAME] = $token;

        // Mensagem vazia — o modal vai cobrir tudo
        return new \WP_Error('tokzap_2fa_required', '');
    }

    // -----------------------------------------------------------------------
    // Hook: login_message — injeta modal popup de tela cheia
    // -----------------------------------------------------------------------

    public function handleLoginMessage(string $message): string
    {
        $token = $this->get2faPendingToken();
        if ($token === null) {
            return $message;
        }

        $state        = get_transient('tokzap_2fa_'.$token);
        $masked_phone = $this->maskPhone((string) ($state['phone'] ?? ''));
        $nonce        = wp_create_nonce('tokzap_frontend');
        $ajax_url     = esc_url(admin_url('admin-ajax.php'));
        $login_url    = esc_url(wp_login_url());

        ob_start();
        ?>
<style>
#tokzap-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
}
#tokzap-modal {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 380px;
    margin: 16px;
    box-shadow: 0 24px 64px rgba(0,0,0,.3);
    overflow: hidden;
    animation: tokzap-in .25s ease;
}
@keyframes tokzap-in {
    from { transform: translateY(20px) scale(.96); opacity: 0; }
    to   { transform: translateY(0)    scale(1);   opacity: 1; }
}
.tokzap-modal-header {
    background: #39b54a;
    padding: 20px 24px 16px;
    color: #fff;
}
.tokzap-modal-header h2 {
    margin: 0 0 4px;
    font-size: 17px;
    font-weight: 700;
    font-family: system-ui, sans-serif;
}
.tokzap-modal-header p {
    margin: 0;
    font-size: 13px;
    opacity: .88;
    font-family: system-ui, sans-serif;
}
.tokzap-modal-body {
    padding: 24px;
}
.tokzap-digits {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 20px;
}
.tokzap-2fa-digit {
    width: 46px !important;
    height: 54px !important;
    text-align: center;
    font-size: 24px !important;
    font-weight: 700;
    border: 2px solid #d1d5db !important;
    border-radius: 8px !important;
    outline: none;
    color: #111827;
    transition: border-color .15s;
    padding: 0 !important;
    box-shadow: none !important;
    background: #fff !important;
}
.tokzap-2fa-digit:focus {
    border-color: #39b54a !important;
    box-shadow: 0 0 0 3px rgba(57,181,74,.15) !important;
}
.tokzap-2fa-digit.filled {
    border-color: #39b54a !important;
}
#tokzap-2fa-submit {
    display: block;
    width: 100%;
    padding: 12px;
    background: #39b54a;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    font-family: system-ui, sans-serif;
    transition: background .15s;
}
#tokzap-2fa-submit:hover:not(:disabled) { background: #2d9139; }
#tokzap-2fa-submit:disabled { opacity: .65; cursor: not-allowed; }
#tokzap-2fa-msg {
    margin-top: 10px;
    font-size: 13px;
    color: #dc2626;
    font-family: system-ui, sans-serif;
    text-align: center;
    display: none;
}
.tokzap-modal-footer {
    padding: 12px 24px;
    border-top: 1px solid #f3f4f6;
    text-align: center;
    font-size: 12px;
    color: #9ca3af;
    font-family: system-ui, sans-serif;
}
.tokzap-modal-footer a { color: #9ca3af; }
</style>

<div id="tokzap-overlay">
    <div id="tokzap-modal" role="dialog" aria-modal="true" aria-label="Verificação em duas etapas">
        <div class="tokzap-modal-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
                <div>
                    <h2>🔐 <?php esc_html_e('Verificação em duas etapas', 'tokzap'); ?></h2>
                    <p>
                        <?php
                        printf(
                            esc_html__('Código enviado para %s via WhatsApp.', 'tokzap'),
                            esc_html($masked_phone)
                        );
                        ?>
                    </p>
                </div>
                <button id="tokzap-2fa-close"
                        type="button"
                        aria-label="<?php esc_attr_e('Cancelar', 'tokzap'); ?>"
                        style="flex-shrink:0;background:rgba(255,255,255,.2);border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;font-size:18px;color:#fff;line-height:1;display:flex;align-items:center;justify-content:center;margin-top:2px">
                    &times;
                </button>
            </div>
        </div>
        <div class="tokzap-modal-body">
            <div class="tokzap-digits" id="tokzap-2fa-digits">
                <?php for ($i = 0; $i < 6; $i++) : ?>
                <input type="text"
                       inputmode="numeric"
                       maxlength="1"
                       data-index="<?php echo $i; ?>"
                       class="tokzap-2fa-digit"
                       autocomplete="<?php echo $i === 0 ? 'one-time-code' : 'off'; ?>"/>
                <?php endfor; ?>
            </div>

            <button id="tokzap-2fa-submit">
                <?php esc_html_e('Verificar e entrar', 'tokzap'); ?>
            </button>

            <div id="tokzap-2fa-msg"></div>
        </div>
        <div class="tokzap-modal-footer">
            <a href="<?php echo $login_url; ?>">
                <?php esc_html_e('Não recebi o código — tentar novamente', 'tokzap'); ?>
            </a>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var digits    = document.querySelectorAll('.tokzap-2fa-digit');
    var submitBtn = document.getElementById('tokzap-2fa-submit');
    var msgEl     = document.getElementById('tokzap-2fa-msg');
    var nonce     = <?php echo wp_json_encode($nonce); ?>;
    var ajaxUrl   = <?php echo wp_json_encode($ajax_url); ?>;

    // Navegação entre dígitos
    digits.forEach(function (input, idx) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace') {
                if (input.value === '' && idx > 0) {
                    digits[idx - 1].value = '';
                    digits[idx - 1].classList.remove('filled');
                    digits[idx - 1].focus();
                    e.preventDefault();
                }
            }
            if (e.key === 'ArrowLeft'  && idx > 0) { digits[idx - 1].focus(); e.preventDefault(); }
            if (e.key === 'ArrowRight' && idx < 5) { digits[idx + 1].focus(); e.preventDefault(); }
            if (e.key === 'Enter') { doSubmit(); }
        });
        input.addEventListener('input', function () {
            var val = input.value.replace(/\D/g, '');
            input.value = val.slice(-1);
            if (val) {
                input.classList.add('filled');
                if (idx < 5) { digits[idx + 1].focus(); }
                if (idx === 5) { doSubmit(); }
            } else {
                input.classList.remove('filled');
            }
        });
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            var raw = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            digits.forEach(function (d, i) {
                d.value = raw[i] || '';
                d.classList.toggle('filled', !!raw[i]);
            });
            var next = [].slice.call(digits).find(function (d) { return !d.value; });
            (next || digits[5]).focus();
            if (raw.length === 6) { doSubmit(); }
        });
        input.addEventListener('focus', function () { input.select(); });
    });

    function getCode() {
        return [].slice.call(digits).map(function (d) { return d.value; }).join('');
    }

    function showMsg(msg) {
        msgEl.textContent = msg;
        msgEl.style.display = 'block';
    }

    function clearMsg() { msgEl.style.display = 'none'; }

    function doSubmit() {
        var code = getCode();
        if (code.length !== 6) {
            showMsg(<?php echo wp_json_encode(__('Digite os 6 dígitos do código.', 'tokzap')); ?>);
            return;
        }
        clearMsg();
        submitBtn.disabled = true;
        submitBtn.textContent = <?php echo wp_json_encode(__('Verificando…', 'tokzap')); ?>;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var data = {};
            try { data = JSON.parse(xhr.responseText); } catch (e) {}

            if (data.success) {
                window.location.href = data.data.redirect || <?php echo wp_json_encode(admin_url()); ?>;
            } else {
                if (data.data && data.data.force_reload) {
                    window.location.href = <?php echo wp_json_encode($login_url); ?>;
                    return;
                }
                showMsg((data.data && data.data.message) || <?php echo wp_json_encode(__('Código incorreto.', 'tokzap')); ?>);
                submitBtn.disabled = false;
                submitBtn.textContent = <?php echo wp_json_encode(__('Verificar e entrar', 'tokzap')); ?>;
                digits.forEach(function (d) { d.value = ''; d.classList.remove('filled'); });
                digits[0].focus();
            }
        };
        xhr.send(
            'action=tokzap_2fa_complete' +
            '&nonce=' + encodeURIComponent(nonce) +
            '&code='  + encodeURIComponent(code)
        );
    }

    submitBtn.addEventListener('click', doSubmit);

    var closeBtn = document.getElementById('tokzap-2fa-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeBtn.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) { return; }
                window.location.href = <?php echo wp_json_encode($login_url); ?>;
            };
            xhr.send('action=tokzap_2fa_cancel');
        });
    }

    if (digits[0]) { digits[0].focus(); }
}());
</script>
        <?php
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // AJAX: tokzap_2fa_cancel — apaga transient e expira cookie
    // -----------------------------------------------------------------------

    public function ajax2faCancel(): void
    {
        $token = isset($_COOKIE[self::COOKIE_NAME])
            ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]))
            : '';

        if (! empty($token)) {
            delete_transient('tokzap_2fa_'.$token);
            delete_transient('tokzap_2fa_attempts_'.$token);
        }

        // Expirar o cookie no browser
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        wp_send_json_success(['redirect' => wp_login_url()]);
    }

    // -----------------------------------------------------------------------
    // AJAX: tokzap_2fa_complete
    // -----------------------------------------------------------------------

    public function ajax2faComplete(): void
    {
        check_ajax_referer('tokzap_frontend', 'nonce');

        $token = isset($_COOKIE[self::COOKIE_NAME])
            ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]))
            : '';

        if (empty($token)) {
            wp_send_json_error(['message' => __('Sessão expirada. Faça login novamente.', 'tokzap')]);
            return;
        }

        $state = get_transient('tokzap_2fa_'.$token);
        if (! $state || ! is_array($state)) {
            wp_send_json_error(['message' => __('Sessão expirada. Faça login novamente.', 'tokzap')]);
            return;
        }

        $code = preg_replace('/\D/', '', (string) (isset($_POST['code']) ? wp_unslash($_POST['code']) : ''));
        if (strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Digite os 6 dígitos do código.', 'tokzap')]);
            return;
        }

        $attempts_key = 'tokzap_2fa_attempts_'.$token;
        $attempts     = (int) get_transient($attempts_key);
        if ($attempts >= self::MAX_ATTEMPTS) {
            delete_transient('tokzap_2fa_'.$token);
            wp_send_json_error([
                'message'      => __('Máximo de tentativas excedido. Faça login novamente.', 'tokzap'),
                'force_reload' => true,
            ]);
            return;
        }

        $result = (new TokZapClient)->verifyOtp((string) $state['phone'], $code);
        if (is_wp_error($result)) {
            set_transient($attempts_key, $attempts + 1, self::TRANSIENT_TTL);
            wp_send_json_error(['message' => __('Código incorreto ou expirado.', 'tokzap')]);
            return;
        }

        delete_transient('tokzap_2fa_'.$token);
        delete_transient($attempts_key);

        $remember = (bool) ($state['remember'] ?? false);
        wp_set_auth_cookie((int) $state['user_id'], $remember);

        $user     = get_userdata((int) $state['user_id']);
        $redirect = ($user && ! user_can($user, 'edit_posts')) ? home_url('/') : admin_url();

        wp_send_json_success(['redirect' => $redirect]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function get2faPendingToken(): ?string
    {
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        $token = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
        return get_transient('tokzap_2fa_'.$token) ? $token : null;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return str_repeat('*', strlen($digits));
        }
        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }

    private function is2faEnabled(): bool
    {
        return (bool) get_option('tokzap_2fa_enabled', false);
    }

    private function is2faRequired(): bool
    {
        return (bool) get_option('tokzap_2fa_required', false);
    }
}
