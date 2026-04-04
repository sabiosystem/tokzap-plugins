/**
 * TokZap OTP Widget — WordPress Edition
 *
 * Versão adaptada do widget para uso no WordPress.
 * A API Key e a URL do AJAX são injetadas via wp_localize_script
 * como `tokzapSettings.ajaxUrl` e `tokzapSettings.nonce`.
 *
 * Expõe window.TokZap com os mesmos métodos do widget standalone.
 */
(function (window, document) {
    'use strict';

    var settings   = window.tokzapSettings || {};
    var ajaxUrl    = settings.ajaxUrl || '';
    var nonce      = settings.nonce || '';
    var PRIMARY    = '#39b54a';
    var DARK       = '#2d9139';

    // ── CSS injection ────────────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('tokzap-wp-modal-styles')) { return; }
        var style = document.createElement('style');
        style.id  = 'tokzap-wp-modal-styles';
        style.textContent = [
            '#tz-wp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99998;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .2s}',
            '#tz-wp-overlay.tz-visible{opacity:1}',
            '#tz-wp-modal{background:#fff;border-radius:16px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;transform:translateY(12px) scale(.97);transition:transform .2s,opacity .2s;opacity:0}',
            '#tz-wp-overlay.tz-visible #tz-wp-modal{transform:translateY(0) scale(1);opacity:1}',
            '.tz-wp-header{background:' + PRIMARY + ';padding:20px 24px 16px;color:#fff;position:relative}',
            '.tz-wp-header h2{margin:0 0 4px;font-size:17px;font-weight:700;font-family:system-ui,sans-serif}',
            '.tz-wp-header p{margin:0;font-size:13px;opacity:.85;font-family:system-ui,sans-serif}',
            '.tz-wp-close{position:absolute;top:14px;right:14px;background:rgba(255,255,255,.2);border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:16px;color:#fff;display:flex;align-items:center;justify-content:center;line-height:1}',
            '.tz-wp-close:hover{background:rgba(255,255,255,.3)}',
            '.tz-wp-body{padding:24px;font-family:system-ui,sans-serif}',
            '.tz-wp-label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}',
            '.tz-wp-input{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:15px;font-family:system-ui,sans-serif;outline:none;transition:border-color .15s}',
            '.tz-wp-input:focus{border-color:' + PRIMARY + ';box-shadow:0 0 0 3px rgba(57,181,74,.12)}',
            '.tz-wp-input-otp{text-align:center;letter-spacing:.35em;font-size:22px;font-weight:700;color:#111827}',
            '.tz-wp-btn{display:block;width:100%;padding:11px 16px;border:none;border-radius:8px;font-size:15px;font-weight:600;font-family:system-ui,sans-serif;cursor:pointer;transition:background .15s,opacity .15s;margin-top:16px}',
            '.tz-wp-btn-primary{background:' + PRIMARY + ';color:#fff}',
            '.tz-wp-btn-primary:hover:not(:disabled){background:' + DARK + '}',
            '.tz-wp-btn-primary:disabled{opacity:.6;cursor:not-allowed}',
            '.tz-wp-btn-link{background:none;color:' + PRIMARY + ';font-size:13px;margin-top:10px;padding:6px 0}',
            '.tz-wp-btn-link:hover{text-decoration:underline}',
            '.tz-wp-error{font-size:12px;color:#ef4444;margin-top:8px;display:none}',
            '.tz-wp-error.tz-show{display:block}',
            '.tz-wp-hint{font-size:12px;color:#6b7280;margin-top:6px}',
            '.tz-wp-footer{padding:10px 24px;border-top:1px solid #f3f4f6;text-align:center;font-size:11px;color:#9ca3af;font-family:system-ui,sans-serif}',
            '.tz-wp-footer a{color:' + PRIMARY + ';text-decoration:none;font-weight:600}',
            '.tz-wp-success{text-align:center;padding:8px 0 4px}',
            '.tz-wp-success-icon{font-size:40px;margin-bottom:12px}',
            '.tz-wp-success h3{margin:0 0 6px;font-size:17px;font-weight:700;font-family:system-ui,sans-serif;color:#111827}',
            '.tz-wp-success p{margin:0;font-size:13px;color:#6b7280;font-family:system-ui,sans-serif}',
            '.tz-wp-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:tz-spin .7s linear infinite;margin-right:6px;vertical-align:middle}',
            '@keyframes tz-spin{to{transform:rotate(360deg)}}',
        ].join('');
        document.head.appendChild(style);
    }

    // ── DOM ──────────────────────────────────────────────────────────────
    function buildModal() {
        if (document.getElementById('tz-wp-overlay')) {
            return document.getElementById('tz-wp-overlay');
        }

        var overlay = document.createElement('div');
        overlay.id = 'tz-wp-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        overlay.innerHTML = '<div id="tz-wp-modal">'
            + '<div class="tz-wp-header">'
            +   '<h2>Verificação WhatsApp</h2>'
            +   '<p>Enviaremos um código via WhatsApp.</p>'
            +   '<button class="tz-wp-close" id="tz-wp-close">&times;</button>'
            + '</div>'
            + '<div id="tz-wp-step-phone" class="tz-wp-body">'
            +   '<label class="tz-wp-label" for="tz-wp-phone">Número do WhatsApp</label>'
            +   '<input id="tz-wp-phone" class="tz-wp-input" type="tel" placeholder="5511999999999" autocomplete="tel" />'
            +   '<div id="tz-wp-phone-error" class="tz-wp-error"></div>'
            +   '<p class="tz-wp-hint">Código do país + DDD + número</p>'
            +   '<button id="tz-wp-send-btn" class="tz-wp-btn tz-wp-btn-primary">Enviar código</button>'
            + '</div>'
            + '<div id="tz-wp-step-otp" class="tz-wp-body" style="display:none">'
            +   '<label class="tz-wp-label" for="tz-wp-code">Código recebido</label>'
            +   '<input id="tz-wp-code" class="tz-wp-input tz-wp-input-otp" type="text" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" />'
            +   '<div id="tz-wp-otp-error" class="tz-wp-error"></div>'
            +   '<p class="tz-wp-hint">6 dígitos enviados ao seu WhatsApp</p>'
            +   '<button id="tz-wp-verify-btn" class="tz-wp-btn tz-wp-btn-primary">Verificar</button>'
            +   '<button id="tz-wp-resend-btn" class="tz-wp-btn tz-wp-btn-link">Reenviar código</button>'
            + '</div>'
            + '<div id="tz-wp-step-success" class="tz-wp-body" style="display:none">'
            +   '<div class="tz-wp-success">'
            +     '<div class="tz-wp-success-icon">✅</div>'
            +     '<h3>Verificado!</h3>'
            +     '<p>Número confirmado com sucesso.</p>'
            +   '</div>'
            + '</div>'
            + '<div class="tz-wp-footer">Powered by <a href="https://tokzap.com" target="_blank">TokZap Free</a></div>'
            + '</div>';

        document.body.appendChild(overlay);
        return overlay;
    }

    // ── AJAX helper ──────────────────────────────────────────────────────
    function doAjax(action, data, callback) {
        var body = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
        Object.keys(data).forEach(function (k) {
            body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var res = {};
            try { res = JSON.parse(xhr.responseText); } catch (e) { /* noop */ }
            if (res.success) {
                callback(null, res.data || {});
            } else {
                callback(new Error('ajax_error'), res.data || {});
            }
        };
        xhr.send(body);
    }

    // ── Modal helpers ────────────────────────────────────────────────────
    var state = { phone: '', onSuccess: null, onError: null, prevFocus: null };

    function showStep(name) {
        ['phone', 'otp', 'success'].forEach(function (s) {
            var el = document.getElementById('tz-wp-step-' + s);
            if (el) { el.style.display = s === name ? '' : 'none'; }
        });
    }

    function setError(id, msg) {
        var el = document.getElementById(id);
        if (!el) { return; }
        el.textContent = msg || '';
        msg ? el.classList.add('tz-show') : el.classList.remove('tz-show');
    }

    function setLoading(btnId, on) {
        var btn = document.getElementById(btnId);
        if (!btn) { return; }
        btn.disabled = on;
        if (on) {
            btn.innerHTML = '<span class="tz-wp-spinner"></span>Aguarde…';
        } else {
            var labels = { 'tz-wp-send-btn': 'Enviar código', 'tz-wp-verify-btn': 'Verificar' };
            btn.textContent = labels[btnId] || btn.textContent;
        }
    }

    function openModal(opts) {
        injectStyles();
        var overlay = buildModal();
        opts = opts || {};

        state.onSuccess = opts.onSuccess || null;
        state.onError   = opts.onError || null;
        state.phone     = '';
        state.prevFocus = document.activeElement;

        showStep('phone');
        setError('tz-wp-phone-error', '');
        setError('tz-wp-otp-error', '');

        var phoneInput = document.getElementById('tz-wp-phone');
        var codeInput  = document.getElementById('tz-wp-code');
        if (phoneInput) { phoneInput.value = opts.phone || ''; }
        if (codeInput)  { codeInput.value  = ''; }

        overlay.style.display = 'flex';
        requestAnimationFrame(function () {
            overlay.classList.add('tz-visible');
            setTimeout(function () { if (phoneInput) { phoneInput.focus(); } }, 50);
        });

        document.getElementById('tz-wp-close').onclick = closeModal;
        document.getElementById('tz-wp-send-btn').onclick   = doSend;
        document.getElementById('tz-wp-verify-btn').onclick = doVerify;
        document.getElementById('tz-wp-resend-btn').onclick = function () {
            showStep('phone');
            setError('tz-wp-phone-error', '');
            setError('tz-wp-otp-error', '');
            if (phoneInput) { phoneInput.focus(); }
        };

        document.addEventListener('keydown', handleKey);
        overlay.addEventListener('click', handleOverlayClick);
    }

    function closeModal() {
        var overlay = document.getElementById('tz-wp-overlay');
        if (!overlay) { return; }
        overlay.classList.remove('tz-visible');
        setTimeout(function () { overlay.style.display = 'none'; }, 200);
        document.removeEventListener('keydown', handleKey);
        overlay.removeEventListener('click', handleOverlayClick);
        if (state.prevFocus) { try { state.prevFocus.focus(); } catch (e) { /* noop */ } }
    }

    function handleKey(e) {
        if (e.key === 'Escape') { closeModal(); }
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('tz-wp-overlay')) { closeModal(); }
    }

    function doSend() {
        var phoneInput = document.getElementById('tz-wp-phone');
        var phone = phoneInput ? phoneInput.value.replace(/\D/g, '') : '';

        if (phone.length < 10) {
            setError('tz-wp-phone-error', 'Informe um número válido com DDD e código do país.');
            if (phoneInput) { phoneInput.focus(); }
            return;
        }

        setError('tz-wp-phone-error', '');
        setLoading('tz-wp-send-btn', true);
        state.phone = phone;

        doAjax('tokzap_send_otp', { phone: phone }, function (err, data) {
            setLoading('tz-wp-send-btn', false);
            if (err) {
                var msg = data && data.message ? data.message : 'Erro ao enviar código.';
                setError('tz-wp-phone-error', msg);
                if (state.onError) { state.onError(err, data); }
            } else {
                showStep('otp');
                var ci = document.getElementById('tz-wp-code');
                if (ci) { ci.value = ''; ci.focus(); }
            }
        });
    }

    function doVerify() {
        var codeInput = document.getElementById('tz-wp-code');
        var code = codeInput ? codeInput.value.replace(/\D/g, '') : '';

        if (code.length !== 6) {
            setError('tz-wp-otp-error', 'Informe os 6 dígitos do código.');
            if (codeInput) { codeInput.focus(); }
            return;
        }

        setError('tz-wp-otp-error', '');
        setLoading('tz-wp-verify-btn', true);

        doAjax('tokzap_verify_otp', { phone: state.phone, code: code }, function (err, data) {
            setLoading('tz-wp-verify-btn', false);
            if (err) {
                var msg = data && data.message ? data.message : 'Código inválido ou expirado.';
                setError('tz-wp-otp-error', msg);
                if (state.onError) { state.onError(err, data); }
            } else {
                showStep('success');
                if (state.onSuccess) { state.onSuccess(state.phone); }
                setTimeout(closeModal, 2500);
            }
        });
    }

    // ── Public API ───────────────────────────────────────────────────────
    window.TokZap = {
        openModal:  openModal,
        closeModal: closeModal,
        send: function (phone, callback) {
            doAjax('tokzap_send_otp', { phone: phone.replace(/\D/g, '') }, function (err, data) {
                callback(err, data);
            });
        },
        verify: function (phone, code, callback) {
            doAjax('tokzap_verify_otp', { phone: phone.replace(/\D/g, ''), code: code }, function (err, data) {
                callback(err, data);
            });
        },
    };

}(window, document));
