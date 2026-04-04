/**
 * TokZap Admin — JavaScript
 *
 * AJAX de teste de conexão em tempo real e UX da tela de configurações.
 * Requer: jQuery (disponível no wp-admin), tokzapAdmin localizado via wp_localize_script.
 */
/* global tokzapAdmin */

(function ($) {
    'use strict';

    // -----------------------------------------------------------------------
    // Teste de conexão
    // -----------------------------------------------------------------------

    var $testBtn    = $('#tokzap-test-btn');
    var $statusWrap = $('#tokzap-connection-status');

    /**
     * Renderiza o badge de status no container de resultado.
     *
     * @param {string} status   'connected' | 'disconnected' | 'invalid'
     * @param {string} phone    Número do WhatsApp (opcional, só quando connected)
     */
    function renderStatus(status, phone) {
        var cssClass;
        var icon;
        var label;

        switch (status) {
            case 'connected':
                cssClass = 'tokzap-status-connected';
                icon     = '✅';
                label    = tokzapAdmin.i18n.connected;
                if (phone) {
                    label += ' — ' + phone;
                }
                break;

            case 'disconnected':
                cssClass = 'tokzap-status-disconnected';
                icon     = '⚠️';
                label    = tokzapAdmin.i18n.disconnected;
                break;

            default: // 'invalid' e qualquer estado desconhecido
                cssClass = 'tokzap-status-invalid';
                icon     = '❌';
                label    = tokzapAdmin.i18n.invalid;
        }

        $statusWrap.html(
            '<span class="tokzap-status-badge ' + cssClass + '">' +
            icon + ' ' + $('<span>').text(label).html() +
            '</span>'
        );
    }

    /**
     * Exibe spinner + mensagem "Testando..." enquanto aguarda resposta.
     */
    function renderTesting() {
        $statusWrap.html(
            '<span class="tokzap-status-testing">' +
            '<span class="tokzap-spinner" aria-hidden="true"></span>' +
            $('<span>').text(tokzapAdmin.i18n.testing).html() +
            '</span>'
        );
    }

    $testBtn.on('click', function () {
        var apiKey = $('#tokzap_api_key').val();

        if (!apiKey || apiKey.trim() === '') {
            $statusWrap.html(
                '<span class="tokzap-status-badge tokzap-status-invalid">⚠ ' +
                $('<span>').text(tokzapAdmin.i18n.empty_key).html() +
                '</span>'
            );
            return;
        }

        $testBtn.prop('disabled', true).text(tokzapAdmin.i18n.testing);
        renderTesting();

        $.post(
            tokzapAdmin.ajaxurl,
            {
                action:  'tokzap_test_connection',
                nonce:   tokzapAdmin.nonce,
                api_key: apiKey,
            },
            function (response) {
                if (response && response.success && response.data) {
                    renderStatus(response.data.status, response.data.phone || '');
                } else {
                    renderStatus('invalid', '');
                }
            }
        ).fail(function () {
            $statusWrap.html(
                '<span class="tokzap-status-badge tokzap-status-invalid">❌ ' +
                $('<span>').text(tokzapAdmin.i18n.network_error).html() +
                '</span>'
            );
        }).always(function () {
            $testBtn.prop('disabled', false).text(tokzapAdmin.i18n.test);
        });
    });

    // -----------------------------------------------------------------------
    // 2FA: "Obrigatório" só habilitado quando "Habilitado" estiver marcado
    // -----------------------------------------------------------------------

    var $2faEnabled  = $('#tokzap_2fa_enabled');
    var $2faRequired = $('#tokzap_2fa_required');

    function sync2faRequired() {
        var isEnabled = $2faEnabled.prop('checked');
        $2faRequired.prop('disabled', !isEnabled);
        $2faRequired.closest('tr').toggleClass('tokzap-field-disabled', !isEnabled);
    }

    $2faEnabled.on('change', sync2faRequired);
    sync2faRequired(); // estado inicial ao carregar a página

    // -----------------------------------------------------------------------
    // Copy shortcode
    // -----------------------------------------------------------------------

    $('#tokzap-copy-shortcode').on('click', function () {
        var $btn  = $(this);
        var text  = $('#tokzap-shortcode-text').text();
        var original = $btn.text();

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.text('✓ Copiado!');
                setTimeout(function () { $btn.text(original); }, 2000);
            }).catch(function () {
                fallbackCopy(text, $btn, original);
            });
        } else {
            fallbackCopy(text, $btn, original);
        }
    });

    /**
     * Fallback para copiar texto em browsers sem Clipboard API.
     *
     * @param {string} text
     * @param {jQuery} $btn
     * @param {string} originalLabel
     */
    function fallbackCopy(text, $btn, originalLabel) {
        var $tmp = $('<textarea>').val(text).css({ position: 'fixed', top: 0, left: 0, opacity: 0 });
        $('body').append($tmp);
        $tmp.focus().select();
        try {
            document.execCommand('copy');
            $btn.text('✓ Copiado!');
            setTimeout(function () { $btn.text(originalLabel); }, 2000);
        } catch (e) {
            // silencioso — navegador muito antigo
        }
        $tmp.remove();
    }

}(jQuery));
