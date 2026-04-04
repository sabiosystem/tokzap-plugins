/**
 * TokZap Frontend — Formulário OTP
 *
 * Controla o fluxo de três passos do [tokzap_otp_form]:
 *   1. Telefone  →  2. Código  →  3. Sucesso
 *
 * Requer: jQuery (disponível no wp-admin e frontend quando enqueued).
 * Nenhuma dependência externa.
 */
/* global tokzapFrontend, jQuery */

(function ($) {
    'use strict';

    var i18n = (tokzapFrontend && tokzapFrontend.i18n) ? tokzapFrontend.i18n : {};

    // =========================================================================
    // Máscara de telefone
    // =========================================================================

    /**
     * Aplica máscara "(11) 9 0000-0000" ao valor digitado.
     * Remove tudo que não é dígito antes de aplicar.
     *
     * @param   {string}  raw  Valor bruto do input
     * @returns {string}       Valor mascarado
     */
    function applyPhoneMask(raw) {
        var d = raw.replace(/\D/g, '').substring(0, 11);
        var len = d.length;

        if (len === 0) { return ''; }
        if (len <= 2) { return '(' + d; }
        if (len <= 3) { return '(' + d.substring(0, 2) + ') ' + d.substring(2); }
        if (len <= 7) { return '(' + d.substring(0, 2) + ') ' + d.substring(2, 3) + ' ' + d.substring(3); }
        return (
            '(' + d.substring(0, 2) + ') ' +
            d.substring(2, 3) + ' ' +
            d.substring(3, 7) + '-' +
            d.substring(7, 11)
        );
    }

    /**
     * Extrai somente os dígitos de um número formatado.
     *
     * @param   {string}  masked
     * @returns {string}
     */
    function digitsOnly(masked) {
        return masked.replace(/\D/g, '');
    }

    // =========================================================================
    // Mensagens inline
    // =========================================================================

    /**
     * Exibe uma mensagem na div .tokzap-message mais próxima de $context.
     *
     * @param {jQuery} $context  Elemento filho ou irmão do passo ativo
     * @param {string} text
     * @param {string} type      'error' | 'success' | 'info'
     */
    function showMessage($context, text, type) {
        $context.closest('.tokzap-step')
            .find('.tokzap-message')
            .removeClass('error success info')
            .addClass(type || 'info')
            .text(text);
    }

    function clearMessage($context) {
        $context.closest('.tokzap-step').find('.tokzap-message').text('').removeClass('error success info');
    }

    // =========================================================================
    // Countdown de reenvio
    // =========================================================================

    /**
     * Inicia um countdown de `seconds` segundos no wrapper $wrap.
     * Ao terminar, exibe o botão "Reenviar".
     *
     * @param {jQuery} $wrap     .tokzap-step-code
     * @param {number} seconds
     */
    function startCountdown($wrap, seconds) {
        var $countdown = $wrap.find('.tokzap-countdown');
        var $timer     = $wrap.find('.tokzap-timer');
        var $resend    = $wrap.find('.tokzap-btn-resend');

        $countdown.show();
        $resend.hide();
        $timer.text(seconds);

        clearInterval($wrap.data('tokzap-timer-id'));

        var id = setInterval(function () {
            seconds -= 1;
            $timer.text(seconds);

            if (seconds <= 0) {
                clearInterval(id);
                $countdown.hide();
                $resend.show();
            }
        }, 1000);

        $wrap.data('tokzap-timer-id', id);
    }

    // =========================================================================
    // Inputs de 6 dígitos
    // =========================================================================

    /**
     * Inicializa os inputs de código .tokzap-digit em $wrap.
     * Suporta navegação automática, paste e filtro numérico.
     *
     * @param {jQuery} $wrap  .tokzap-step-code
     */
    function initDigitInputs($wrap) {
        var $digits = $wrap.find('.tokzap-digit');

        $digits.on('keydown', function (e) {
            var $this  = $(this);
            var idx    = parseInt($this.data('index'), 10);
            var key    = e.key;

            // Só permite: dígitos, Backspace, Delete, Tab, teclas de seta
            if (
                !/^\d$/.test(key) &&
                !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(key)
            ) {
                e.preventDefault();
                return;
            }

            if (key === 'Backspace') {
                if ($this.val() === '' && idx > 0) {
                    $digits.eq(idx - 1).val('').removeClass('filled').focus();
                    e.preventDefault();
                }
            }

            if (key === 'ArrowLeft' && idx > 0) {
                $digits.eq(idx - 1).focus();
                e.preventDefault();
            }

            if (key === 'ArrowRight' && idx < 5) {
                $digits.eq(idx + 1).focus();
                e.preventDefault();
            }
        });

        $digits.on('input', function () {
            var $this = $(this);
            var idx   = parseInt($this.data('index'), 10);
            var val   = $this.val().replace(/\D/g, '');

            // Garantir máximo 1 dígito
            $this.val(val.substring(0, 1));

            if (val !== '') {
                $this.addClass('filled');
                // Avançar para o próximo campo
                if (idx < 5) {
                    $digits.eq(idx + 1).focus();
                }
                // Auto-submit ao preencher o último dígito
                if (idx === 5) {
                    var $btn = $wrap.find('.tokzap-btn-verify');
                    if (! $btn.prop('disabled')) {
                        $btn.trigger('click');
                    }
                }
            } else {
                $this.removeClass('filled');
            }
        });

        $digits.on('paste', function (e) {
            e.preventDefault();
            var raw  = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
            var nums = raw.replace(/\D/g, '').substring(0, 6);

            $digits.each(function (i) {
                var ch = nums[i] || '';
                $(this).val(ch).toggleClass('filled', ch !== '');
            });

            // Focar no próximo campo vazio (ou no último se completo)
            var nextEmpty = $digits.filter(function () { return $(this).val() === ''; }).first();
            (nextEmpty.length ? nextEmpty : $digits.eq(5)).focus();

            if (nums.length === 6) {
                var $btn = $wrap.find('.tokzap-btn-verify');
                if (! $btn.prop('disabled')) {
                    $btn.trigger('click');
                }
            }
        });

        $digits.on('focus', function () {
            $(this).select();
        });
    }

    /**
     * Coleta os 6 dígitos e retorna como string.
     *
     * @param   {jQuery}  $wrap  .tokzap-step-code
     * @returns {string}
     */
    function collectCode($wrap) {
        return $wrap.find('.tokzap-digit').map(function () {
            return $(this).val();
        }).get().join('');
    }

    /**
     * Limpa todos os inputs de código e remove a classe filled.
     *
     * @param {jQuery} $wrap
     */
    function clearDigits($wrap) {
        $wrap.find('.tokzap-digit').val('').removeClass('filled');
    }

    // =========================================================================
    // Inicialização de cada instância do formulário
    // =========================================================================

    function initForm($form) {
        var $stepPhone   = $form.find('.tokzap-step-phone');
        var $stepCode    = $form.find('.tokzap-step-code');
        var $stepSuccess = $form.find('.tokzap-step-success');
        var $phoneInput  = $form.find('.tokzap-phone-input');
        var $btnSend     = $form.find('.tokzap-btn-send');
        var $btnVerify   = $form.find('.tokzap-btn-verify');
        var $btnResend   = $form.find('.tokzap-btn-resend');
        var $btnChange   = $form.find('.tokzap-btn-change');
        var $phoneDisplay = $form.find('.tokzap-phone-display');
        var redirectUrl  = $form.data('redirect') || '';

        // Inicializar digit inputs
        initDigitInputs($stepCode);

        // ---- Máscara de telefone ----
        $phoneInput.on('input', function () {
            var pos    = this.selectionStart;
            var oldLen = this.value.length;
            var masked = applyPhoneMask(this.value);
            this.value = masked;

            // Ajustar cursor para não pular ao fim ao deletar
            var diff = masked.length - oldLen;
            this.setSelectionRange(pos + diff, pos + diff);
        });

        // ---- Passo 1 → 2 (Enviar código) ----
        function doSend() {
            var phone = digitsOnly($phoneInput.val());

            if (phone.length < 10) {
                showMessage($btnSend, i18n.invalidPhone || 'Digite um número válido com DDD.', 'error');
                return;
            }

            clearMessage($btnSend);
            $btnSend.prop('disabled', true).text(i18n.sending || 'Enviando...');

            $.post(
                tokzapFrontend.ajaxurl,
                {
                    action: 'tokzap_send_otp',
                    nonce:  tokzapFrontend.nonce,
                    phone:  phone,
                },
                function (response) {
                    if (response.success) {
                        $phoneDisplay.text($phoneInput.val());
                        $stepPhone.fadeOut(200, function () {
                            $stepCode.fadeIn(200);
                            $stepCode.find('.tokzap-digit').first().focus();
                            startCountdown($stepCode, 60);
                        });
                    } else {
                        var data = response.data || {};

                        if (data.limit) {
                            var msg = (data.message || '') + ' ';
                            if (data.upgrade_url) {
                                // Criar link de upgrade como texto puro
                                msg += 'Upgrade: ' + data.upgrade_url;
                            }
                            showMessage($btnSend, msg, 'error');
                        } else {
                            showMessage($btnSend, data.message || 'Erro ao enviar.', 'error');
                        }
                    }
                }
            ).fail(function () {
                showMessage($btnSend, 'Erro de conexão. Tente novamente.', 'error');
            }).always(function () {
                $btnSend.prop('disabled', false).text(
                    $btnSend.data('original-text') || (i18n.test || 'Enviar código')
                );
            });
        }

        // Preservar texto original do botão
        $btnSend.data('original-text', $btnSend.text().trim());

        $btnSend.on('click', doSend);

        // Reenvio
        $btnResend.on('click', function () {
            clearDigits($stepCode);
            clearMessage($btnVerify);
            doSendFromCode();
        });

        function doSendFromCode() {
            var phone = digitsOnly($phoneInput.val());
            $btnResend.hide();
            $stepCode.find('.tokzap-countdown').show();

            $.post(
                tokzapFrontend.ajaxurl,
                {
                    action: 'tokzap_send_otp',
                    nonce:  tokzapFrontend.nonce,
                    phone:  phone,
                },
                function () {
                    startCountdown($stepCode, 60);
                }
            );
        }

        // ---- Passo 2 → 3 (Verificar código) ----
        $btnVerify.on('click', function () {
            var code  = collectCode($stepCode);
            var phone = digitsOnly($phoneInput.val());

            if (code.length !== 6) {
                showMessage($btnVerify, i18n.invalidCode || 'Digite os 6 dígitos do código.', 'error');
                return;
            }

            clearMessage($btnVerify);
            $btnVerify.prop('disabled', true).text(i18n.verifying || 'Verificando...');

            $.post(
                tokzapFrontend.ajaxurl,
                {
                    action:    'tokzap_verify_otp',
                    nonce:     tokzapFrontend.nonce,
                    phone:     phone,
                    code:      code,
                    redirect:  redirectUrl,
                },
                function (response) {
                    if (response.success) {
                        var data     = response.data || {};
                        var redirect = data.redirect || redirectUrl;

                        // Disparar evento customizado para integrações externas
                        document.dispatchEvent(new CustomEvent('tokzap:verified', {
                            detail: { phone: phone },
                        }));

                        if (redirect) {
                            window.location.href = redirect;
                        } else {
                            $stepCode.fadeOut(200, function () {
                                $stepSuccess.fadeIn(200);
                            });
                        }
                    } else {
                        var msg = (response.data && response.data.message) || 'Código incorreto.';
                        showMessage($btnVerify, msg, 'error');
                        clearDigits($stepCode);
                        $stepCode.find('.tokzap-digit').first().focus();
                    }
                }
            ).fail(function () {
                showMessage($btnVerify, 'Erro de conexão. Tente novamente.', 'error');
            }).always(function () {
                $btnVerify.prop('disabled', false).text(
                    $btnVerify.data('original-text') || (i18n.verify || 'Verificar')
                );
            });
        });

        $btnVerify.data('original-text', $btnVerify.text().trim());

        // ---- Trocar número ----
        $btnChange.on('click', function () {
            clearInterval($stepCode.data('tokzap-timer-id'));
            clearDigits($stepCode);
            clearMessage($btnVerify);
            $phoneInput.val('');

            $stepCode.hide();
            $stepPhone.show();
            $phoneInput.focus();
        });
    }

    // =========================================================================
    // Bootstrap — inicializa todos os formulários presentes na página
    // =========================================================================

    $(function () {
        if (typeof tokzapFrontend === 'undefined') {
            return;
        }

        $('.tokzap-form-wrap').each(function () {
            initForm($(this));
        });

        // ---- Formulário de registro ----
        initRegistrationForm();
    });

    // =========================================================================
    // Formulário de registro (RegistrationOtp)
    // =========================================================================

    function initRegistrationForm() {
        var $sendBtn   = $('#tokzap-reg-send');
        var $verifyBtn = $('#tokzap-reg-verify');
        var $otpWrap   = $('#tokzap-reg-otp');
        var $status    = $('#tokzap-reg-status');
        var $verified  = $('#tokzap_phone_verified');
        var $phone     = $('#tokzap_phone');

        if (! $sendBtn.length) {
            return;
        }

        // Máscara no campo de telefone do registro
        $phone.on('input', function () {
            this.value = applyPhoneMask(this.value);
        });

        $sendBtn.on('click', function () {
            var phone = digitsOnly($phone.val());

            if (phone.length < 10) {
                $status.text(i18n.invalidPhone || 'Digite um número válido com DDD.').css('color', '#dc2626');
                return;
            }

            $sendBtn.prop('disabled', true).text(i18n.sending || 'Enviando...');
            $status.text('').css('color', '');

            $.post(
                tokzapFrontend.ajaxurl,
                {
                    action: 'tokzap_send_otp',
                    nonce:  $sendBtn.data('nonce'),
                    phone:  phone,
                },
                function (response) {
                    if (response.success) {
                        $otpWrap.show();
                        $status.text(response.data.message || 'Código enviado!').css('color', '#16a34a');
                    } else {
                        var msg = (response.data && response.data.message) || 'Erro ao enviar.';
                        $status.text(msg).css('color', '#dc2626');
                    }
                }
            ).fail(function () {
                $status.text('Erro de conexão.').css('color', '#dc2626');
            }).always(function () {
                $sendBtn.prop('disabled', false).text('Enviar código de verificação');
            });
        });

        $verifyBtn.on('click', function () {
            var phone = digitsOnly($phone.val());
            var $regDigits = $otpWrap.find('.tokzap-digit');
            var code = $regDigits.map(function () { return $(this).val(); }).get().join('');

            if (code.length !== 6) {
                $status.text(i18n.invalidCode || 'Digite os 6 dígitos.').css('color', '#dc2626');
                return;
            }

            $verifyBtn.prop('disabled', true).text(i18n.verifying || 'Verificando...');

            $.post(
                tokzapFrontend.ajaxurl,
                {
                    action: 'tokzap_reg_verify_otp',
                    nonce:  $verifyBtn.data('nonce'),
                    phone:  phone,
                    code:   code,
                },
                function (response) {
                    if (response.success) {
                        $verified.val('1');
                        $otpWrap.hide();
                        $status.text(response.data.message || 'Verificado!').css('color', '#16a34a');
                        $sendBtn.hide();
                    } else {
                        var msg = (response.data && response.data.message) || 'Código incorreto.';
                        $status.text(msg).css('color', '#dc2626');
                    }
                }
            ).fail(function () {
                $status.text('Erro de conexão.').css('color', '#dc2626');
            }).always(function () {
                $verifyBtn.prop('disabled', false).text('Verificar');
            });
        });
    }

}(jQuery));
