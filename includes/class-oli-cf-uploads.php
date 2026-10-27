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
	 * Cron hook that deletes leftover staging older than one hour.
	 *
	 * @var string
	 */
	const PURGE_CRON_HOOK = 'oli_cf_purge_stale_uploads';

	/**
	 * Age after which leftover staging is deleted (seconds).
	 *
	 * @var int
	 */
	const STALE_MAX_AGE = 3600;

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
	 * Staging directories still owned by this request.
	 *
	 * @var string[]
	 */
	private static $pending_dirs = array();

	/**
	 * Staged files still owned by this request.
	 *
	 * @var string[]
	 */
	private static $pending_files = array();

	/**
	 * Whether the request shutdown cleaner is registered.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

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
	 * True when $path is a real PHP upload (or a test override).
	 *
	 * @param string $path Temporary path.
	 * @return bool
	 */
	public static function is_real_uploaded_file( $path ) {
		$path = (string) $path;

		if ( '' === $path || ! is_file( $path ) ) {
			return false;
		}

		if ( function_exists( 'apply_filters' ) ) {
			$override = apply_filters( 'oli_cf_is_uploaded_file', null, $path );

			if ( null !== $override ) {
				return (bool) $override;
			}
		}

		return is_uploaded_file( $path );
	}

	/**
	 * Parent directory for per-submission staging folders.
	 *
	 * Prefers an off-web temp directory. Never writes directly into
	 * get_temp_dir() when that path is wp-content/. Falls back to the
	 * plugin tmp/ folder, which is denied by .htaccess and index.php.
	 *
	 * @return string
	 */
	public static function staging_parent() {
		$sys = self::normalize_dir( sys_get_temp_dir() );

		if ( self::is_off_web_writable( $sys ) ) {
			return $sys;
		}

		$wp_tmp = function_exists( 'get_temp_dir' ) ? self::normalize_dir( get_temp_dir() ) : '';

		if ( $wp_tmp && $wp_tmp !== $sys && self::is_off_web_writable( $wp_tmp ) ) {
			return $wp_tmp;
		}

		$parent = self::plugin_tmp_dir();
		self::ensure_directory( $parent );
		self::protect_directory( $parent );

		return $parent;
	}

	/**
	 * Plugin-owned protected temp directory.
	 *
	 * @return string
	 */
	public static function plugin_tmp_dir() {
		$base = defined( 'OLI_CF_PLUGIN_DIR' ) ? OLI_CF_PLUGIN_DIR : dirname( __DIR__ ) . '/';

		return self::normalize_dir( $base . 'tmp' );
	}

	/**
	 * Create a private random staging directory for one submission.
	 *
	 * @return string Absolute path or empty string.
	 */
	public static function create_staging_directory() {
		$parent = self::staging_parent();

		if ( '' === $parent ) {
			return '';
		}

		self::ensure_directory( $parent );

		if ( self::is_inside_web_root( $parent ) ) {
			self::protect_directory( $parent );
		}

		for ( $i = 0; $i < 5; $i++ ) {
			$dir = $parent . '/oli-cf-' . self::unique_suffix();

			if ( file_exists( $dir ) ) {
				continue;
			}

			if ( ! self::ensure_directory( $dir ) ) {
				continue;
			}

			if ( self::is_inside_web_root( $dir ) ) {
				self::protect_directory( $dir );
			}

			return $dir;
		}

		return '';
	}

	/**
	 * Copy an uploaded file into a unique temp path with a safe name.
	 *
	 * Plugin Check forbids move_uploaded_file(). After is_uploaded_file()
	 * we copy into the private dir and delete the PHP tmp (same outcome).
	 *
	 * @param array<string, mixed> $file     File array from $_FILES.
	 * @param string               $dest_dir Optional staging directory.
	 * @return string Absolute path or empty string.
	 */
	public static function stage_file( $file, $dest_dir = '' ) {
		if ( ! self::file_was_uploaded( $file ) || empty( $file['tmp_name'] ) ) {
			return '';
		}

		$tmp = (string) $file['tmp_name'];

		if ( ! self::is_real_uploaded_file( $tmp ) ) {
			return '';
		}

		$original = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'upload';

		if ( function_exists( 'wp_check_filetype_and_ext' ) ) {
			$checked = wp_check_filetype_and_ext( $tmp, $original );

			if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
				return '';
			}
		}

		if ( '' === $original ) {
			$original = 'upload';
		}

		if ( '' === $dest_dir ) {
			$dest_dir = self::create_staging_directory();
		}

		$dest_dir = self::normalize_dir( $dest_dir );

		if ( '' === $dest_dir || ! is_dir( $dest_dir ) ) {
			return '';
		}

		$dest = $dest_dir . '/' . $original;

		if ( file_exists( $dest ) ) {
			$dest = $dest_dir . '/' . self::unique_suffix() . '-' . $original;
		}

		if ( ! self::relocate_uploaded_file( $tmp, $dest ) ) {
			return '';
		}

		return $dest;
	}

	/**
	 * Stage every uploaded file for the given field list.
	 *
	 * @param array<string, mixed>             $files    $_FILES-like array.
	 * @param array<int, array<string, mixed>> $fields   Parsed fields.
	 * @param string                           $dest_dir Optional staging directory.
	 * @return string[] Paths keyed by field name.
	 */
	public static function stage_files( $files, $fields, $dest_dir = '' ) {
		$staged = array();

		if ( ! is_array( $files ) ) {
			return $staged;
		}

		if ( '' === $dest_dir ) {
			$dest_dir = self::create_staging_directory();
		}

		foreach ( $fields as $field ) {
			if ( empty( $field['type'] ) || 'file' !== $field['type'] || empty( $field['name'] ) ) {
				continue;
			}

			$name = $field['name'];

			if ( empty( $files[ $name ] ) || ! self::file_was_uploaded( $files[ $name ] ) ) {
				continue;
			}

			$path = self::stage_file( $files[ $name ], $dest_dir );

			if ( '' !== $path ) {
				$staged[ $name ] = $path;
			}
		}

		return $staged;
	}

	/**
	 * Remember paths so a fatal or exit still deletes them.
	 *
	 * @param string   $dir   Staging directory.
	 * @param string[] $paths Staged files.
	 */
	public static function track_for_shutdown( $dir, $paths = array() ) {
		$dir = (string) $dir;

		if ( '' !== $dir ) {
			self::$pending_dirs[] = $dir;
			self::$pending_dirs   = array_values( array_unique( self::$pending_dirs ) );
		}

		if ( is_array( $paths ) ) {
			foreach ( $paths as $path ) {
				$path = (string) $path;

				if ( '' !== $path ) {
					self::$pending_files[] = $path;
				}
			}
		}

		if ( ! self::$shutdown_registered ) {
			register_shutdown_function( array( __CLASS__, 'shutdown_cleanup' ) );
			self::$shutdown_registered = true;
		}
	}

	/**
	 * Delete anything still tracked for this request.
	 */
	public static function shutdown_cleanup() {
		self::cleanup_submission( '', self::$pending_files );

		foreach ( self::$pending_dirs as $dir ) {
			self::delete_staging_directory( $dir );
		}

		self::$pending_files = array();
		self::$pending_dirs  = array();
	}

	/**
	 * Delete staged files and their directory.
	 *
	 * @param string   $dir   Staging directory.
	 * @param string[] $paths Staged files.
	 */
	public static function cleanup_submission( $dir, $paths ) {
		self::delete_files( is_array( $paths ) ? $paths : array() );
		self::delete_staging_directory( $dir );

		if ( is_array( $paths ) ) {
			self::$pending_files = array_values( array_diff( self::$pending_files, $paths ) );
		}

		if ( '' !== (string) $dir ) {
			self::$pending_dirs = array_values( array_diff( self::$pending_dirs, array( (string) $dir ) ) );
		}
	}

	/**
	 * Delete leftover oli-cf-* staging older than one hour.
	 *
	 * @param int $max_age Seconds. Defaults to STALE_MAX_AGE.
	 * @return int Number of paths removed.
	 */
	public static function purge_stale_staging( $max_age = null ) {
		$max_age = null === $max_age ? self::STALE_MAX_AGE : (int) $max_age;
		$removed = 0;
		$now     = time();

		foreach ( self::staging_scan_roots() as $root ) {
			if ( '' === $root || ! is_dir( $root ) ) {
				continue;
			}

			$matches = glob( $root . '/oli-cf-*' );

			if ( ! is_array( $matches ) ) {
				continue;
			}

			foreach ( $matches as $path ) {
				$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- leftover may vanish between glob and stat.

				if ( false === $mtime || ( $now - (int) $mtime ) < $max_age ) {
					continue;
				}

				if ( is_dir( $path ) ) {
					if ( self::delete_staging_directory( $path ) ) {
						++$removed;
					}
				} elseif ( self::delete_file( $path ) ) {
					++$removed;
				}
			}
		}

		return $removed;
	}

	/**
	 * Schedule the hourly leftover purge if it is not already scheduled.
	 */
	public static function maybe_schedule_purge() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( wp_next_scheduled( self::PURGE_CRON_HOOK ) ) {
			return;
		}

		$delay = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : self::STALE_MAX_AGE;
		wp_schedule_event( time() + $delay, 'hourly', self::PURGE_CRON_HOOK );
	}

	/**
	 * Unschedule the leftover purge.
	 */
	public static function clear_scheduled_purge() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::PURGE_CRON_HOOK );
		}
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
	 * Recursively delete a managed oli-cf-* staging directory.
	 *
	 * @param string $dir Directory.
	 * @return bool True when the directory is gone (or was empty/missing).
	 */
	public static function delete_staging_directory( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir || ! is_dir( $dir ) ) {
			return true;
		}

		if ( ! self::is_managed_staging_path( $dir ) ) {
			return false;
		}

		$items = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- directory may disappear during cleanup.

		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}

				$path = $dir . '/' . $item;

				if ( is_dir( $path ) ) {
					self::delete_staging_directory( $path );
				} else {
					self::delete_file( $path );
				}
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- private staging dir may already be gone.

		return ! is_dir( $dir );
	}

	/**
	 * Roots that may hold leftover oli-cf-* files or directories.
	 *
	 * @return string[]
	 */
	private static function staging_scan_roots() {
		$roots = array();
		$sys   = self::normalize_dir( sys_get_temp_dir() );

		if ( '' !== $sys ) {
			$roots[] = $sys;
		}

		if ( function_exists( 'get_temp_dir' ) ) {
			$wp_tmp = self::normalize_dir( get_temp_dir() );

			if ( '' !== $wp_tmp && $wp_tmp !== $sys && ! self::is_wp_content_dir( $wp_tmp ) ) {
				$roots[] = $wp_tmp;
			}
		}

		$plugin_tmp = self::plugin_tmp_dir();

		if ( is_dir( $plugin_tmp ) ) {
			$roots[] = $plugin_tmp;
		}

		return array_values( array_unique( $roots ) );
	}

	/**
	 * Move a verified PHP upload into the private staging path.
	 *
	 * @param string $tmp  Uploaded tmp_name.
	 * @param string $dest Destination path.
	 * @return bool
	 */
	private static function relocate_uploaded_file( $tmp, $dest ) {
		if ( ! self::is_real_uploaded_file( $tmp ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Plugin Check forbids move_uploaded_file(); copy after is_uploaded_file() then delete the PHP tmp.
		$copied = copy( $tmp, $dest );

		if ( ! $copied || ! file_exists( $dest ) ) {
			return false;
		}

		self::delete_file( $tmp );

		return true;
	}

	/**
	 * Whether this path is an oli-cf staging file or directory we created.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private static function is_managed_staging_path( $path ) {
		$base = basename( self::normalize_dir( $path ) );

		if ( ! preg_match( '/^oli-cf-[A-Za-z0-9_-]+$/', $base ) && 0 !== strpos( $base, 'oli-cf-' ) ) {
			return false;
		}

		$parent = self::normalize_dir( dirname( $path ) );
		$roots  = self::staging_scan_roots();

		return in_array( $parent, $roots, true );
	}

	/**
	 * Writable directory that is not under the web root or wp-content.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function is_off_web_writable( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- off-web temp dir, not a media path.
			return false;
		}

		return ! self::is_inside_web_root( $dir ) && ! self::is_wp_content_dir( $dir );
	}

	/**
	 * Whether $dir is WP_CONTENT_DIR (get_temp_dir() fallback).
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function is_wp_content_dir( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir || ! defined( 'WP_CONTENT_DIR' ) ) {
			return false;
		}

		$content = self::normalize_dir( WP_CONTENT_DIR );

		return '' !== $content && ( $dir === $content || 0 === strpos( $dir . '/', $content . '/' ) );
	}

	/**
	 * Whether $dir lives under ABSPATH.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function is_inside_web_root( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir || ! defined( 'ABSPATH' ) ) {
			return false;
		}

		$abs = self::normalize_dir( ABSPATH );

		if ( '' === $abs ) {
			return false;
		}

		return $dir === $abs || 0 === strpos( $dir . '/', $abs . '/' );
	}

	/**
	 * Create a directory if needed.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function ensure_directory( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir ) {
			return false;
		}

		if ( is_dir( $dir ) ) {
			return true;
		}

		if ( function_exists( 'wp_mkdir_p' ) ) {
			return (bool) wp_mkdir_p( $dir );
		}

		return @mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- private staging fallback when wp_mkdir_p() is absent.
	}

	/**
	 * Write Apache deny rules and a silent index.php.
	 *
	 * @param string $dir Directory.
	 */
	private static function protect_directory( $dir ) {
		$dir = self::normalize_dir( $dir );

		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$index = $dir . '/index.php';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- one-time deny stub, not user content.
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Apache deny rules for a private staging dir.
			file_put_contents( $htaccess, self::deny_htaccess() );
		}
	}

	/**
	 * Apache 2.2 + 2.4 deny-all rules.
	 *
	 * @return string
	 */
	private static function deny_htaccess() {
		return "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
	}

	/**
	 * Normalize a directory path (no trailing slash).
	 *
	 * @param string $dir Directory.
	 * @return string
	 */
	private static function normalize_dir( $dir ) {
		$dir = (string) $dir;

		if ( '' === $dir ) {
			return '';
		}

		if ( function_exists( 'wp_normalize_path' ) ) {
			$dir = wp_normalize_path( $dir );
		} else {
			$dir = str_replace( '\\', '/', $dir );
		}

		return rtrim( $dir, '/\\' );
	}

	/**
	 * Unique suffix for staged filenames and directories.
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
