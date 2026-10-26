<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Oli_CF_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		if ( 'oli_contact_form' === $screen->post_type ) {
			return true;
		}

		$extra_screens = array(
			'oli_contact_form_page_oli-cf-settings',
			'oli-contact-forms_page_oli-cf-settings',
			'oli_contact_form_page_oli-cf-import-cf7',
			'oli-contact-forms_page_oli-cf-import-cf7',
		);

		return in_array( $screen->id, $extra_screens, true );
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			OLI_CF_PLUGIN_URL . 'assets/css/oli-cf-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen || 'oli_contact_form' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			OLI_CF_PLUGIN_URL . 'assets/js/oli-cf-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'oliCfAdmin',
			array(
				'strings' => array(
					'field_name'     => __( 'Enter field name (e.g., "your-name"):', 'oli-contact-form' ),
					'required'       => __( 'Is this field required?', 'oli-contact-form' ),
					'options'        => __( 'Enter options (separated by spaces, quote multi-word options):', 'oli-contact-form' ),
					'acceptance'     => __( 'Enter acceptance text:', 'oli-contact-form' ),
					'acceptance_def' => __( 'I accept the terms', 'oli-contact-form' ),
					'file_limit'     => __( 'File size limit (e.g., "2mb"):', 'oli-contact-form' ),
					'file_types'     => __( 'Allowed file types (e.g., "jpg|png|pdf"):', 'oli-contact-form' ),
					'button_text'    => __( 'Button text:', 'oli-contact-form' ),
					'send'           => __( 'Send', 'oli-contact-form' ),
				),
			)
		);
	}

	/**
	 * Show the CF7 import result on this plugin's screens only.
	 */
	public function maybe_import_notice() {
		if ( ! $this->is_plugin_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['oli_cf_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag.
			return;
		}

		$count = absint( wp_unslash( $_GET['oli_cf_imported'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of imported forms */
				_n( '%d form imported.', '%d forms imported.', $count, 'oli-contact-form' ),
				$count
			)
		);
		echo '</p></div>';
	}
}
