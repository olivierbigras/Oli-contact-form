<?php
/**
 * Uninstall Oli Contact Form.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$oli_cf_table = $wpdb->prefix . 'oli_cf_messages';

if ( preg_match( '/^[A-Za-z0-9_]+$/', $oli_cf_table ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall drops a leftover table from older versions that stored copies.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $oli_cf_table ) );
}

delete_option( 'oli_cf_settings' );
delete_option( 'oli_cf_db_version' );

wp_clear_scheduled_hook( 'oli_cf_purge_stored_messages' );

$oli_cf_forms = get_posts(
	array(
		'post_type'      => 'oli_contact_form',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $oli_cf_forms as $oli_cf_form_id ) {
	wp_delete_post( (int) $oli_cf_form_id, true );
}
