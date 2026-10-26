<?php
/**
 * Plugin Name: Oli Contact Form
 * Plugin URI: https://github.com/olivierbigras/Oli-contact-form
 * Description: A simple, accessible contact form plugin with CF7-like tag syntax and built-in anti-spam protection.
 * Version: 0.3.5
 * Author: Olivier Bigras
 * Author URI: https://github.com/olivierbigras
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oli-contact-form
 * Requires at least: 6.5
 * Requires PHP: 7.4
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OLI_CF_VERSION', '0.3.5' );
define( 'OLI_CF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLI_CF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OLI_CF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function oli_cf_activate() {
	require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-activator.php';
	Oli_CF_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function oli_cf_deactivate() {
	require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-deactivator.php';
	Oli_CF_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'oli_cf_activate' );
register_deactivation_hook( __FILE__, 'oli_cf_deactivate' );

/**
 * The core plugin class.
 */
require OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf.php';

/**
 * Begins execution of the plugin.
 */
function oli_cf_run() {
	$plugin = new Oli_CF();
	$plugin->run();
}
oli_cf_run();
