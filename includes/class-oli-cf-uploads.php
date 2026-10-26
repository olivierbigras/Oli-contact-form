<?php
/**
 * Temporary file uploads. Files are attached to email then deleted.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upload helper: stage, attach, delete. Never keep files.
 */
class Oli_CF_Uploads {

	/**
	 * Default size limit used when a file tag has no limit: option.
	 *
	 * @var string
	 */
	const DEFAULT_LIMIT = '5mb';

	/**
	 * Default extensions when a file tag has no filetypes: option.
	 *
	 * @var string
	 */
	const DEFAULT_FILETYPES = 'jpg|jpeg|png|gif|webp|pdf|doc|docx|txt';

	/**
	 * Extensions that must never be accepted.
	 *
	 * @var string[]
	 */
	const BLOCKED_EXTENSIONS = array(
		'php',
		'php3',
		'php4',
		'php5',
		'phtml',
		'phar',
		'exe',
		'js',
		'html',
		'htm',
		'shtml',
		'htaccess',
		'sh',
		'bat',
		'cmd',
		'com',
		'cgi',
		'pl',
		'py',
		'svg',
	);

	/**
	 * Parse a size string such as "2mb" into bytes.
	 *
	 * @param string $size Size string.
	 * @return int
	 */
	public static function parse_size( $size ) {
		$size  = strtolower( trim( (string) $size ) );
		$value = (int) $size;

		if ( $value < 0 ) {
			$value = 0;
		}

		if ( false !== strpos( $size, 'gb' ) ) {
			return $value * 1024 * 1024 * 1024;
		}

		if ( false !== strpos( $size, 'mb' ) ) {
			return $value * 1024 * 1024;
		}

		if ( false !== strpos( $size, 'kb' ) ) {
			return $value * 1024;
		}

		return $value;
	}

	/**
	 * Split a filetypes option into lowercase extensions.
	 *
	 * @param string $filetypes Pipe-separated extensions.
	 * @return string[]
	 */
	public static function parse_filetypes( $filetypes ) {
		$parts = explode( '|', strtolower( (string) $filetypes ) );
		$out   = array();

		foreach ( $parts as $part ) {
			$part = ltrim( trim( $part ), '.' );
			$part = preg_replace( '/[^a-z0-9]/', '', $part );

			if ( '' === $part ) {
				continue;
			}

			if ( in_array( $part, self::BLOCKED_EXTENSIONS, true ) ) {
				continue;
			}

			$out[] = $part;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * HTML accept attribute from a filetypes option.
	 *
	 * @param string $filetypes Pipe-separated extensions.
	 * @return string
	 */
	public static function accept_attribute( $filetypes ) {
		$exts = self::parse_filetypes( $filetypes );

		if ( empty( $exts ) ) {
			return '';
		}

		$accept = array();

		foreach ( $exts as $ext ) {
			$accept[] = '.' . $ext;
		}

		return implode( ',', $accept );
	}

	/**
	 * Whether an uploaded file array looks present.
	 *
	 * @param array<string, mixed> $file File array from $_FILES.
	 * @return bool
	 */
	public static function file_was_uploaded( $file ) {
		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			return false;
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Copy an uploaded file into a unique temp path with a safe name.
	 *
	 * @param array<string, mixed> $file File array from $_FILES.
	 * @return string Absolute path or empty string.
	 */
	public static function stage_file( $file ) {
		if ( ! self::file_was_uploaded( $file ) || empty( $file['tmp_name'] ) ) {
			return '';
		}

		$tmp = $file['tmp_name'];

		if ( ! is_readable( $tmp ) ) {
			return '';
		}

		$original = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'upload';

		if ( '' === $original ) {
			$original = 'upload';
		}

		$dest_dir = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$dest_dir = rtrim( (string) $dest_dir, '/\\' ) . DIRECTORY_SEPARATOR;
		$dest     = $dest_dir . 'oli-cf-' . self::unique_suffix() . '-' . $original;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- staging a short-lived attachment copy before wp_mail(); Plugin Check forbids move_uploaded_file().
		$copied = copy( $tmp, $dest );

		if ( ! $copied || ! file_exists( $dest ) ) {
			return '';
		}

		return $dest;
	}

	/**
	 * Stage every uploaded file for the given field list.
	 *
	 * @param array<string, mixed>         $files  $_FILES-like array.
	 * @param array<int, array<string, mixed>> $fields Parsed fields.
	 * @return string[] Paths keyed by field name.
	 */
	public static function stage_files( $files, $fields ) {
		$staged = array();

		if ( ! is_array( $files ) ) {
			return $staged;
		}

		foreach ( $fields as $field ) {
			if ( empty( $field['type'] ) || 'file' !== $field['type'] || empty( $field['name'] ) ) {
				continue;
			}

			$name = $field['name'];

			if ( empty( $files[ $name ] ) || ! self::file_was_uploaded( $files[ $name ] ) ) {
				continue;
			}

			$path = self::stage_file( $files[ $name ] );

			if ( '' !== $path ) {
				$staged[ $name ] = $path;
			}
		}

		return $staged;
	}

	/**
	 * Delete one file if it still exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when the file is gone.
	 */
	public static function delete_file( $path ) {
		$path = (string) $path;

		if ( '' === $path ) {
			return true;
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- fallback when wp_delete_file() is unavailable (unit tests).
			unlink( $path );
		}

		return ! file_exists( $path );
	}

	/**
	 * Delete every staged path. Always safe to call.
	 *
	 * @param string[] $paths Paths.
	 * @return int Number of paths that no longer exist.
	 */
	public static function delete_files( $paths ) {
		$gone = 0;

		if ( ! is_array( $paths ) ) {
			return 0;
		}

		foreach ( $paths as $path ) {
			if ( self::delete_file( $path ) ) {
				++$gone;
			}
		}

		return $gone;
	}

	/**
	 * Unique suffix for staged filenames.
	 *
	 * @return string
	 */
	private static function unique_suffix() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 12, false, false );
		}

		try {
			return bin2hex( random_bytes( 6 ) );
		} catch ( Exception $e ) {
			return (string) mt_rand( 100000, 999999 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- fallback when random_bytes() is unavailable.
		}
	}
}
