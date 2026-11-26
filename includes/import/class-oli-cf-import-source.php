<?php
/**
 * Shared helpers for import sources.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for an import source.
 */
abstract class Oli_CF_Import_Source implements Oli_CF_Import_Adapter {

	/**
	 * Whether this source owns the legacy shortcode.
	 *
	 * @return bool
	 */
	public function owns_legacy_shortcode() {
		return false;
	}

	/**
	 * Previous option key to rename, when this source has one.
	 *
	 * @return string
	 */
	public function legacy_option_key() {
		return '';
	}

	/**
	 * Previous post meta key to rename, when this source has one.
	 *
	 * @return string
	 */
	public function legacy_meta_key() {
		return '';
	}

	/**
	 * Previous admin page slug to redirect, when this source has one.
	 *
	 * @return string
	 */
	public function legacy_page_slug() {
		return '';
	}

	/**
	 * Previous admin-post action to keep registered.
	 *
	 * @return string
	 */
	public function legacy_admin_action() {
		return '';
	}

	/**
	 * Previous screen IDs.
	 *
	 * @return string[]
	 */
	public function legacy_screen_ids() {
		return array();
	}

	/**
	 * The source is offered when its plugin is active or its data remains.
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->is_active() || $this->has_data();
	}

	/**
	 * Copy a renamed option and meta key. Safe to run more than once.
	 */
	public function migrate_legacy_storage() {
		$this->migrate_legacy_option();
		$this->migrate_legacy_meta();
	}

	/**
	 * Convert a decoded record. Tests call this with fixtures.
	 *
	 * @param array<string, mixed> $record Source record.
	 * @return array<string, mixed>
	 */
	abstract public function convert_record( array $record );

	/**
	 * A test may force active or data detection for one source key.
	 *
	 * @param string $bucket active or data.
	 * @return bool|null Null when the test did not set a flag.
	 */
	protected function forced_flag( $bucket ) {
		if ( isset( $GLOBALS['oli_cf_test_import_db'][ $bucket ] ) && is_array( $GLOBALS['oli_cf_test_import_db'][ $bucket ] ) && array_key_exists( $this->key(), $GLOBALS['oli_cf_test_import_db'][ $bucket ] ) ) {
			return (bool) $GLOBALS['oli_cf_test_import_db'][ $bucket ][ $this->key() ];
		}

		return null;
	}

