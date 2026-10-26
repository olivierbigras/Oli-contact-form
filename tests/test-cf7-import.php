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

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} CF7 import test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll CF7 import tests passed.\n";
exit( 0 );
