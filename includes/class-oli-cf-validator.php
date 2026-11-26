<?php
/**
 * Validate form submissions.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validator class.
 */
class Oli_CF_Validator {

	/**
	 * Default editable messages.
	 *
	 * @return array<string, string>
	 */
	public static function default_messages() {
		return array(
			'success'          => __( 'Thank you for your message. It has been sent.', 'oli-contact-form' ),
			'send_error'       => __( 'There was an error sending your message. Please try again.', 'oli-contact-form' ),
			'validation_error' => __( 'One or more fields have an error. Please check and try again.', 'oli-contact-form' ),
			'required'         => __( 'This field is required.', 'oli-contact-form' ),
			'email'            => __( 'Please enter a valid email address.', 'oli-contact-form' ),
			'phone'            => __( 'Please enter a valid phone number.', 'oli-contact-form' ),
			'url'              => __( 'Please enter a valid URL.', 'oli-contact-form' ),
			'number'           => __( 'Please enter a valid number.', 'oli-contact-form' ),
			'date'             => __( 'Please enter a valid date.', 'oli-contact-form' ),
			'too_long'         => __( 'This field is too long.', 'oli-contact-form' ),
			'too_short'        => __( 'This field is too short.', 'oli-contact-form' ),
			'acceptance'       => __( 'You must accept this to proceed.', 'oli-contact-form' ),
			'upload_failed'    => __( 'File upload failed.', 'oli-contact-form' ),
			'file_too_large'   => __( 'The uploaded file is too large.', 'oli-contact-form' ),
			'file_type'        => __( 'This file type is not allowed.', 'oli-contact-form' ),
		);
	}

	/**
	 * Previous message keys, including keys used by imported sources.
	 *
	 * @return array<string, string>
	 */
	public static function legacy_message_keys() {
		return array(
			'mail_sent_ok'             => 'success',
			'mail_sent_ng'             => 'send_error',
			'validation_error'         => 'validation_error',
			'invalid_required'         => 'required',
			'invalid_email'            => 'email',
			'invalid_tel'              => 'phone',
			'invalid_url'              => 'url',
			'invalid_number'           => 'number',
			'invalid_date'             => 'date',
			'invalid_too_long'         => 'too_long',
			'invalid_too_short'        => 'too_short',
			'accept_terms'             => 'acceptance',
			'upload_failed'            => 'upload_failed',
			'upload_file_too_large'    => 'file_too_large',
			'upload_file_type'         => 'file_type',
			'upload_file_type_invalid' => 'file_type',
		);
	}

	/**
	 * Merge saved messages with defaults.
	 *
	 * Older keys are copied onto the current keys when the current key is still empty.
	 *
	 * @param mixed $messages Saved messages.
	 * @return array<string, string>
	 */
	public static function normalize_messages( $messages ) {
		$defaults = self::default_messages();

		if ( ! is_array( $messages ) ) {
			return $defaults;
		}

		$incoming = $messages;

		foreach ( self::legacy_message_keys() as $old_key => $new_key ) {
			if ( isset( $messages[ $old_key ] ) && is_string( $messages[ $old_key ] ) && '' !== trim( $messages[ $old_key ] ) ) {
				if ( ! isset( $incoming[ $new_key ] ) || ! is_string( $incoming[ $new_key ] ) || '' === trim( $incoming[ $new_key ] ) ) {
					$incoming[ $new_key ] = $messages[ $old_key ];
				}
			}
		}

		foreach ( $defaults as $key => $default ) {
			if ( isset( $incoming[ $key ] ) && is_string( $incoming[ $key ] ) && '' !== trim( $incoming[ $key ] ) ) {
				$defaults[ $key ] = $incoming[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Validate form data.
	 *
	 * @param array<string, mixed> $data     Posted data.
	 * @param string               $template Form template.
	 * @param array<string, mixed> $files    Uploaded files.
	 * @param array<string, string>|null $messages Editable messages.
	 * @return array<string, string>|true Array of errors or true if valid.
	 */
	public static function validate( $data, $template, $files = array(), $messages = null ) {
		$messages = self::normalize_messages( $messages );
		$errors   = array();

		if ( is_array( $template ) ) {
			$fields = Oli_CF_Fields::to_parser_fields( $template );
		} else {
			$fields = Oli_CF_Form_Parser::parse_fields( $template );
		}

		if ( empty( $fields ) ) {
			return true;
		}

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! is_array( $files ) ) {
			$files = array();
		}

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || 'submit' === $field['type'] ) {
				continue;
			}

			$error = self::validate_field( $field, $data, $files, $messages );

			if ( $error ) {
				$errors[ $field['name'] ] = $error;
			}
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Validate a single parsed field.
	 *
	 * @param array<string, mixed> $field    Field definition.
	 * @param array<string, mixed> $data     Posted data.
	 * @param array<string, mixed> $files    Uploaded files.
	 * @param array<string, string> $messages Messages.
	 * @return string|false Error message or false if valid.
	 */
	public static function validate_field( $field, $data, $files, $messages ) {
		$type     = isset( $field['type'] ) ? $field['type'] : '';
		$name     = isset( $field['name'] ) ? $field['name'] : '';
		$required = ! empty( $field['required'] );
		$value    = isset( $data[ $name ] ) ? $data[ $name ] : '';

		if ( $required ) {
			if ( 'file' === $type ) {
				if ( empty( $files[ $name ] ) || ! Oli_CF_Uploads::file_was_uploaded( $files[ $name ] ) ) {
					return $messages['required'];
				}
			} elseif ( 'checkbox' === $type ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					return $messages['required'];
				}
			} elseif ( 'acceptance' === $type ) {
				if ( empty( $value ) || '1' !== (string) $value ) {
					return $messages['acceptance'];
				}
			} elseif ( '' === $value || false === $value || ( is_array( $value ) && empty( $value ) ) ) {
				if ( '0' !== $value ) {
					return $messages['required'];
				}
			}
		}

		if ( 'file' === $type ) {
			if ( empty( $files[ $name ] ) || ! Oli_CF_Uploads::file_was_uploaded( $files[ $name ] ) ) {
				return false;
			}

			return self::validate_file( $files[ $name ], isset( $field['attributes'] ) ? $field['attributes'] : array(), $messages );
		}

		if ( ( '' === $value || false === $value || ( is_array( $value ) && empty( $value ) ) ) && ! $required ) {
			return false;
		}

		if ( is_array( $value ) ) {
			return false;
		}

		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return $messages['email'];
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return $messages['url'];
				}
				break;

			case 'tel':
			case 'phone':
				if ( ! preg_match( '/^[\d\s\+\-\(\)]+$/', (string) $value ) ) {
					return $messages['phone'];
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return $messages['number'];
				}
				break;

			case 'date':
				$timestamp = strtotime( (string) $value );
				if ( false === $timestamp ) {
					return $messages['date'];
				}
				break;
		}

		return self::validate_length( $value, $field, $messages );
	}

