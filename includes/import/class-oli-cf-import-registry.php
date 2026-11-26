<?php
/**
 * The import sources this plugin knows about.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of import sources.
 */
class Oli_CF_Import_Registry {

	/**
	 * Load every source class.
	 */
	public static function load() {
		$dir = defined( 'OLI_CF_PLUGIN_DIR' ) ? OLI_CF_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';

		require_once $dir . 'includes/import/interface-oli-cf-import-adapter.php';
		require_once $dir . 'includes/import/class-oli-cf-import-reader.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-tagged.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-gravity.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-wpforms.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-ninja.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-fluent.php';
		require_once $dir . 'includes/import/class-oli-cf-import-source-formidable.php';
	}

	/**
	 * Every source, in a stable order.
	 *
	 * @return Oli_CF_Import_Source[]
	 */
	public static function sources() {
		self::load();

		return array(
			new Oli_CF_Import_Source_Tagged(),
			new Oli_CF_Import_Source_Gravity(),
			new Oli_CF_Import_Source_Wpforms(),
			new Oli_CF_Import_Source_Ninja(),
			new Oli_CF_Import_Source_Fluent(),
			new Oli_CF_Import_Source_Formidable(),
		);
	}

	/**
	 * One source by key.
	 *
	 * @param string $key Source key.
	 * @return Oli_CF_Import_Source|null
	 */
	public static function get( $key ) {
		foreach ( self::sources() as $source ) {
			if ( $source->key() === (string) $key ) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * Sources that are active or still have saved forms.
	 *
	 * @return Oli_CF_Import_Source[]
	 */
	public static function available() {
		$available = array();

		foreach ( self::sources() as $source ) {
			if ( $source->is_available() ) {
				$available[] = $source;
			}
		}

		return $available;
	}

	/**
	 * The source that owns the legacy shortcode, when one is loaded.
	 *
	 * @return bool
	 */
	/**
	 * The source whose shortcode name we can keep serving after import.
	 *
	 * @return Oli_CF_Import_Source|null
	 */
	public static function legacy_shortcode_source() {
		foreach ( self::sources() as $source ) {
			if ( $source->owns_legacy_shortcode() ) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * The source that owns the legacy shortcode, when one is loaded.
	 *
	 * @return bool
	 */
	public static function legacy_shortcode_blocked() {
		foreach ( self::sources() as $source ) {
			if ( $source->owns_legacy_shortcode() && $source->is_active() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Previous page slug, when a source still has one.
	 *
	 * @return string
	 */
	public static function legacy_page_slug() {
		foreach ( self::sources() as $source ) {
			if ( '' !== $source->legacy_page_slug() ) {
				return $source->legacy_page_slug();
			}
		}

		return '';
	}

	/**
	 * Previous admin-post action.
	 *
	 * @return string
	 */
	public static function legacy_admin_action() {
		foreach ( self::sources() as $source ) {
			if ( '' !== $source->legacy_admin_action() ) {
				return $source->legacy_admin_action();
			}
		}

		return '';
	}

	/**
	 * Previous screen IDs.
	 *
	 * @return string[]
	 */
	public static function legacy_screen_ids() {
		$ids = array();

		foreach ( self::sources() as $source ) {
			$ids = array_merge( $ids, $source->legacy_screen_ids() );
		}

		return $ids;
	}

	/**
	 * Previous option key.
	 *
	 * @return string
	 */
	public static function legacy_option_key() {
		foreach ( self::sources() as $source ) {
			if ( '' !== $source->legacy_option_key() ) {
				return $source->legacy_option_key();
			}
		}

		return '';
	}
}