	/**
	 * Decode a JSON string or pass an array through.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>|array<int, mixed>
	 */
	protected function json_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Unserialize a value without allowing objects.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>|array<int, mixed>
	 */
	protected function array_value( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		$json = $this->json_array( $value );

		if ( ! empty( $json ) ) {
			return $json;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		if ( ! preg_match( '/^[abisdN]:/', $value ) ) {
			return array();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- read-only import of a serialized array; objects are refused by allowed_classes.
		$decoded = unserialize( $value, array( 'allowed_classes' => false ) );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Build the structure stored on an imported form.
	 *
	 * @param string                               $title         Form title.
	 * @param array<int, array<string, mixed>>     $fields        Fields.
	 * @param array<int, array<string, mixed>>     $notifications Notifications.
	 * @param array<string, string>                $messages      Messages.
	 * @param array<int, array<string, string>>    $unsupported   Unsupported fields.
	 * @param string                               $source_id     Source ID.
	 * @return array<string, mixed>
	 */
	protected function result( $title, $fields, $notifications, $messages, $unsupported, $source_id ) {
		if ( empty( $messages ) ) {
			$messages = Oli_CF_Validator::default_messages();
		}

		return array(
			'title'         => sanitize_text_field( (string) $title ),
			'fields'        => Oli_CF_Fields::normalize_list( $fields ),
			'notifications' => Oli_CF_Notifications::normalize_list( $notifications ),
			'messages'      => Oli_CF_Validator::normalize_messages( $messages ),
			'unsupported'   => $unsupported,
			'source_id'     => (string) $source_id,
		);
	}

	/**
	 * Turn simple recipient rows into notifications and rewrite known tokens.
	 *
	 * @param array<int, array<string, string>> $rows       Recipient rows.
	 * @param array<string, string>             $token_map  Exact token replacements.
	 * @return array<int, array<string, mixed>>
	 */
	protected function notifications_from_rows( $rows, $token_map ) {
		$notifications = array();
		$index         = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item             = Oli_CF_Notifications::blank();
			$item['id']       = 0 === $index ? 'admin' : 'notice-' . ( $index + 1 );
			$item['label']    = 0 === $index ? __( 'Admin notification', 'oli-contact-form' ) : __( 'Notification', 'oli-contact-form' );
			$item['enabled']  = ! isset( $row['enabled'] ) || ! empty( $row['enabled'] );
			$item['to']       = $this->replace_tokens( isset( $row['to'] ) ? $row['to'] : '', $token_map );
			$item['from']     = $this->replace_tokens( isset( $row['from'] ) ? $row['from'] : '', $token_map );
			$item['reply_to'] = $this->replace_tokens( isset( $row['reply_to'] ) ? $row['reply_to'] : '', $token_map );
			$item['subject']  = $this->replace_tokens( isset( $row['subject'] ) ? $row['subject'] : '', $token_map );
			$item['body']     = $this->replace_tokens( isset( $row['body'] ) ? $row['body'] : '', $token_map );
			$notifications[]  = $item;
			++$index;
		}

		if ( count( $notifications ) > 1 ) {
			$notifications[1]['id']    = 'sender';
			$notifications[1]['label'] = __( 'Sender confirmation', 'oli-contact-form' );
		}

		return $notifications;
	}

	/**
	 * Replace exact tokens, then field-id placeholders of the form {field:already}.
	 *
	 * @param string                $text Text.
	 * @param array<string, string> $map  Replacements.
	 * @return string
	 */
	protected function replace_tokens( $text, $map ) {
		$text = strtr( (string) $text, $map );
		$text = Oli_CF_Notifications::rewrite_tokens( $text );

		return $text;
	}

	/**
	 * Choice labels from a list of strings or label/text/value arrays.
	 *
	 * @param mixed $choices Raw choices.
	 * @return string[]
	 */
	protected function choice_labels( $choices ) {
		$labels = array();

		if ( ! is_array( $choices ) ) {
			return $labels;
		}

		foreach ( $choices as $choice ) {
			if ( is_array( $choice ) ) {
				if ( isset( $choice['label'] ) && '' !== (string) $choice['label'] ) {
					$labels[] = (string) $choice['label'];
				} elseif ( isset( $choice['text'] ) && '' !== (string) $choice['text'] ) {
					$labels[] = (string) $choice['text'];
				} elseif ( isset( $choice['value'] ) ) {
					$labels[] = (string) $choice['value'];
				}
			} elseif ( is_scalar( $choice ) && '' !== (string) $choice ) {
				$labels[] = (string) $choice;
			}
		}

		return $labels;
	}

	/**
	 * Copy the previous option into legacy_shortcode_compat.
	 */
	private function migrate_legacy_option() {
		$legacy = $this->legacy_option_key();

		if ( '' === $legacy || ! function_exists( 'get_option' ) ) {
			return;
		}

		$settings = get_option( 'oli_cf_settings', array() );

		if ( ! is_array( $settings ) || ! array_key_exists( $legacy, $settings ) ) {
			return;
		}

		if ( ! array_key_exists( 'legacy_shortcode_compat', $settings ) ) {
			$settings['legacy_shortcode_compat'] = ! empty( $settings[ $legacy ] );
		}

		unset( $settings[ $legacy ] );
		update_option( 'oli_cf_settings', $settings );
	}

	/**
	 * Move the previous import meta onto the neutral keys.
	 */
	private function migrate_legacy_meta() {
		$legacy = $this->legacy_meta_key();

		if ( '' === $legacy ) {
			return;
		}

		foreach ( Oli_CF_Import_Reader::post_ids_with_meta( $legacy ) as $post_id ) {
			$old = get_post_meta( $post_id, $legacy, true );

			if ( '' === (string) get_post_meta( $post_id, '_oli_cf_imported_from', true ) ) {
				update_post_meta( $post_id, '_oli_cf_imported_from', $this->key() );
			}

			if ( '' === (string) get_post_meta( $post_id, '_oli_cf_import_source_id', true ) ) {
				update_post_meta( $post_id, '_oli_cf_import_source_id', (string) $old );
			}

			delete_post_meta( $post_id, $legacy );
		}
	}
}
