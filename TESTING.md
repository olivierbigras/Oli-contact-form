# Testing Guide for Oli Contact Form v0.4.0

## CLI tests

```bash
php tests/run.php
```

Covers validation (including minlength/maxlength), mail tokens, reCAPTCHA v2/v3 (version setting, score rules), upload deletion, form import (file fields and acceptance required unless marked optional), field markup, notification conditions, migration of stored forms, AJAX `self`/banner/spinner reset, no-JS validation vs mail_failed transients, status-banner redirect hash, field-border specificity, and required-field marks.

## Manual checks

1. Activate the plugin. **Oli Contact Forms** appears in the admin menu.
2. Add a form with text, email, phone, textarea, select, radio, checkbox, acceptance, and file fields.
3. Embed with `[oli_contact_form id="…"]` and with the Oli Contact Form block.
4. Submit with JavaScript on (AJAX) and off (redirect + status nonce).
5. Confirm required/invalid messages are editable on the form editor.
6. Confirm the admin notification and the optional sender confirmation replace `{field:name}` tokens.
7. Upload a file, confirm it arrives as an attachment, and confirm it is not left in uploads or temp.
8. Import an existing form, review unsupported fields, and confirm the draft uses the field builder.
9. Enable Turnstile or reCAPTCHA and confirm the script loads only on pages that contain a form.

## PHPCS

```bash
phpcs --standard=WordPress-Extra --extensions=php --ignore=vendor/,tests/ .
```
