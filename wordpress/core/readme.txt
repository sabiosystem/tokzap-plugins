=== TokZap WhatsApp OTP ===
Contributors: tokzap
Tags: otp, whatsapp, two-factor authentication, 2fa, verification
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add WhatsApp OTP verification to any page, login form, or registration — no coding required. Free forever plan included.

== Description ==

**TokZap WhatsApp OTP** lets you verify users via WhatsApp one-time codes (OTP) on any WordPress site — without writing a single line of code.

Instead of SMS (which is expensive and often unreliable), TokZap sends 6-digit codes directly through WhatsApp using your own number. No per-message fees. No third-party SIM cards.

= How it works =

1. Connect your WhatsApp number to TokZap via QR Code (takes ~2 minutes)
2. Generate an API Key in your TokZap dashboard
3. Install this plugin and paste the API Key in Settings → TokZap
4. Done — start verifying users via WhatsApp

= Features =

**Shortcode — verify on any page**

Add `[tokzap_otp_form]` to any page or post. A clean 3-step form (phone → code → success) appears automatically. Supports redirect after verification.

**Two-Factor Authentication (2FA) on login**

Enable 2FA in Settings → TokZap. After a user enters their password correctly, a WhatsApp code is sent automatically. A popup modal appears on the login screen — the user enters the 6-digit code and is logged in. No password screen remains visible.

Works with optional or mandatory 2FA. Users without a verified WhatsApp number can be blocked or allowed through depending on your settings.

**WhatsApp verification on registration**

Add a WhatsApp field to the native WordPress registration form. Users must verify their number before their account is created. The verified phone is saved to user meta for future use.

**Free Forever plan**

* 1 WhatsApp instance (your own number)
* 50 OTPs per day
* Fixed message template
* TokZap branding on the form
* No credit card required

**Paid plans** unlock unlimited OTPs, custom message templates, branding removal, webhooks, and multiple WhatsApp instances. See [tokzap.com/pricing](https://tokzap.com) for details.

= Use cases =

* Verify phone numbers before allowing checkout (WooCommerce)
* Protect gated content pages
* Add 2FA to admin and editor logins
* Confirm registrations before account activation
* Any situation where you need to confirm a user's WhatsApp number

= Privacy =

Phone numbers entered in the form are sent to the TokZap API to deliver the WhatsApp message. No phone numbers are stored by this plugin in the WordPress database. OTP codes are stored temporarily (5 minutes) in Redis on the TokZap servers and deleted after use. See [tokzap.com/privacy](https://tokzap.com) for the full privacy policy.

== Installation ==

**Via WordPress admin (recommended)**

1. Go to **Plugins → Add New Plugin**
2. Search for "TokZap WhatsApp OTP"
3. Click **Install Now** then **Activate**

**Via ZIP upload**

1. Download the ZIP from [tokzap.com/docs/wordpress](https://tokzap.com/docs/wordpress)
2. Go to **Plugins → Add New Plugin → Upload Plugin**
3. Choose the ZIP file and click **Install Now**, then **Activate**

**Setup**

1. Go to **Settings → TokZap**
2. Paste your API Key (get one free at [tokzap.com](https://tokzap.com))
3. Click **Test Connection** to verify
4. Add `[tokzap_otp_form]` to any page — or enable 2FA / registration OTP in the same settings page

== Frequently Asked Questions ==

= Is it really free? =

Yes. The Free plan is free forever — no credit card, no trial period. You get 1 WhatsApp instance and 50 OTPs per day. Paid plans start when you need more.

= Do I need a dedicated WhatsApp number? =

No. You can use any existing WhatsApp number — personal or business. The number connects as a linked device (WhatsApp Multi-Device), so your phone can stay off after connecting.

= Does it work with WhatsApp Business? =

Yes. TokZap works with both regular WhatsApp and WhatsApp Business accounts.

= How do I get an API Key? =

1. Sign up at [tokzap.com](https://tokzap.com) (free)
2. Connect your WhatsApp via QR Code in the dashboard
3. Go to API Keys → Generate Key
4. Copy the key and paste it in Settings → TokZap

= How long does the OTP code last? =

Each code expires in 5 minutes. After that the user must request a new one.

= Can I customize the WhatsApp message? =

On paid plans (Starter and above) you can set a custom message template using `{{code}}` as the placeholder. On the Free plan the message is fixed: "Your code: {{code}} — Sent via TokZap Free".

= Does 2FA work for all users or just admins? =

You choose. In Settings → TokZap you can enable optional 2FA (only users who have a verified WhatsApp number will be asked for a code) or mandatory 2FA (all users must have a verified number to log in).

= What happens if the user doesn't receive the code? =

The user can click "Didn't receive the code?" to go back to the login page and try again. Each login attempt sends a fresh code.

= Is the plugin compatible with WooCommerce, Elementor, etc.? =

The shortcode `[tokzap_otp_form]` works anywhere WordPress shortcodes work — pages, posts, widgets, Elementor text widgets, WooCommerce checkout hooks, etc.

= Where is my data stored? =

Phone numbers typed in the form are only used to deliver the WhatsApp message and are not stored by the plugin in WordPress. OTP codes live in Redis on TokZap servers for up to 5 minutes. See our privacy policy for details.

== Screenshots ==

1. The OTP verification form rendered via [tokzap_otp_form] shortcode
2. The 2FA popup modal on the WordPress login screen
3. Settings page — API Key, 2FA options, custom message
4. TokZap dashboard — connect WhatsApp via QR Code and manage API Keys

== Changelog ==

= 1.0.0 =
* Initial release
* Shortcode [tokzap_otp_form] for OTP verification on any page
* Two-factor authentication (2FA) via WhatsApp on the login screen
* WhatsApp phone verification on the registration form
* Settings page with API Key, 2FA toggle, custom message and branding options
* Plan sync — plugin automatically detects Free vs paid plan from the API
* Rate limiting: max 3 OTP requests per IP per hour (WordPress layer)

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
