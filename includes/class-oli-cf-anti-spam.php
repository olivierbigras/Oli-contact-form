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
			$version          = self::normalize_recaptcha_version( isset( $settings['recaptcha_version'] ) ? $settings['recaptcha_version'] : 'v3' );
			$recaptcha_result = self::verify_recaptcha( $data, $settings['recaptcha_secret_key'], $version );

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
	 * Verify Google reCAPTCHA v2 or v3 response.
	 *
	 * @param array<string, mixed> $data       Posted data.
	 * @param string               $secret_key reCAPTCHA secret key.
	 * @param string               $version    Configured version (v2 or v3).
	 * @return array<string, string>|true True if valid, array with error if failed.
	 */
	private static function verify_recaptcha( $data, $secret_key, $version = 'v3' ) {
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

		if ( ! self::recaptcha_body_is_valid( $body, $version ) ) {
			return array(
				'type'    => 'spam',
				'message' => __( 'Spam detected by reCAPTCHA.', 'oli-contact-form' ),
			);
		}

		return true;
	}

	/**
	 * Whether a siteverify JSON body is an accepted reCAPTCHA result.
	 *
	 * v2 responses have `success` (and often `hostname`) but no `score`.
	 * The score threshold applies only to v3.
	 *
	 * @param mixed  $body    Decoded siteverify body.
	 * @param string $version Configured version (v2 or v3).
	 * @return bool
	 */
	public static function recaptcha_body_is_valid( $body, $version = 'v3' ) {
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return false;
		}

		$version = self::normalize_recaptcha_version( $version );

		if ( 'v3' === $version ) {
			if ( ! isset( $body['score'] ) || (float) $body['score'] < 0.5 ) {
				return false;
			}
		}

		return self::recaptcha_hostname_is_valid( $body );
	}

	/**
	 * Normalize a configured reCAPTCHA version. Never inferred from the key.
	 *
	 * @param mixed $version Version string or settings array.
	 * @return string 'v2' or 'v3'.
	 */
	public static function normalize_recaptcha_version( $version ) {
		if ( is_array( $version ) ) {
			$version = isset( $version['recaptcha_version'] ) ? $version['recaptcha_version'] : 'v3';
		}

		return ( 'v2' === strtolower( (string) $version ) ) ? 'v2' : 'v3';
	}

	/**
	 * Accept a siteverify hostname when one is present.
	 *
	 * Official Google test keys report `testkey.google.com`. When the site
	 * host cannot be determined, a successful response is still accepted.
	 *
	 * @param array<string, mixed> $body Decoded siteverify body.
	 * @return bool
	 */
	public static function recaptcha_hostname_is_valid( $body ) {
		if ( empty( $body['hostname'] ) || ! is_string( $body['hostname'] ) ) {
			return true;
		}

		$got = strtolower( $body['hostname'] );

		if ( 'testkey.google.com' === $got ) {
			return true;
		}

		$expected = self::expected_site_hostname();

		if ( '' === $expected ) {
			return true;
		}

		return $got === $expected;
	}

	/**
	 * Site hostname used to compare a reCAPTCHA hostname claim.
	 *
	 * @return string
	 */
	private static function expected_site_hostname() {
		if ( ! function_exists( 'home_url' ) || ! function_exists( 'wp_parse_url' ) ) {
			return '';
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return ( is_string( $host ) && '' !== $host ) ? strtolower( $host ) : '';
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
