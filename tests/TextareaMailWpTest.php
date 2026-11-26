<?php
/**
 * Short textarea values on a real WordPress install.
 *
 * The unit-test stub of sanitize_text_field() used to keep line breaks, so a
 * length check could flatten a short paragraph without a test noticing.
 * Run with WP-CLI: wp eval-file tests/TextareaMailWpTest.php
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "TextareaMailWpTest must run inside WordPress.\n" );
	exit( 1 );
}

if ( ! class_exists( 'Oli_CF_Fields' ) ) {
	require_once WP_PLUGIN_DIR . '/oli-contact-form/oli-contact-form.php';
}

if ( ! post_type_exists( 'oli_contact_form' ) ) {
	$post_type = new Oli_CF_Post_Type();
	$post_type->register_post_type();
}

$GLOBALS['oli_cf_wp_failures'] = array();

/**
 * Record one assertion.
 *
 * @param bool   $ok      Result.
 * @param string $message Label.
 */
function oli_cf_wp_textarea_check( $ok, $message ) {
	if ( $ok ) {
		echo 'ok ' . $message . "\n";
		return;
	}

	$GLOBALS['oli_cf_wp_failures'][] = $message;
	echo 'FAIL ' . $message . "\n";
}

$admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);

if ( empty( $admin ) ) {
	fwrite( STDERR, "No administrator user.\n" );
	exit( 1 );
}

wp_set_current_user( (int) $admin[0]->ID );

$form_id = wp_insert_post(
	array(
		'post_type'   => 'oli_contact_form',
		'post_status' => 'draft',
		'post_title'  => 'Textarea mail',
	),
	true
);

oli_cf_wp_textarea_check( ! is_wp_error( $form_id ) && $form_id > 0, 'textarea form was created' );

Oli_CF_Fields::save(
	(int) $form_id,
	array(
		array(
			'type'     => 'text',
			'name'     => 'your-name',
			'label'    => 'Name',
			'required' => true,
		),
		array(
			'type'     => 'textarea',
			'name'     => 'message',
			'label'    => 'Message',
			'required' => false,
		),
	)
);

$note             = Oli_CF_Notifications::blank();
$note['enabled']  = true;
$note['to']       = 'admin@example.com';
$note['from']     = 'admin@example.com';
$note['subject']  = 'Hi';
$note['body']     = '{field:message}';
Oli_CF_Notifications::save( (int) $form_id, array( $note ) );

$GLOBALS['oli_cf_wp_mail_body'] = '';
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) {
		unset( $null );
		$GLOBALS['oli_cf_wp_mail_body'] = isset( $atts['message'] ) ? (string) $atts['message'] : '';

		return true;
	},
	10,
	2
);

$message = "Une ligne\nDeuxième";
$result  = ( new Oli_CF_Submission() )->process_submission(
	(int) $form_id,
	array(
		'your-name' => 'Ada',
		'message'   => $message,
	),
	array()
);

$mail = (string) $GLOBALS['oli_cf_wp_mail_body'];
oli_cf_wp_textarea_check( ! empty( $result['success'] ), 'short textarea submission is sent' );
oli_cf_wp_textarea_check( false !== strpos( $mail, 'Une ligne' ), 'mail keeps the first line' );
oli_cf_wp_textarea_check( false !== strpos( $mail, 'Deuxième' ), 'mail keeps the second line' );
oli_cf_wp_textarea_check( false !== strpos( $mail, '<br>' ), 'mail keeps the line break' );
oli_cf_wp_textarea_check( false === strpos( $mail, 'Une ligne Deuxième' ), 'mail does not flatten the textarea' );

if ( ! empty( $GLOBALS['oli_cf_wp_failures'] ) ) {
	fwrite( STDERR, count( $GLOBALS['oli_cf_wp_failures'] ) . " assertion(s) failed.\n" );
	exit( 1 );
}

echo "TextareaMailWpTest passed.\n";
