=== TokZap WhatsApp OTP ===
Contributors: tokzap
Tags: otp, whatsapp, verification, authentication, sms alternative
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Autenticação OTP via WhatsApp. Adicione verificação de identidade ao seu site WordPress em minutos.

== Description ==

O **TokZap WhatsApp OTP** permite que você adicione verificação de identidade por WhatsApp em qualquer página do seu WordPress — sem escrever código.

**Como funciona:**

1. O usuário informa o número do WhatsApp
2. Recebe um código de 6 dígitos via WhatsApp
3. Informa o código no formulário
4. Identidade verificada!

**Recursos:**

* Shortcode `[tokzap_verify]` para inserir em qualquer página ou post
* Suporte a redirecionamento após verificação
* Mensagem de sucesso personalizável
* Formulário responsivo
* Gratuito para sempre (plano Free — 50 OTPs/dia)

**Plano Free:**

* 1 instância WhatsApp
* 50 OTPs/dia por instância
* Mensagem padrão (não personalizável)
* Branding "Powered by TokZap Free"

**Planos pagos** a partir de R$ 49/mês oferecem OTPs ilimitados, mensagem personalizada, múltiplas instâncias e sem branding.

== Installation ==

1. Faça upload da pasta `tokzap-wp` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins → Plugins instalados**
3. Acesse **Configurações → TokZap** e insira sua API Key
4. Obtenha sua API Key gratuitamente em [tokzap.com](https://tokzap.com)
5. Insira `[tokzap_verify]` em qualquer página

== Frequently Asked Questions ==

= Preciso pagar para usar? =

Não. O plano Free é gratuito para sempre com 50 OTPs/dia.

= Como obtenho uma API Key? =

Crie sua conta em [tokzap.com](https://tokzap.com), conecte seu WhatsApp via QR Code e gere a API Key na seção "API Keys".

= O plugin funciona sem o número do WhatsApp do meu negócio? =

Não. Você precisa de um número WhatsApp para conectar como instância no TokZap. Pode ser qualquer número — pessoal ou comercial.

= O código expira? =

Sim. Cada código OTP expira em 5 minutos.

= Posso personalizar a mensagem enviada? =

Nos planos pagos (a partir de R$ 49/mês) você pode personalizar a mensagem. No plano Free a mensagem é fixa.

== Screenshots ==

1. Formulário de verificação (shortcode)
2. Página de configurações
3. Exemplo com redirecionamento

== Changelog ==

= 1.0.0 =
* Lançamento inicial
* Shortcode [tokzap_verify]
* Página de configurações
* Suporte a redirect e mensagem de sucesso

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin.
