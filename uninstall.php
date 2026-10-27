<?php
/**
 * Uninstall Oli Contact Form.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-oli-cf-uploads.php';

/**
 * Remove plugin data for the current site.
 */
function oli_cf_uninstall_current_site() {
	global $wpdb;

	$oli_cf_table = $wpdb->prefix . 'oli_cf_messages';

	if ( preg_match( '/^[A-Za-z0-9_]+$/', $oli_cf_table ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall drops a leftover table from older versions that stored copies.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $oli_cf_table ) );
	}

	delete_option( 'oli_cf_settings' );
	delete_option( 'oli_cf_db_version' );
	delete_option( Oli_CF_Uploads::NOTICE_OPTION );

	wp_clear_scheduled_hook( 'oli_cf_purge_stored_messages' );
	Oli_CF_Uploads::clear_scheduled_purge();

	oli_cf_delete_feedback_transients();

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
}

/**
 * Delete oli_cf_fb_* transients and their timeout rows.
 */
function oli_cf_delete_feedback_transients() {
	global $wpdb;

	$transient_like = $wpdb->esc_like( '_transient_oli_cf_fb_' ) . '%';
	$timeout_like   = $wpdb->esc_like( '_transient_timeout_oli_cf_fb_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall must drop leftover feedback transients that hold posted values.
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$transient_like,
			$timeout_like
		)
	);
}

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$oli_cf_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $oli_cf_site_ids as $oli_cf_site_id ) {
		switch_to_blog( (int) $oli_cf_site_id );
		oli_cf_uninstall_current_site();
		restore_current_blog();
	}
} else {
	oli_cf_uninstall_current_site();
}
