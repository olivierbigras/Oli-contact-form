=== Oli Contact Form ===
Contributors: bigrat95
Tags: contact form, form, email, ajax, contact
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple, lightweight contact form plugin. Author: Olivier Bigras. Author URI: https://github.com/olivierbigras. Email: oli@olivierbigras.com

== Description ==

Oli Contact Form is a deliberately simple alternative to Contact Form 7. Create forms in the admin list, embed them with a shortcode or a block, and send the result by email. Nothing is stored in a submissions table.

Author: Olivier Bigras
Author URI: https://github.com/olivierbigras
Plugin URI: https://github.com/olivierbigras/Oli-contact-form
Email: oli@olivierbigras.com

**Key Features:**

* **Admin form list** - Create and edit forms under Oli Contact Forms
* **Shortcode and block** - `[oli_contact_form id="123"]` or the Oli Contact Form block
* **Field types** - text, email, phone (`tel` / `phone`), textarea, select, radio, checkbox, acceptance, file
* **File uploads** - Extension and size limits. The file is attached to the notification email and deleted from the server immediately after sending
* **Required fields and validation** - Server-side checks with editable messages
* **Admin email + optional confirmation** - Mail 2 sends a copy to the sender. Use `[field-name]` tags in the subject and body
* **Anti-spam** - Honeypot on every form, plus optional Cloudflare Turnstile or Google reCAPTCHA v2/v3 (scripts load only on pages that contain a form)
* **AJAX submit** - With a non-JavaScript fallback
* **Translatable** - Standard gettext strings with the `oli-contact-form` text domain, compatible with TranslatePress
* **CF7 importer** - Converts existing Contact Form 7 form tags and mail settings
* **No submission database** - Email only. No tracking

Google reCAPTCHA (v2 or v3) and Cloudflare Turnstile stay off until an administrator enables them and enters API keys. See **External services** below.

== External services ==

This plugin does not call any third-party API by default. The built-in honeypot runs entirely on your WordPress site. The following services are used only after an administrator enables them under Oli Contact Forms → Settings.

**Why remote scripts are required:** Google reCAPTCHA v3 and Cloudflare Turnstile are third-party anti-spam services. Their client scripts must be loaded from the provider CDN (`www.google.com` / `challenges.cloudflare.com`). Tokens are issued and signed by those services and can only be verified on their servers. The scripts cannot be copied into this plugin or self-hosted: a local copy would not mint valid tokens. They are therefore not “offloaded plugin assets”; they are the official service APIs. The plugin never loads them unless (1) an administrator has enabled the service and saved a site key, and (2) the current page actually contains a form (shortcode or block). Server-side `siteverify` calls run only on submit, and only when that service is enabled.

