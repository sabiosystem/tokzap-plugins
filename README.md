# TokZap Plugins & Integrations

Official integrations, plugins and extensions for [TokZap](https://tokzap.com) — WhatsApp OTP authentication gateway.

## Plugins

### WordPress

| Plugin | Status | Download |
|---|---|---|
| [WordPress Core](./wordpress/core/) | ✅ Disponível v1.0.0 | [tokzap.com/downloads/wordpress](https://tokzap.com/downloads/wordpress) |
| [WooCommerce Add-on](./wordpress/add-ons/woocommerce/) | 🚧 Em breve | — |
| [Gravity Forms Add-on](./wordpress/add-ons/gravity-forms/) | 🚧 Em breve | — |
| [WPForms Add-on](./wordpress/add-ons/wpforms/) | 🚧 Em breve | — |
| [MemberPress Add-on](./wordpress/add-ons/memberpress/) | 🚧 Em breve | — |
| [LearnDash Add-on](./wordpress/add-ons/learndash/) | 🚧 Em breve | — |
| [Contact Form 7 Add-on](./wordpress/add-ons/contact-form-7/) | 🚧 Em breve | — |

### No-Code / Automação

| Integração | Status | Download |
|---|---|---|
| [n8n Community Node](./nocode/n8n/) | 🚧 Em breve | — |
| [Zapier App](./nocode/zapier/) | 🚧 Em breve | — |
| [Make (Integromat) App](./nocode/make/) | 🚧 Em breve | — |

### E-commerce

| Integração | Status |
|---|---|
| [Shopify App](./ecommerce/shopify/) | 🚧 Em breve |
| [Nuvemshop App](./ecommerce/nuvemshop/) | 🚧 Em breve |
| [VTEX IO Extension](./ecommerce/vtex/) | 🚧 Em breve |

### LMS

| Integração | Status |
|---|---|
| [Moodle Plugin](./lms/moodle/) | 🚧 Em breve |

## Tag Convention

Releases seguem o padrão `{pasta}/v{semver}`:

```
wordpress/v1.0.0
n8n/v1.0.0
shopify/v1.0.0
```

Cada tag dispara o workflow de release correspondente em `.github/workflows/`.

## API Reference

Todas as integrações usam a API REST do TokZap:

```
Base URL:  https://api.tokzap.com/v1
Auth:      Authorization: Bearer tk_live_xxxxxxxxxxxxxxxxxxxx
```

Obtenha sua API Key em [tokzap.com/api-keys](https://tokzap.com/api-keys).  
Documentação completa em [tokzap.com/docs](https://tokzap.com/docs).

## Contributing

Veja [CONTRIBUTING.md](./CONTRIBUTING.md).

---

© [SABIO SYSTEM TECNOLOGIA LTDA](https://tokzap.com) · CNPJ 64.475.846/0001-60
