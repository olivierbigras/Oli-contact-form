<?php
/**
 * Label links against a real WordPress install.
 *
 * The unit-test bootstrap stubs wp_kses_post() and esc_url(), which hide the
 * case where a rejected URL is rewritten into an http address. Run with WP-CLI:
 * wp eval-file tests/LabelLinkWpTest.php
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "LabelLinkWpTest must run inside WordPress.\n" );
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
function oli_cf_wp_label_check( $ok, $message ) {
	if ( $ok ) {
		echo 'ok ' . $message . "\n";
		return;
	}

	$GLOBALS['oli_cf_wp_failures'][] = $message;
	echo 'FAIL ' . $message . "\n";
}

/**
 * Consent label stored on a form.
 *
 * @param int $form_id Form ID.
 * @return string
 */
function oli_cf_wp_consent_label( $form_id ) {
	foreach ( Oli_CF_Fields::for_form( $form_id ) as $field ) {
		if ( isset( $field['name'] ) && 'consent' === $field['name'] ) {
			return (string) $field['label'];
		}
	}

	return '';
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

$privacy = 'https://example.com/privacy-policy';
$hostile = 'Agree <a href="javascript:alert(1)" onclick="alert(1)">here</a> <a href="' . $privacy . '">privacy policy</a>';
$editor  = new Oli_CF_Meta_Boxes();

$form_id = wp_insert_post(
	array(
		'post_type'   => 'oli_contact_form',
		'post_status' => 'draft',
		'post_title'  => 'Label link',
	),
	true
);

oli_cf_wp_label_check( ! is_wp_error( $form_id ) && $form_id > 0, 'label-link form was created' );

$_POST = array(
	'oli_cf_nonce'  => wp_create_nonce( 'oli_cf_save_form' ),
	'oli_cf_fields' => array(
		array(
			'type'     => 'acceptance',
			'name'     => 'consent',
			'label'    => $hostile,
			'required' => '1',
		),
	),
);
$editor->save_form_data( (int) $form_id, get_post( (int) $form_id ) );

$saved = oli_cf_wp_consent_label( (int) $form_id );
oli_cf_wp_label_check( false === strpos( $saved, 'javascript:' ), 'builder save drops javascript:' );
oli_cf_wp_label_check( false === strpos( $saved, 'http://alert(1)' ), 'builder save does not invent http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $saved, 'onclick' ), 'builder save drops onclick' );
oli_cf_wp_label_check( false !== strpos( $saved, 'href="' . $privacy . '"' ), 'builder save keeps the privacy link' );

$html = Oli_CF_Form_Parser::render_fields( Oli_CF_Fields::for_form( (int) $form_id ), (int) $form_id );
oli_cf_wp_label_check( false === strpos( $html, 'javascript:' ), 'public HTML drops javascript:' );
oli_cf_wp_label_check( false === strpos( $html, 'http://alert(1)' ), 'public HTML does not invent http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $html, 'onclick' ), 'public HTML drops onclick' );
oli_cf_wp_label_check( false !== strpos( $html, 'href="' . esc_url( $privacy ) . '"' ), 'public HTML keeps the privacy link' );

$escaped = str_replace(
	array( '&', '"', '<', '>' ),
	array( '&amp;', '&quot;', '&lt;', '&gt;' ),
	$hostile
);
$markup  = '{{field type="acceptance" name="consent" required label="' . $escaped . '"}}';

$_POST = array(
	'oli_cf_nonce'         => wp_create_nonce( 'oli_cf_save_form' ),
	'oli_cf_use_markup'    => '1',
	'oli_cf_fields_markup' => $markup,
);
$editor->save_form_data( (int) $form_id, get_post( (int) $form_id ) );

$from_markup = oli_cf_wp_consent_label( (int) $form_id );
oli_cf_wp_label_check( false === strpos( $from_markup, 'javascript:' ), 'markup save drops javascript:' );
oli_cf_wp_label_check( false === strpos( $from_markup, 'http://alert(1)' ), 'markup save does not invent http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $from_markup, 'onclick' ), 'markup save drops onclick' );
oli_cf_wp_label_check( false !== strpos( $from_markup, 'href="' . $privacy . '"' ), 'markup save keeps the privacy link' );

if ( ! empty( $GLOBALS['oli_cf_wp_failures'] ) ) {
	fwrite( STDERR, count( $GLOBALS['oli_cf_wp_failures'] ) . " assertion(s) failed.\n" );
	exit( 1 );
}

echo "LabelLinkWpTest passed.\n";
