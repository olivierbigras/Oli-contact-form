<?php
/**
 * Read-only lookups for import sources.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads posts and custom tables. Nothing here writes to those tables.
 */
class Oli_CF_Import_Reader {

	/**
	 * Table suffixes this reader is willing to query.
	 *
	 * @return string[]
	 */
	public static function tables() {
		return array(
			'gf_form',
			'gf_form_meta',
			'nf3_forms',
			'nf3_fields',
			'nf3_field_meta',
			'nf3_actions',
			'nf3_action_meta',
			'fluentform_forms',
			'fluentform_form_meta',
			'frm_forms',
			'frm_fields',
		);
	}

	/**
	 * Whether a whitelisted table has at least one row.
	 *
	 * @param string $suffix Table suffix without the WordPress prefix.
	 * @return bool
	 */
	public static function table_has_rows( $suffix ) {
		return count( self::select_all( $suffix ) ) > 0;
	}

	/**
	 * Every row of a whitelisted table.
	 *
	 * @param string $suffix Table suffix.
	 * @return array<int, array<string, mixed>>
	 */
	public static function select_all( $suffix ) {
		$fixtures = self::fixtures();

		if ( null !== $fixtures ) {
			return isset( $fixtures[ $suffix ] ) && is_array( $fixtures[ $suffix ] ) ? $fixtures[ $suffix ] : array();
		}

		$table = self::prefixed_table( $suffix );

		if ( '' === $table ) {
			return array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only import lookup; the table name is an internal whitelist passed through %i.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Rows whose form_id column matches.
	 *
	 * @param string $suffix  Table suffix.
	 * @param int    $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function select_by_form_id( $suffix, $form_id ) {
		return self::select_by_int_column( $suffix, 'form_id', $form_id );
	}

	/**
	 * Rows whose parent_id column matches.
	 *
	 * @param string $suffix    Table suffix.
	 * @param int    $parent_id Parent ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function select_by_parent_id( $suffix, $parent_id ) {
		return self::select_by_int_column( $suffix, 'parent_id', $parent_id );
	}

	/**
	 * Posts of one type, as associative rows.
	 *
	 * @param string $post_type Post type.
	 * @return array<int, array<string, mixed>>
	 */
	public static function posts_of_type( $post_type ) {
		if ( isset( $GLOBALS['oli_cf_test_import_db']['posts'] ) && is_array( $GLOBALS['oli_cf_test_import_db']['posts'] ) ) {
			$rows = isset( $GLOBALS['oli_cf_test_import_db']['posts'][ $post_type ] ) ? $GLOBALS['oli_cf_test_import_db']['posts'][ $post_type ] : array();

			return is_array( $rows ) ? $rows : array();
		}

		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
			)
		);
		$rows  = array();

		foreach ( (array) $posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$rows[] = array(
				'ID'           => (int) $post->ID,
				'post_title'   => isset( $post->post_title ) ? (string) $post->post_title : '',
				'post_content' => isset( $post->post_content ) ? (string) $post->post_content : '',
				'post_status'  => isset( $post->post_status ) ? (string) $post->post_status : '',
			);
		}

		return $rows;
	}

	/**
	 * One post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return mixed
	 */
	public static function meta( $post_id, $key ) {
		if ( isset( $GLOBALS['oli_cf_test_import_db']['meta'][ $post_id ] ) && is_array( $GLOBALS['oli_cf_test_import_db']['meta'][ $post_id ] ) && array_key_exists( $key, $GLOBALS['oli_cf_test_import_db']['meta'][ $post_id ] ) ) {
			return $GLOBALS['oli_cf_test_import_db']['meta'][ $post_id ][ $key ];
		}

		if ( function_exists( 'get_post_meta' ) ) {
			return get_post_meta( (int) $post_id, $key, true );
		}

		return '';
	}

	/**
	 * Post IDs that have a meta key.
	 *
	 * @param string $meta_key Meta key.
	 * @return int[]
	 */
	public static function post_ids_with_meta( $meta_key ) {
		$meta_key = (string) $meta_key;

		if ( ! isset( $GLOBALS['wpdb'] ) && isset( $GLOBALS['oli_cf_test_meta'] ) && is_array( $GLOBALS['oli_cf_test_meta'] ) ) {
			$ids = array();

			foreach ( $GLOBALS['oli_cf_test_meta'] as $id => $meta ) {
				if ( is_array( $meta ) && array_key_exists( $meta_key, $meta ) ) {
					$ids[] = (int) $id;
				}
			}

			return $ids;
		}

		global $wpdb;

		if ( ! isset( $wpdb->postmeta ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time read of a legacy meta key during upgrade.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM %i WHERE meta_key = %s', $wpdb->postmeta, $meta_key ) );

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Rows matching an integer column that is part of the whitelist.
	 *
	 * @param string $suffix Table suffix.
	 * @param string $column Column name.
	 * @param int    $value  Value.
	 * @return array<int, array<string, mixed>>
	 */
	private static function select_by_int_column( $suffix, $column, $value ) {
		$value = (int) $value;

		if ( null !== self::fixtures() ) {
			$matched = array();

			foreach ( self::select_all( $suffix ) as $row ) {
				if ( isset( $row[ $column ] ) && (int) $row[ $column ] === $value ) {
					$matched[] = $row;
				}
			}

			return $matched;
		}

		$table = self::prefixed_table( $suffix );

		if ( '' === $table || ! in_array( $column, array( 'form_id', 'parent_id' ), true ) ) {
			return array();
		}

		global $wpdb;

		if ( 'form_id' === $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only import lookup.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE form_id = %d', $table, $value ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only import lookup.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE parent_id = %d', $table, $value ), ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Prefixed table name, or an empty string when the suffix is not allowed.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	private static function prefixed_table( $suffix ) {
		if ( ! in_array( $suffix, self::tables(), true ) ) {
			return '';
		}

		global $wpdb;

		if ( ! isset( $wpdb->prefix ) ) {
			return '';
		}

		return $wpdb->prefix . $suffix;
	}

	/**
	 * Fixture tables, when a test has installed them.
	 *
	 * @return array<string, array<int, array<string, mixed>>>|null
	 */
	private static function fixtures() {
		if ( isset( $GLOBALS['oli_cf_test_import_db']['tables'] ) && is_array( $GLOBALS['oli_cf_test_import_db']['tables'] ) ) {
			return $GLOBALS['oli_cf_test_import_db']['tables'];
		}

		return null;
	}
}
