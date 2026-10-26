<?php
/**
 * Front-end helper tests (reCAPTCHA widgets, redirect, shortcode).
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$v2_settings = array(
	'recaptcha_enabled'  => true,
	'recaptcha_version'  => 'v2',
	'recaptcha_site_key' => 'site-v2',
);

$v3_settings = array(
	'recaptcha_enabled'  => true,
	'recaptcha_version'  => 'v3',
	'recaptcha_site_key' => 'site-v3',
);

oli_cf_assert_same( 'v2', Oli_CF_Public::recaptcha_version( $v2_settings ), 'version setting v2 is used as-is' );
oli_cf_assert_same( 'v3', Oli_CF_Public::recaptcha_version( $v3_settings ), 'version setting v3 is used as-is' );
oli_cf_assert_same( 'v3', Oli_CF_Public::recaptcha_version( array( 'recaptcha_site_key' => 'site-v2' ) ), 'version is not inferred from the key' );

oli_cf_assert_same(
	'https://www.google.com/recaptcha/api.js',
	Oli_CF_Public::recaptcha_script_url( $v2_settings ),
	'v2 script URL has no render parameter'
);

oli_cf_assert_same(
	'https://www.google.com/recaptcha/api.js?render=site-v3',
	Oli_CF_Public::recaptcha_script_url( $v3_settings ),
	'v3 script URL includes render=SITEKEY'
);

$v2_html = Oli_CF_Public::recaptcha_widget_html( 12, $v2_settings );
oli_cf_assert_true( false !== strpos( $v2_html, 'g-recaptcha' ), 'v2 renders the checkbox widget' );
oli_cf_assert_true( false !== strpos( $v2_html, 'data-sitekey="site-v2"' ), 'v2 widget has the site key' );
oli_cf_assert_same( false, strpos( $v2_html, 'name="g-recaptcha-response"' ), 'v2 does not add a hidden token input' );

$v3_html = Oli_CF_Public::recaptcha_widget_html( 12, $v3_settings );
oli_cf_assert_true( false !== strpos( $v3_html, 'name="g-recaptcha-response"' ), 'v3 renders the hidden token field' );
oli_cf_assert_same( false, strpos( $v3_html, 'g-recaptcha"' ), 'v3 does not render the checkbox widget' );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/oli-cf-public.js' );
oli_cf_assert_true( false !== strpos( $js, 'recaptchaVersion' ), 'public JS branches on recaptchaVersion' );
oli_cf_assert_true( false !== strpos( $js, 'grecaptcha.execute' ), 'public JS still calls execute for v3' );
oli_cf_assert_true( false !== strpos( $js, "=== 'v3'" ), 'execute is gated on version v3' );
oli_cf_assert_true( false !== strpos( $js, 'responseJSON' ), 'AJAX error handler reads JSON success payloads' );
oli_cf_assert_true( false !== strpos( $js, 'oli-cf-field-error' ), 'JS writes per-field error messages' );
oli_cf_assert_true( false !== strpos( $js, 'aria-describedby' ), 'JS links errors with aria-describedby' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/oli-cf-public.css' );
oli_cf_assert_true( false !== strpos( $css, '#8c8f94' ), 'field border uses the contrast color' );
oli_cf_assert_true( false !== strpos( $css, 'focus-visible' ), 'focus-visible outline is defined' );
oli_cf_assert_true( false !== strpos( $css, '#dc3232 !important' ), 'invalid fields use a visible red border' );

$redirect = Oli_CF_Submission::resolve_redirect_url(
	34,
	array(
		'oli_cf_redirect' => 'https://example.com/contact/',
	)
);
oli_cf_assert_same( 'https://example.com/contact/#oli-cf-form-34', $redirect, 'hidden permalink plus form anchor is used' );

$GLOBALS['oli_cf_test_raw_referer'] = 'https://example.com/page/';
$from_referer                       = Oli_CF_Submission::resolve_redirect_url( 9, array() );
oli_cf_assert_same( 'https://example.com/page/#oli-cf-form-9', $from_referer, 'raw referer is the fallback' );
$GLOBALS['oli_cf_test_raw_referer'] = '';

$evil = Oli_CF_Submission::resolve_redirect_url(
	1,
	array(
		'oli_cf_redirect' => 'https://evil.example/phish',
	)
);
oli_cf_assert_same( 'https://example.com/#oli-cf-form-1', $evil, 'invalid redirect falls back to home then the form anchor' );

oli_cf_assert_true(
	Oli_CF_Shortcode::should_register_cf7_compat( array( 'cf7_compat_enabled' => true ) ),
	'compat shortcode registers when enabled and CF7 is absent'
);

$GLOBALS['oli_cf_test_shortcodes']['contact-form-7'] = true;
oli_cf_assert_false(
	Oli_CF_Shortcode::should_register_cf7_compat( array( 'cf7_compat_enabled' => true ) ),
	'compat shortcode is skipped when [contact-form-7] already exists'
);
unset( $GLOBALS['oli_cf_test_shortcodes']['contact-form-7'] );

oli_cf_assert_false(
	Oli_CF_Shortcode::should_register_cf7_compat( array( 'cf7_compat_enabled' => false ) ),
	'compat shortcode stays off when the setting is disabled'
);

$required_html = Oli_CF_Form_Parser::parse( '<label> Your name [text* your-name] </label>', 5 );
oli_cf_assert_true( false !== strpos( $required_html, '<span class="oli-cf-required" aria-hidden="true"> *</span>' ), 'required labels get an asterisk' );
oli_cf_assert_true( Oli_CF_Form_Parser::template_has_required( '[text* your-name] [email your-email]' ), 'template_has_required detects starred tags' );
oli_cf_assert_false( Oli_CF_Form_Parser::template_has_required( '[text your-name]' ), 'optional-only templates have no required note' );

$js_v3_gate = preg_match( '/recaptchaVersion === [\'"]v3[\'"]/', $js );
oli_cf_assert_true( (bool) $js_v3_gate, 'v3 execute is not used for v2' );

$submission_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-oli-cf-submission.php' );
oli_cf_assert_true( false !== strpos( $submission_src, 'wp_doing_ajax()' ), 'non-AJAX handler returns when wp_doing_ajax' );
oli_cf_assert_true( false !== strpos( $submission_src, 'wp_validate_redirect' ), 'redirect URL is validated' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} front test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll front tests passed.\n";
exit( 0 );
