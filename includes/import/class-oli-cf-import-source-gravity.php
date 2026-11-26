<?php
/**
 * Import source that reads Gravity Forms tables.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Forms reader.
 */
class Oli_CF_Import_Source_Gravity extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'gravity';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Gravity Forms';
	}

	/**
	 * Whether the plugin is loaded.
	 *
	 * @return bool
	 */
	public function is_active() {
		$forced = $this->forced_flag( 'active' );

		if ( null !== $forced ) {
			return $forced;
		}

		return class_exists( 'GFForms', false ) || class_exists( 'GFAPI', false );
	}

	/**
	 * Whether form rows remain.
	 *
	 * @return bool
	 */
	public function has_data() {
		$forced = $this->forced_flag( 'data' );

		if ( null !== $forced ) {
			return $forced;
		}

		return Oli_CF_Import_Reader::table_has_rows( 'gf_form' );
	}

	/**
	 * List forms.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms() {
		$forms = array();

		foreach ( Oli_CF_Import_Reader::select_all( 'gf_form' ) as $row ) {
			if ( ! empty( $row['is_trash'] ) ) {
				continue;
			}

			$id      = isset( $row['id'] ) ? (string) $row['id'] : '';
			$forms[] = array(
				'id'    => $id,
				'title' => isset( $row['title'] ) && '' !== (string) $row['title'] ? (string) $row['title'] : $id,
			);
		}

		return $forms;
	}

	/**
	 * Convert one form.
	 *
	 * @param int|string $source_id Source form ID.
	 * @return array<string, mixed>|null
	 */
	public function convert( $source_id ) {
		foreach ( Oli_CF_Import_Reader::select_all( 'gf_form' ) as $row ) {
			if ( ! isset( $row['id'] ) || (string) $row['id'] !== (string) $source_id ) {
				continue;
			}

			$meta                = Oli_CF_Import_Reader::select_by_form_id( 'gf_form_meta', (int) $row['id'] );
			$row['display_meta'] = isset( $meta[0]['display_meta'] ) ? $meta[0]['display_meta'] : '';

			if ( isset( $meta[0]['confirmations'] ) ) {
				$row['confirmations'] = $meta[0]['confirmations'];
			}

			if ( isset( $meta[0]['notifications'] ) ) {
				$row['notifications'] = $meta[0]['notifications'];
			}

			return $this->convert_record( $row );
		}

		return null;
	}

	/**
	 * Convert a decoded record.
	 *
	 * @param array<string, mixed> $record Source record.
	 * @return array<string, mixed>
	 */
	public function convert_record( array $record ) {
		$display     = $this->json_array( isset( $record['display_meta'] ) ? $record['display_meta'] : array() );
		$raw_fields  = isset( $display['fields'] ) && is_array( $display['fields'] ) ? $display['fields'] : array();
		$used        = array();
		$fields      = array();
		$unsupported = array();
		$names_by_id = array();

		foreach ( $raw_fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type   = isset( $raw['type'] ) ? strtolower( (string) $raw['type'] ) : '';
			$label  = isset( $raw['label'] ) ? (string) $raw['label'] : '';
			$id     = isset( $raw['id'] ) ? (string) $raw['id'] : '';
			$mapped = $this->map_type( $type );

			if ( '' === $mapped ) {
				$unsupported[] = array(
					'type'  => $type,
					'label' => $label,
				);
				continue;
			}

			$name               = Oli_CF_Fields::unique_name( $label, 'field-' . $id, $used );
			$names_by_id[ $id ] = $name;

			if ( 'acceptance' === $mapped ) {
				$consent  = isset( $raw['checkboxLabel'] ) && '' !== (string) $raw['checkboxLabel'] ? (string) $raw['checkboxLabel'] : $label;
				$fields[] = Oli_CF_Fields::make( 'acceptance', $name, $consent, true );
				continue;
			}

			$args = array(
				'options' => $this->choice_labels( isset( $raw['choices'] ) ? $raw['choices'] : array() ),
			);

			if ( 'file' === $mapped && ! empty( $raw['maxFileSize'] ) ) {
				$args['limit'] = absint( $raw['maxFileSize'] ) . 'mb';
			}

			if ( 'hidden' === $mapped && isset( $raw['defaultValue'] ) ) {
				$args['default'] = (string) $raw['defaultValue'];
			}

			$fields[] = Oli_CF_Fields::make( $mapped, $name, $label, ! empty( $raw['isRequired'] ), $args );
		}

		$fields[] = Oli_CF_Fields::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false );

		$notes     = $this->json_array( isset( $record['notifications'] ) ? $record['notifications'] : ( isset( $display['notifications'] ) ? $display['notifications'] : array() ) );
		$rows      = array();
		$token_map = $this->token_map( $names_by_id );

		foreach ( $notes as $note ) {
			if ( ! is_array( $note ) || ( isset( $note['isActive'] ) && empty( $note['isActive'] ) ) ) {
				continue;
			}

			$rows[] = array(
				'to'       => isset( $note['to'] ) ? (string) $note['to'] : '',
				'from'     => isset( $note['from'] ) ? (string) $note['from'] : '',
				'reply_to' => isset( $note['replyTo'] ) ? (string) $note['replyTo'] : '',
				'subject'  => isset( $note['subject'] ) ? (string) $note['subject'] : '',
				'body'     => isset( $note['message'] ) ? (string) $note['message'] : '',
			);
		}

		$messages      = Oli_CF_Validator::default_messages();
		$confirmations = $this->json_array( isset( $record['confirmations'] ) ? $record['confirmations'] : ( isset( $display['confirmations'] ) ? $display['confirmations'] : array() ) );

		foreach ( $confirmations as $confirmation ) {
			if ( is_array( $confirmation ) && isset( $confirmation['message'] ) && is_string( $confirmation['message'] ) && '' !== $confirmation['message'] ) {
				$messages['success'] = wp_strip_all_tags( $confirmation['message'] );
				break;
			}
		}

		$title         = isset( $record['title'] ) ? (string) $record['title'] : '';
		$notifications = $this->notifications_from_rows( $rows, $token_map );
		$notifications = $this->rewrite_labeled_ids( $notifications, $names_by_id );

		return $this->result(
			$title,
			$fields,
			$notifications,
			$messages,
			$unsupported,
			isset( $record['id'] ) ? (string) $record['id'] : ''
		);
	}

	/**
	 * Replace {Label:id} tokens once field names are known.
	 *
	 * @param array<int, array<string, mixed>> $notifications Notifications.
	 * @param array<string, string>            $names_by_id   Field id to name.
	 * @return array<int, array<string, mixed>>
	 */
	private function rewrite_labeled_ids( $notifications, $names_by_id ) {
		foreach ( $notifications as $index => $item ) {
			foreach ( array( 'to', 'from', 'reply_to', 'subject', 'body', 'headers' ) as $key ) {
				if ( ! isset( $item[ $key ] ) || ! is_string( $item[ $key ] ) ) {
					continue;
				}

				$rewritten = preg_replace_callback(
					'/\{[^}:]+:(\d+(?:\.\d+)?)\}/',
					static function ( $matches ) use ( $names_by_id ) {
						$id = $matches[1];

						if ( ! isset( $names_by_id[ $id ] ) ) {
							return $matches[0];
						}

						return '{field:' . $names_by_id[ $id ] . '}';
					},
					$item[ $key ]
				);

				$notifications[ $index ][ $key ] = is_string( $rewritten ) ? $rewritten : $item[ $key ];
			}
		}

		return $notifications;
	}

	/**
	 * Map a source type onto a field type.
	 *
	 * @param string $type Source type.
	 * @return string Empty when the type is not supported.
	 */
	private function map_type( $type ) {
		$map = array(
			'text'       => 'text',
			'textarea'   => 'textarea',
			'email'      => 'email',
			'phone'      => 'tel',
			'website'    => 'url',
			'number'     => 'number',
			'date'       => 'date',
			'select'     => 'select',
			'radio'      => 'radio',
			'checkbox'   => 'checkbox',
			'fileupload' => 'file',
			'hidden'     => 'hidden',
			'consent'    => 'acceptance',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	/**
	 * Token replacements for this source.
	 *
	 * @param array<string, string> $names_by_id Field id to name.
	 * @return array<string, string>
	 */
	private function token_map( $names_by_id ) {
		$map = array(
			'{admin_email}' => '{site:admin_email}',
		);

		foreach ( $names_by_id as $id => $name ) {
			$map[ '{' . $id . '}' ] = '{field:' . $name . '}';
		}

		return $map;
	}
}
