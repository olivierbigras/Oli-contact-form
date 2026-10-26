# Review notes for wordpress.org (not shipped in the plugin ZIP)

Source: https://github.com/olivierbigras/Oli-contact-form

This file is for the plugin review team. It is listed in `.distignore` and `.gitattributes` (`export-ignore`) so it never appears in the distribution ZIP.

## OffloadedContent — Google reCAPTCHA v3 and Cloudflare Turnstile

Plugin Check reports `OffloadedContent` for the official reCAPTCHA and Turnstile URLs in `public/class-oli-cf-public.php` (client scripts) and `includes/class-oli-cf-anti-spam.php` (server-side `siteverify`). Those URLs are kept on purpose.

Google reCAPTCHA (v2 or v3) and Cloudflare Turnstile are optional anti-spam **services**, not plugin assets hosted on a CDN. Their client scripts (`https://www.google.com/recaptcha/api.js` and `https://challenges.cloudflare.com/turnstile/v0/api.js`) must be loaded from the provider. v2 omits the `render` parameter; v3 appends `?render=SITEKEY`. Tokens are issued and signed by Google or Cloudflare; a copy bundled in this plugin would not mint valid tokens and would violate each provider’s integration requirements. The matching `siteverify` endpoints are the only supported way to check those tokens.

This matches the documented exception for plugins that perform a service (similar to Akismet calling a remote spam API): the remote call *is* the feature, not an offloaded jQuery/CSS file.

Loading is gated:

1. An administrator must enable the service and save a site key under Oli Contact Forms → Settings.
2. The client script is enqueued only when a form is present on the page (shortcode `[oli_contact_form]`, the `oli-cf/contact-form` block, or a mapped CF7 shortcode when compatibility mode is on).
3. `siteverify` runs only on form submit, and only if that service is enabled.

The built-in honeypot never leaves the site. Secret keys stay in the options table and are never printed in storefront HTML or JavaScript.

The same justification is in `readme.txt` under **External services**, with terms and privacy links for each provider.

Each remote URL has a `phpcs:ignore` comment naming the Plugin Check sniff and this reason.

## Translations

The plugin does not call `load_plugin_textdomain()` and does not ship `.po` or `.mo` files. The text domain is `oli-contact-form`.

## No submission storage

Form submissions are sent with `wp_mail()` only. File uploads are copied to a temporary path, attached to the notification email, and deleted immediately after send.
