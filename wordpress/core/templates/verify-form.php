<?php
/**
 * Template padrão do formulário de verificação OTP TokZap.
 *
 * Variáveis disponíveis (passadas pelo shortcode renderer):
 *
 * @var string $nonce           Nonce de segurança WordPress
 * @var string $on_success      'message' ou 'redirect'
 * @var string $redirect_url    URL de redirecionamento (quando on_success=redirect)
 * @var string $success_message Mensagem de sucesso (quando on_success=message)
 */
defined('ABSPATH') || exit;

$form_id = 'tokzap-form-'.wp_rand(1000, 9999);
?>

<div class="tokzap-verify-wrap" id="<?php echo esc_attr($form_id); ?>-wrap">

    <?php // ── Passo 1: Telefone ────────────────────────────────────────────?>
    <div class="tokzap-step" id="<?php echo esc_attr($form_id); ?>-step-phone">
        <form id="<?php echo esc_attr($form_id); ?>-phone-form" novalidate>
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />

            <div class="tokzap-field">
                <label for="<?php echo esc_attr($form_id); ?>-phone">
                    <?php esc_html_e('Número do WhatsApp', 'tokzap'); ?>
                </label>
                <input
                    type="tel"
                    id="<?php echo esc_attr($form_id); ?>-phone"
                    name="phone"
                    placeholder="5511999999999"
                    autocomplete="tel"
                    required
                />
                <span class="tokzap-hint">
                    <?php esc_html_e('Código do país + DDD + número. Ex: 5511999999999', 'tokzap'); ?>
                </span>
                <span class="tokzap-error" id="<?php echo esc_attr($form_id); ?>-phone-error" hidden></span>
            </div>

            <button type="submit" class="tokzap-btn tokzap-btn-primary">
                <?php esc_html_e('Enviar código', 'tokzap'); ?>
            </button>
        </form>
    </div>

    <?php // ── Passo 2: Código OTP ──────────────────────────────────────────?>
    <div class="tokzap-step" id="<?php echo esc_attr($form_id); ?>-step-otp" hidden>
        <form id="<?php echo esc_attr($form_id); ?>-otp-form" novalidate>
            <div class="tokzap-field">
                <label for="<?php echo esc_attr($form_id); ?>-code">
                    <?php esc_html_e('Código de 6 dígitos', 'tokzap'); ?>
                </label>
                <input
                    type="text"
                    id="<?php echo esc_attr($form_id); ?>-code"
                    name="code"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="000000"
                    autocomplete="one-time-code"
                    required
                />
                <span class="tokzap-hint">
                    <?php esc_html_e('Digite o código enviado ao seu WhatsApp.', 'tokzap'); ?>
                </span>
                <span class="tokzap-error" id="<?php echo esc_attr($form_id); ?>-otp-error" hidden></span>
            </div>

            <button type="submit" class="tokzap-btn tokzap-btn-primary">
                <?php esc_html_e('Verificar', 'tokzap'); ?>
            </button>

            <button type="button" class="tokzap-btn tokzap-btn-link" id="<?php echo esc_attr($form_id); ?>-resend">
                <?php esc_html_e('Reenviar código', 'tokzap'); ?>
            </button>
        </form>
    </div>

    <?php // ── Passo 3: Sucesso ─────────────────────────────────────────────?>
    <div class="tokzap-step" id="<?php echo esc_attr($form_id); ?>-step-success" hidden>
        <div class="tokzap-success">
            <span class="tokzap-success-icon">&#10003;</span>
            <p class="tokzap-success-message"><?php echo esc_html($success_message); ?></p>
        </div>
    </div>

    <?php // ── Rodapé com branding TokZap (Free) ───────────────────────────?>
    <div class="tokzap-branding">
        <?php
        printf(
            /* translators: %s: link para tokzap.com */
            esc_html__('Powered by %s', 'tokzap'),
            '<a href="https://tokzap.com" target="_blank" rel="noopener">TokZap Free</a>'
        );
?>
    </div>
</div>

