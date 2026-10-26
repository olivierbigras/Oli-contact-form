<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public class.
 */
class Oli_CF_Public {

	/**
	 * Whether form assets were already enqueued for this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

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
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register assets and enqueue them when the queried content contains a form.
	 */
	public function maybe_enqueue_assets() {
		if ( self::page_has_form() ) {
			self::enqueue_form_assets();
		}
	}

	/**
	 * Enqueue public CSS/JS and optional captcha scripts.
	 *
	 * Called from the shortcode/block when a form actually renders, and from
	 * wp_enqueue_scripts when the queried post already contains a form.
	 *
	 * Google reCAPTCHA and Cloudflare Turnstile client scripts are official
	 * service APIs. They cannot be self-hosted: the providers require their
	 * CDN scripts so tokens can be issued. They are loaded only when an
	 * administrator has enabled the service and this request is rendering a
	 * form (shortcode or block).
	 */
	public static function enqueue_form_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}

		$settings = get_option( 'oli_cf_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$deps = array( 'jquery' );

		if ( self::recaptcha_client_should_load( $settings ) ) {
			$recaptcha_version = self::recaptcha_version( $settings );
			wp_enqueue_script(
				'oli-cf-recaptcha',
				// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Official Google reCAPTCHA API; must be served by Google and cannot be self-hosted. Loaded only when reCAPTCHA is enabled and a form is present.
				self::recaptcha_script_url( $settings ),
				array(),
				$recaptcha_version,
				true
			);
			$deps[] = 'oli-cf-recaptcha';
		}

		if ( self::turnstile_client_should_load( $settings ) ) {
			wp_enqueue_script(
				'oli-cf-turnstile',
				// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Official Cloudflare Turnstile widget API; must be served by Cloudflare and cannot be self-hosted. Loaded only when Turnstile is enabled and a form is present.
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				array(),
				'v0',
				true
			);
			$deps[] = 'oli-cf-turnstile';
		}

		wp_enqueue_style(
			'oli-cf-public',
			OLI_CF_PLUGIN_URL . 'assets/css/oli-cf-public.css',
			array(),
			OLI_CF_VERSION,
			'all'
		);

		wp_enqueue_script(
			'oli-cf-public',
			OLI_CF_PLUGIN_URL . 'assets/js/oli-cf-public.js',
			$deps,
			OLI_CF_VERSION,
			true
		);

		wp_localize_script(
			'oli-cf-public',
			'oliCf',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'oli_cf_submit' ),
				'recaptchaSiteKey' => self::recaptcha_client_should_load( $settings ) ? $settings['recaptcha_site_key'] : '',
				'recaptchaVersion' => self::recaptcha_version( $settings ),
				'turnstileSiteKey' => self::turnstile_client_should_load( $settings ) ? $settings['turnstile_site_key'] : '',
				'strings'          => array(
					'validationError' => __( 'One or more fields have an error. Please check and try again.', 'oli-contact-form' ),
					'unexpectedError' => __( 'An unexpected error occurred. Please try again.', 'oli-contact-form' ),
					'errorBannerOne'  => __( 'The form contains 1 error.', 'oli-contact-form' ),
					/* translators: %d: number of invalid fields */
					'errorBannerMany' => __( 'The form contains %d errors.', 'oli-contact-form' ),
					/* translators: %d: number of invalid fields */
					'errorBanner'     => __( 'The form contains %d errors.', 'oli-contact-form' ),
					'invalidRequired' => __( 'This field is required.', 'oli-contact-form' ),
					'invalidEmail'    => __( 'Please enter a valid email address.', 'oli-contact-form' ),
					'invalidTel'      => __( 'Please enter a valid phone number.', 'oli-contact-form' ),
					'invalidUrl'      => __( 'Please enter a valid URL.', 'oli-contact-form' ),
					'invalidNumber'   => __( 'Please enter a valid number.', 'oli-contact-form' ),
					'invalidDate'     => __( 'Please enter a valid date.', 'oli-contact-form' ),
					'acceptTerms'     => __( 'You must accept this to proceed.', 'oli-contact-form' ),
				),
			)
		);

		self::$assets_enqueued = true;
	}

	/**
	 * Whether the official reCAPTCHA client script should load on this request.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	private static function recaptcha_client_should_load( $settings ) {
		return ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_site_key'] );
	}

	/**
	 * Configured reCAPTCHA version (v2 or v3). Never inferred from the key.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	public static function recaptcha_version( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return Oli_CF_Anti_Spam::normalize_recaptcha_version( $settings );
	}

	/**
	 * Official Google reCAPTCHA script URL for the configured version.
	 *
	 * v2 uses api.js with no render parameter. v3 uses api.js?render=SITEKEY.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	public static function recaptcha_script_url( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$base = 'https://www.google.com/recaptcha/api.js';

		if ( 'v2' === self::recaptcha_version( $settings ) ) {
			return $base;
		}

		$key = isset( $settings['recaptcha_site_key'] ) ? (string) $settings['recaptcha_site_key'] : '';

		return $base . '?render=' . rawurlencode( $key );
	}

	/**
	 * Markup for the configured reCAPTCHA widget (v2 checkbox or v3 hidden token).
	 *
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return string
	 */
	public static function recaptcha_widget_html( $form_id, $settings ) {
		if ( ! self::recaptcha_client_should_load( $settings ) ) {
			return '';
		}

		$form_id = absint( $form_id );
		$key     = isset( $settings['recaptcha_site_key'] ) ? (string) $settings['recaptcha_site_key'] : '';

		if ( 'v2' === self::recaptcha_version( $settings ) ) {
			return sprintf(
				'<div class="g-recaptcha" data-sitekey="%s"></div>',
				esc_attr( $key )
			);
		}

		return sprintf(
			'<input type="hidden" name="g-recaptcha-response" id="oli-cf-recaptcha-%d" value="" />',
			$form_id
		);
	}

	/**
	 * Whether the official Turnstile client script should load on this request.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	private static function turnstile_client_should_load( $settings ) {
		return ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] );
	}

	/**
	 * Whether the main queried content contains a form shortcode or block.
	 *
	 * @return bool
	 */
	public static function page_has_form() {
		global $post;

		if ( $post instanceof WP_Post && self::content_has_form( $post->post_content ) ) {
			return true;
		}

		$queried = get_queried_object();

		if ( $queried instanceof WP_Post && self::content_has_form( $queried->post_content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether a content string contains this plugin's shortcode or block.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public static function content_has_form( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		if ( has_shortcode( $content, 'oli_contact_form' ) ) {
			return true;
		}

		if ( function_exists( 'has_block' ) && has_block( 'oli-cf/contact-form', $content ) ) {
			return true;
		}

		$settings = get_option( 'oli_cf_settings', array() );

		if ( ! empty( $settings['cf7_compat_enabled'] ) && has_shortcode( $content, 'contact-form-7' ) ) {
			return true;
		}

		return false;
	}
}
