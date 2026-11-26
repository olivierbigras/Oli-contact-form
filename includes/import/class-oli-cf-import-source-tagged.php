<?php
/**
 * Import source that reads forms stored by Contact Form 7.
 *
 * The product name and the source keys below exist only so an existing
 * install can be read. They are not used as branding anywhere else.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Form 7 reader.
 */
class Oli_CF_Import_Source_Tagged extends Oli_CF_Import_Source {

	/**
	 * Source key.
	 *
	 * @return string
	 */
	public function key() {
		return 'wpcf7';
	}

	/**
	 * Screen label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Contact Form 7';
	}

	/**
	 * This source is the one that registers the same shortcode name.
	 *
	 * @return bool
	 */
	public function owns_legacy_shortcode() {
		return true;
	}

	/**
	 * Previous settings key.
	 *
	 * @return string
	 */
	public function legacy_option_key() {
		return 'cf7_compat_enabled';
	}

	/**
	 * Previous import meta key.
	 *
	 * @return string
	 */
	public function legacy_meta_key() {
		return '_oli_cf_imported_from_cf7';
	}

	/**
	 * Previous import page.
	 *
	 * @return string
	 */
	public function legacy_page_slug() {
		return 'oli-cf-import-cf7';
	}

	/**
	 * Previous admin-post action.
	 *
	 * @return string
	 */
	public function legacy_admin_action() {
		return 'oli_cf_import_cf7';
	}

	/**
	 * Previous screen IDs.
	 *
	 * @return string[]
	 */
	public function legacy_screen_ids() {
		return array(
			'oli_contact_form_page_oli-cf-import-cf7',
			'oli-contact-forms_page_oli-cf-import-cf7',
		);
	}

	/**
	 * Post type used by the source plugin.
	 *
	 * @return string
	 */
	public function post_type() {
		return 'wpcf7_contact_form';
	}

	/**
	 * Whether the source plugin is loaded.
	 *
	 * @return bool
	 */
	public function is_active() {
		$forced = $this->forced_flag( 'active' );

		if ( null !== $forced ) {
			return $forced;
		}

		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7', false ) || class_exists( 'WPCF7_ContactForm', false );
	}

	/**
	 * Whether saved forms remain.
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
			$id      = isset( $post['ID'] ) ? (string) $post['ID'] : '';
			$forms[] = array(
				'id'    => $id,
				'title' => isset( $post['post_title'] ) && '' !== $post['post_title'] ? (string) $post['post_title'] : $id,
			);
		}

		return $forms;
	}

	/**
	 * Convert one stored form.
	 *
	 * @param int|string $source_id Source form ID.
	 * @return array<string, mixed>|null
	 */
	public function convert( $source_id ) {
		foreach ( Oli_CF_Import_Reader::posts_of_type( $this->post_type() ) as $post ) {
			if ( ! isset( $post['ID'] ) || (string) $post['ID'] !== (string) $source_id ) {
				continue;
			}

			return $this->convert_record(
				array(
					'id'       => $post['ID'],
					'title'    => isset( $post['post_title'] ) ? $post['post_title'] : '',
					'form'     => Oli_CF_Import_Reader::meta( (int) $post['ID'], '_form' ),
					'mail'     => Oli_CF_Import_Reader::meta( (int) $post['ID'], '_mail' ),
					'mail_2'   => Oli_CF_Import_Reader::meta( (int) $post['ID'], '_mail_2' ),
					'messages' => Oli_CF_Import_Reader::meta( (int) $post['ID'], '_messages' ),
				)
			);
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
		$template      = isset( $record['form'] ) ? (string) $record['form'] : '';
		$reported      = Oli_CF_Fields::legacy_report( $template );
		$mail          = $this->normalize_mail_box( isset( $record['mail'] ) ? $record['mail'] : array() );
		$mail_2        = $this->normalize_mail_box( isset( $record['mail_2'] ) ? $record['mail_2'] : array() );
		$notifications = Oli_CF_Notifications::from_legacy_mail( $mail, $mail_2 );
		$messages      = array();
		$incoming      = isset( $record['messages'] ) && is_array( $record['messages'] ) ? $record['messages'] : array();

		foreach ( Oli_CF_Validator::legacy_message_keys() as $old => $new ) {
			if ( isset( $incoming[ $old ] ) && is_string( $incoming[ $old ] ) && '' !== $incoming[ $old ] ) {
				$messages[ $new ] = $incoming[ $old ];
			}
		}

		return $this->result(
			isset( $record['title'] ) ? (string) $record['title'] : '',
			$reported['fields'],
			$notifications,
			$messages,
			$reported['unsupported'],
			isset( $record['id'] ) ? (string) $record['id'] : ''
		);
	}

	/**
	 * Map a mail box that uses the source's own array keys.
	 *
	 * @param mixed $mail Raw mail box.
	 * @return array<string, string>
	 */
	private function normalize_mail_box( $mail ) {
		if ( ! is_array( $mail ) ) {
			return array();
		}

		$headers = isset( $mail['additional_headers'] ) ? (string) $mail['additional_headers'] : '';
		$reply   = isset( $mail['reply_to'] ) ? (string) $mail['reply_to'] : '';
		$kept    = array();
		$lines   = preg_split( '/\r\n|\r|\n/', $headers );

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				if ( preg_match( '/^Reply-To\s*:\s*(.+)$/i', $line, $matches ) ) {
					if ( '' === $reply ) {
						$reply = trim( $matches[1] );
					}
					continue;
				}

				if ( '' !== trim( $line ) ) {
					$kept[] = $line;
				}
			}
		}

		$to   = isset( $mail['recipient'] ) ? (string) $mail['recipient'] : ( isset( $mail['to'] ) ? (string) $mail['to'] : '' );
		$from = isset( $mail['sender'] ) ? (string) $mail['sender'] : ( isset( $mail['from'] ) ? (string) $mail['from'] : '' );

		$box = array(
			'to'                 => $to,
			'from'               => $from,
			'subject'            => isset( $mail['subject'] ) ? (string) $mail['subject'] : '',
			'body'               => isset( $mail['body'] ) ? (string) $mail['body'] : '',
			'reply_to'           => $reply,
			'additional_headers' => implode( "\n", $kept ),
		);

		if ( array_key_exists( 'active', $mail ) ) {
			$box['active'] = $mail['active'];
		} elseif ( array_key_exists( 'enabled', $mail ) ) {
			$box['enabled'] = $mail['enabled'];
		}

		return $box;
	}
}
