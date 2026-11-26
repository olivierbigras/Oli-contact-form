<?php
/**
 * Import source that reads Ninja Forms tables.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ninja Forms reader.
 */
class Oli_CF_Import_Source_Ninja extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'ninja';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Ninja Forms';
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

		return class_exists( 'Ninja_Forms', false );
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

		return Oli_CF_Import_Reader::table_has_rows( 'nf3_forms' );
	}

	/**
	 * List forms.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms() {
		$forms = array();

		foreach ( Oli_CF_Import_Reader::select_all( 'nf3_forms' ) as $row ) {
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
		foreach ( Oli_CF_Import_Reader::select_all( 'nf3_forms' ) as $row ) {
			if ( ! isset( $row['id'] ) || (string) $row['id'] !== (string) $source_id ) {
				continue;
			}

			$row['fields']      = Oli_CF_Import_Reader::select_by_parent_id( 'nf3_fields', (int) $row['id'] );
			$row['meta']        = Oli_CF_Import_Reader::select_all( 'nf3_field_meta' );
			$row['actions']     = Oli_CF_Import_Reader::select_by_parent_id( 'nf3_actions', (int) $row['id'] );
			$row['action_meta'] = Oli_CF_Import_Reader::select_all( 'nf3_action_meta' );

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
		$raw_fields  = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : array();
		$meta_rows   = isset( $record['meta'] ) && is_array( $record['meta'] ) ? $record['meta'] : array();
		$used        = array();
		$fields      = array();
		$unsupported = array();
		$names       = array();

		foreach ( $raw_fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type  = isset( $raw['type'] ) ? strtolower( (string) $raw['type'] ) : '';
			$key   = isset( $raw['key'] ) ? (string) $raw['key'] : '';
			$label = isset( $raw['label'] ) ? (string) $raw['label'] : '';
			$meta  = $this->meta_for( isset( $raw['id'] ) ? (int) $raw['id'] : 0, $meta_rows );

			if ( '' === $label && isset( $meta['label'] ) ) {
				$label = (string) $meta['label'];
			}

			$mapped = $this->map_type( $type );

			if ( 'submit' === $mapped ) {
				continue;
			}

			if ( '' === $mapped ) {
				$unsupported[] = array(
					'type'  => $type,
					'label' => $label,
				);
				continue;
			}

			$name          = Oli_CF_Fields::unique_name( '' !== $key ? $key : $label, 'field', $used );
			$names[ $key ] = $name;
			$options       = $this->choice_labels( isset( $meta['options'] ) ? $meta['options'] : array() );
			$required      = ! empty( $raw['required'] ) || ! empty( $meta['required'] );

			if ( 'checkbox' === $type && count( $options ) < 2 ) {
				$fields[] = Oli_CF_Fields::make( 'acceptance', $name, $label, true );
				continue;
			}

			$fields[] = Oli_CF_Fields::make( $mapped, $name, $label, $required, array( 'options' => $options ) );
		}

		$fields[] = Oli_CF_Fields::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false );

		$rows = array();

		foreach ( $this->email_actions( $record ) as $action ) {
			$rows[] = array(
				'to'       => isset( $action['to'] ) ? (string) $action['to'] : '',
				'from'     => isset( $action['from'] ) ? (string) $action['from'] : '',
				'reply_to' => isset( $action['reply_to'] ) ? (string) $action['reply_to'] : '',
				'subject'  => isset( $action['subject'] ) ? (string) $action['subject'] : '',
				'body'     => isset( $action['body'] ) ? (string) $action['body'] : '',
			);
		}

		$token_map = array(
			'{wp:admin_email}' => '{site:admin_email}',
		);

		foreach ( $names as $key => $name ) {
			$token_map[ '{field:' . $key . '}' ] = '{field:' . $name . '}';
		}

		return $this->result(
			isset( $record['title'] ) ? (string) $record['title'] : '',
			$fields,
			$this->notifications_from_rows( $rows, $token_map ),
			Oli_CF_Validator::default_messages(),
			$unsupported,
			isset( $record['id'] ) ? (string) $record['id'] : ''
		);
	}

	/**
	 * Map a source type.
	 *
	 * @param string $type Source type.
	 * @return string
	 */
	private function map_type( $type ) {
		$map = array(
			'textbox'      => 'text',
			'email'        => 'email',
			'phone'        => 'tel',
			'textarea'     => 'textarea',
			'listselect'   => 'select',
			'listradio'    => 'radio',
			'listcheckbox' => 'checkbox',
			'checkbox'     => 'checkbox',
			'file'         => 'file',
			'file_upload'  => 'file',
			'hidden'       => 'hidden',
			'date'         => 'date',
			'number'       => 'number',
			'submit'       => 'submit',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	/**
	 * Meta values for one field.
	 *
	 * @param int                              $field_id Field ID.
	 * @param array<int, array<string, mixed>> $rows     Meta rows.
	 * @return array<string, mixed>
	 */
	private function meta_for( $field_id, $rows ) {
		$meta = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['parent_id'] ) || (int) $row['parent_id'] !== $field_id ) {
				continue;
			}

			$key = isset( $row['key'] ) ? (string) $row['key'] : '';

			if ( '' === $key ) {
				continue;
			}

			$value        = isset( $row['value'] ) ? $row['value'] : '';
			$meta[ $key ] = is_string( $value ) ? $this->maybe_decode( $value ) : $value;
		}

		return $meta;
	}

	/**
	 * Email actions stored beside the form.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<int, array<string, string>>
	 */
	private function email_actions( $record ) {
		$actions = isset( $record['actions'] ) && is_array( $record['actions'] ) ? $record['actions'] : array();
		$meta    = isset( $record['action_meta'] ) && is_array( $record['action_meta'] ) ? $record['action_meta'] : array();
		$emails  = array();

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}

			$type = isset( $action['type'] ) ? (string) $action['type'] : '';

			if ( 'email' !== $type ) {
				continue;
			}

			$values   = $this->meta_for( isset( $action['id'] ) ? (int) $action['id'] : 0, $meta );
			$emails[] = array(
				'to'       => isset( $values['to'] ) ? (string) $values['to'] : '',
				'from'     => isset( $values['from'] ) ? (string) $values['from'] : '',
				'reply_to' => isset( $values['reply_to'] ) ? (string) $values['reply_to'] : '',
				'subject'  => isset( $values['email_subject'] ) ? (string) $values['email_subject'] : '',
				'body'     => isset( $values['email_message'] ) ? (string) $values['email_message'] : '',
			);
		}

		return $emails;
	}

	/**
	 * Decode a JSON or serialized meta value.
	 *
	 * @param string $value Raw value.
	 * @return mixed
	 */
	private function maybe_decode( $value ) {
		$decoded = $this->array_value( $value );

		return empty( $decoded ) ? $value : $decoded;
	}
}
