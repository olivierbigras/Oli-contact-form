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
oli_cf_assert_true( (bool) preg_match( '/handleAjaxResponse:\s*function[\s\S]*?var self = this;/', $js ), 'handleAjaxResponse binds var self = this' );
oli_cf_assert_true( false !== strpos( $js, 'resetSubmit' ), 'submit button reset helper exists' );
oli_cf_assert_true( false !== strpos( $js, 'complete:' ), 'AJAX complete resets the submit button' );
oli_cf_assert_true( false !== strpos( $js, 'errorBannerOne' ), 'singular error banner string is used' );
oli_cf_assert_true( false !== strpos( $js, 'errorBannerMany' ), 'plural error banner string is used' );
oli_cf_assert_true( false !== strpos( $js, ' : ' ), 'error summary prefixes the field label' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/oli-cf-public.css' );
oli_cf_assert_true( false !== strpos( $css, '#8c8f94' ), 'field border uses the contrast color' );
oli_cf_assert_true( false !== strpos( $css, '.oli-cf-form .oli-cf-field' ), 'field border selector includes the form to beat themes' );
oli_cf_assert_true( false !== strpos( $css, 'input.oli-cf-field' ), 'input.oli-cf-field raises specificity over input[type]' );
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

$status_redirect = Oli_CF_Submission::resolve_redirect_url(
	6,
	array(
		'oli_cf_redirect' => 'https://example.com/req/',
	),
	true
);
oli_cf_assert_same( 'https://example.com/req/#oli-cf-response-6', $status_redirect, 'status redirect anchors the message banner' );
oli_cf_assert_same( '#oli-cf-response-6', Oli_CF_Submission::redirect_fragment( 6, true ), 'status fragment is the response id' );
oli_cf_assert_same( '#oli-cf-form-6', Oli_CF_Submission::redirect_fragment( 6, false ), 'no-status fragment stays on the form' );

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
oli_cf_assert_true( false !== strpos( $submission_src, 'oli_cf_status' ), 'non-AJAX redirect sets oli_cf_status' );
oli_cf_assert_true( false !== strpos( $submission_src, 'oli_cf_token' ), 'non-AJAX redirect sets a feedback token' );
oli_cf_assert_true( false !== strpos( $submission_src, 'store_feedback' ), 'validation feedback is stored in a transient' );

oli_cf_assert_same( 'validation', Oli_CF_Submission::normalize_status( array( 'success' => false, 'status' => 'validation' ) ), 'validation status is kept' );
oli_cf_assert_same( 'mail_failed', Oli_CF_Submission::normalize_status( array( 'success' => false, 'status' => 'mail_failed' ) ), 'mail_failed status is kept' );
oli_cf_assert_same( 'success', Oli_CF_Submission::normalize_status( array( 'success' => true ) ), 'success status is normalized' );

$GLOBALS['oli_cf_test_transients'] = array();
$token = Oli_CF_Submission::store_feedback(
	12,
	array(
		'success' => false,
		'status'  => 'validation',
		'message' => 'One or more fields have an error. Please check and try again.',
		'errors'  => array(
			'your-email' => 'Please enter a valid email address.',
		),
		'values'  => array(
			'your-name'  => 'Ada',
			'your-email' => 'not-an-email',
		),
	)
);
oli_cf_assert_true( is_string( $token ) && strlen( $token ) >= 12, 'feedback token is generated' );
oli_cf_assert_true( isset( $GLOBALS['oli_cf_test_transients'][ 'oli_cf_fb_' . $token ] ), 'feedback is stored under the token' );
oli_cf_assert_same( 300, $GLOBALS['oli_cf_test_transients'][ 'oli_cf_fb_' . $token ]['ttl'], 'feedback transient lasts 5 minutes' );

$wrong_form = Oli_CF_Submission::consume_feedback( $token, 99 );
oli_cf_assert_same( array(), $wrong_form, 'feedback for another form is rejected' );

$feedback = Oli_CF_Submission::consume_feedback( $token, 12 );
oli_cf_assert_same( 'validation', $feedback['status'], 'consumed feedback keeps validation status' );
oli_cf_assert_same( 'Ada', $feedback['values']['your-name'], 'sanitized values are replayed from the transient' );
oli_cf_assert_true( isset( $feedback['errors']['your-email'] ), 'field errors are replayed from the transient' );
oli_cf_assert_false( get_transient( 'oli_cf_fb_' . $token ), 'feedback transient is deleted after consume' );

$replay = Oli_CF_Form_Parser::parse(
	'<label> Your email [email* your-email] </label>',
	12,
	array(
		'values' => array( 'your-email' => 'not-an-email' ),
		'errors' => array( 'your-email' => 'Please enter a valid email address.' ),
	)
);
oli_cf_assert_true( false !== strpos( $replay, 'value="not-an-email"' ), 'invalid email is repopulated after no-JS validation' );
oli_cf_assert_true( false !== strpos( $replay, 'aria-invalid="true"' ), 'repopulated invalid field is aria-invalid' );
oli_cf_assert_true( false !== strpos( $replay, 'oli-cf-field-error' ), 'repopulated field shows a per-field error' );
oli_cf_assert_true( false !== strpos( $replay, 'Please enter a valid email address.' ), 'repopulated error text is shown' );

$shortcode_src = file_get_contents( dirname( __DIR__ ) . '/public/class-oli-cf-shortcode.php' );
oli_cf_assert_true( false !== strpos( $shortcode_src, 'tabindex="-1"' ), 'response output is focusable' );
oli_cf_assert_true( false !== strpos( $shortcode_src, 'id="oli-cf-response-' ), 'response output has a unique id' );
oli_cf_assert_true( false !== strpos( $shortcode_src, 'format_error_summary_item' ), 'no-JS error list prefixes the field label' );

$wrapped_labels = Oli_CF_Form_Parser::field_labels( '<label> Your email [email* your-email] </label> [file your-file] [acceptance consent "I agree to the privacy policy"]' );
oli_cf_assert_same( 'Your email', $wrapped_labels['your-email'], 'wrapping label text is the field label' );
oli_cf_assert_same( 'Your File', $wrapped_labels['your-file'], 'unlabeled fields use a humanized name' );
oli_cf_assert_same( 'I agree to the privacy policy', $wrapped_labels['consent'], 'acceptance values become the label' );
oli_cf_assert_same(
	'Your email : Please fill out this field.',
	Oli_CF_Form_Parser::format_error_summary_item( 'Your email', 'Please fill out this field.' ),
	'no-JS summary line matches the JS Label : message format'
);
oli_cf_assert_true( false !== strpos( $shortcode_src, 'oli_cf_status' ), 'shortcode reads oli_cf_status' );
oli_cf_assert_true( false !== strpos( $shortcode_src, 'consume_feedback' ), 'shortcode consumes the feedback token' );
oli_cf_assert_true( false !== strpos( $js, 'oli-cf-response-' ), 'JS can focus the hashed status banner' );
oli_cf_assert_true( false !== strpos( $submission_src, 'redirect_fragment' ), 'redirect hash is chosen from status' );

$submit_html = Oli_CF_Form_Parser::parse( '[submit "Send"]', 8 );
oli_cf_assert_same( false, strpos( $submit_html, 'disabled' ), 'submit button is not disabled without JS' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} front test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll front tests passed.\n";
exit( 0 );
