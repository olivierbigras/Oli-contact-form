<?php
/**
 * JSON post meta against a real WordPress install.
 *
 * Run with WP-CLI, not the unit-test bootstrap:
 * wp eval-file tests/JsonStorageWpTest.php
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "JsonStorageWpTest must run inside WordPress.\n" );
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
function oli_cf_wp_check( $ok, $message ) {
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

$subject = 'Merci — "café" 😀';
$body    = "Ligne 1\nLigne 2 \"citée\"\nÉmilie";

$form_id = wp_insert_post(
	array(
		'post_type'   => 'oli_contact_form',
		'post_status' => 'publish',
		'post_title'  => 'Aller-retour',
	),
	true
);

oli_cf_wp_check( ! is_wp_error( $form_id ) && $form_id > 0, 'editor form was created' );

$_POST = array(
	'oli_cf_nonce'         => wp_create_nonce( 'oli_cf_save_form' ),
	'oli_cf_fields'        => array(
		array(
			'type'     => 'textarea',
			'name'     => 'message',
			'label'    => 'Message',
			'required' => '1',
		),
	),
	'oli_cf_notifications' => array(
		array(
			'id'                 => 'admin',
			'label'              => 'Admin',
			'enabled'            => '1',
			'to'                 => 'admin@example.com',
			'from'               => 'admin@example.com',
			'subject'            => $subject,
			'body'               => $body,
			'condition_operator' => 'always',
		),
	),
	'oli_cf_messages'      => array(
		'success' => 'Merci',
	),
);

$editor = new Oli_CF_Meta_Boxes();
$editor->save_form_data( (int) $form_id, get_post( (int) $form_id ) );

$saved = Oli_CF_Notifications::for_form( (int) $form_id );
$raw   = Oli_CF_Notifications::read( (int) $form_id );

oli_cf_wp_check( isset( $saved[0]['subject'] ) && $subject === $saved[0]['subject'], 'editor keeps the em dash, quotes, accent and emoji' );
oli_cf_wp_check( isset( $saved[0]['body'] ) && $body === $saved[0]['body'], 'editor keeps the multiline body' );
oli_cf_wp_check( false !== strpos( $raw, '\\n' ), 'stored JSON still contains a newline escape' );
oli_cf_wp_check( false !== strpos( $raw, '\\u2014' ), 'stored JSON still contains the em dash escape' );
oli_cf_wp_check( '2' === (string) get_post_meta( (int) $form_id, '_oli_cf_schema', true ), 'editor writes schema 2' );

$legacy_mail = array(
	'active'  => true,
	'to'      => 'admin@example.com',
	'from'    => 'Site <admin@example.com>',
	'subject' => $subject,
	'body'    => $body,
);
$legacy_mail_2 = array(
	'active'  => true,
	'to'      => 'ada@example.com',
	'subject' => $subject,
	'body'    => $body,
);
$notes = Oli_CF_Notifications::from_legacy_mail( $legacy_mail, $legacy_mail_2 );
$good  = Oli_CF_Notifications::encode( $notes );

update_post_meta( (int) $form_id, '_oli_cf_legacy_template', '[textarea message]' );
update_post_meta( (int) $form_id, '_oli_cf_legacy_mail', $legacy_mail );
update_post_meta( (int) $form_id, '_oli_cf_legacy_mail_2', $legacy_mail_2 );
update_post_meta( (int) $form_id, '_oli_cf_schema', '2' );
update_post_meta( (int) $form_id, Oli_CF_Notifications::META, $good );

$damaged = Oli_CF_Notifications::read( (int) $form_id );

oli_cf_wp_check( $damaged !== $good, 'writing JSON without wp_slash changes it' );
oli_cf_wp_check( false === strpos( $damaged, '\\n' ), 'the damaged JSON lost its newline escape' );
oli_cf_wp_check( false !== strpos( $damaged, 'u2014' ) && false === strpos( $damaged, '\\u2014' ), 'the damaged JSON turned the em dash escape into u2014' );

update_post_meta( (int) $form_id, Oli_CF_Notifications::META, $good );
oli_cf_wp_check( true === Oli_CF_Upgrade::restore_form( (int) $form_id ), 'restore rebuilds from the legacy copy' );

$restored = Oli_CF_Notifications::for_form( (int) $form_id );

oli_cf_wp_check( isset( $restored[0]['body'] ) && $body === $restored[0]['body'], 'restore keeps the multiline body' );
oli_cf_wp_check( isset( $restored[1]['subject'] ) && $subject === $restored[1]['subject'], 'restore keeps the second notification subject' );
$kept = get_post_meta( (int) $form_id, '_oli_cf_legacy_mail', true );
oli_cf_wp_check( is_array( $kept ) && ! empty( $kept ), 'restore keeps the legacy copy' );

$importer = new Oli_CF_Importer();
$insert   = new ReflectionMethod( 'Oli_CF_Importer', 'insert_draft' );
$insert->setAccessible( true );
$imported = $insert->invoke(
	$importer,
	'gravity',
	array(
		'title'         => 'Importé',
		'source_id'     => '44',
		'fields'        => Oli_CF_Fields::from_markup( '{{field type="text" name="nom" label="Nom"}}' ),
		'notifications' => array(
			array(
				'id'      => 'admin',
				'label'   => 'Admin',
				'enabled' => true,
				'to'      => 'admin@example.com',
				'subject' => $subject,
				'body'    => $body,
			),
		),
		'messages'      => array(
			'success' => 'Merci',
		),
	)
);

oli_cf_wp_check( true === $imported, 'import inserts a draft' );

$drafts = get_posts(
	array(
		'post_type'      => 'oli_contact_form',
		'post_status'    => 'draft',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	)
);
$draft_id = ! empty( $drafts ) ? (int) $drafts[0]->ID : 0;
$imported_notes = $draft_id ? Oli_CF_Notifications::for_form( $draft_id ) : array();
$imported_raw   = $draft_id ? Oli_CF_Notifications::read( $draft_id ) : '';

oli_cf_wp_check( isset( $imported_notes[0]['subject'] ) && $subject === $imported_notes[0]['subject'], 'import keeps the em dash, quotes, accent and emoji' );
oli_cf_wp_check( isset( $imported_notes[0]['body'] ) && $body === $imported_notes[0]['body'], 'import keeps the multiline body' );
oli_cf_wp_check( false !== strpos( $imported_raw, '\\u2014' ), 'imported JSON keeps the em dash escape' );

$_POST    = array();
$legacy_id = wp_insert_post(
	array(
		'post_type'   => 'oli_contact_form',
		'post_status' => 'publish',
		'post_title'  => 'Avant 0.4.0',
	),
	true
);
update_post_meta( (int) $legacy_id, '_oli_cf_form_template', '[text* your-name]' );
update_post_meta(
	(int) $legacy_id,
	'_oli_cf_mail',
	array(
		'active'  => true,
		'to'      => 'admin@example.com',
		'from'    => 'admin@example.com',
		'subject' => 'Hi [your-name]',
		'body'    => "Hello\n[your-name]",
	)
);

delete_option( Oli_CF_Upgrade::OPTION );
Oli_CF_Upgrade::maybe_upgrade();

$converted_fields = Oli_CF_Fields::for_form( (int) $legacy_id );

oli_cf_wp_check( isset( $converted_fields[0]['name'] ) && 'your-name' === $converted_fields[0]['name'], 'upgrade converts the old template once' );
oli_cf_wp_check( '[text* your-name]' === (string) get_post_meta( (int) $legacy_id, '_oli_cf_legacy_template', true ), 'upgrade keeps the original template copy' );

$edited_subject = 'Sujet modifié — "après" 😀';
$edited_body    = "Ligne éditée\nDeuxième ligne";

$_POST = array(
	'oli_cf_nonce'         => wp_create_nonce( 'oli_cf_save_form' ),
	'oli_cf_fields'        => array(
		array(
			'type'     => 'text',
			'name'     => 'your-name',
			'label'    => 'Your name',
			'required' => '1',
		),
		array(
			'type'  => 'tel',
			'name'  => 'phone',
			'label' => 'Phone',
		),
	),
	'oli_cf_notifications' => array(
		array(
			'id'                 => 'admin',
			'label'              => 'Admin',
			'enabled'            => '1',
			'to'                 => 'admin@example.com',
			'from'               => 'admin@example.com',
			'subject'            => $edited_subject,
			'body'               => $edited_body,
			'condition_operator' => 'always',
		),
	),
);
$editor->save_form_data( (int) $legacy_id, get_post( (int) $legacy_id ) );

clean_post_cache( (int) $legacy_id );
wp_cache_flush();
$raw_before_reload = Oli_CF_Notifications::read( (int) $legacy_id );
Oli_CF_Upgrade::maybe_upgrade();
oli_cf_wp_check( Oli_CF_Notifications::read( (int) $legacy_id ) === $raw_before_reload, 'reloading and upgrading does not rewrite the stored JSON' );

$kept_fields = Oli_CF_Fields::for_form( (int) $legacy_id );
$kept_notes  = Oli_CF_Notifications::for_form( (int) $legacy_id );

oli_cf_wp_check( isset( $kept_fields[1]['name'] ) && 'phone' === $kept_fields[1]['name'], 'a later upgrade keeps the field added in the editor' );
oli_cf_wp_check( isset( $kept_notes[0]['subject'] ) && $edited_subject === $kept_notes[0]['subject'], 'a later upgrade keeps the edited subject' );
oli_cf_wp_check( isset( $kept_notes[0]['body'] ) && $edited_body === $kept_notes[0]['body'], 'a later upgrade keeps the edited multiline body' );

if ( ! empty( $GLOBALS['oli_cf_wp_failures'] ) ) {
	fwrite( STDERR, count( $GLOBALS['oli_cf_wp_failures'] ) . " assertion(s) failed.\n" );
	exit( 1 );
}

echo "JsonStorageWpTest passed.\n";
