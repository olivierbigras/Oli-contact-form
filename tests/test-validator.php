<?php
/**
 * Validation tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$template = '<label>[text* your-name]</label> [email* your-email] [tel your-phone] [textarea your-message] [acceptance* terms "I agree"] [file your-file limit:1mb filetypes:pdf|jpg] [submit "Send"]';

$ok = Oli_CF_Validator::validate(
	array(
		'your-name'    => 'Ada Lovelace',
		'your-email'   => 'ada@example.com',
		'your-phone'   => '+1 555-0100',
		'your-message' => 'Hello',
		'terms'        => '1',
	),
	$template,
	array()
);

oli_cf_assert_same( true, $ok, 'valid submission without a file passes' );

$required = Oli_CF_Validator::validate(
	array(
		'your-name'  => '',
		'your-email' => 'ada@example.com',
		'terms'      => '1',
	),
	$template,
	array()
);

oli_cf_assert_true( is_array( $required ), 'missing required name returns errors' );
oli_cf_assert_true( isset( $required['your-name'] ), 'required error is keyed by field name' );

$email = Oli_CF_Validator::validate(
	array(
		'your-name'  => 'Ada',
		'your-email' => 'not-an-email',
		'terms'      => '1',
	),
	$template,
	array()
);

oli_cf_assert_true( is_array( $email ) && isset( $email['your-email'] ), 'invalid email is rejected' );

$phone = Oli_CF_Validator::validate(
	array(
		'your-name'  => 'Ada',
		'your-email' => 'ada@example.com',
		'your-phone' => 'call me later',
		'terms'      => '1',
	),
	$template,
	array()
);

oli_cf_assert_true( is_array( $phone ) && isset( $phone['your-phone'] ), 'invalid phone is rejected' );

$acceptance = Oli_CF_Validator::validate(
	array(
		'your-name'  => 'Ada',
		'your-email' => 'ada@example.com',
	),
	$template,
	array()
);

oli_cf_assert_true( is_array( $acceptance ) && isset( $acceptance['terms'] ), 'required acceptance is rejected' );

$custom = Oli_CF_Validator::validate(
	array(
		'your-name'  => '',
		'your-email' => 'ada@example.com',
		'terms'      => '1',
	),
	$template,
	array(),
	array(
		'invalid_required' => 'Name please',
	)
);

oli_cf_assert_same( 'Name please', $custom['your-name'], 'editable required message is used' );

$big = Oli_CF_Validator::validate(
	array(
		'your-name'  => 'Ada',
		'your-email' => 'ada@example.com',
		'terms'      => '1',
	),
	$template,
	array(
		'your-file' => array(
			'name'     => 'notes.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => '/tmp/fake',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 2 * 1024 * 1024,
		),
	)
);

oli_cf_assert_true( is_array( $big ) && isset( $big['your-file'] ), 'oversized file is rejected' );

$bad_type = Oli_CF_Validator::validate(
	array(
		'your-name'  => 'Ada',
		'your-email' => 'ada@example.com',
		'terms'      => '1',
	),
	$template,
	array(
		'your-file' => array(
			'name'     => 'shell.php',
			'type'     => 'application/x-php',
			'tmp_name' => '/tmp/fake',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 100,
		),
	)
);

oli_cf_assert_true( is_array( $bad_type ) && isset( $bad_type['your-file'] ), 'php upload is rejected' );

$phone_alias = Oli_CF_Form_Parser::parse_tag( 'phone* your-phone' );
oli_cf_assert_same( 'tel', $phone_alias['type'], 'phone tag aliases to tel' );
oli_cf_assert_true( $phone_alias['required'], 'phone* is required' );

$length_template = '[text* your-name maxlength:20 minlength:2] [textarea your-message maxlength:40] [submit "Send"]';

$too_long = Oli_CF_Validator::validate(
	array(
		'your-name'    => str_repeat( 'A', 60 ),
		'your-message' => 'Hello',
	),
	$length_template,
	array()
);

oli_cf_assert_true( is_array( $too_long ) && isset( $too_long['your-name'] ), 'maxlength is enforced on the server' );

$too_short = Oli_CF_Validator::validate(
	array(
		'your-name'    => 'A',
		'your-message' => 'Hello',
	),
	$length_template,
	array()
);

oli_cf_assert_true( is_array( $too_short ) && isset( $too_short['your-name'] ), 'minlength is enforced on the server' );

$ok_length = Oli_CF_Validator::validate(
	array(
		'your-name'    => 'Ada Lovelace',
		'your-message' => 'Hello',
	),
	$length_template,
	array()
);

oli_cf_assert_same( true, $ok_length, 'value within minlength/maxlength passes' );

$multibyte = Oli_CF_Validator::validate(
	array(
		'your-name'    => str_repeat( 'é', 21 ),
		'your-message' => 'Hello',
	),
	$length_template,
	array()
);

oli_cf_assert_true( is_array( $multibyte ) && isset( $multibyte['your-name'] ), 'maxlength uses mb_strlen for multibyte characters' );

oli_cf_assert_same( 21, Oli_CF_Validator::string_length( str_repeat( 'é', 21 ) ), 'string_length counts characters not bytes' );

$length_html = Oli_CF_Form_Parser::parse( '[text* your-name maxlength:20 minlength:2]', 1 );
oli_cf_assert_true( false !== strpos( $length_html, 'maxlength="20"' ), 'maxlength is rendered on the input' );
oli_cf_assert_true( false !== strpos( $length_html, 'minlength="2"' ), 'minlength is rendered on the input' );

$textarea_html = Oli_CF_Form_Parser::parse( '[textarea your-message maxlength:40 minlength:5]', 1 );
oli_cf_assert_true( false !== strpos( $textarea_html, 'maxlength="40"' ), 'maxlength is rendered on the textarea' );
oli_cf_assert_true( false !== strpos( $textarea_html, 'minlength="5"' ), 'minlength is rendered on the textarea' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} validator test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll validator tests passed.\n";
exit( 0 );
