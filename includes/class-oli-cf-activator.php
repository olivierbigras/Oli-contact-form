<?php
/**
 * Fired during plugin activation.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 */
class Oli_CF_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'class-oli-cf-uploads.php';
		Oli_CF_Uploads::maybe_schedule_purge();
		Oli_CF_Uploads::maybe_upgrade();

		flush_rewrite_rules();

		require_once plugin_dir_path( __DIR__ ) . 'admin/class-oli-cf-settings.php';

		$defaults = Oli_CF_Settings::get_defaults();
		$current  = get_option( 'oli_cf_settings', false );

		if ( false === $current || ! is_array( $current ) ) {
			add_option( 'oli_cf_settings', $defaults );
			return;
		}

		unset( $current['store_messages'], $current['retention_days'] );
		update_option( 'oli_cf_settings', wp_parse_args( $current, $defaults ) );
	}
}