<script>
(function() {
    var formId      = <?php echo wp_json_encode($form_id); ?>;
    var onSuccess   = <?php echo wp_json_encode($on_success); ?>;
    var redirectUrl = <?php echo wp_json_encode($redirect_url); ?>;
    var ajaxUrl     = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce       = <?php echo wp_json_encode($nonce); ?>;

    var wrap      = document.getElementById( formId + '-wrap' );
    var stepPhone = document.getElementById( formId + '-step-phone' );
    var stepOtp   = document.getElementById( formId + '-step-otp' );
    var stepOk    = document.getElementById( formId + '-step-success' );

    var phoneInput = document.getElementById( formId + '-phone' );
    var codeInput  = document.getElementById( formId + '-code' );
    var phoneError = document.getElementById( formId + '-phone-error' );
    var otpError   = document.getElementById( formId + '-otp-error' );
    var resendBtn  = document.getElementById( formId + '-resend' );

    var currentPhone = '';

    function showError( el, msg ) {
        el.textContent = msg;
        el.hidden = false;
    }

    function clearError( el ) {
        el.textContent = '';
        el.hidden = true;
    }

    function setLoading( btn, loading ) {
        btn.disabled = loading;
        btn.style.opacity = loading ? '0.6' : '1';
    }

    function doPost( action, data, callback ) {
        var body = 'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( nonce );
        Object.keys( data ).forEach( function( key ) {
            body += '&' + encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] );
        });

        fetch( ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        })
        .then( function( r ) { return r.json(); } )
        .then( function( res ) { callback( null, res ); } )
        .catch( function( err ) { callback( err, null ); } );
    }

    // Passo 1: enviar OTP
    document.getElementById( formId + '-phone-form' ).addEventListener( 'submit', function( e ) {
        e.preventDefault();
        clearError( phoneError );

        var phone = phoneInput.value.replace( /\D/g, '' );
        if ( phone.length < 10 ) {
            showError( phoneError, <?php echo wp_json_encode(__('Informe um número válido com DDD e código do país.', 'tokzap')); ?> );
            return;
        }

        currentPhone = phone;
        var btn = this.querySelector( 'button[type="submit"]' );
        setLoading( btn, true );

        doPost( 'tokzap_send_otp', { phone: phone }, function( err, res ) {
            setLoading( btn, false );
            if ( err || ! res || ! res.success ) {
                var msg = ( res && res.data && res.data.message )
                    ? res.data.message
                    : <?php echo wp_json_encode(__('Erro ao enviar código. Tente novamente.', 'tokzap')); ?>;
                showError( phoneError, msg );
                return;
            }
            stepPhone.hidden = true;
            stepOtp.hidden   = false;
            if ( codeInput ) { codeInput.value = ''; codeInput.focus(); }
        });
    });

    // Passo 2: verificar OTP
    document.getElementById( formId + '-otp-form' ).addEventListener( 'submit', function( e ) {
        e.preventDefault();
        clearError( otpError );

        var code = codeInput.value.replace( /\D/g, '' );
        if ( code.length !== 6 ) {
            showError( otpError, <?php echo wp_json_encode(__('Digite os 6 dígitos do código.', 'tokzap')); ?> );
            return;
        }

        var btn = this.querySelector( 'button[type="submit"]' );
        setLoading( btn, true );

        doPost( 'tokzap_verify_otp', { phone: currentPhone, code: code }, function( err, res ) {
            setLoading( btn, false );
            if ( err || ! res || ! res.success ) {
                var msg = ( res && res.data && res.data.message )
                    ? res.data.message
                    : <?php echo wp_json_encode(__('Código inválido ou expirado.', 'tokzap')); ?>;
                showError( otpError, msg );
                return;
            }

            if ( onSuccess === 'redirect' && redirectUrl ) {
                window.location.href = redirectUrl;
            } else {
                stepOtp.hidden    = true;
                stepOk.hidden     = false;
            }
        });
    });

    // Reenviar
    if ( resendBtn ) {
        resendBtn.addEventListener( 'click', function() {
            clearError( otpError );
            stepOtp.hidden   = true;
            stepPhone.hidden = false;
            if ( phoneInput ) { phoneInput.focus(); }
        });
    }
}());
</script>
