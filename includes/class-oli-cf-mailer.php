<?php
/**
 * Handle email sending.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailer class.
 */
class Oli_CF_Mailer {

	/**
	 * Send form submission emails.
	 *
	 * @param int                  $form_id     Form ID.
	 * @param array<string, mixed> $data        Posted data.
	 * @param string[]             $attachments Absolute paths of staged files.
	 * @return bool True on success, false on failure.
	 */
	public static function send( $form_id, $data, $attachments = array() ) {
		$mail_settings   = get_post_meta( $form_id, '_oli_cf_mail', true );
		$mail_2_settings = get_post_meta( $form_id, '_oli_cf_mail_2', true );

		if ( empty( $mail_settings ) || ! is_array( $mail_settings ) ) {
			return false;
		}

		$main       = self::build_mail( $mail_settings, $data, $attachments );
		$main['to'] = self::resolve_recipient( $main['to'] );

		if ( empty( $main['to'] ) ) {
			return false;
		}

		$result = wp_mail( $main['to'], $main['subject'], $main['body'], $main['headers'], $main['attachments'] );

		if ( is_array( $mail_2_settings ) && ! empty( $mail_2_settings['enabled'] ) ) {
			$mail_2 = self::build_mail( $mail_2_settings, $data, array() );

			if ( ! empty( $mail_2['to'] ) ) {
				wp_mail( $mail_2['to'], $mail_2['subject'], $mail_2['body'], $mail_2['headers'], $mail_2['attachments'] );
			}
		}

		return (bool) $result;
	}