	/**
	 * Apply minlength / maxlength using character length (mb_strlen).
	 *
	 * @param mixed                $value    Posted value.
	 * @param array<string, mixed> $field    Field definition.
	 * @param array<string, string> $messages Messages.
	 * @return string|false
	 */
	public static function validate_length( $value, $field, $messages ) {
		$attrs = isset( $field['attributes'] ) && is_array( $field['attributes'] ) ? $field['attributes'] : array();
		$len   = self::string_length( $value );

		if ( isset( $attrs['maxlength'] ) && is_numeric( $attrs['maxlength'] ) ) {
			$max = (int) $attrs['maxlength'];

			if ( $max > 0 && $len > $max ) {
				return isset( $messages['too_long'] ) ? $messages['too_long'] : __( 'This field is too long.', 'oli-contact-form' );
			}
		}

		if ( isset( $attrs['minlength'] ) && is_numeric( $attrs['minlength'] ) ) {
			$min = (int) $attrs['minlength'];

			if ( $min > 0 && $len < $min ) {
				return isset( $messages['too_short'] ) ? $messages['too_short'] : __( 'This field is too short.', 'oli-contact-form' );
			}
		}

		return false;
	}

	/**
	 * Character length of a submitted string.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function string_length( $value ) {
		$value = (string) $value;

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		return strlen( $value );
	}

	/**
	 * Validate an uploaded file.
	 *
	 * @param array<string, mixed>  $file       File array.
	 * @param array<string, string> $attributes Field attributes.
	 * @param array<string, string> $messages   Messages.
	 * @return string|false
	 */
	public static function validate_file( $file, $attributes, $messages ) {
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			switch ( (int) $file['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					return $messages['file_too_large'];
				default:
					return $messages['upload_failed'];
			}
		}

		$limit = isset( $attributes['limit'] ) && '' !== $attributes['limit'] ? $attributes['limit'] : Oli_CF_Uploads::DEFAULT_LIMIT;
		$types = isset( $attributes['filetypes'] ) && '' !== $attributes['filetypes'] ? $attributes['filetypes'] : Oli_CF_Uploads::DEFAULT_FILETYPES;

		$limit_bytes = Oli_CF_Uploads::parse_size( $limit );
		$size        = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $limit_bytes > 0 && $size > $limit_bytes ) {
			return sprintf(
				/* translators: %s: file size limit */
				__( 'The uploaded file exceeds the maximum allowed size of %s.', 'oli-contact-form' ),
				$limit
			);
		}

		$allowed = Oli_CF_Uploads::parse_filetypes( $types );
		$ext     = strtolower( pathinfo( isset( $file['name'] ) ? $file['name'] : '', PATHINFO_EXTENSION ) );

		if ( in_array( $ext, Oli_CF_Uploads::BLOCKED_EXTENSIONS, true ) ) {
			return $messages['file_type'];
		}

		if ( ! empty( $allowed ) && ! in_array( $ext, $allowed, true ) ) {
			return sprintf(
				/* translators: %s: allowed file types */
				__( 'This file type is not allowed. Allowed types: %s', 'oli-contact-form' ),
				implode( ', ', $allowed )
			);
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' !== $tmp && is_file( $tmp ) && ! Oli_CF_Uploads::is_real_uploaded_file( $tmp ) ) {
			return $messages['upload_failed'];
		}

		if ( '' !== $tmp && is_readable( $tmp ) && Oli_CF_Uploads::extension_expects_image( $ext ) ) {
			$mime = Oli_CF_Uploads::file_mime_type( $tmp );

			if ( '' !== $mime && 0 !== strpos( $mime, 'image/' ) ) {
				return $messages['file_type'];
			}
		}

		if ( '' !== $tmp && is_readable( $tmp ) && function_exists( 'wp_check_filetype_and_ext' ) ) {
			$checked   = wp_check_filetype_and_ext( $tmp, isset( $file['name'] ) ? (string) $file['name'] : '' );
			$real_ext  = ( ! empty( $checked['ext'] ) && is_string( $checked['ext'] ) ) ? strtolower( $checked['ext'] ) : '';
			$real_type = ( ! empty( $checked['type'] ) && is_string( $checked['type'] ) ) ? $checked['type'] : '';

			if ( '' === $real_ext || '' === $real_type || $real_ext !== $ext ) {
				return $messages['file_type'];
			}

			if ( ! empty( $allowed ) && ! in_array( $real_ext, $allowed, true ) ) {
				return $messages['file_type'];
			}
		}

		return false;
	}
}
