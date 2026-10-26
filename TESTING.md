# Testing Guide for Oli Contact Form v0.3.1

## CLI tests

```bash
php tests/run.php
```

Covers validation (including minlength/maxlength), mail building and CF7 special mail-tags, reCAPTCHA v2/v3 body checks, upload deletion, and CF7 import conversion.

## Manual checks

1. Activate the plugin. **Oli Contact Forms** appears in the admin menu.
2. Add a form with text, email, phone, textarea, select, radio, checkbox, acceptance, and file fields.
3. Embed with `[oli_contact_form id="…"]` and with the Oli Contact Form block.
4. Submit with JavaScript on (AJAX) and off (redirect + status nonce).
5. Confirm required/invalid messages are editable on the form editor.
6. Confirm the admin email and optional Mail 2 confirmation replace `[field]` tags.
7. Upload a file, confirm it arrives as an attachment, and confirm it is not left in uploads or temp.
8. Import a Contact Form 7 form and compare tags plus mail settings.
9. Enable Turnstile or reCAPTCHA and confirm the script loads only on pages that contain a form.

## PHPCS

```bash
phpcs --standard=WordPress-Extra --extensions=php --ignore=vendor/,tests/ .
```