	/**
	 * Build a mail payload without sending.
	 *
	 * @param array<string, mixed> $settings    Mail settings.
	 * @param array<string, mixed> $data        Posted data.
	 * @param string[]             $attachments Attachment paths.
	 * @return array<string, mixed>
	 */
	public static function build_mail( $settings, $data, $attachments = array() ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$to      = self::replace_tags( isset( $settings['to'] ) ? $settings['to'] : '', $data );
		$subject = self::replace_tags( isset( $settings['subject'] ) ? $settings['subject'] : '', $data );
		$body    = self::replace_tags( isset( $settings['body'] ) ? $settings['body'] : '', $data );
		$headers = array();

		if ( ! empty( $settings['from'] ) ) {
			$from = self::replace_tags( $settings['from'], $data );
			$from = self::sanitize_header_value( $from );

			if ( '' !== $from ) {
				$headers[] = 'From: ' . $from;
			}
		}

		if ( ! empty( $settings['reply_to'] ) ) {
			$reply_to = self::replace_tags( $settings['reply_to'], $data );
			$reply_to = self::sanitize_header_value( $reply_to );

			if ( '' !== $reply_to ) {
				$headers[] = 'Reply-To: ' . $reply_to;
			}
		}

		if ( ! empty( $settings['additional_headers'] ) ) {
			$extra_lines = preg_split( '/\r\n|\r|\n/', (string) $settings['additional_headers'] );

			if ( is_array( $extra_lines ) ) {
				foreach ( $extra_lines as $header ) {
					$header = self::sanitize_header_value( self::replace_tags( trim( $header ), $data ) );

					if ( '' !== $header ) {
						$headers[] = $header;
					}
				}
			}
		}

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$safe_attachments = array();

		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $path ) {
				$path = (string) $path;

				if ( '' !== $path && file_exists( $path ) ) {
					$safe_attachments[] = $path;
				}
			}
		}

		return array(
			'to'          => self::sanitize_header_value( $to ),
			'subject'     => $subject,
			'body'        => nl2br( esc_html( $body ), false ),
			'headers'     => $headers,
			'attachments' => $safe_attachments,
		);
	}

	/**
	 * Replace [field] tags with submitted values or CF7 special mail-tags.
	 *
	 * @param string               $text Text with tags.
	 * @param array<string, mixed> $data Posted data.
	 * @return string
	 */
	public static function replace_tags( $text, $data ) {
		$text = (string) $text;

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return preg_replace_callback(
			'/\[([^\]]+)\]/',
			function ( $matches ) use ( $data ) {
				$field_name = trim( $matches[1] );

				if ( isset( $data[ $field_name ] ) ) {
					$value = $data[ $field_name ];

					if ( is_array( $value ) ) {
						$clean = array();

						foreach ( $value as $item ) {
							$clean[] = sanitize_text_field( (string) $item );
						}

						return implode( ', ', $clean );
					}

					return sanitize_textarea_field( (string) $value );
				}

				$special = self::resolve_special_tag( $field_name );

				if ( null !== $special ) {
					return sanitize_textarea_field( $special );
				}

				return '';
			},
			$text
		);
	}

	/**
	 * Resolve a Contact Form 7 special mail-tag.
	 *
	 * Unknown tags return null so replace_tags can treat them as empty.
	 *
	 * @param string $tag Tag name without brackets (may include format:).
	 * @return string|null
	 */
	public static function resolve_special_tag( $tag ) {
		$tag = trim( (string) $tag );

		if ( '' === $tag || '_' !== $tag[0] ) {
			return null;
		}

		$tokens = preg_split( '/\s+/', $tag );
		$name   = strtolower( (string) $tokens[0] );
		$format = '';

		foreach ( array_slice( (array) $tokens, 1 ) as $token ) {
			if ( 0 === stripos( $token, 'format:' ) ) {
				$format = substr( $token, 7 );
			}
		}

		switch ( $name ) {
			case '_site_admin_email':
				return (string) get_option( 'admin_email' );
			case '_site_title':
				return self::site_info( 'name', 'blogname' );
			case '_site_description':
				return self::site_info( 'description', 'blogdescription' );
			case '_site_url':
				if ( function_exists( 'home_url' ) ) {
					return home_url( '/' );
				}

				return self::site_info( 'url', 'home' );
			case '_remote_ip':
				return self::request_ip();
			case '_user_agent':
				return self::request_user_agent();
			case '_url':
				return self::request_url();
			case '_date':
				return self::localized_datetime( '' !== $format ? $format : self::option_or( 'date_format', 'F j, Y' ) );
			case '_time':
				return self::localized_datetime( '' !== $format ? $format : self::option_or( 'time_format', 'g:i a' ) );
			case '_post_id':
				return function_exists( 'get_the_ID' ) ? (string) get_the_ID() : '';
			case '_post_name':
				return function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_name' ) : '';
			case '_post_title':
				return function_exists( 'get_the_title' ) ? (string) get_the_title() : '';
			case '_post_url':
				return function_exists( 'get_permalink' ) ? (string) get_permalink() : '';
			case '_post_author':
				return self::current_post_author_field( 'display_name' );
			case '_post_author_email':
				return self::current_post_author_field( 'user_email' );
			case '_user_login':
				return self::current_user_field( 'user_login' );
			case '_user_email':
				return self::current_user_field( 'user_email' );
			case '_user_display_name':
				return self::current_user_field( 'display_name' );
			case '_user_first_name':
				return self::current_user_field( 'first_name' );
			case '_user_last_name':
				return self::current_user_field( 'last_name' );
			case '_user_id':
				return self::current_user_field( 'ID' );
			default:
				return null;
		}
	}

	/**
	 * Fall back to the site admin email when the resolved recipient is empty or invalid.
	 *
	 * @param string $to Resolved To header.
	 * @return string
	 */
	public static function resolve_recipient( $to ) {
		$to = self::sanitize_header_value( (string) $to );

		if ( self::recipient_is_valid( $to ) ) {
			return $to;
		}

		$fallback = self::sanitize_header_value( (string) get_option( 'admin_email' ) );

		self::debug_log(
			sprintf(
				'Empty or invalid mail recipient "%s"; falling back to admin_email.',
				$to
			)
		);

		return $fallback;
	}

	/**
	 * Whether a To value contains at least one valid email address.
	 *
	 * @param string $to Recipient string.
	 * @return bool
	 */
	public static function recipient_is_valid( $to ) {
		$to = trim( (string) $to );

		if ( '' === $to ) {
			return false;
		}

		$parts = preg_split( '/\s*,\s*/', $to );

		if ( ! is_array( $parts ) ) {
			return false;
		}

		foreach ( $parts as $part ) {
			$email = trim( $part );

			if ( preg_match( '/<([^>]+)>/', $part, $matches ) ) {
				$email = trim( $matches[1] );
			} elseif ( preg_match( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $part, $matches ) ) {
				$email = $matches[0];
			}

			if ( is_email( $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Strip CR/LF from a header value.
	 *
	 * @param string $value Header value.
	 * @return string
	 */
	public static function sanitize_header_value( $value ) {
		$value = str_replace( array( '%0a', '%0d', '%0A', '%0D' ), '', (string) $value );
		$lines = preg_split( '/\r\n|\r|\n/', $value );

		return trim( isset( $lines[0] ) ? $lines[0] : '' );
	}

	/**
	 * Site info helper with get_bloginfo() then get_option() fallback.
	 *
	 * @param string $bloginfo get_bloginfo() show value.
	 * @param string $option   Option name.
	 * @return string
	 */
	private static function site_info( $bloginfo, $option ) {
		if ( function_exists( 'get_bloginfo' ) ) {
			$value = get_bloginfo( $bloginfo );

			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return (string) get_option( $option, '' );
	}

	/**
	 * Option value or a default string.
	 *
	 * @param string $name           Option name.
	 * @param string $default_value  Default.
	 * @return string
	 */
	private static function option_or( $name, $default_value ) {
		$value = get_option( $name, $default_value );

		return ( is_string( $value ) && '' !== $value ) ? $value : $default_value;
	}

	/**
	 * Localized date/time string.
	 *
	 * @param string $format Date format.
	 * @return string
	 */
	private static function localized_datetime( $format ) {
		if ( function_exists( 'date_i18n' ) ) {
			return date_i18n( $format );
		}

		return gmdate( $format );
	}

	/**
	 * Visitor IP for [_remote_ip].
	 *
	 * @return string
	 */
	private static function request_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Visitor user agent for [_user_agent].
	 *
	 * @return string
	 */
	private static function request_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * Page URL for [_url].
	 *
	 * @return string
	 */
	private static function request_url() {
		if ( function_exists( 'wp_get_referer' ) ) {
			$referer = wp_get_referer();

			if ( is_string( $referer ) && '' !== $referer ) {
				return $referer;
			}
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = wp_unslash( $_SERVER['HTTP_REFERER'] );

			if ( function_exists( 'esc_url_raw' ) ) {
				return esc_url_raw( $referer );
			}

			return esc_url( $referer );
		}

		if ( function_exists( 'home_url' ) ) {
			return home_url( '/' );
		}

		return '';
	}

	/**
	 * Current logged-in user field, or empty when not logged in.
	 *
	 * @param string $field User property.
	 * @return string
	 */
	private static function current_user_field( $field ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( ! $user || empty( $user->ID ) ) {
			return '';
		}

		if ( 'ID' === $field ) {
			return (string) $user->ID;
		}

		if ( isset( $user->$field ) ) {
			return (string) $user->$field;
		}

		return '';
	}

	/**
	 * Author field of the current post.
	 *
	 * @param string $field User property.
	 * @return string
	 */
	private static function current_post_author_field( $field ) {
		if ( ! function_exists( 'get_the_author_meta' ) ) {
			return '';
		}

		$value = get_the_author_meta( $field );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Write a debug-only notice.
	 *
	 * @param string $message Message.
	 */
	private static function debug_log( $message ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only empty-recipient fallback.
		error_log( 'Oli Contact Form: ' . $message );
	}
}
