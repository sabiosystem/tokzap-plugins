# TokZap WhatsApp OTP — WordPress Plugin

Add WhatsApp OTP verification to any WordPress page with a single shortcode.

**Plugin:** `tokzap-whatsapp-otp`  
**Version:** 1.0.0  
**Requires:** WordPress 5.8+ · PHP 7.4+  
**License:** GPL v2 or later

## Installation

1. Download the latest `tokzap-whatsapp-otp.zip` from [Releases](https://github.com/sabiosystem/tokzap-plugins/releases/latest)
2. WordPress admin → Plugins → Add New → Upload Plugin → select the ZIP
3. Activate the plugin
4. Go to **Settings → TokZap** and enter your API Key (`tk_live_...`)
5. Add `[tokzap_verify]` to any page or post

Get your API key at [tokzap.com/api-keys](https://tokzap.com/api-keys).

## Shortcode

```
[tokzap_verify]
[tokzap_verify on_success="redirect" redirect_url="/obrigado"]
[tokzap_verify on_success="message" success_message="Identidade confirmada!"]
```

| Attribute | Default | Description |
|---|---|---|
| `on_success` | `message` | `message` shows success text · `redirect` redirects |
| `redirect_url` | — | Destination URL when `on_success="redirect"` |
| `success_message` | `Número verificado com sucesso!` | Text shown after verification |

## Plans

| Plan | OTPs/day | Custom message | Branding |
|---|---|---|---|
| Free | 50 | ❌ | "Powered by TokZap Free" |
| Paid | Unlimited | ✅ | None |

Start free at [tokzap.com](https://tokzap.com) — no credit card required.
