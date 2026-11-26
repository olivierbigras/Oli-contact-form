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

/**
 * Escape a label so it can sit in a markup attribute.
 *
 * @param string $label Label.
 * @return string
 */
function oli_cf_wp_label_attr( $label ) {
	return str_replace(
		array( '&', '"', '<', '>' ),
		array( '&amp;', '&quot;', '&lt;', '&gt;' ),
		$label
	);
}

/**
 * Save builder rows through the editor.
 *
 * @param Oli_CF_Meta_Boxes $editor  Meta boxes.
 * @param int               $form_id Form ID.
 * @param array<int, mixed> $rows    Posted rows.
 */
function oli_cf_wp_save_builder_fields( $editor, $form_id, $rows ) {
	$_POST = array(
		'oli_cf_nonce'  => wp_create_nonce( 'oli_cf_save_form' ),
		'oli_cf_fields' => $rows,
	);
	$editor->save_form_data( (int) $form_id, get_post( (int) $form_id ) );
}

/**
 * Save one markup definition through the editor.
 *
 * @param Oli_CF_Meta_Boxes $editor  Meta boxes.
 * @param int               $form_id Form ID.
 * @param string            $markup  Text definition.
 */
function oli_cf_wp_save_markup_fields( $editor, $form_id, $markup ) {
	$_POST = array(
		'oli_cf_nonce'         => wp_create_nonce( 'oli_cf_save_form' ),
		'oli_cf_use_markup'    => '1',
		'oli_cf_fields_markup' => $markup,
	);
	$editor->save_form_data( (int) $form_id, get_post( (int) $form_id ) );
}

$notice_key = Oli_CF_Fields::label_link_notice_key( get_current_user_id() );
$absolute   = esc_url( home_url( '/privacy-policy/' ) );
delete_transient( $notice_key );

oli_cf_wp_save_builder_fields(
	$editor,
	(int) $form_id,
	array(
		array(
			'type'     => 'acceptance',
			'name'     => 'consent',
			'label'    => 'Read the <a href="/privacy-policy/">privacy policy</a>',
			'required' => '1',
		),
	)
);

$relative   = oli_cf_wp_consent_label( (int) $form_id );
$raw_fields = json_decode( (string) get_post_meta( (int) $form_id, Oli_CF_Fields::META, true ), true );
$raw_label  = '';

if ( is_array( $raw_fields ) ) {
	foreach ( $raw_fields as $raw_field ) {
		if ( is_array( $raw_field ) && isset( $raw_field['name'] ) && 'consent' === $raw_field['name'] ) {
			$raw_label = (string) $raw_field['label'];
		}
	}
}

