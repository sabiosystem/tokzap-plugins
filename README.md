# TokZap Plugins & Integrations

Official integrations, plugins and extensions for [TokZap](https://tokzap.com) — WhatsApp OTP gateway.

## Plugins

| Plugin | Platform | Status | Download |
|---|---|---|---|
| [WordPress](./wordpress/) | WordPress 5.8+ | ✅ Stable v1.0.0 | [Latest release](https://github.com/sabiosystem/tokzap-plugins/releases/latest) |
| [WooCommerce](./woocommerce/) | WooCommerce | 🔜 Em breve | — |
| [n8n](./n8n/) | n8n Community | 🔜 Em breve | — |
| [Zapier](./zapier/) | Zapier App | 🔜 Em breve | — |
| [Make](./make/) | Make (Integromat) | 🔜 Em breve | — |
| [Shopify](./shopify/) | Shopify App Store | 🔜 Em breve | — |
| [Nuvemshop](./nuvemshop/) | Nuvemshop App Store | 🔜 Em breve | — |
| [Moodle](./moodle/) | Moodle LMS | 🔜 Em breve | — |

## Quickstart

All integrations use the TokZap REST API:

```
Base URL: https://api.tokzap.com/v1
Auth:     Authorization: Bearer tk_live_xxxxxxxxxxxxxxxxxxxx
```

Get your API key at [tokzap.com/api-keys](https://tokzap.com/api-keys).

## API Reference

Full documentation at [tokzap.com/docs](https://tokzap.com/docs).

### Send OTP
```bash
curl -X POST https://api.tokzap.com/v1/otp/send \
  -H "Authorization: Bearer tk_live_..." \
  -H "Content-Type: application/json" \
  -d '{"phone": "5511999999999"}'
```

### Verify OTP
```bash
curl -X POST https://api.tokzap.com/v1/otp/verify \
  -H "Authorization: Bearer tk_live_..." \
  -H "Content-Type: application/json" \
  -d '{"phone": "5511999999999", "code": "123456"}'
```

## Contributing

Issues and PRs are welcome. Each integration lives in its own directory with its own `README.md` and release workflow.

## License

Each plugin ships under its platform's recommended license (see each directory).  
TokZap API — © [SABIO SYSTEM TECNOLOGIA LTDA](https://tokzap.com)
