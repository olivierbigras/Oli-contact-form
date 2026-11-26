<?php
/**
 * Import source that reads Formidable Forms tables.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formidable Forms reader.
 */
class Oli_CF_Import_Source_Formidable extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'formidable';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Formidable Forms';
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

		return class_exists( 'FrmForm', false ) || class_exists( 'FrmAppController', false );
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

		return Oli_CF_Import_Reader::table_has_rows( 'frm_forms' );
	}

	/**
	 * List forms.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms() {
		$forms = array();

		foreach ( Oli_CF_Import_Reader::select_all( 'frm_forms' ) as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';

			if ( 'trash' === $status ) {
				continue;
			}

			$id      = isset( $row['id'] ) ? (string) $row['id'] : '';
			$forms[] = array(
				'id'    => $id,
				'title' => isset( $row['name'] ) && '' !== (string) $row['name'] ? (string) $row['name'] : $id,
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
		foreach ( Oli_CF_Import_Reader::select_all( 'frm_forms' ) as $row ) {
			if ( ! isset( $row['id'] ) || (string) $row['id'] !== (string) $source_id ) {
				continue;
			}

			$row['fields'] = Oli_CF_Import_Reader::select_by_form_id( 'frm_fields', (int) $row['id'] );

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
		$used        = array();
		$fields      = array();
		$unsupported = array();
		$names_by_id = array();

		foreach ( $raw_fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type    = isset( $raw['type'] ) ? strtolower( (string) $raw['type'] ) : '';
			$label   = isset( $raw['name'] ) ? (string) $raw['name'] : '';
			$id      = isset( $raw['id'] ) ? (string) $raw['id'] : '';
			$options = $this->array_value( isset( $raw['options'] ) ? $raw['options'] : array() );
			$mapped  = $this->map_type( $type );

			if ( '' === $mapped ) {
				$unsupported[] = array(
					'type'  => $type,
					'label' => $label,
				);
				continue;
			}

			$name               = Oli_CF_Fields::unique_name( isset( $raw['field_key'] ) ? (string) $raw['field_key'] : $label, 'field-' . $id, $used );
			$names_by_id[ $id ] = $name;
			$choices            = $this->choice_labels( $options );
			$required           = ! empty( $raw['required'] );

			if ( 'checkbox' === $mapped && count( $choices ) < 2 ) {
				$fields[] = Oli_CF_Fields::make( 'acceptance', $name, $label, true );
				continue;
			}

			$fields[] = Oli_CF_Fields::make( $mapped, $name, $label, $required, array( 'options' => $choices ) );
		}

		$fields[] = Oli_CF_Fields::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false );

		$form_options = $this->array_value( isset( $record['options'] ) ? $record['options'] : array() );
		$rows         = array();
		$notification = isset( $form_options['notification'] ) && is_array( $form_options['notification'] ) ? $form_options['notification'] : array();

		if ( ! empty( $notification ) ) {
			$rows[] = array(
				'to'      => isset( $notification['email_to'] ) ? (string) $notification['email_to'] : '',
				'from'    => isset( $notification['from'] ) ? (string) $notification['from'] : '',
				'subject' => isset( $notification['email_subject'] ) ? (string) $notification['email_subject'] : '',
				'body'    => isset( $notification['email_message'] ) ? (string) $notification['email_message'] : '',
			);
		}

		$token_map = array(
			'[admin_email]' => '{site:admin_email}',
		);

		foreach ( $names_by_id as $id => $name ) {
			$token_map[ '[' . $id . ']' ] = '{field:' . $name . '}';
		}

		$messages = Oli_CF_Validator::default_messages();

		if ( isset( $form_options['success_msg'] ) && is_string( $form_options['success_msg'] ) && '' !== $form_options['success_msg'] ) {
			$messages['success'] = wp_strip_all_tags( $form_options['success_msg'] );
		}

		$title = isset( $record['name'] ) ? (string) $record['name'] : ( isset( $record['title'] ) ? (string) $record['title'] : '' );

		return $this->result( $title, $fields, $this->notifications_from_rows( $rows, $token_map ), $messages, $unsupported, isset( $record['id'] ) ? (string) $record['id'] : '' );
	}

	/**
	 * Map a source type.
	 *
	 * @param string $type Source type.
	 * @return string
	 */
	private function map_type( $type ) {
		$map = array(
			'text'     => 'text',
			'email'    => 'email',
			'phone'    => 'tel',
			'url'      => 'url',
			'number'   => 'number',
			'date'     => 'date',
			'textarea' => 'textarea',
			'select'   => 'select',
			'radio'    => 'radio',
			'checkbox' => 'checkbox',
			'file'     => 'file',
			'hidden'   => 'hidden',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}
}
