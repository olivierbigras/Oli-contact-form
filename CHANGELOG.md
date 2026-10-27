# Changelog

All notable changes to Oli Contact Form will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.12] - 2026-10-27

### Modifié
- Readme : la description courte indique que les pièces jointes ne sont « jamais conservées sur votre site » (« never kept on your site »).

### Sécurité
- La notice de résultat d'import CF7 exige un nonce dédié (`oli_cf_imported_nonce`), ajouté aux deux redirections de l'importeur.
- Le masquage de la notice de staging vérifie le nonce avec `wp_verify_nonce()` avant toute lecture de la requête.
- Les réglages de courriel sont lus via `filter_input()` puis nettoyés champ par champ par `Oli_CF_Mailer::sanitize_mail_settings()` (les balises `<[champ]>` sont conservées), sans `phpcs:ignore`.
- Échappement tardif : le gabarit du formulaire et le widget reCAPTCHA passent par `wp_kses()` avec une liste blanche explicite à l'affichage.
- Désinstallation : requête sur la table des options avec `%i`.

### Corrigé
- Le pot de miel et les jetons reCAPTCHA/Turnstile étaient retirés des données avant le contrôle anti-spam (depuis 0.3.3) : un pot de miel rempli n'était pas rejeté, et un captcha activé avec clé secrète refusait tous les envois. Ils sont de nouveau vérifiés.
- Le repli sans JavaScript s'exécute sur `wp_loaded` au lieu de `init` : un refus (nonce invalide) ne déclenche plus d'avis WooCommerce « appelé trop tôt ».

### Notes
- Version 0.3.11 sautée (réservée à une version de travail non publiée).

## [0.3.10] - 2026-10-27

### Changed
- Readme: clearer short description.

## [0.3.9] - 2026-10-27

### Security
- Plus de repli vers `tmp/` du plugin ni vers un dossier sous la racine web. Le staging n’utilise que `get_temp_dir()` / `sys_get_temp_dir()` (ou `OLI_CF_STAGING_DIR` / `oli_cf_staging_parent`) si le `realpath` est hors `ABSPATH`, `WP_CONTENT_DIR` et uploads.
- Dossier d’attente en `0700`, fichier en `0600`, nom aléatoire `.bin`. Le nom d’origine n’apparaît que comme clé de pièce jointe `wp_mail()`.
- Sans dossier privé : la soumission avec fichier est refusée (message traduisible, aucun copie, aucun courriel) et un avis admin refermable est fixé. Mise à jour : nettoyage de l’ancien `tmp/` du plugin.

## [0.3.8] - 2026-10-27

### Security
- Les fichiers téléversés passent par un dossier temporaire privé à nom aléatoire, sont joints au courriel, puis sont toujours supprimés (`try/finally`, `register_shutdown_function`, purge des restes de plus d’une heure au prochain envoi et par cron).
- `is_uploaded_file()` est exigé avant toute copie. `wp_check_filetype_and_ext` complète la liste d’extensions. Les fichiers ne sont jamais écrits directement dans `get_temp_dir()` lorsque celui-ci vaut `wp-content/`.
- `uninstall.php` supprime les transients `oli_cf_fb_*` (et `_timeout`), y compris en multisite.

## [0.3.7] - 2026-10-26

### Fixed
- Saving mail settings used `sanitize_textarea_field` / `sanitize_text_field`, which stripped `<[your-email]>` from the default body (`From: [your-name] <[your-email]>`). Mail body, From, Reply-To, To, Subject, and Additional headers now keep `<[field]>` and `<user@example.com>`, strip real HTML, drop null bytes, and remove CR/LF from single-line headers.

## [0.3.6] - 2026-10-26

### Fixed
- Chargement des traductions du script de l’éditeur de bloc (`wp_set_script_translations` sur `oli-cf-block-editor`).
- Sujets, corps et libellés (From / Subject / Message) des courriels par défaut désormais traduisibles. Les valeurs déjà enregistrées dans les options ou métas restent inchangées.
- En-tête `Domain Path: /languages` et fichier POT régénéré (y compris les chaînes JS du bloc).

## [0.3.5] - 2026-10-26

### Fixed
- `_oli_cf_mail` (and Mail 2) is merged with defaults via `wp_parse_args` before the form editor reads keys. A programmatic or migrated meta missing `reply_to` no longer triggers an undefined-array-key warning. The mailer normalizes the same keys before send.

## [0.3.4] - 2026-10-26

### Fixed
- No-JS redirects with a status now hash `#oli-cf-response-{id}` (the banner has that id and `tabindex="-1"`). Without a status the hash stays `#oli-cf-form-{id}`. A tiny no-dependency script focuses the banner when JS is on; the fragment works without JS.
- No-JS error summary links prefix the field label (`Label : message`), matching the JS summary.
- Field border `#8c8f94` uses `.oli-cf-form .oli-cf-field` / `input.oli-cf-field` so Storefront and Twenty Twenty-Five do not win on `input[type=text]`.

## [0.3.3] - 2026-10-26

### Fixed
- AJAX `handleAjaxResponse()` used `self` without binding it (`window.self`), so success and error banners never appeared and the submit button stayed disabled with a spinner. The handler now uses `var self = this` and `resetSubmit()` on success, error, and network failure (`complete`).
- No-JS validation was shown as a generic send error. Redirects now set `oli_cf_status=validation` or `mail_failed` and replay sanitized values plus per-field errors from a 5-minute token transient (no PII in the URL).
- JS error summary names the field (`Label : message`) and uses singular/plural strings.
- `.oli-cf-response-output` has `tabindex="-1"` and receives focus after a no-JS return.
- Imported CF7 `[acceptance]` is required by default, like CF7, with or without `*`. It is optional only when the CF7 `optional` option is present. The plugin default form still uses `[acceptance … optional]`.

## [0.3.2] - 2026-10-26

### Added
- Settings field for reCAPTCHA version (v2 checkbox or v3 invisible). The version is never inferred from the site key.
- Required-field asterisks in labels and a “Required field” note when a form has required fields.
- Per-field validation messages (`aria-describedby`, focus first invalid field).

### Fixed
- AJAX submits were also handled on `init`, which sent a 302 and showed a generic error after a successful mail. The non-AJAX handler now returns immediately when `wp_doing_ajax()`.
- Non-JS submits redirected to the homepage because `wp_get_referer()` is false on a same-page POST. Redirect uses a hidden permalink validated with `wp_validate_redirect()`, then `wp_get_raw_referer()`, and lands on `#oli-cf-form-{id}`.
- reCAPTCHA v3 siteverify responses without a `score` are rejected. v2 still accepts `success: true`.
- Front end loads `api.js` without `render=` and the checkbox widget for v2; v3 keeps `api.js?render=` plus `grecaptcha.execute`. AJAX treats a JSON success payload as success even if the transport used the error callback.
- CF7 `[file your-file limit:… filetypes:…]` keeps those options. Existing type/size rules, `.php` rejection, attach-then-delete still apply.
- CF7 `[acceptance]…[/acceptance]` uses the inner text as the checkbox label.
- Keyboard focus uses `:focus-visible` (2px `#0073aa` on fields, `#1d2327` on the submit button). Field borders are `#8c8f94` (3.24:1).
- `[contact-form-7]` is registered only when compatibility mode is on **and** Contact Form 7 is not active.

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
