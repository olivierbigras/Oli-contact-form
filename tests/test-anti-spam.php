<?php
/**
 * Anti-spam / reCAPTCHA tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$v2_ok = Oli_CF_Anti_Spam::recaptcha_body_is_valid(
	array(
		'success'  => true,
		'hostname' => 'testkey.google.com',
	)
);
oli_cf_assert_same( true, $v2_ok, 'reCAPTCHA v2 success without score is accepted' );

$v2_fail = Oli_CF_Anti_Spam::recaptcha_body_is_valid(
	array(
		'success' => false,
	)
);
oli_cf_assert_same( false, $v2_fail, 'reCAPTCHA v2 unsuccessful body is rejected' );

$v3_low = Oli_CF_Anti_Spam::recaptcha_body_is_valid(
	array(
		'success' => true,
		'score'   => 0.1,
	)
);
oli_cf_assert_same( false, $v3_low, 'reCAPTCHA v3 score below 0.5 is rejected' );

$v3_ok = Oli_CF_Anti_Spam::recaptcha_body_is_valid(
	array(
		'success' => true,
		'score'   => 0.9,
	)
);
oli_cf_assert_same( true, $v3_ok, 'reCAPTCHA v3 score at or above 0.5 is accepted' );

$v3_edge = Oli_CF_Anti_Spam::recaptcha_body_is_valid(
	array(
		'success' => true,
		'score'   => 0.5,
	)
);
oli_cf_assert_same( true, $v3_edge, 'reCAPTCHA v3 score of exactly 0.5 is accepted' );

$missing = Oli_CF_Anti_Spam::recaptcha_body_is_valid( array() );
oli_cf_assert_same( false, $missing, 'empty reCAPTCHA body is rejected' );

$host_ok = Oli_CF_Anti_Spam::recaptcha_hostname_is_valid(
	array(
		'hostname' => 'example.com',
	)
);
oli_cf_assert_same( true, $host_ok, 'hostname matching the site is accepted' );

$host_test = Oli_CF_Anti_Spam::recaptcha_hostname_is_valid(
	array(
		'hostname' => 'testkey.google.com',
	)
);
oli_cf_assert_same( true, $host_test, 'official Google test hostname is accepted' );

$host_bad = Oli_CF_Anti_Spam::recaptcha_hostname_is_valid(
	array(
		'hostname' => 'evil.example.net',
	)
);
oli_cf_assert_same( false, $host_bad, 'unexpected hostname is rejected when the site host is known' );

$host_missing = Oli_CF_Anti_Spam::recaptcha_hostname_is_valid( array() );
oli_cf_assert_same( true, $host_missing, 'missing hostname is not treated as a failure' );

$honeypot = Oli_CF_Anti_Spam::check( array( 'oli_cf_hp' => 'bot' ) );
oli_cf_assert_true( is_array( $honeypot ), 'filled honeypot is rejected' );

$clean = Oli_CF_Anti_Spam::check( array( 'your-name' => 'Ada' ) );
oli_cf_assert_same( true, $clean, 'submission without captcha settings passes' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} anti-spam test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll anti-spam tests passed.\n";
exit( 0 );
