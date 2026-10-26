<?php
/**
 * Anti-spam protection.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anti-spam class.
 */
class Oli_CF_Anti_Spam {

	/**
	 * Check if submission passes anti-spam tests.
	 *
	 * @param array<string, mixed> $data Posted data.
	 * @return array<string, string>|true True if valid, array with error message if spam.
	 */
	public static function check( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! empty( $data['oli_cf_hp'] ) ) {
			return array(
				'type'    => 'spam',
				'message' => __( 'Spam detected.', 'oli-contact-form' ),
			);
		}

		$settings = get_option( 'oli_cf_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! empty( $settings['recaptcha_enabled'] ) && ! empty( $settings['recaptcha_secret_key'] ) ) {
			$recaptcha_result = self::verify_recaptcha( $data, $settings['recaptcha_secret_key'] );

			if ( is_array( $recaptcha_result ) ) {
				return $recaptcha_result;
			}
		}

		if ( ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_secret_key'] ) ) {
			$turnstile_result = self::verify_turnstile( $data, $settings['turnstile_secret_key'] );

			if ( is_array( $turnstile_result ) ) {
				return $turnstile_result;
			}
		}

		return true;
	}

	/**
	 * Verify Google reCAPTCHA v3 response.
	 *
	 * @param array<string, mixed> $data       Posted data.
	 * @param string               $secret_key reCAPTCHA secret key.
	 * @return array<string, string>|true True if valid, array with error if failed.
	 */
	private static function verify_recaptcha( $data, $secret_key ) {
		if ( empty( $data['g-recaptcha-response'] ) ) {
			return array(
				'type'    => 'validation_failed',
				'message' => __( 'reCAPTCHA verification failed. Please try again.', 'oli-contact-form' ),
			);
		}

		// Official Google reCAPTCHA verification endpoint. Tokens issued by
		// Google's client script can only be verified on Google's servers.
		$response = wp_remote_post(
			// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Official Google reCAPTCHA siteverify API (server-side). Required by the service and cannot be self-hosted. Called only when reCAPTCHA is enabled and a form is submitted.
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $data['g-recaptcha-response'],
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'type'    => 'validation_failed',
				'message' => __( 'reCAPTCHA verification failed. Please try again.', 'oli-contact-form' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) || ! isset( $body['score'] ) || $body['score'] < 0.5 ) {
			return array(
				'type'    => 'spam',
				'message' => __( 'Spam detected by reCAPTCHA.', 'oli-contact-form' ),
			);
		}

		return true;
	}

	/**
	 * Verify Cloudflare Turnstile response.
	 *
	 * @param array<string, mixed> $data       Posted data.
	 * @param string               $secret_key Turnstile secret key.
	 * @return array<string, string>|true True if valid, array with error if failed.
	 */
	private static function verify_turnstile( $data, $secret_key ) {
		if ( empty( $data['cf-turnstile-response'] ) ) {
			return array(
				'type'    => 'validation_failed',
				'message' => __( 'Turnstile verification failed. Please try again.', 'oli-contact-form' ),
			);
		}

		// Official Cloudflare Turnstile verification endpoint. Tokens issued
		// by Cloudflare's widget can only be verified on Cloudflare's servers.
		$response = wp_remote_post(
			// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Official Cloudflare Turnstile siteverify API (server-side). Required by the service and cannot be self-hosted. Called only when Turnstile is enabled and a form is submitted.
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $data['cf-turnstile-response'],
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'type'    => 'validation_failed',
				'message' => __( 'Turnstile verification failed. Please try again.', 'oli-contact-form' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) ) {
			return array(
				'type'    => 'spam',
				'message' => __( 'Spam detected by Turnstile.', 'oli-contact-form' ),
			);
		}

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
