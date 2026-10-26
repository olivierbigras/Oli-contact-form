<?php
/**
 * Handle form submissions.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submission handler class.
 */
class Oli_CF_Submission {

	/**
	 * Transient prefix for no-JS validation/mail feedback.
	 */
	const FEEDBACK_TRANSIENT_PREFIX = 'oli_cf_fb_';

	/**
	 * How long the no-JS feedback transient lives, in seconds.
	 */
	const FEEDBACK_TTL = 300;

	/**
	 * Handle AJAX form submission.
	 */
	public function handle_ajax_submission() {
		check_ajax_referer( 'oli_cf_submit', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;

		if ( ! $form_id && isset( $_POST['oli_cf_form_id'] ) ) {
			$form_id = absint( wp_unslash( $_POST['oli_cf_form_id'] ) );
		}

		if ( ! $form_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid form ID.', 'oli-contact-form' ),
				)
			);
		}

		$result = $this->process_submission( $form_id, wp_unslash( $_POST ), $this->normalize_files( $_FILES ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * Handle non-AJAX form submission (fallback).
	 */
	public function handle_non_ajax_submission() {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['oli_cf_submit'] ) || ! isset( $_POST['oli_cf_form_id'] ) ) {
			return;
		}

		$form_id = absint( wp_unslash( $_POST['oli_cf_form_id'] ) );

		if ( ! $form_id ) {
			return;
		}

		if ( ! isset( $_POST['oli_cf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_cf_nonce'] ) ), 'oli_cf_submit_' . $form_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-contact-form' ) );
		}

		$result = $this->process_submission( $form_id, wp_unslash( $_POST ), $this->normalize_files( $_FILES ) );

		$posted       = wp_unslash( $_POST );
		$redirect_url = self::resolve_redirect_url( $form_id, is_array( $posted ) ? $posted : array() );
		$status       = self::normalize_status( $result );

		$status_args = array(
			'oli_cf_status_nonce' => wp_create_nonce( 'oli_cf_status_' . $form_id ),
			'oli_cf_status'       => $status,
		);

		if ( $result['success'] ) {
			$status_args['oli_cf_success'] = $form_id;
		} else {
			$status_args['oli_cf_error'] = $form_id;
			$token                       = self::store_feedback( $form_id, $result );

			if ( '' !== $token ) {
				$status_args['oli_cf_token'] = $token;
			}
		}

