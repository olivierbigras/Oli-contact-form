<?php
/**
 * Minimal WordPress stubs for CLI tests.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/fake-wp-content' );
}

if ( ! defined( 'OLI_CF_PLUGIN_DIR' ) ) {
	define( 'OLI_CF_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'UPLOAD_ERR_OK' ) ) {
	define( 'UPLOAD_ERR_OK', 0 );
}

if ( ! defined( 'UPLOAD_ERR_INI_SIZE' ) ) {
	define( 'UPLOAD_ERR_INI_SIZE', 1 );
}

if ( ! defined( 'UPLOAD_ERR_FORM_SIZE' ) ) {
	define( 'UPLOAD_ERR_FORM_SIZE', 2 );
}

if ( ! defined( 'UPLOAD_ERR_NO_FILE' ) ) {
	define( 'UPLOAD_ERR_NO_FILE', 4 );
}

/**
 * @param mixed $value Value.
 * @return mixed
 */
function oli_cf_test_sanitize_string( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'oli_cf_test_sanitize_string', $value );
	}

	$value = is_string( $value ) ? $value : (string) $value;
	$value = stripslashes( $value );

	return trim( strip_tags( $value ) );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function sanitize_text_field( $value ) {
		return oli_cf_test_sanitize_string( $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function sanitize_textarea_field( $value ) {
		return oli_cf_test_sanitize_string( $value );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * @param string $email Email.
	 * @return string
	 */
	function sanitize_email( $email ) {
		return strtolower( trim( (string) $email ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );

		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * @param string $filename Filename.
	 * @return string
	 */
	function sanitize_file_name( $filename ) {
		return basename( str_replace( array( '\\', "\0" ), '', (string) $filename ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * @param string $class Class.
	 * @return string
	 */
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * @param string $html  HTML.
	 * @param array  $allow Allowed.
	 * @return string
	 */
	function wp_kses( $html, $allow ) {
		unset( $allow );
		return $html;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * @param string $html HTML.
	 * @return string
	 */
	function wp_kses_post( $html ) {
		return $html;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * @param string $email Email.
	 * @return bool
	 */
	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $name    Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		$options = array(
			'admin_email'     => 'admin@example.com',
			'blogname'        => 'Test Site',
			'blogdescription' => 'Just another WordPress site',
			'home'            => 'https://example.com',
			'siteurl'         => 'https://example.com',
			'date_format'     => 'F j, Y',
			'time_format'     => 'g:i a',
		);

		if ( isset( $options[ $name ] ) ) {
			return $options[ $name ];
		}

		return $default;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * @param string $show Show.
	 * @return string
	 */
	function get_bloginfo( $show = '' ) {
		switch ( $show ) {
			case 'name':
				return (string) get_option( 'blogname' );
			case 'description':
				return (string) get_option( 'blogdescription' );
			case 'url':
				return (string) get_option( 'home' );
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		$home = rtrim( (string) get_option( 'home' ), '/' );

		if ( '' === $path || '/' === $path ) {
			return $home . '/';
		}

		return $home . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * @return string
	 */
	function wp_get_referer() {
		return 'https://example.com/contact/';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * @return string
	 */
	function get_the_title() {
		return 'Contact';
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	/**
	 * @return int
	 */
	function get_the_ID() {
		return 42;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * @return string
	 */
	function get_permalink() {
		return 'https://example.com/contact/';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	/**
	 * @param string $field Field.
	 * @return string
	 */
	function get_post_field( $field ) {
		return 'post_name' === $field ? 'contact' : '';
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * @param string $format Format.
	 * @return string
	 */
	function date_i18n( $format ) {
		return gmdate( $format, 1761513600 );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	/**
	 * @return object
	 */
	function wp_get_current_user() {
		return (object) array(
			'ID'           => 0,
			'user_login'   => '',
			'user_email'   => '',
			'display_name' => '',
			'first_name'   => '',
			'last_name'    => '',
		);
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return wp_json_encode_fallback( $data );
	}
}

/**
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_fallback( $data ) {
	$json = json_encode( $data );

	return is_string( $json ) ? $json : '';
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args     Args.
	 * @param mixed $defaults Defaults.
	 * @return array<string, mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( ! is_array( $defaults ) ) {
			$defaults = array();
		}

		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['oli_cf_test_doing_ajax'] );
	}
}

if ( ! function_exists( 'wp_validate_redirect' ) ) {
	/**
	 * @param string $location Location.
	 * @param string $default  Default.
	 * @return string
	 */
	function wp_validate_redirect( $location, $default = '' ) {
		$location = (string) $location;

		if ( '' === $location ) {
			return $default;
		}

		if ( 0 === strpos( $location, 'https://evil.example' ) ) {
			return $default;
		}

		return $location;
	}
}

if ( ! function_exists( 'wp_get_raw_referer' ) ) {
	/**
	 * @return string
	 */
	function wp_get_raw_referer() {
		return isset( $GLOBALS['oli_cf_test_raw_referer'] ) ? (string) $GLOBALS['oli_cf_test_raw_referer'] : '';
	}
}

if ( ! function_exists( 'shortcode_exists' ) ) {
	/**
	 * @param string $tag Tag.
	 * @return bool
	 */
	function shortcode_exists( $tag ) {
		return ! empty( $GLOBALS['oli_cf_test_shortcodes'][ $tag ] );
	}
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
	/**
	 * @param string $field Field.
	 * @return string
	 */
	function get_the_author_meta( $field ) {
		unset( $field );
		return '';
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * @param string $file File path.
	 */
	function wp_delete_file( $file ) {
		if ( is_string( $file ) && file_exists( $file ) ) {
			unlink( $file );
		}
	}
}

if ( ! function_exists( 'get_temp_dir' ) ) {
	/**
	 * @return string
	 */
	function get_temp_dir() {
		if ( isset( $GLOBALS['oli_cf_test_temp_dir'] ) ) {
			return (string) $GLOBALS['oli_cf_test_temp_dir'];
		}

		return sys_get_temp_dir();
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );

		return preg_replace( '#/+#', '/', $path );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * @param string $target Target.
	 * @return bool
	 */
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Args.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $priority, $accepted_args );
		$GLOBALS['oli_cf_test_filters'][ (string) $hook ][] = $callback;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['oli_cf_test_filters'][ (string) $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['oli_cf_test_filters'][ (string) $hook ] as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Args.
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  ...$args Args.
	 */
	function do_action( $hook, ...$args ) {
		if ( empty( $GLOBALS['oli_cf_test_filters'][ (string) $hook ] ) ) {
			return;
		}

		foreach ( $GLOBALS['oli_cf_test_filters'][ (string) $hook ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param int $id ID.
	 * @return object|null
	 */
	function get_post( $id ) {
		$id = (int) $id;

		return isset( $GLOBALS['oli_cf_test_posts'][ $id ] ) ? $GLOBALS['oli_cf_test_posts'][ $id ] : null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $id     ID.
	 * @param string $key    Key.
	 * @param bool   $single Single.
	 * @return mixed
	 */
	function get_post_meta( $id, $key = '', $single = false ) {
		$id = (int) $id;

		if ( isset( $GLOBALS['oli_cf_test_meta'][ $id ][ $key ] ) ) {
			return $GLOBALS['oli_cf_test_meta'][ $id ][ $key ];
		}

		return $single ? '' : array();
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * @param string|string[] $to          To.
	 * @param string          $subject     Subject.
	 * @param string          $message     Message.
	 * @param string|string[] $headers     Headers.
	 * @param string|string[] $attachments Attachments.
	 * @return bool
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		if ( ! empty( $GLOBALS['oli_cf_test_wp_mail_exception'] ) ) {
			throw new RuntimeException( (string) $GLOBALS['oli_cf_test_wp_mail_exception'] );
		}

		do_action( 'phpmailer_init', (object) array() );
		do_action(
			'wp_mail',
			array(
				'to'          => $to,
				'subject'     => $subject,
				'message'     => $message,
				'headers'     => $headers,
				'attachments' => $attachments,
			)
		);

		$GLOBALS['oli_cf_test_wp_mail_calls'][] = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		);

		return empty( $GLOBALS['oli_cf_test_wp_mail_fail'] );
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	/**
	 * @param string $file     File path.
	 * @param string $filename Filename.
	 * @param array  $mimes    Mimes.
	 * @return array<string, mixed>
	 */
	function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
		unset( $mimes );

		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);

		if ( '' === $ext || ! isset( $map[ $ext ] ) ) {
			return array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			);
		}

		$real = '';

		if ( is_readable( $file ) && function_exists( 'finfo_file' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';

			if ( $finfo ) {
				finfo_close( $finfo );
			}
		} elseif ( is_readable( $file ) && function_exists( 'mime_content_type' ) ) {
			$real = (string) mime_content_type( $file );
		}

		$expected = $map[ $ext ];

		if ( '' !== $real ) {
			$real = strtolower( strtok( $real, ';' ) );

			if ( 'txt' === $ext && ( 0 === strpos( $real, 'text/' ) || 'inode/x-empty' === $real || 'application/x-empty' === $real ) ) {
				return array(
					'ext'             => $ext,
					'type'            => 'text/plain',
					'proper_filename' => false,
				);
			}

			$aliases = array(
				'image/jpeg' => array( 'image/jpeg', 'image/jpg', 'image/pjpeg' ),
			);
			$ok      = ( $real === $expected ) || ( isset( $aliases[ $expected ] ) && in_array( $real, $aliases[ $expected ], true ) );

			if ( ! $ok ) {
				return array(
					'ext'             => false,
					'type'            => false,
					'proper_filename' => false,
				);
			}
		}

		return array(
			'ext'             => $ext,
			'type'            => $expected,
			'proper_filename' => false,
		);
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return false
	 */
	function wp_next_scheduled( $hook ) {
		unset( $hook );
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * @param int    $timestamp  Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook       Hook.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		unset( $timestamp, $recurrence, $hook );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * @param string $hook Hook.
	 */
	function wp_clear_scheduled_hook( $hook ) {
		unset( $hook );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * @param string $single Single.
	 * @param string $plural Plural.
	 * @param int    $number Number.
	 * @return string
	 */
	function _n( $single, $plural, $number, $domain = null ) {
		unset( $domain );
		return ( 1 === (int) $number ) ? $single : $plural;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		if ( ! isset( $GLOBALS['oli_cf_test_transients'] ) || ! is_array( $GLOBALS['oli_cf_test_transients'] ) ) {
			$GLOBALS['oli_cf_test_transients'] = array();
		}

		$GLOBALS['oli_cf_test_transients'][ (string) $key ] = array(
			'value' => $value,
			'ttl'   => (int) $ttl,
		);

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['oli_cf_test_transients'][ (string) $key ] ) ) {
			return false;
		}

		return $GLOBALS['oli_cf_test_transients'][ (string) $key ]['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		if ( isset( $GLOBALS['oli_cf_test_transients'][ (string) $key ] ) ) {
			unset( $GLOBALS['oli_cf_test_transients'][ (string) $key ] );
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * @param int  $length              Length.
	 * @param bool $special_chars       Special.
	 * @param bool $extra_special_chars Extra.
	 * @return string
	 */
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $special_chars, $extra_special_chars );
		return substr( bin2hex( random_bytes( 16 ) ), 0, (int) $length );
	}
}

$oli_cf_failures = 0;

/**
 * Assert two values are identical.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $label    Label.
 */
function oli_cf_assert_same( $expected, $actual, $label ) {
	global $oli_cf_failures;

	if ( $expected !== $actual ) {
		++$oli_cf_failures;
		fwrite( STDERR, "FAIL {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
		return;
	}

	echo "OK   {$label}\n";
}

/**
 * Assert a value is true-ish.
 *
 * @param mixed  $actual Actual.
 * @param string $label  Label.
 */
function oli_cf_assert_true( $actual, $label ) {
	oli_cf_assert_same( true, (bool) $actual, $label );
}

/**
 * Assert a value is false.
 *
 * @param mixed  $actual Actual.
 * @param string $label  Label.
 */
function oli_cf_assert_false( $actual, $label ) {
	oli_cf_assert_same( false, (bool) $actual, $label );
}

require_once dirname( __DIR__ ) . '/includes/class-oli-cf-form-parser.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-cf-uploads.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-cf-validator.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-cf-mailer.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-cf-anti-spam.php';
require_once dirname( __DIR__ ) . '/includes/class-oli-cf-submission.php';
require_once dirname( __DIR__ ) . '/admin/class-oli-cf-importer.php';
require_once dirname( __DIR__ ) . '/admin/class-oli-cf-settings.php';
require_once dirname( __DIR__ ) . '/public/class-oli-cf-public.php';
require_once dirname( __DIR__ ) . '/public/class-oli-cf-shortcode.php';
