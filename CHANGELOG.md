# Changelog

All notable changes to Oli Contact Form will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.1] - 2026-10-26

### Fixed
- reCAPTCHA v2 submissions were rejected because the verifier required a `score` that v2 does not return. `success: true` is enough for v2; the 0.5 score threshold applies only to v3. Hostname is checked when present (official Google test keys that report `testkey.google.com` remain accepted). Turnstile is unchanged.
- Contact Form 7 special mail-tags (`[_site_admin_email]`, `[_site_title]`, `[_site_url]`, `[_site_description]`, `[_remote_ip]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, and related) are resolved when mail is built. If the resolved recipient is empty or invalid, the site `admin_email` is used and the case is logged when `WP_DEBUG` is on.
- `maxlength` and `minlength` are validated on the server with `mb_strlen` and rendered on text inputs and textareas.

## [0.3.0] - 2026-10-26

### Removed
- Optional submission database, Messages admin screen, retention cron, and privacy exporters. Submissions are email-only.
- `load_plugin_textdomain()`. WordPress.org language packs load the `oli-contact-form` text domain automatically.

### Added
- CLI tests for validation, mail building, upload deletion, and Contact Form 7 import.
- Editable field-level validation messages (required, email, phone, file type/size, acceptance).
- `phone` form tag as an alias of `tel`.
- File staging with extension and size limits. Staged files are attached to the notification email and deleted immediately after send.

### Changed
- Author URI is `https://github.com/olivierbigras`. Plugin URI stays `https://github.com/olivierbigras/Oli-contact-form`.
- CF7 importer converts form tags, mail (including Reply-To), mail 2, and messages.
- Honeypot remains the built-in anti-spam measure; reCAPTCHA v3 and Turnstile stay optional and load only on form pages.
- Gutenberg block uses API version 3 and `wp-block-editor`.

## [0.2.1] - 2026-10-21

### Fixed
- Add `ABSPATH` direct-access guards to every plugin PHP file (Plugin Check).
- Keep official reCAPTCHA and Turnstile CDN/API URLs (they cannot be self-hosted). Load client scripts only when the service is enabled and the page has a form; document the exception in `readme.txt` and `REVIEW-NOTES.md`.

### Changed
- Default notification `From` is the site admin email. `[your-email]` stays in `Reply-To` to avoid SPF/DMARC failures.
- Default form template includes an optional consent checkbox (`[acceptance consent …]` without `*`).
- Distribution ZIP excludes development files via `.distignore` and `.gitattributes` `export-ignore`.

## [0.2.0] - 2026-10-21

### Added
- Optional **Keep a copy of messages** setting (disabled by default). Removed in 0.3.0.

## [0.1.1] - 2026-10-21

### Changed
- Document Google reCAPTCHA v3 and Cloudflare Turnstile in `readme.txt` under `== External services ==`.
- Extract the CF7 importer "select all" inline script into `assets/js/oli-cf-importer.js`.
- Move the honeypot off-screen style into `assets/css/oli-cf-public.css`.
- Prefix captcha script handles as `oli-cf-recaptcha` and `oli-cf-turnstile`.
- Enqueue public CSS/JS and captcha scripts only when a form is present.
- Sign non-AJAX redirect status query args with `oli_cf_status_nonce`.

## [0.1.0] - 2026-10-19

### Added
- Initial release of Oli Contact Form
