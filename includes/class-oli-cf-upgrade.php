<?php
/**
 * Versioned, idempotent upgrades.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade routines.
 */
class Oli_CF_Upgrade {

	/**
	 * Routine that converts forms to the field builder.
	 *
	 * @var string
	 */
	const ROUTINE_FIELDS = '0.4.0-fields';

	/**
	 * Option that stores which routines have finished.
	 *
	 * @var string
	 */
	const OPTION = 'oli_cf_upgrade_routines';

	/**
	 * Option that stores the last migration report.
	 *
	 * @var string
	 */
	const REPORT = 'oli_cf_migration_report';

	/**
	 * Run pending upgrades.
	 */
	public static function maybe_upgrade() {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}

		self::load();

		if ( class_exists( 'Oli_CF_Uploads' ) ) {
			Oli_CF_Uploads::maybe_upgrade();
		}

		$done = get_option( self::OPTION, array() );

		if ( ! is_array( $done ) ) {
			$done = array();
		}

		if ( empty( $done[ self::ROUTINE_FIELDS ] ) ) {
			$report                       = self::migrate_forms();
			$done[ self::ROUTINE_FIELDS ] = time();
			update_option( self::OPTION, $done, false );
			update_option( self::REPORT, $report, false );
		}
	}

	/**
	 * Load classes this routine needs. Safe to call more than once.
	 */
	public static function load() {
		$dir = defined( 'OLI_CF_PLUGIN_DIR' ) ? OLI_CF_PLUGIN_DIR : dirname( __DIR__ ) . '/';

		require_once $dir . 'includes/class-oli-cf-form-parser.php';
		require_once $dir . 'includes/class-oli-cf-fields.php';
		require_once $dir . 'includes/class-oli-cf-mailer.php';
		require_once $dir . 'includes/class-oli-cf-notifications.php';
		require_once $dir . 'includes/class-oli-cf-validator.php';
		require_once $dir . 'includes/import/class-oli-cf-import-registry.php';
	}

	/**
	 * Convert every form once.
	 *
	 * Running this again does not overwrite the original copies.
	 *
	 * @return array<string, int>
	 */
	public static function migrate_forms() {
		self::load();

		$report = array(
			'forms'         => 0,
			'converted'     => 0,
			'skipped'       => 0,
			'fields'        => 0,
			'notifications' => 0,
		);

		if ( class_exists( 'Oli_CF_Import_Registry' ) ) {
			foreach ( Oli_CF_Import_Registry::sources() as $source ) {
				$source->migrate_legacy_storage();
			}
		}

		foreach ( self::form_ids() as $form_id ) {
			++$report['forms'];
			$result = self::migrate_form( $form_id );

			if ( 'converted' === $result['status'] ) {
				++$report['converted'];
				$report['fields']        += (int) $result['fields'];
				$report['notifications'] += (int) $result['notifications'];
			} else {
				++$report['skipped'];
			}
		}

		return $report;
	}

	/**
	 * Convert one form. A form already on the current schema is left untouched.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string, mixed>
	 */
	public static function migrate_form( $form_id ) {
		$form_id = (int) $form_id;
		$schema  = get_post_meta( $form_id, '_oli_cf_schema', true );

		if ( Oli_CF_Fields::SCHEMA === (string) $schema ) {
			return array(
				'status'        => 'skipped',
				'fields'        => 0,
				'notifications' => 0,
			);
		}

		$template = get_post_meta( $form_id, '_oli_cf_form_template', true );
		$mail     = get_post_meta( $form_id, '_oli_cf_mail', true );
		$mail_2   = get_post_meta( $form_id, '_oli_cf_mail_2', true );
		$messages = get_post_meta( $form_id, '_oli_cf_messages', true );

		self::backup_once( $form_id, '_oli_cf_legacy_template', $template );
		self::backup_once( $form_id, '_oli_cf_legacy_mail', $mail );
		self::backup_once( $form_id, '_oli_cf_legacy_mail_2', $mail_2 );
		self::backup_once( $form_id, '_oli_cf_legacy_messages', $messages );

		$fields = array();

		if ( is_string( $template ) && '' !== trim( $template ) ) {
			$fields = Oli_CF_Fields::from_legacy_template( $template );
		}

		if ( empty( $fields ) ) {
			$existing = Oli_CF_Fields::decode( get_post_meta( $form_id, Oli_CF_Fields::META, true ) );

			if ( ! empty( $existing ) ) {
				$fields = $existing;
			}
		}

		update_post_meta( $form_id, Oli_CF_Fields::META, Oli_CF_Fields::encode( $fields ) );

		$notifications = Oli_CF_Notifications::from_legacy_mail( $mail, $mail_2 );
		update_post_meta( $form_id, Oli_CF_Notifications::META, Oli_CF_Notifications::encode( $notifications ) );
		update_post_meta( $form_id, '_oli_cf_messages', Oli_CF_Validator::normalize_messages( $messages ) );
		update_post_meta( $form_id, '_oli_cf_schema', Oli_CF_Fields::SCHEMA );

		return array(
			'status'        => 'converted',
			'fields'        => count( $fields ),
			'notifications' => count( $notifications ),
		);
	}

	/**
	 * Rebuild a form from the original copy. The copy is not deleted.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function restore_form( $form_id ) {
		self::load();

		$form_id  = (int) $form_id;
		$template = get_post_meta( $form_id, '_oli_cf_legacy_template', true );
		$mail     = get_post_meta( $form_id, '_oli_cf_legacy_mail', true );
		$mail_2   = get_post_meta( $form_id, '_oli_cf_legacy_mail_2', true );
		$messages = get_post_meta( $form_id, '_oli_cf_legacy_messages', true );

		if ( ! is_string( $template ) || '' === trim( $template ) ) {
			return false;
		}

		$fields        = Oli_CF_Fields::from_legacy_template( $template );
		$notifications = Oli_CF_Notifications::from_legacy_mail( $mail, $mail_2 );

		update_post_meta( $form_id, Oli_CF_Fields::META, Oli_CF_Fields::encode( $fields ) );
		update_post_meta( $form_id, Oli_CF_Notifications::META, Oli_CF_Notifications::encode( $notifications ) );
		update_post_meta( $form_id, '_oli_cf_messages', Oli_CF_Validator::normalize_messages( is_array( $messages ) ? $messages : array() ) );
		update_post_meta( $form_id, '_oli_cf_schema', Oli_CF_Fields::SCHEMA );

		return true;
	}

	/**
	 * Form IDs to convert.
	 *
	 * @return int[]
	 */
	public static function form_ids() {
		if ( isset( $GLOBALS['oli_cf_test_form_ids'] ) && is_array( $GLOBALS['oli_cf_test_form_ids'] ) ) {
			return array_map( 'absint', $GLOBALS['oli_cf_test_form_ids'] );
		}

		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => 'oli_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Store a backup only when one is not already there.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $key     Backup meta key.
	 * @param mixed  $value   Current value.
	 */
	private static function backup_once( $form_id, $key, $value ) {
		$existing = get_post_meta( $form_id, $key, true );

		if ( is_string( $existing ) && '' !== $existing ) {
			return;
		}

		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return;
		}

		if ( is_string( $value ) && '' === $value ) {
			return;
		}

		if ( ! is_array( $value ) && ! is_string( $value ) ) {
			return;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return;
		}

		update_post_meta( $form_id, $key, $value );
	}
}