Source code: [https://github.com/olivierbigras/Oli-contact-form](https://github.com/olivierbigras/Oli-contact-form)

= Google reCAPTCHA =

When **Enable Google reCAPTCHA** is checked, a version (v2 or v3) is selected, and a site key is saved, visitors who view a form load Google's official reCAPTCHA script. The version is a saved setting; it is never guessed from the key.

* v2 loads `https://www.google.com/recaptcha/api.js` (no `render` parameter) and shows the “I'm not a robot” checkbox. The widget token is posted as `g-recaptcha-response`.
* v3 loads `https://www.google.com/recaptcha/api.js?render=SITEKEY` and calls `grecaptcha.execute` on submit. Siteverify must return a score of at least 0.5.
* When it runs: only if reCAPTCHA is enabled **and** the page has a form (shortcode or block). Verification runs on each submission.
* Server endpoint: `https://www.google.com/recaptcha/api/siteverify` via `wp_remote_post` (only on submit, only if enabled).
* Data sent: the visitor's reCAPTCHA response token, your reCAPTCHA secret key, and the visitor's IP address (`remoteip`). Google may also collect device and interaction data as described in their privacy policy.
* Service: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Terms: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Cloudflare Turnstile =

When **Enable Cloudflare Turnstile** is checked and a site key is saved, visitors who view a form load Cloudflare's official Turnstile widget. On submit, WordPress verifies the widget token with Cloudflare. The script is not bundled because Cloudflare requires `https://challenges.cloudflare.com/turnstile/v0/api.js`.

* When it runs: only if Turnstile is enabled **and** the page has a form (shortcode or block). Verification runs on each submission.
* Client script (official CDN, cannot be self-hosted): `https://challenges.cloudflare.com/turnstile/v0/api.js`
* Server endpoint: `https://challenges.cloudflare.com/turnstile/v0/siteverify` via `wp_remote_post` (only on submit, only if enabled).
* Data sent: the visitor's Turnstile response token, your Turnstile secret key, and the visitor's IP address (`remoteip`). Cloudflare may also collect device and challenge data as described in their privacy policy.
* Service: [https://www.cloudflare.com/products/turnstile/](https://www.cloudflare.com/products/turnstile/)
* Terms: [https://www.cloudflare.com/website-terms/](https://www.cloudflare.com/website-terms/)
* Privacy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

Secret keys are stored in the WordPress options table and are never printed in storefront HTML or JavaScript.

== Privacy ==

This plugin does **not** store form submissions. Messages are sent with WordPress `wp_mail()` only. File uploads are attached to the notification email and deleted from the server right after sending.

Optional anti-spam services (Google reCAPTCHA or Cloudflare Turnstile) may process visitor data according to those providers’ policies when they are enabled.

Uninstalling the plugin deletes settings and contact form posts.

== Installation ==

1. Upload the `oli-contact-form` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Oli Contact Forms' in the admin menu to create your first form
4. Use the shortcode `[oli_contact_form id="123"]` or the Gutenberg block to display your form

== Frequently Asked Questions ==

= How do I create a form? =

Go to 'Oli Contact Forms' > 'Add New' in your WordPress admin. Use the tag generator buttons to add fields, or write tags directly using CF7-like syntax (e.g., `[text* your-name]` for a required text field).

= What field types are supported? =

Text, email, phone (`[tel]` or `[phone]`), textarea, select, checkbox, radio, acceptance, and file upload. Submit buttons are supported. Extra input types (`url`, `number`, `date`) also work.

= Can I import my Contact Form 7 forms? =

Yes. Go to 'Oli Contact Forms' > 'Import from CF7' to convert your existing CF7 form tags and mail settings.

= Does it work without JavaScript? =

Yes. AJAX is used when JavaScript is available. Without it, the form posts normally and redirects back with a status message.

= How do I enable anti-spam protection? =

Every form includes a honeypot field. For additional protection, go to 'Oli Contact Forms' > 'Settings' to enable Google reCAPTCHA (v2 or v3) or Cloudflare Turnstile.

= Where are form submissions stored? =

They are not stored. Submissions are sent by email only. Uploaded files are attached to that email and then deleted.

= Can I customize the validation messages? =

Yes. Each form has a Messages box for the success text, mail error, form-level validation error, and field-level messages.

= Is it compatible with TranslatePress? =

Yes. User-facing strings use the `oli-contact-form` text domain and standard WordPress translation functions.

== Screenshots ==

1. Form editor with tag generator buttons
2. Mail settings configuration
3. Form displayed on the frontend
4. Gutenberg block settings
5. CF7 importer

== Changelog ==

= 0.3.4 =
* No-JS status return now hashes #oli-cf-response-{id} (the banner has that id, tabindex="-1"). Without a status the hash stays #oli-cf-form-{id}.
* No-JS error summary links prefix the field label (Label : message), like the JS summary.
* Field border #8c8f94 uses a more specific selector so Storefront and Twenty Twenty-Five do not override it.

= 0.3.3 =
* AJAX success and error banners show again: handleAjaxResponse now binds `this` instead of window.self.
* The submit button and spinner reset after success, validation errors, and network failures.
* No-JS validation is distinguished from a send failure (oli_cf_status=validation|mail_failed). Field values and errors come back via a 5-minute token transient, not the URL.
* JS error summary names the field and uses singular/plural copy.
* Status banner (.oli-cf-response-output) is focusable (tabindex="-1") after a no-JS return.
* Imported CF7 [acceptance] is required by default (with or without *); optional only when the CF7 optional option is present.

= 0.3.2 =
* reCAPTCHA version is a setting (v2 checkbox or v3 execute). Do not guess the version from the key.
* v3 siteverify responses without a score are rejected. v2 still accepts success:true.
* CF7 [file] tags keep limit and filetypes. Paired [acceptance]…[/acceptance] becomes the checkbox label.
* AJAX submits are no longer intercepted by the non-AJAX handler (JSON success instead of a 302).
* Non-JS submits return to the same page (#oli-cf-form-{id}) via a validated hidden permalink.
* Per-field error messages, required asterisks, stronger focus and contrast styles.
* Do not register [contact-form-7] while Contact Form 7 is active.

= 0.3.1 =
* Accept reCAPTCHA v2 when siteverify returns success (score threshold is v3 only).
* Resolve Contact Form 7 special mail-tags such as [_site_admin_email] on send. Fall back to the site admin email when To is empty or invalid.
* Enforce maxlength and minlength on the server (mb_strlen) and render those attributes in the form HTML.

= 0.3.0 =
* Remove submission database storage. Email only.
* Remove load_plugin_textdomain(); translations load from WordPress.org language packs.
* Editable field-level validation messages.
* File uploads are staged, attached, and deleted immediately after send.
* CF7 importer converts form tags and mail settings, including Reply-To.
* Tests for validation, mail build, upload deletion, and CF7 import.
* Author URI https://github.com/olivierbigras (Plugin URI remains the repository URL).

= 0.2.1 =
* Add ABSPATH direct-access guards to every plugin PHP file.
* Load reCAPTCHA and Turnstile CDN scripts only when the service is enabled and the page has a form; document why they cannot be self-hosted.
* Default notification From is the site admin email; the visitor address stays in Reply-To (SPF/DMARC).
* Add an optional consent checkbox to the default form template.
* Exclude development files from the distribution ZIP.

= 0.2.0 =
* Optional stored message copies (removed in 0.3.0).

= 0.1.1 =
* Document Google reCAPTCHA v3 and Cloudflare Turnstile under External services.
* Move the CF7 importer script into an enqueued file.
* Load front-end scripts only on form pages.
* Verify the non-AJAX success/error query args with a nonce.

= 0.1.0 =
* Initial release

== Upgrade Notice ==

= 0.3.4 =
No-JS status returns land on the message banner. Field borders keep #8c8f94 under common themes.

= 0.3.3 =
Fixes the AJAX banner crash (self is undefined). Imported CF7 acceptance fields are required unless they use the optional option.

= 0.3.2 =
Choose reCAPTCHA v2 or v3 in Settings. AJAX success is shown correctly. Imported CF7 file and acceptance fields keep their options and labels.

= 0.3.1 =
Fixes reCAPTCHA v2 rejection, empty imported CF7 recipients, and missing maxlength/minlength checks.

= 0.3.0 =
Submissions are email-only. The optional Messages database from 0.2.x is removed. File uploads are still attached to email and then deleted.

= 0.2.1 =
New forms use the site admin email as From (visitor email in Reply-To) and include an optional consent checkbox. Existing forms are unchanged.

= 0.1.0 =
Initial release of Oli Contact Form.
