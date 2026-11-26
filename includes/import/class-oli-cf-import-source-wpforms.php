<?php
/**
 * Import source that reads WPForms posts.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPForms reader.
 */
class Oli_CF_Import_Source_Wpforms extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'wpforms';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'WPForms';
	}

	/**
	 * Post type.
	 *
	 * @return string
	 */
	public function post_type() {
		return 'wpforms';
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

		return function_exists( 'wpforms' ) || class_exists( 'WPForms', false );
	}

	/**
	 * Whether posts remain.
	 *
	 * @return bool
	 */
	public function has_data() {
		$forced = $this->forced_flag( 'data' );

		if ( null !== $forced ) {
			return $forced;
		}

		return count( Oli_CF_Import_Reader::posts_of_type( $this->post_type() ) ) > 0;
	}

	/**
	 * List forms.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms() {
		$forms = array();

		foreach ( Oli_CF_Import_Reader::posts_of_type( $this->post_type() ) as $post ) {
			$status = isset( $post['post_status'] ) ? (string) $post['post_status'] : '';

			if ( 'trash' === $status || 'auto-draft' === $status ) {
				continue;
			}

			$id      = isset( $post['ID'] ) ? (string) $post['ID'] : '';
			$forms[] = array(
				'id'    => $id,
				'title' => isset( $post['post_title'] ) && '' !== $post['post_title'] ? (string) $post['post_title'] : $id,
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
		foreach ( Oli_CF_Import_Reader::posts_of_type( $this->post_type() ) as $post ) {
			if ( ! isset( $post['ID'] ) || (string) $post['ID'] !== (string) $source_id ) {
				continue;
			}

			$decoded          = $this->json_array( isset( $post['post_content'] ) ? $post['post_content'] : '' );
			$decoded['id']    = $post['ID'];
			$decoded['title'] = isset( $post['post_title'] ) ? $post['post_title'] : '';

			return $this->convert_record( $decoded );
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
			$options            = $this->choice_labels( isset( $raw['choices'] ) ? $raw['choices'] : array() );
			$required           = ! empty( $raw['required'] );

			if ( 'gdpr-checkbox' === $type ) {
				$fields[] = Oli_CF_Fields::make( 'acceptance', $name, $label, true );
				continue;
			}

			$args = array( 'options' => $options );

			if ( 'file' === $mapped && ! empty( $raw['max_file_size'] ) ) {
				$args['limit'] = absint( $raw['max_file_size'] ) . 'mb';
			}

			$fields[] = Oli_CF_Fields::make( $mapped, $name, $label, $required, $args );
		}

		$fields[] = Oli_CF_Fields::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false );

		$settings = isset( $record['settings'] ) && is_array( $record['settings'] ) ? $record['settings'] : array();
		$notes    = isset( $settings['notifications'] ) && is_array( $settings['notifications'] ) ? $settings['notifications'] : array();
		$rows     = array();

		foreach ( $notes as $note ) {
			if ( ! is_array( $note ) ) {
				continue;
			}

			$rows[] = array(
				'enabled'  => ! isset( $note['enable'] ) || ! empty( $note['enable'] ),
				'to'       => isset( $note['email'] ) ? (string) $note['email'] : '',
				'from'     => isset( $note['sender_address'] ) ? (string) $note['sender_address'] : '',
				'reply_to' => isset( $note['replyto'] ) ? (string) $note['replyto'] : '',
				'subject'  => isset( $note['subject'] ) ? (string) $note['subject'] : '',
				'body'     => isset( $note['message'] ) ? (string) $note['message'] : '',
			);
		}

		$messages = Oli_CF_Validator::default_messages();

		if ( isset( $settings['confirmations'] ) && is_array( $settings['confirmations'] ) ) {
			foreach ( $settings['confirmations'] as $confirmation ) {
				if ( is_array( $confirmation ) && isset( $confirmation['message'] ) && is_string( $confirmation['message'] ) && '' !== $confirmation['message'] ) {
					$messages['success'] = wp_strip_all_tags( $confirmation['message'] );
					break;
				}
			}
		}

		return $this->result(
			isset( $record['title'] ) ? (string) $record['title'] : '',
			$fields,
			$this->notifications_from_rows( $rows, $this->token_map( $names_by_id ) ),
			$messages,
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
			'text'          => 'text',
			'textarea'      => 'textarea',
			'email'         => 'email',
			'select'        => 'select',
			'radio'         => 'radio',
			'checkbox'      => 'checkbox',
			'number'        => 'number',
			'url'           => 'url',
			'phone'         => 'tel',
			'date-time'     => 'date',
			'file-upload'   => 'file',
			'hidden'        => 'hidden',
			'gdpr-checkbox' => 'acceptance',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	/**
	 * Token replacements.
	 *
	 * @param array<string, string> $names_by_id Field id to name.
	 * @return array<string, string>
	 */
	private function token_map( $names_by_id ) {
		$map = array(
			'{admin_email}' => '{site:admin_email}',
		);

		foreach ( $names_by_id as $id => $name ) {
			$map[ '{field_id="' . $id . '"}' ] = '{field:' . $name . '}';
		}

		return $map;
	}
}