oli_cf_wp_label_check( false !== strpos( $relative, 'href="' . $absolute . '"' ), 'builder save turns a root-relative href into home_url' );
oli_cf_wp_label_check( false === strpos( $relative, 'href="/privacy-policy/"' ), 'builder save does not keep the root-relative href' );
oli_cf_wp_label_check( false !== strpos( $raw_label, 'href="' . $absolute . '"' ), 'stored JSON keeps the home_url address' );
oli_cf_wp_label_check( false === strpos( $raw_label, 'href="/privacy-policy/"' ), 'stored JSON does not keep the root-relative href' );
oli_cf_wp_label_check( false === get_transient( $notice_key ), 'a root-relative link does not warn' );
$relative_html = Oli_CF_Form_Parser::render_fields( Oli_CF_Fields::for_form( (int) $form_id ), (int) $form_id );
oli_cf_wp_label_check( false !== strpos( $relative_html, 'href="' . $absolute . '"' ), 'public HTML uses the home_url address' );
oli_cf_wp_label_check( false === strpos( $relative_html, 'javascript:' ), 'public HTML of a root-relative link has no javascript:' );
oli_cf_wp_label_check( false === strpos( $relative_html, 'http://alert(1)' ), 'public HTML of a root-relative link has no http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $relative_html, 'onclick' ), 'public HTML of a root-relative link has no onclick' );

delete_transient( $notice_key );
$markup_relative = '{{field type="acceptance" name="consent" required label="' . oli_cf_wp_label_attr( 'Read the <a href="/privacy-policy/">privacy policy</a>' ) . '"}}';
oli_cf_wp_save_markup_fields( $editor, (int) $form_id, $markup_relative );
$relative_markup = oli_cf_wp_consent_label( (int) $form_id );
oli_cf_wp_label_check( false !== strpos( $relative_markup, 'href="' . $absolute . '"' ), 'markup save turns a root-relative href into home_url' );
oli_cf_wp_label_check( false === strpos( $relative_markup, 'href="/privacy-policy/"' ), 'markup save does not keep the root-relative href' );
oli_cf_wp_label_check( false === get_transient( $notice_key ), 'markup root-relative link does not warn' );

delete_transient( $notice_key );
oli_cf_wp_save_builder_fields(
	$editor,
	(int) $form_id,
	array(
		array(
			'type'     => 'textarea',
			'name'     => 'message',
			'label'    => '<a href="javascript:alert(1)" onclick="alert(1)">Message</a>',
			'required' => '1',
		),
		array(
			'type'  => 'text',
			'name'  => 'notes',
			'label' => '<a href="data:text/plain,hello" onclick="alert(1)">Notes</a>',
		),
	)
);

$rejected_fields = Oli_CF_Fields::for_form( (int) $form_id );
$rejected_saved  = wp_json_encode( $rejected_fields );
oli_cf_wp_label_check( false === strpos( (string) $rejected_saved, 'javascript:' ), 'rejected builder href drops javascript:' );
oli_cf_wp_label_check( false === strpos( (string) $rejected_saved, 'http://alert(1)' ), 'rejected builder href does not invent http://alert(1)' );
oli_cf_wp_label_check( false === strpos( (string) $rejected_saved, 'onclick' ), 'rejected builder href drops onclick' );
oli_cf_wp_label_check( false === strpos( (string) $rejected_saved, 'data:' ), 'rejected builder href drops data:' );

$pending = get_transient( $notice_key );
oli_cf_wp_label_check( is_array( $pending ) && in_array( 'Message', $pending, true ), 'rejected href warns for the user who saved' );
oli_cf_wp_label_check( is_array( $pending ) && in_array( 'Notes', $pending, true ), 'rejected href names every affected label' );
oli_cf_wp_label_check( false === get_transient( Oli_CF_Fields::label_link_notice_key( get_current_user_id() + 1 ) ), 'warning is not stored for another user' );
oli_cf_wp_label_check( false === get_option( 'oli_cf_label_link_notice' ), 'warning is not a global option' );

$notices = new Oli_CF_Admin( 'oli-contact-form', OLI_CF_VERSION );
ob_start();
$notices->maybe_label_link_notice();
$notice_html = ob_get_clean();
oli_cf_wp_label_check( false !== strpos( $notice_html, 'notice notice-warning is-dismissible' ), 'warning is a dismissible notice-warning' );
oli_cf_wp_label_check( false !== strpos( $notice_html, esc_html( 'A link was removed from the label of "Message" because its address is not a full http or https URL.' ) ), 'warning names the Message label' );
oli_cf_wp_label_check( false !== strpos( $notice_html, esc_html( 'A link was removed from the label of "Notes" because its address is not a full http or https URL.' ) ), 'warning names the Notes label' );
oli_cf_wp_label_check( false === strpos( $notice_html, 'javascript:' ), 'warning does not print javascript:' );
oli_cf_wp_label_check( false === strpos( $notice_html, 'http://alert(1)' ), 'warning does not print http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $notice_html, 'onclick' ), 'warning does not print onclick' );
oli_cf_wp_label_check( false === get_transient( $notice_key ), 'warning is shown on the next load and then cleared' );

delete_transient( $notice_key );
$markup_rejected = '{{field type="text" name="message" label="' . oli_cf_wp_label_attr( '<a href="//evil.example/phish" onclick="alert(1)">Message</a>' ) . '"}}';
oli_cf_wp_save_markup_fields( $editor, (int) $form_id, $markup_rejected );
$markup_rejected_label = '';

foreach ( Oli_CF_Fields::for_form( (int) $form_id ) as $field ) {
	if ( isset( $field['name'] ) && 'message' === $field['name'] ) {
		$markup_rejected_label = (string) $field['label'];
	}
}

oli_cf_wp_label_check( false === strpos( $markup_rejected_label, 'javascript:' ), 'markup rejected href drops javascript:' );
oli_cf_wp_label_check( false === strpos( $markup_rejected_label, 'http://alert(1)' ), 'markup rejected href does not invent http://alert(1)' );
oli_cf_wp_label_check( false === strpos( $markup_rejected_label, 'onclick' ), 'markup rejected href drops onclick' );
oli_cf_wp_label_check( false === strpos( $markup_rejected_label, 'evil.example' ), 'markup save does not keep a protocol-relative host' );
oli_cf_wp_label_check( false === strpos( $markup_rejected_label, 'href=' ), 'markup save keeps only the inner text of a rejected href' );
$markup_pending = get_transient( $notice_key );
oli_cf_wp_label_check( is_array( $markup_pending ) && in_array( 'Message', $markup_pending, true ), 'markup rejected href warns for the user who saved' );

ob_start();
$editor->render_form_editor( get_post( (int) $form_id ) );
$editor_html = ob_get_clean();
oli_cf_wp_label_check( false !== strpos( $editor_html, 'oli-cf-label-input' ), 'field label uses the label textarea' );
oli_cf_wp_label_check( 1 === preg_match( '/<textarea[^>]*class="[^"]*large-text[^"]*oli-cf-label-input/', $editor_html ), 'field label textarea is large-text' );
oli_cf_wp_label_check( false === strpos( $editor_html, 'regular-text' ), 'field label is not a regular-text input' );

if ( ! empty( $GLOBALS['oli_cf_wp_failures'] ) ) {
	fwrite( STDERR, count( $GLOBALS['oli_cf_wp_failures'] ) . " assertion(s) failed.\n" );
	exit( 1 );
}

echo "LabelLinkWpTest passed.\n";
