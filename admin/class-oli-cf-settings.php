<?php
/**
 * Settings page.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Oli_CF_Settings {

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults() {
		return array(
			'recaptcha_enabled'       => false,
			'recaptcha_version'       => 'v3',
			'recaptcha_site_key'      => '',
			'recaptcha_secret_key'    => '',
			'turnstile_enabled'       => false,
			'turnstile_site_key'      => '',
			'turnstile_secret_key'    => '',
			'legacy_shortcode_compat' => false,
		);
	}

	/**
	 * Add settings page to admin menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=oli_contact_form',
			__( 'Settings', 'oli-contact-form' ),
			__( 'Settings', 'oli-contact-form' ),
			'manage_options',
			'oli-cf-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'oli_cf_settings_group',
			'oli_cf_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'oli_cf_anti_spam_section',
			__( 'Anti-spam Protection', 'oli-contact-form' ),
			array( $this, 'render_anti_spam_section' ),
			'oli-cf-settings'
		);

		add_settings_field(
			'recaptcha_enabled',
			__( 'Enable Google reCAPTCHA', 'oli-contact-form' ),
			array( $this, 'render_checkbox_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array( 'field' => 'recaptcha_enabled' )
		);

		add_settings_field(
			'recaptcha_version',
			__( 'reCAPTCHA version', 'oli-contact-form' ),
			array( $this, 'render_select_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array(
				'field'   => 'recaptcha_version',
				'options' => array(
					'v2' => __( 'v2 (checkbox)', 'oli-contact-form' ),
					'v3' => __( 'v3 (invisible score)', 'oli-contact-form' ),
				),
			)
		);

		add_settings_field(
			'recaptcha_site_key',
			__( 'reCAPTCHA Site Key', 'oli-contact-form' ),
			array( $this, 'render_text_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array( 'field' => 'recaptcha_site_key' )
		);

		add_settings_field(
			'recaptcha_secret_key',
			__( 'reCAPTCHA Secret Key', 'oli-contact-form' ),
			array( $this, 'render_text_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array(
				'field' => 'recaptcha_secret_key',
				'type'  => 'password',
			)
		);

		add_settings_field(
			'turnstile_enabled',
			__( 'Enable Cloudflare Turnstile', 'oli-contact-form' ),
			array( $this, 'render_checkbox_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array( 'field' => 'turnstile_enabled' )
		);

		add_settings_field(
			'turnstile_site_key',
			__( 'Turnstile Site Key', 'oli-contact-form' ),
			array( $this, 'render_text_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array( 'field' => 'turnstile_site_key' )
		);

		add_settings_field(
			'turnstile_secret_key',
			__( 'Turnstile Secret Key', 'oli-contact-form' ),
			array( $this, 'render_text_field' ),
			'oli-cf-settings',
			'oli_cf_anti_spam_section',
			array(
				'field' => 'turnstile_secret_key',
				'type'  => 'password',
			)
		);

		add_settings_section(
			'oli_cf_compat_section',
			__( 'Existing shortcodes', 'oli-contact-form' ),
			array( $this, 'render_compat_section' ),
			'oli-cf-settings'
		);

		add_settings_field(
			'legacy_shortcode_compat',
			__( 'Keep the contact-form-7 shortcode', 'oli-contact-form' ),
			array( $this, 'render_checkbox_field' ),
			'oli-cf-settings',
			'oli_cf_compat_section',
			array(
				'field'       => 'legacy_shortcode_compat',
				'description' => __( 'Serve imported forms that are still embedded with the contact-form-7 shortcode. This stays off while another plugin already provides that shortcode.', 'oli-contact-form' ),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'oli_cf_settings_group' );
				do_settings_sections( 'oli-cf-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render anti-spam section description.
	 */
	public function render_anti_spam_section() {
		echo '<p>' . esc_html__( 'Oli Contact Form includes a honeypot field on every form. Configure additional anti-spam services below if needed.', 'oli-contact-form' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Note: When enabled, external requests will be made to Google (reCAPTCHA) or Cloudflare (Turnstile) on form submission.', 'oli-contact-form' ) . '</p>';
	}

	/**
	 * Render compatibility section description.
	 */
	public function render_compat_section() {
		echo '<p>' . esc_html__( 'Use this when a site already embeds forms with the contact-form-7 shortcode.', 'oli-contact-form' ) . '</p>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'oli_cf_settings', array() );
		$field   = $args['field'];
		$checked = ! empty( $options[ $field ] );
		?>
		<label>
			<input type="checkbox" name="oli_cf_settings[<?php echo esc_attr( $field ); ?>]" value="1" <?php checked( $checked ); ?> />
			<?php
			if ( ! empty( $args['description'] ) ) {
				echo '<span class="description">' . esc_html( $args['description'] ) . '</span>';
			}
			?>
		</label>
		<?php
	}

	/**
	 * Render a select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'oli_cf_settings', array() );
		$field   = $args['field'];
		$value   = isset( $options[ $field ] ) ? $options[ $field ] : '';
		$choices = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();

		if ( 'recaptcha_version' === $field ) {
			$value = self::normalize_recaptcha_version( $value );
		}
		?>
		<select name="oli_cf_settings[<?php echo esc_attr( $field ); ?>]" id="oli-cf-<?php echo esc_attr( $field ); ?>">
			<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( (string) $choice_value ); ?>" <?php selected( $value, $choice_value ); ?>>
					<?php echo esc_html( $choice_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Normalize a saved reCAPTCHA version. Never inferred from the key.
	 *
	 * @param mixed $version Version.
	 * @return string
	 */
	public static function normalize_recaptcha_version( $version ) {
		return Oli_CF_Anti_Spam::normalize_recaptcha_version( $version );
	}

	/**
	 * Read the shortcode setting, including a value posted under a previous key.
	 *
	 * @param array<string, mixed> $input Posted settings.
	 * @return bool
	 */
	private static function posted_legacy_shortcode( $input ) {
		if ( array_key_exists( 'legacy_shortcode_compat', $input ) ) {
			return ! empty( $input['legacy_shortcode_compat'] );
		}

		$legacy = class_exists( 'Oli_CF_Import_Registry' ) ? Oli_CF_Import_Registry::legacy_option_key() : '';

		if ( '' !== $legacy && array_key_exists( $legacy, $input ) ) {
			return ! empty( $input[ $legacy ] );
		}

		return false;
	}

	/**
	 * Render text field.
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'oli_cf_settings', array() );
		$field   = $args['field'];
		$value   = isset( $options[ $field ] ) ? $options[ $field ] : '';
		$type    = isset( $args['type'] ) ? $args['type'] : 'text';
		?>
		<input type="<?php echo esc_attr( $type ); ?>" name="oli_cf_settings[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Input values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$sanitized = self::get_defaults();

		$sanitized['recaptcha_enabled']    = ! empty( $input['recaptcha_enabled'] );
		$sanitized['recaptcha_version']    = self::normalize_recaptcha_version( isset( $input['recaptcha_version'] ) ? $input['recaptcha_version'] : 'v3' );
		$sanitized['recaptcha_site_key']   = isset( $input['recaptcha_site_key'] ) ? sanitize_text_field( $input['recaptcha_site_key'] ) : '';
		$sanitized['recaptcha_secret_key'] = isset( $input['recaptcha_secret_key'] ) ? sanitize_text_field( $input['recaptcha_secret_key'] ) : '';

		$sanitized['turnstile_enabled']    = ! empty( $input['turnstile_enabled'] );
		$sanitized['turnstile_site_key']   = isset( $input['turnstile_site_key'] ) ? sanitize_text_field( $input['turnstile_site_key'] ) : '';
		$sanitized['turnstile_secret_key'] = isset( $input['turnstile_secret_key'] ) ? sanitize_text_field( $input['turnstile_secret_key'] ) : '';

		$sanitized['legacy_shortcode_compat'] = self::posted_legacy_shortcode( $input );

		return $sanitized;
	}
}
