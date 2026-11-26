<?php
/**
 * Import source that reads Fluent Forms tables.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fluent Forms reader.
 */
class Oli_CF_Import_Source_Fluent extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'fluent';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Fluent Forms';
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

		return defined( 'FLUENTFORM' ) || class_exists( 'FluentForm\App\App', false ) || function_exists( 'wpFluentForm' );
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

		return Oli_CF_Import_Reader::table_has_rows( 'fluentform_forms' );
	}

	/**
	 * List forms.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms() {
		$forms = array();

		foreach ( Oli_CF_Import_Reader::select_all( 'fluentform_forms' ) as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';

			if ( 'trash' === $status ) {
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
		foreach ( Oli_CF_Import_Reader::select_all( 'fluentform_forms' ) as $row ) {
			if ( ! isset( $row['id'] ) || (string) $row['id'] !== (string) $source_id ) {
				continue;
			}

			$row['meta'] = Oli_CF_Import_Reader::select_by_form_id( 'fluentform_form_meta', (int) $row['id'] );

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
		$form        = $this->json_array( isset( $record['form_fields'] ) ? $record['form_fields'] : array() );
		$raw_fields  = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$used        = array();
		$fields      = array();
		$unsupported = array();
		$names       = array();

		foreach ( $raw_fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$element  = isset( $raw['element'] ) ? strtolower( (string) $raw['element'] ) : '';
			$attrs    = isset( $raw['attributes'] ) && is_array( $raw['attributes'] ) ? $raw['attributes'] : array();
			$settings = isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array();
			$name_key = isset( $attrs['name'] ) ? (string) $attrs['name'] : '';
			$label    = isset( $settings['label'] ) ? (string) $settings['label'] : $name_key;
			$mapped   = $this->map_type( $element );

			if ( '' === $mapped ) {
				$unsupported[] = array(
					'type'  => $element,
					'label' => $label,
				);
				continue;
			}

			$name               = Oli_CF_Fields::unique_name( $name_key, $label, $used );
			$names[ $name_key ] = $name;
			$options            = array();

			if ( isset( $settings['advanced_options'] ) ) {
				$options = $this->choice_labels( $settings['advanced_options'] );
			}

			$required = ! empty( $settings['validation_rules']['required']['value'] );

			if ( 'terms_and_condition' === $element ) {
				$fields[] = Oli_CF_Fields::make( 'acceptance', $name, $label, true );
				continue;
			}

			$fields[] = Oli_CF_Fields::make( $mapped, $name, $label, $required, array( 'options' => $options ) );
		}

		$fields[]  = Oli_CF_Fields::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false );
		$rows      = $this->notification_rows( $record );
		$token_map = array(
			'{wp.admin_email}' => '{site:admin_email}',
		);

		foreach ( $names as $key => $name ) {
			$token_map[ '{inputs.' . $key . '}' ] = '{field:' . $name . '}';
		}

		$messages = Oli_CF_Validator::default_messages();

		if ( isset( $form['form_settings']['confirmation']['messageToShow'] ) && is_string( $form['form_settings']['confirmation']['messageToShow'] ) ) {
			$messages['success'] = wp_strip_all_tags( $form['form_settings']['confirmation']['messageToShow'] );
		}

		return $this->result(
			isset( $record['title'] ) ? (string) $record['title'] : '',
			$fields,
			$this->notifications_from_rows( $rows, $token_map ),
			$messages,
			$unsupported,
			isset( $record['id'] ) ? (string) $record['id'] : ''
		);
	}

	/**
	 * Map an element name.
	 *
	 * @param string $type Element name.
	 * @return string
	 */
	private function map_type( $type ) {
		$map = array(
			'input_text'          => 'text',
			'input_email'         => 'email',
			'textarea'            => 'textarea',
			'select'              => 'select',
			'input_radio'         => 'radio',
			'input_checkbox'      => 'checkbox',
			'input_url'           => 'url',
			'input_number'        => 'number',
			'input_date'          => 'date',
			'phone'               => 'tel',
			'input_file'          => 'file',
			'input_hidden'        => 'hidden',
			'terms_and_condition' => 'acceptance',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	/**
	 * Notification rows from form meta or an embedded list.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<int, array<string, string>>
	 */
	private function notification_rows( $record ) {
		$rows = array();
		$meta = isset( $record['meta'] ) && is_array( $record['meta'] ) ? $record['meta'] : array();

		foreach ( $meta as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['meta_key'] ) || 'notifications' !== (string) $row['meta_key'] ) {
				continue;
			}

			$decoded = $this->json_array( isset( $row['value'] ) ? $row['value'] : array() );

			foreach ( $decoded as $note ) {
				if ( ! is_array( $note ) ) {
					continue;
				}

				$rows[] = array(
					'enabled'  => ! isset( $note['enabled'] ) || ! empty( $note['enabled'] ),
					'to'       => isset( $note['sendTo']['email'] ) ? (string) $note['sendTo']['email'] : '',
					'from'     => isset( $note['fromEmail'] ) ? (string) $note['fromEmail'] : '',
					'reply_to' => isset( $note['replyTo'] ) ? (string) $note['replyTo'] : '',
					'subject'  => isset( $note['subject'] ) ? (string) $note['subject'] : '',
					'body'     => isset( $note['message'] ) ? (string) $note['message'] : '',
				);
			}
		}

		return $rows;
	}
}