		wp_safe_redirect( add_query_arg( $status_args, $redirect_url ) );
		exit;
	}

	/**
	 * Status string stored in the redirect and the feedback transient.
	 *
	 * @param array<string, mixed> $result process_submission() result.
	 * @return string
	 */
	public static function normalize_status( $result ) {
		if ( ! is_array( $result ) ) {
			return 'mail_failed';
		}

		if ( ! empty( $result['success'] ) ) {
			return 'success';
		}

		$status = isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : '';

		if ( in_array( $status, array( 'validation', 'mail_failed', 'spam' ), true ) ) {
			return $status;
		}

		return 'mail_failed';
	}

	/**
	 * Store sanitized no-JS feedback (errors + values). The URL only gets a token.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $result  process_submission() result.
	 * @return string Token, or empty string on failure.
	 */
	public static function store_feedback( $form_id, $result ) {
		$form_id = absint( $form_id );

		if ( ! $form_id || ! is_array( $result ) ) {
			return '';
		}

		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 24, false, false ) : bin2hex( random_bytes( 12 ) );
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

		if ( ! is_string( $token ) || strlen( $token ) < 12 ) {
			return '';
		}

		$errors = array();

		if ( isset( $result['errors'] ) && is_array( $result['errors'] ) ) {
			foreach ( $result['errors'] as $field => $message ) {
				if ( ! is_string( $field ) || '' === $field || ! is_string( $message ) ) {
					continue;
				}

				$errors[ $field ] = sanitize_text_field( $message );
			}
		}

		$values = array();

		if ( isset( $result['values'] ) && is_array( $result['values'] ) ) {
			$values = $result['values'];
		}

		$payload = array(
			'form_id' => $form_id,
			'status'  => self::normalize_status( $result ),
			'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
			'errors'  => $errors,
			'values'  => $values,
		);

		if ( ! set_transient( self::FEEDBACK_TRANSIENT_PREFIX . $token, $payload, self::FEEDBACK_TTL ) ) {
			return '';
		}

		return $token;
	}

	/**
	 * Read and delete a one-shot no-JS feedback payload.
	 *
	 * @param string $token   Token from the redirect query string.
	 * @param int    $form_id Expected form ID.
	 * @return array<string, mixed>
	 */
	public static function consume_feedback( $token, $form_id ) {
		$token   = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		$form_id = absint( $form_id );

		if ( ! is_string( $token ) || '' === $token || ! $form_id ) {
			return array();
		}

		$key     = self::FEEDBACK_TRANSIENT_PREFIX . $token;
		$payload = get_transient( $key );

		if ( ! is_array( $payload ) ) {
			return array();
		}

		if ( ! isset( $payload['form_id'] ) || absint( $payload['form_id'] ) !== $form_id ) {
			return array();
		}

		delete_transient( $key );

		return $payload;
	}

	/**
	 * Redirect target after a non-AJAX submit: posted permalink, raw referer, then home.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $posted  Unslashed POST data.
	 * @return string
	 */
	public static function resolve_redirect_url( $form_id, $posted ) {
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		$candidate = '';

		if ( ! empty( $posted['oli_cf_redirect'] ) && is_string( $posted['oli_cf_redirect'] ) ) {
			$candidate = $posted['oli_cf_redirect'];
		}

		if ( '' === $candidate && function_exists( 'wp_get_raw_referer' ) ) {
			$raw = wp_get_raw_referer();

			if ( is_string( $raw ) && '' !== $raw ) {
				$candidate = $raw;
			}
		}

		if ( '' === $candidate && function_exists( 'wp_get_referer' ) ) {
			$ref = wp_get_referer();

			if ( is_string( $ref ) && '' !== $ref ) {
				$candidate = $ref;
			}
		}

		$fallback = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

		if ( function_exists( 'wp_validate_redirect' ) ) {
			$validated = wp_validate_redirect( $candidate, $fallback );
		} else {
			$validated = '' !== $candidate ? $candidate : $fallback;
		}

		if ( ! is_string( $validated ) || '' === $validated ) {
			$validated = $fallback;
		}

		$validated = preg_replace( '/#.*$/', '', $validated );

		return $validated . '#oli-cf-form-' . absint( $form_id );
	}

	/**
	 * Process form submission.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $post    POST data (already unslashed).
	 * @param array<string, mixed> $files   FILES data.
	 * @return array<string, mixed>
	 */
	public function process_submission( $form_id, $post, $files ) {
		$form = get_post( $form_id );

		if ( ! $form || 'oli_contact_form' !== $form->post_type ) {
			return array(
				'success' => false,
				'status'  => 'mail_failed',
				'message' => __( 'Form not found.', 'oli-contact-form' ),
			);
		}

		$template = get_post_meta( $form_id, '_oli_cf_form_template', true );
		$messages = Oli_CF_Validator::normalize_messages( get_post_meta( $form_id, '_oli_cf_messages', true ) );

		if ( empty( $template ) ) {
			return array(
				'success' => false,
				'status'  => 'mail_failed',
				'message' => __( 'Form configuration error.', 'oli-contact-form' ),
			);
		}

		$data   = $this->sanitize_posted_data( $post );
		$fields = Oli_CF_Form_Parser::parse_fields( $template );

		/**
		 * Fires before validation.
		 *
		 * @param int                  $form_id Form ID.
		 * @param array<string, mixed> $data    Sanitized posted data.
		 * @param array<string, mixed> $files   Files.
		 */
		do_action( 'oli_cf_before_validation', $form_id, $data, $files );

		$spam_check = Oli_CF_Anti_Spam::check( $data );

		if ( is_array( $spam_check ) ) {
			return array(
				'success' => false,
				'status'  => 'spam',
				'message' => $spam_check['message'],
				'values'  => $data,
			);
		}

		$validation = Oli_CF_Validator::validate( $data, $template, $files, $messages );

		if ( is_array( $validation ) ) {
			return array(
				'success' => false,
				'status'  => 'validation',
				'message' => $messages['validation_error'],
				'errors'  => $validation,
				'values'  => $data,
			);
		}

		$staged = Oli_CF_Uploads::stage_files( $files, $fields );

		/**
		 * Fires before sending mail.
		 *
		 * @param int                  $form_id Form ID.
		 * @param array<string, mixed> $data    Sanitized posted data.
		 * @param string[]             $staged  Staged attachment paths.
		 */
		do_action( 'oli_cf_before_send', $form_id, $data, $staged );

		$sent = Oli_CF_Mailer::send( $form_id, $data, array_values( $staged ) );

		Oli_CF_Uploads::delete_files( $staged );

		if ( ! $sent ) {
			return array(
				'success' => false,
				'status'  => 'mail_failed',
				'message' => $messages['mail_sent_ng'],
				'values'  => $data,
			);
		}

		/**
		 * Fires after a successful send. Uploaded files have already been deleted.
		 *
		 * @param int                  $form_id Form ID.
		 * @param array<string, mixed> $data    Sanitized posted data.
		 */
		do_action( 'oli_cf_after_send', $form_id, $data );

		return array(
			'success' => true,
			'status'  => 'success',
			'message' => $messages['mail_sent_ok'],
		);
	}

	/**
	 * Sanitize posted fields used in mail and validation.
	 *
	 * @param array<string, mixed> $post Posted data.
	 * @return array<string, mixed>
	 */
	private function sanitize_posted_data( $post ) {
		$clean = array();

		if ( ! is_array( $post ) ) {
			return $clean;
		}

		$skip = array(
			'action',
			'nonce',
			'oli_cf_nonce',
			'oli_cf_redirect',
			'_wpnonce',
			'_wp_http_referer',
			'oli_cf_submit',
			'oli_cf_form_id',
			'form_id',
			'oli_cf_hp',
			'g-recaptcha-response',
			'cf-turnstile-response',
		);

		foreach ( $post as $key => $value ) {
			$key = is_string( $key ) ? $key : (string) $key;

			if ( in_array( $key, $skip, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', $value );
				continue;
			}

			$value = (string) $value;

			if ( strlen( $value ) > 200 ) {
				$clean[ $key ] = sanitize_textarea_field( $value );
			} else {
				$clean[ $key ] = sanitize_text_field( $value );
			}
		}

		return $clean;
	}

	/**
	 * Keep only the keys needed from $_FILES.
	 *
	 * @param array<string, mixed> $files Files array.
	 * @return array<string, mixed>
	 */
	private function normalize_files( $files ) {
		$out = array();

		if ( ! is_array( $files ) ) {
			return $out;
		}

		foreach ( $files as $key => $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$out[ $key ] = array(
				'name'     => isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '',
				'type'     => isset( $file['type'] ) ? sanitize_text_field( (string) $file['type'] ) : '',
				'tmp_name' => isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '',
				'error'    => isset( $file['error'] ) ? (int) $file['error'] : 0,
				'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
			);
		}

		return $out;
	}
}
