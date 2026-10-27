<?php
/**
 * CF7 import conversion tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$cf7_template = '<label> Your name
    [text* your-name] </label>

<label> Your email
    [email* your-email] </label>

[tel your-phone]
[select* topic "General Inquiry" "Support"]
[file your-file limit:2mb filetypes:jpg|png|pdf]
[acceptance* terms "I accept"]
[recaptcha]
[submit "Send"]';

$converted = Oli_CF_Importer::convert_template( $cf7_template );

oli_cf_assert_true( false !== strpos( $converted, '[text* your-name]' ), 'text* tag is kept' );
oli_cf_assert_true( false !== strpos( $converted, '[email* your-email]' ), 'email* tag is kept' );
oli_cf_assert_true( false !== strpos( $converted, '[tel your-phone]' ), 'tel tag is kept' );
oli_cf_assert_true( false !== strpos( $converted, '[select* topic "General Inquiry" "Support"]' ), 'quoted select options are kept' );
oli_cf_assert_true( false !== strpos( $converted, '[file your-file limit:2mb filetypes:jpg|png|pdf]' ), 'file limits are kept' );
oli_cf_assert_true( false !== strpos( $converted, '[acceptance* terms "I accept"]' ), 'acceptance tag is kept' );
oli_cf_assert_true( false !== strpos( $converted, '[submit "Send"]' ), 'submit tag is kept' );
oli_cf_assert_same( false, strpos( $converted, '[recaptcha]' ), 'recaptcha tag is removed' );

$mail = Oli_CF_Importer::convert_mail(
	array(
		'recipient'          => 'owner@example.com',
		'sender'             => 'Site <admin@example.com>',
		'subject'            => 'New message from [your-name]',
		'body'               => "From: [your-name] <[your-email]>\n\n[your-message]",
		'additional_headers' => "Reply-To: [your-email]\nBcc: copy@example.com",
	),
	false
);

oli_cf_assert_same( 'owner@example.com', $mail['to'], 'CF7 recipient becomes To' );

$cf7_default_mail = Oli_CF_Importer::convert_mail(
	array(
		'recipient' => '[_site_admin_email]',
		'sender'    => '[_site_title] <[_site_admin_email]>',
		'subject'   => '[_site_title] "[your-subject]"',
		'body'      => "From: [_site_url]\n\n[your-message]",
	),
	false
);

oli_cf_assert_same( '[_site_admin_email]', $cf7_default_mail['to'], 'CF7 special To tag is kept at import' );

$resolved_import = Oli_CF_Mailer::build_mail( $cf7_default_mail, array( 'your-subject' => 'Hi', 'your-message' => 'Hello' ) );
oli_cf_assert_same( 'admin@example.com', $resolved_import['to'], 'imported [_site_admin_email] resolves when mail is built' );
oli_cf_assert_true( false !== strpos( $resolved_import['subject'], 'Test Site' ), 'imported [_site_title] resolves in the subject' );
oli_cf_assert_same( 'Site <admin@example.com>', $mail['from'], 'CF7 sender becomes From' );
oli_cf_assert_same( 'New message from [your-name]', $mail['subject'], 'subject tags are preserved' );
oli_cf_assert_same( '[your-email]', $mail['reply_to'], 'Reply-To is lifted out of additional headers' );
oli_cf_assert_same( 'Bcc: copy@example.com', $mail['additional_headers'], 'other headers remain' );

$mail_2 = Oli_CF_Importer::convert_mail(
	array(
		'active'    => true,
		'recipient' => '[your-email]',
		'sender'    => 'admin@example.com',
		'subject'   => 'Thanks [your-name]',
		'body'      => 'We got [your-message]',
	),
	true
);

oli_cf_assert_true( $mail_2['enabled'], 'mail 2 active flag becomes enabled' );
oli_cf_assert_same( '[your-email]', $mail_2['to'], 'mail 2 recipient is the sender tag' );

$disabled = Oli_CF_Importer::convert_mail( array(), true );
oli_cf_assert_false( $disabled['enabled'], 'empty mail 2 is disabled' );

$messages = Oli_CF_Importer::convert_messages(
	array(
		'mail_sent_ok'             => 'Got it.',
		'validation_error'         => 'Fix the form.',
		'upload_file_type_invalid' => 'Wrong type.',
	)
);

oli_cf_assert_same( 'Got it.', $messages['mail_sent_ok'], 'CF7 success message is imported' );
oli_cf_assert_same( 'Fix the form.', $messages['validation_error'], 'CF7 validation message is imported' );
oli_cf_assert_same( 'Wrong type.', $messages['upload_file_type'], 'CF7 file-type message is mapped' );
oli_cf_assert_true( isset( $messages['invalid_required'] ), 'default messages are filled in' );

$phone = Oli_CF_Importer::convert_form_tag( 'phone* your-phone' );
oli_cf_assert_same( 'tel* your-phone', $phone, 'CF7 phone tag becomes tel' );

$file_cf7 = '[file* your-file filetypes:gif|png|jpg|jpeg limit:1mb]';
$file_out = Oli_CF_Importer::convert_template( $file_cf7 );
oli_cf_assert_true( false !== strpos( $file_out, '[file* your-file' ), 'required file tag is imported' );
oli_cf_assert_true( false !== strpos( $file_out, 'limit:1mb' ), 'file limit option is imported' );
oli_cf_assert_true( false !== strpos( $file_out, 'filetypes:' ), 'filetypes option is imported' );

$file_fields = Oli_CF_Form_Parser::parse_fields( $file_out );
oli_cf_assert_same( 'file', $file_fields[0]['type'], 'imported file tag is the plugin file type' );
oli_cf_assert_same( '1mb', $file_fields[0]['attributes']['limit'], 'imported limit is on the field' );
oli_cf_assert_true( ! empty( $file_fields[0]['attributes']['filetypes'] ), 'imported filetypes are on the field' );

$php_file = Oli_CF_Validator::validate(
	array(),
	$file_out,
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
oli_cf_assert_true( is_array( $php_file ) && isset( $php_file['your-file'] ), 'imported file field still rejects php' );

$accept_cf7 = Oli_CF_Importer::convert_template( '[acceptance consent] I agree to be contacted. [/acceptance]' );
oli_cf_assert_true( false !== strpos( $accept_cf7, 'I agree to be contacted.' ), 'CF7 acceptance inner text becomes the label' );
oli_cf_assert_same( false, strpos( $accept_cf7, '[/acceptance]' ), 'closing acceptance tag is removed' );

$accept_html = Oli_CF_Form_Parser::parse( '[acceptance consent] J\'accepte d\'être contacté. [/acceptance]', 7 );
oli_cf_assert_true( false !== strpos( $accept_html, 'J\'accepte d\'être contacté.' ), 'paired acceptance text is the checkbox label' );
oli_cf_assert_same( false, strpos( $accept_html, 'I accept the terms' ), 'default acceptance label is not used when text is provided' );

$plain_accept = Oli_CF_Form_Parser::parse_tag( 'acceptance your-consent' );
oli_cf_assert_true( ! empty( $plain_accept['required'] ), 'CF7 acceptance without asterisk is required' );

$star_accept = Oli_CF_Form_Parser::parse_tag( 'acceptance* your-consent' );
oli_cf_assert_true( ! empty( $star_accept['required'] ), 'CF7 acceptance* is required' );

$optional_accept = Oli_CF_Form_Parser::parse_tag( 'acceptance your-consent optional "I agree"' );
oli_cf_assert_false( ! empty( $optional_accept['required'] ), 'CF7 acceptance is optional only with the optional option' );
oli_cf_assert_false( in_array( 'optional', $optional_accept['values'], true ), 'optional is not part of the acceptance label' );

$quoted_optional = Oli_CF_Form_Parser::parse_tag( 'acceptance your-consent "optional"' );
oli_cf_assert_true( ! empty( $quoted_optional['required'] ), 'quoted optional stays a label, field remains required' );

$imported_plain = Oli_CF_Importer::convert_template( '[acceptance your-consent] I agree to the privacy policy. [/acceptance]' );
$imported_plain_fields = Oli_CF_Form_Parser::parse_fields( $imported_plain );
oli_cf_assert_true( isset( $imported_plain_fields[0]['required'] ) && $imported_plain_fields[0]['required'], 'imported CF7 [acceptance] without asterisk is required' );

$imported_plain_html = Oli_CF_Form_Parser::parse( $imported_plain, 3 );
oli_cf_assert_true( false !== strpos( $imported_plain_html, 'required="required"' ), 'imported required acceptance renders required' );
oli_cf_assert_true( false !== strpos( $imported_plain_html, 'aria-required="true"' ), 'imported required acceptance is aria-required' );

$imported_optional = Oli_CF_Importer::convert_template( '[acceptance your-consent optional] I agree [/acceptance]' );
oli_cf_assert_true( false !== strpos( $imported_optional, ' optional' ), 'imported CF7 optional option is kept unquoted' );
$imported_optional_fields = Oli_CF_Form_Parser::parse_fields( $imported_optional );
oli_cf_assert_false( ! empty( $imported_optional_fields[0]['required'] ), 'imported CF7 acceptance with optional stays optional' );

$imported_optional_html = Oli_CF_Form_Parser::parse( $imported_optional, 4 );
oli_cf_assert_same( false, strpos( $imported_optional_html, 'required="required"' ), 'optional imported acceptance is not required in HTML' );

$missing_plain = Oli_CF_Validator::validate( array(), '[acceptance your-consent "I agree"]', array() );
oli_cf_assert_true( is_array( $missing_plain ) && isset( $missing_plain['your-consent'] ), 'unchecked imported acceptance fails validation' );

$missing_optional = Oli_CF_Validator::validate( array(), '[acceptance your-consent optional "I agree"]', array() );
oli_cf_assert_same( true, $missing_optional, 'unchecked optional acceptance passes validation' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} CF7 import test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll CF7 import tests passed.\n";
exit( 0 );
