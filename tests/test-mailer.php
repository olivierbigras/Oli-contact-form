<?php
/**
 * Mail build tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$replaced = Oli_CF_Mailer::replace_tags(
	'Hello [your-name], email [your-email], topic [topic]',
	array(
		'your-name'  => 'Ada Lovelace',
		'your-email' => 'ada@example.com',
		'topic'      => array( 'Help', 'Sales' ),
	)
);

oli_cf_assert_same(
	'Hello Ada Lovelace, email ada@example.com, topic Help, Sales',
	$replaced,
	'replace_tags fills [field] tags including checkbox arrays'
);

$unknown = Oli_CF_Mailer::replace_tags( 'Left [missing] here', array( 'your-name' => 'Ada' ) );
oli_cf_assert_same( 'Left  here', $unknown, 'unknown tags become empty' );

$special = Oli_CF_Mailer::replace_tags(
	'To [_site_admin_email] from [_site_title] at [_site_url] ([_site_description]) page [_post_title] [_url]',
	array()
);

oli_cf_assert_same(
	'To admin@example.com from Test Site at https://example.com/ (Just another WordPress site) page Contact https://example.com/contact/',
	$special,
	'CF7 special mail-tags resolve at send time'
);

oli_cf_assert_same( 'admin@example.com', Oli_CF_Mailer::resolve_special_tag( '_site_admin_email' ), '[_site_admin_email] is the site admin' );
oli_cf_assert_same( 'Test Site', Oli_CF_Mailer::resolve_special_tag( '_site_title' ), '[_site_title] is the blog name' );
oli_cf_assert_true( '' !== Oli_CF_Mailer::resolve_special_tag( '_date' ), '[_date] is not empty' );
oli_cf_assert_true( '' !== Oli_CF_Mailer::resolve_special_tag( '_time' ), '[_time] is not empty' );

$imported_mail = Oli_CF_Mailer::build_mail(
	array(
		'to'      => '[_site_admin_email]',
		'from'    => '[_site_title] <[_site_admin_email]>',
		'subject' => 'Message from [_site_title]',
		'body'    => 'Sent from [_site_url]',
	),
	array()
);

oli_cf_assert_same( 'admin@example.com', $imported_mail['to'], 'imported CF7 To [_site_admin_email] becomes admin_email' );
oli_cf_assert_true( in_array( 'From: Test Site <admin@example.com>', $imported_mail['headers'], true ), 'From resolves site title and admin email' );

oli_cf_assert_same( 'admin@example.com', Oli_CF_Mailer::resolve_recipient( '' ), 'empty To falls back to admin_email' );
oli_cf_assert_same( 'admin@example.com', Oli_CF_Mailer::resolve_recipient( 'not-an-email' ), 'invalid To falls back to admin_email' );
oli_cf_assert_same( 'owner@example.com', Oli_CF_Mailer::resolve_recipient( 'owner@example.com' ), 'valid To is kept' );
oli_cf_assert_true( Oli_CF_Mailer::recipient_is_valid( 'Site <admin@example.com>' ), 'Name <email> is a valid recipient' );
oli_cf_assert_false( Oli_CF_Mailer::recipient_is_valid( '' ), 'empty recipient is invalid' );

$mail = Oli_CF_Mailer::build_mail(
	array(
		'to'                 => 'owner@example.com',
		'from'               => 'Site <admin@example.com>',
		'subject'            => 'New message from [your-name]',
		'body'               => "From: [your-name] <[your-email]>\n\n[your-message]",
		'reply_to'           => '[your-email]',
		'additional_headers' => "Bcc: copy@example.com\nX-Evil: one\r\ntwo",
	),
	array(
		'your-name'    => 'Ada Lovelace',
		'your-email'   => 'ada@example.com',
		'your-message' => "Line 1\nLine 2",
	)
);

oli_cf_assert_same( 'owner@example.com', $mail['to'], 'to is unchanged when it has no tags' );
oli_cf_assert_same( 'New message from Ada Lovelace', $mail['subject'], 'subject replaces [your-name]' );
oli_cf_assert_true( false !== strpos( $mail['body'], 'Ada Lovelace' ), 'body includes the name' );
oli_cf_assert_true( false !== strpos( $mail['body'], '<br' ), 'body converts newlines to HTML breaks' );
oli_cf_assert_true( in_array( 'From: Site <admin@example.com>', $mail['headers'], true ), 'From header is set' );
oli_cf_assert_true( in_array( 'Reply-To: ada@example.com', $mail['headers'], true ), 'Reply-To uses the sender email' );
oli_cf_assert_true( in_array( 'Content-Type: text/html; charset=UTF-8', $mail['headers'], true ), 'HTML content type is set' );

$injected = Oli_CF_Mailer::sanitize_header_value( "ada@example.com\r\nBcc: evil@example.com" );
oli_cf_assert_same( 'ada@example.com', $injected, 'header injection after a newline is dropped' );

$missing_file = '/tmp/oli-cf-does-not-exist-mailer.bin';
$mail_files   = Oli_CF_Mailer::build_mail(
	array(
		'to'      => 'owner@example.com',
		'subject' => 'File',
		'body'    => 'See attach',
	),
	array(),
	array( $missing_file )
);

oli_cf_assert_same( array(), $mail_files['attachments'], 'missing attachment paths are dropped' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} mailer test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll mailer tests passed.\n";
exit( 0 );
