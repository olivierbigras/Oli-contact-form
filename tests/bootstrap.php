<?php
/**
 * Minimal WordPress stubs for CLI tests.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
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
		return sys_get_temp_dir();
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
require_once dirname( __DIR__ ) . '/admin/class-oli-cf-importer.php';
