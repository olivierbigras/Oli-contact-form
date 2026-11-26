<?php
/**
 * JSON stored in post meta.
 *
 * update_post_meta() runs wp_unslash() before writing. JSON escapes
 * (\n, \", \u2014) must be slashed first or they are destroyed.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and write a JSON blob in post meta.
 */
class Oli_CF_Json_Meta {

	/**
	 * Store a JSON string so that wp_unslash() restores it unchanged.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $json     JSON text from wp_json_encode().
	 */
	public static function write( $post_id, $meta_key, $json ) {
		$json = is_string( $json ) ? $json : '[]';

		update_post_meta( (int) $post_id, (string) $meta_key, wp_slash( $json ) );
	}

	/**
	 * JSON text currently stored for a post, or an empty string.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return string
	 */
	public static function read( $post_id, $meta_key ) {
		$stored = get_post_meta( (int) $post_id, (string) $meta_key, true );

		return is_string( $stored ) ? $stored : '';
	}
}
