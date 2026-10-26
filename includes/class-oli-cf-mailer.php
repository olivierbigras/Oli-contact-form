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

		$main = self::build_mail( $mail_settings, $data, $attachments );

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
	 * Replace [field] tags with submitted values.
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

				return '';
			},
			$text
		);
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
}
