<?php
/**
 * Shortcode functionality.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 */
class Oli_CF_Shortcode {

	/**
	 * Register shortcode.
	 */
	public function register_shortcode() {
		add_shortcode( 'oli_contact_form', array( $this, 'render_shortcode' ) );

		$settings = get_option( 'oli_cf_settings', array() );

		if ( self::should_register_legacy_shortcode( $settings ) ) {
			add_shortcode( 'contact-form-7', array( $this, 'render_legacy_shortcode' ) );
		}
	}

	/**
	 * Render shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'oli_contact_form'
		);

		$form_id = absint( $atts['id'] );

		if ( ! $form_id ) {
			return '';
		}

		return $this->render_form( $form_id );
	}

	/**
	 * Whether the legacy shortcode may be registered.
	 *
	 * It stays off while another plugin already provides that shortcode, or while
	 * the source that owns it is still active.
	 *
	 * @param mixed $settings Plugin settings.
	 * @return bool
	 */
	public static function should_register_legacy_shortcode( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings['legacy_shortcode_compat'] ) ) {
			return false;
		}

		if ( class_exists( 'Oli_CF_Import_Registry' ) && Oli_CF_Import_Registry::legacy_shortcode_blocked() ) {
			return false;
		}

		if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'contact-form-7' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the legacy shortcode for a form imported from another plugin.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_legacy_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'contact-form-7'
		);

		$source_id = absint( $atts['id'] );

		if ( ! $source_id || ! class_exists( 'Oli_CF_Import_Registry' ) ) {
			return '';
		}

		$source = Oli_CF_Import_Registry::legacy_shortcode_source();

		if ( ! $source ) {
			return '';
		}

		$forms = get_posts(
			array(
				'post_type'      => 'oli_contact_form',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one published form by its imported source id.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_oli_cf_imported_from',
						'value' => $source->key(),
					),
					array(
						'key'   => '_oli_cf_import_source_id',
						'value' => (string) $source_id,
					),
				),
			)
		);

		if ( empty( $forms ) ) {
			return '';
		}

		return $this->render_form( $forms[0]->ID );
	}

	/**
	 * Render form HTML.
	 *
	 * @param int $form_id Form ID.
	 * @return string Form HTML.
	 */
	private function render_form( $form_id ) {
		$form = get_post( $form_id );

		if ( ! $form || 'oli_contact_form' !== $form->post_type || 'publish' !== $form->post_status ) {
			return '';
		}

		$fields = Oli_CF_Fields::for_form( $form_id );

		if ( empty( $fields ) ) {
			return '';
		}

		Oli_CF_Public::enqueue_form_assets();

		$success_message = '';
		$error_message   = '';
		$field_state     = array(
			'values' => array(),
			'errors' => array(),
		);
		$status_nonce    = isset( $_GET['oli_cf_status_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['oli_cf_status_nonce'] ) ) : '';
		$status_query    = isset( $_GET['oli_cf_status'] ) ? sanitize_key( wp_unslash( $_GET['oli_cf_status'] ) ) : '';
		$feedback_token  = isset( $_GET['oli_cf_token'] ) ? sanitize_text_field( wp_unslash( $_GET['oli_cf_token'] ) ) : '';

		if ( wp_verify_nonce( $status_nonce, 'oli_cf_status_' . $form_id ) ) {
			$messages   = Oli_CF_Validator::normalize_messages( get_post_meta( $form_id, '_oli_cf_messages', true ) );
			$success_id = isset( $_GET['oli_cf_success'] ) ? absint( wp_unslash( $_GET['oli_cf_success'] ) ) : 0;
			$error_id   = isset( $_GET['oli_cf_error'] ) ? absint( wp_unslash( $_GET['oli_cf_error'] ) ) : 0;

			if ( $success_id === $form_id || 'success' === $status_query ) {
				$success_message = $messages['success'];
			}

			if ( $error_id === $form_id || in_array( $status_query, array( 'validation', 'mail_failed', 'spam' ), true ) ) {
				$feedback = Oli_CF_Submission::consume_feedback( $feedback_token, $form_id );

				if ( ! empty( $feedback ) ) {
					$field_state['errors'] = isset( $feedback['errors'] ) && is_array( $feedback['errors'] ) ? $feedback['errors'] : array();
					$field_state['values'] = isset( $feedback['values'] ) && is_array( $feedback['values'] ) ? $feedback['values'] : array();
					$error_message         = isset( $feedback['message'] ) ? (string) $feedback['message'] : '';
					$feedback_status       = isset( $feedback['status'] ) ? (string) $feedback['status'] : $status_query;

					if ( 'validation' === $feedback_status ) {
						$count = count( $field_state['errors'] );

						if ( $count > 0 ) {
							$error_message = sprintf(
								/* translators: %d: number of invalid fields */
								_n( 'The form contains %d error.', 'The form contains %d errors.', $count, 'oli-contact-form' ),
								$count
							);
						} elseif ( '' === $error_message ) {
							$error_message = $messages['validation_error'];
						}
					} elseif ( '' === $error_message ) {
						$error_message = $messages['send_error'];
					}
				} elseif ( 'validation' === $status_query ) {
					$error_message = $messages['validation_error'];
				} else {
					$error_message = $messages['send_error'];
				}
			}
		}

		$settings = get_option( 'oli_cf_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$form_messages = Oli_CF_Validator::normalize_messages( get_post_meta( $form_id, '_oli_cf_messages', true ) );
		$field_labels  = Oli_CF_Fields::label_map( $fields );
		$has_status    = ( '' !== $success_message || '' !== $error_message );
		$has_required  = Oli_CF_Fields::has_required( $fields );
		$redirect_to   = self::current_page_url();

		ob_start();
		?>
		<div class="oli-cf-container<?php echo $has_status ? ' oli-cf-has-status' : ''; ?>" id="oli-cf-container-<?php echo esc_attr( (string) absint( $form_id ) ); ?>">
			<div class="oli-cf-response-output" id="oli-cf-response-<?php echo esc_attr( (string) absint( $form_id ) ); ?>" tabindex="-1" aria-live="polite" role="<?php echo $error_message ? 'alert' : 'status'; ?>">
				<?php if ( $success_message ) : ?>
					<div class="oli-cf-response-success"><?php echo esc_html( $success_message ); ?></div>
				<?php endif; ?>
				<?php if ( $error_message ) : ?>
					<div class="oli-cf-response-error">
						<?php echo esc_html( $error_message ); ?>
						<?php if ( ! empty( $field_state['errors'] ) ) : ?>
							<ul class="oli-cf-validation-errors">
								<?php foreach ( $field_state['errors'] as $error_field => $error_text ) : ?>
									<?php
									$error_label = isset( $field_labels[ $error_field ] ) ? $field_labels[ $error_field ] : Oli_CF_Form_Parser::humanize_field_name( (string) $error_field );
									$error_item  = Oli_CF_Form_Parser::format_error_summary_item( $error_label, (string) $error_text );
									?>
									<li><a href="#oli-cf-<?php echo esc_attr( (string) absint( $form_id ) ); ?>-<?php echo esc_attr( sanitize_key( (string) $error_field ) ); ?>"><?php echo esc_html( $error_item ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<form class="oli-cf-form" id="oli-cf-form-<?php echo esc_attr( (string) absint( $form_id ) ); ?>" method="post" enctype="multipart/form-data" novalidate="novalidate" data-oli-messages="<?php echo esc_attr( wp_json_encode( $form_messages ) ); ?>">
				<?php wp_nonce_field( 'oli_cf_submit_' . $form_id, 'oli_cf_nonce' ); ?>
				<input type="hidden" name="oli_cf_form_id" value="<?php echo esc_attr( (string) absint( $form_id ) ); ?>" />
				<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) absint( $form_id ) ); ?>" />
				<input type="hidden" name="oli_cf_redirect" value="<?php echo esc_url( $redirect_to ); ?>" />

				<?php if ( $has_required ) : ?>
					<p class="oli-cf-required-note">* <?php esc_html_e( 'Required field', 'oli-contact-form' ); ?></p>
				<?php endif; ?>

				<div class="oli-cf-honeypot" aria-hidden="true">
					<label for="oli-cf-hp-<?php echo esc_attr( (string) absint( $form_id ) ); ?>"><?php esc_html_e( 'Leave this field empty', 'oli-contact-form' ); ?></label>
					<input type="text" name="oli_cf_hp" id="oli-cf-hp-<?php echo esc_attr( (string) absint( $form_id ) ); ?>" value="" tabindex="-1" autocomplete="off" />
				</div>

				<?php echo wp_kses( Oli_CF_Form_Parser::render_fields( $fields, $form_id, $field_state ), Oli_CF_Form_Parser::allowed_html() ); ?>

				<?php echo wp_kses( Oli_CF_Public::recaptcha_widget_html( $form_id, $settings ), Oli_CF_Public::recaptcha_allowed_html() ); ?>

				<?php if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] ) ) : ?>
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" data-callback="oliCfTurnstileCallback"></div>
				<?php endif; ?>

				<input type="hidden" name="oli_cf_submit" value="1" />
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Permalink of the page that currently displays the form.
	 *
	 * @return string
	 */
	public static function current_page_url() {
		if ( function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink();

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		if ( function_exists( 'home_url' ) ) {
			$path = '/';

			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}

			return home_url( $path );
		}

		return '';
	}
}
