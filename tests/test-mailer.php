<?php
/**
 * Mail build tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

$replaced = Oli_CF_Mailer::replace_tags(
	'Hello {field:your-name}, email {field:your-email}, topic {field:topic}',
	array(
		'your-name'  => 'Ada Lovelace',
		'your-email' => 'ada@example.com',
		'topic'      => array( 'Help', 'Sales' ),
	)
);

oli_cf_assert_same(
	'Hello Ada Lovelace, email ada@example.com, topic Help, Sales',
	$replaced,
	'replace_tags fills {field:name} tokens including checkbox arrays'
);

$unknown = Oli_CF_Mailer::replace_tags( 'Left {field:missing} here', array( 'your-name' => 'Ada' ) );
oli_cf_assert_same( 'Left  here', $unknown, 'unknown tokens become empty' );

$special = Oli_CF_Mailer::replace_tags(
	'To {site:admin_email} from {site:title} at {site:url} ({site:description}) page {post:title} {submission:url}',
	array()
);

oli_cf_assert_same(
	'To admin@example.com from Test Site at https://example.com/ (Just another WordPress site) page Contact https://example.com/contact/',
	$special,
	'site, post, and submission tokens resolve at send time'
);

oli_cf_assert_same( 'admin@example.com', Oli_CF_Mailer::resolve_token( 'site', 'admin_email' ), '{site:admin_email} is the site admin' );
oli_cf_assert_same( 'Test Site', Oli_CF_Mailer::resolve_token( 'site', 'title' ), '{site:title} is the blog name' );
oli_cf_assert_true( '' !== Oli_CF_Mailer::resolve_token( 'submission', 'date' ), '{submission:date} is not empty' );
oli_cf_assert_true( '' !== Oli_CF_Mailer::resolve_token( 'submission', 'time' ), '{submission:time} is not empty' );

$imported_mail = Oli_CF_Mailer::build_mail(
	array(
		'to'      => '{site:admin_email}',
		'from'    => '{site:title} <{site:admin_email}>',
		'subject' => 'Message from {site:title}',
		'body'    => 'Sent from {site:url}',
	),
	array()
);

oli_cf_assert_same( 'admin@example.com', $imported_mail['to'], '{site:admin_email} becomes admin_email' );
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
		'subject'            => 'New message from {field:your-name}',
		'body'               => "From: {field:your-name} <{field:your-email}>\n\n{field:your-message}",
		'reply_to'           => '{field:your-email}',
		'additional_headers' => "Bcc: copy@example.com\nX-Evil: one\r\ntwo",
	),
	array(
		'your-name'    => 'Ada Lovelace',
		'your-email'   => 'ada@example.com',
		'your-message' => "Line 1\nLine 2",
	)
);

oli_cf_assert_same( 'owner@example.com', $mail['to'], 'to is unchanged when it has no tags' );
oli_cf_assert_same( 'New message from Ada Lovelace', $mail['subject'], 'subject replaces {field:your-name}' );
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

$partial_mail = array(
	'to'      => 'owner@example.com',
	'from'    => 'admin@example.com',
	'subject' => 'Partial',
	'body'    => 'Hello',
);

$normalized_partial = Oli_CF_Mailer::normalize_mail( $partial_mail );
oli_cf_assert_same( 'owner@example.com', $normalized_partial['to'], 'partial mail keeps the stored To' );
oli_cf_assert_true( array_key_exists( 'reply_to', $normalized_partial ), 'partial mail meta gets reply_to' );
oli_cf_assert_same( '{field:email}', $normalized_partial['reply_to'], 'missing reply_to uses the default' );
oli_cf_assert_true( array_key_exists( 'additional_headers', $normalized_partial ), 'partial mail meta gets additional_headers' );
oli_cf_assert_same( '', $normalized_partial['additional_headers'], 'missing additional_headers is an empty string' );

$built_partial = Oli_CF_Mailer::build_mail(
	$partial_mail,
	array(
		'email' => 'ada@example.com',
	)
);
oli_cf_assert_true( in_array( 'Reply-To: ada@example.com', $built_partial['headers'], true ), 'partial mail send fills default Reply-To' );

foreach ( array( 'to', 'from', 'subject', 'body', 'reply_to', 'additional_headers' ) as $mail_key ) {
	oli_cf_assert_true( array_key_exists( $mail_key, $normalized_partial ), 'normalized mail has ' . $mail_key );
}

$partial_mail_2 = array(
	'enabled' => true,
	'to'      => '{field:email}',
);
$normalized_mail_2 = Oli_CF_Mailer::normalize_mail( $partial_mail_2, true );
oli_cf_assert_true( $normalized_mail_2['enabled'], 'partial mail 2 keeps enabled' );
oli_cf_assert_true( array_key_exists( 'reply_to', $normalized_mail_2 ), 'partial mail 2 meta gets reply_to' );
oli_cf_assert_same( '', $normalized_mail_2['reply_to'], 'mail 2 missing reply_to defaults to empty' );

$meta_src = file_get_contents( dirname( __DIR__ ) . '/admin/class-oli-cf-meta-boxes.php' );
oli_cf_assert_true( false !== strpos( $meta_src, 'oli_cf_notifications' ), 'editor saves a notification list' );
oli_cf_assert_true( false !== strpos( $meta_src, 'Oli_CF_Notifications::for_editor' ), 'editor shows stored notifications' );

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} mailer test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll mailer tests passed.\n";
exit( 0 );
