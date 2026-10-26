<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class.
 */
class Oli_CF_Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'oli_cf_purge_stored_messages' );
		flush_rewrite_rules();
	}
}
