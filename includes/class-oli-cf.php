<?php
/**
 * The core plugin class.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Oli_CF {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Oli_CF_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->version     = OLI_CF_VERSION;
		$this->plugin_name = 'oli-contact-form';

		$this->load_dependencies();
		Oli_CF_Uploads::maybe_upgrade();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-loader.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-post-type.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-form-parser.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-uploads.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-validator.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-mailer.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-submission.php';
		require_once OLI_CF_PLUGIN_DIR . 'includes/class-oli-cf-anti-spam.php';

		require_once OLI_CF_PLUGIN_DIR . 'admin/class-oli-cf-admin.php';
		require_once OLI_CF_PLUGIN_DIR . 'admin/class-oli-cf-meta-boxes.php';
		require_once OLI_CF_PLUGIN_DIR . 'admin/class-oli-cf-settings.php';
		require_once OLI_CF_PLUGIN_DIR . 'admin/class-oli-cf-importer.php';

		require_once OLI_CF_PLUGIN_DIR . 'public/class-oli-cf-public.php';
		require_once OLI_CF_PLUGIN_DIR . 'public/class-oli-cf-shortcode.php';
		require_once OLI_CF_PLUGIN_DIR . 'public/class-oli-cf-block.php';

		$this->loader = new Oli_CF_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Oli_CF_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'maybe_import_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'maybe_staging_notice' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'maybe_dismiss_staging_notice' );

		$post_type = new Oli_CF_Post_Type();
		$this->loader->add_action( 'init', $post_type, 'register_post_type' );

		$meta_boxes = new Oli_CF_Meta_Boxes();
		$this->loader->add_action( 'add_meta_boxes', $meta_boxes, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post_oli_contact_form', $meta_boxes, 'save_form_data', 10, 2 );

		$settings = new Oli_CF_Settings();
		$this->loader->add_action( 'admin_menu', $settings, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $settings, 'register_settings' );

		$importer = new Oli_CF_Importer();
		$this->loader->add_action( 'admin_menu', $importer, 'add_importer_page' );
		$this->loader->add_action( 'admin_enqueue_scripts', $importer, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_post_oli_cf_import_cf7', $importer, 'handle_import' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new Oli_CF_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'maybe_enqueue_assets' );

		$shortcode = new Oli_CF_Shortcode();
		$this->loader->add_action( 'init', $shortcode, 'register_shortcode' );

		$block = new Oli_CF_Block();
		$this->loader->add_action( 'init', $block, 'register_block' );

		$submission = new Oli_CF_Submission();
		$this->loader->add_action( 'init', $submission, 'maybe_schedule_stale_upload_purge' );
		$this->loader->add_action( Oli_CF_Uploads::PURGE_CRON_HOOK, $submission, 'purge_stale_uploads' );
		$this->loader->add_action( 'wp_ajax_oli_cf_submit_form', $submission, 'handle_ajax_submission' );
		$this->loader->add_action( 'wp_ajax_nopriv_oli_cf_submit_form', $submission, 'handle_ajax_submission' );
		$this->loader->add_action( 'init', $submission, 'handle_non_ajax_submission' );
	}

	/**
	 * Run the loader to execute all the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks.
	 *
	 * @return Oli_CF_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
