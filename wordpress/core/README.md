# TokZap WhatsApp OTP — WordPress Plugin

Add WhatsApp OTP verification to any WordPress page with a single shortcode.

**Slug:** `tokzap-whatsapp-otp`  
**Version:** 1.0.0  
**Requires:** WordPress 5.8+ · PHP 7.4+  
**License:** GPL v2 or later

## Download

[tokzap.com/downloads/wordpress](https://tokzap.com/downloads/wordpress) (requer login)

Ou via [GitHub Releases](https://github.com/sabiosystem/tokzap-plugins/releases?q=wordpress).

## Installation

1. Download `tokzap-wordpress-1.0.0.zip`
2. WordPress admin → Plugins → Add New → Upload Plugin → select ZIP
3. Activate the plugin
4. Go to **Settings → TokZap** and enter your API Key (`tk_live_...`)
5. Add `[tokzap_verify]` to any page or post

Get your API Key at [tokzap.com/api-keys](https://tokzap.com/api-keys).

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

## Release

```bash
git tag wordpress/v1.0.1
git push origin wordpress/v1.0.1
```

The GitHub Actions workflow builds the ZIP and creates the release automatically.
