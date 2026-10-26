<?php
/**
 * Import Contact Form 7 forms.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importer class.
 */
class Oli_CF_Importer {

	/**
	 * Add importer page to admin menu.
	 */
	public function add_importer_page() {
		add_submenu_page(
			'edit.php?post_type=oli_contact_form',
			__( 'Import from CF7', 'oli-contact-form' ),
			__( 'Import from CF7', 'oli-contact-form' ),
			'manage_options',
			'oli-cf-import-cf7',
			array( $this, 'render_importer_page' )
		);
	}

	/**
	 * Enqueue the importer "select all" script on the CF7 import screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( false === strpos( $hook, 'oli-cf-import-cf7' ) ) {
			return;
		}

		wp_enqueue_script(
			'oli-cf-importer',
			OLI_CF_PLUGIN_URL . 'assets/js/oli-cf-importer.js',
			array(),
			OLI_CF_VERSION,
			true
		);
	}

	/**
	 * Render importer page.
	 */
	public function render_importer_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$cf7_forms = $this->get_cf7_forms();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import from Contact Form 7', 'oli-contact-form' ); ?></h1>

			<?php if ( empty( $cf7_forms ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No Contact Form 7 forms found. Please make sure Contact Form 7 is installed and you have created some forms.', 'oli-contact-form' ); ?></p>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'This tool will import Contact Form 7 forms into Oli Contact Form. Form tags, mail settings, and messages will be converted.', 'oli-contact-form' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'oli_cf_import_cf7', 'oli_cf_import_nonce' ); ?>
					<input type="hidden" name="action" value="oli_cf_import_cf7" />

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox" id="oli-cf-select-all" /></th>
								<th><?php esc_html_e( 'Form Title', 'oli-contact-form' ); ?></th>
								<th><?php esc_html_e( 'ID', 'oli-contact-form' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cf7_forms as $form ) : ?>
								<tr>
									<td class="check-column">
										<input type="checkbox" name="import_forms[]" value="<?php echo esc_attr( (string) absint( $form->ID ) ); ?>" class="oli-cf-import-checkbox" />
									</td>
									<td><?php echo esc_html( $form->post_title ); ?></td>
									<td><?php echo esc_html( (string) absint( $form->ID ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p>
						<?php submit_button( __( 'Import Selected Forms', 'oli-contact-form' ), 'primary', 'submit', false ); ?>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle import.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ) );
		}

		if ( ! isset( $_POST['oli_cf_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_cf_import_nonce'] ) ), 'oli_cf_import_cf7' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-contact-form' ) );
		}

		$form_ids = isset( $_POST['import_forms'] ) ? array_map( 'absint', wp_unslash( $_POST['import_forms'] ) ) : array();

		if ( empty( $form_ids ) ) {
			wp_safe_redirect( add_query_arg( 'oli_cf_imported', 0, admin_url( 'edit.php?post_type=oli_contact_form&page=oli-cf-import-cf7' ) ) );
			exit;
		}

		$imported_count = 0;

		foreach ( $form_ids as $form_id ) {
			if ( $this->import_cf7_form( $form_id ) ) {
				++$imported_count;
			}
		}

		wp_safe_redirect( add_query_arg( 'oli_cf_imported', $imported_count, admin_url( 'edit.php?post_type=oli_contact_form' ) ) );
		exit;
	}

	/**
	 * Get CF7 forms.
	 *
	 * @return WP_Post[]
	 */
	private function get_cf7_forms() {
		return get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Import a single CF7 form.
	 *
	 * @param int $cf7_form_id CF7 form ID.
	 * @return bool True on success, false on failure.
	 */
	private function import_cf7_form( $cf7_form_id ) {
		$cf7_form = get_post( $cf7_form_id );

		if ( ! $cf7_form || 'wpcf7_contact_form' !== $cf7_form->post_type ) {
			return false;
		}

		$new_form_id = wp_insert_post(
			array(
				'post_title'  => $cf7_form->post_title,
				'post_type'   => 'oli_contact_form',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $new_form_id ) || ! $new_form_id ) {
			return false;
		}

		$cf7_form_data = get_post_meta( $cf7_form_id, '_form', true );
		$cf7_mail      = get_post_meta( $cf7_form_id, '_mail', true );
		$cf7_mail_2    = get_post_meta( $cf7_form_id, '_mail_2', true );
		$cf7_messages  = get_post_meta( $cf7_form_id, '_messages', true );

		update_post_meta( $new_form_id, '_oli_cf_form_template', self::convert_template( $cf7_form_data ) );
		update_post_meta( $new_form_id, '_oli_cf_mail', self::convert_mail( $cf7_mail, false ) );
		update_post_meta( $new_form_id, '_oli_cf_mail_2', self::convert_mail( $cf7_mail_2, true ) );
		update_post_meta( $new_form_id, '_oli_cf_messages', self::convert_messages( $cf7_messages ) );
		update_post_meta( $new_form_id, '_oli_cf_imported_from_cf7', absint( $cf7_form_id ) );

		return true;
	}

	/**
	 * Convert a CF7 form template to Oli Contact Form tags.
	 *
	 * @param string $cf7_template CF7 template.
	 * @return string
	 */
	public static function convert_template( $cf7_template ) {
		$template = Oli_CF_Form_Parser::normalize_template( $cf7_template );

		$template = preg_replace_callback(
			'/\[([^\]]+)\]/',
			function ( $matches ) {
				$converted = self::convert_form_tag( $matches[1] );

				if ( '' === $converted ) {
					return '';
				}

				return '[' . $converted . ']';
			},
			$template
		);

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Convert one CF7 form tag (without brackets).
	 *
	 * @param string $tag Tag content.
	 * @return string
	 */
	public static function convert_form_tag( $tag ) {
		$tokens = Oli_CF_Form_Parser::tokenize( $tag );

		if ( empty( $tokens ) ) {
			return $tag;
		}

		$type_raw = $tokens[0];
		$star     = ( substr( $type_raw, -1 ) === '*' );
		$type     = strtolower( rtrim( $type_raw, '*' ) );

		if ( '' !== $type && '/' === $type[0] ) {
			return '';
		}

		$map = array(
			'text'       => 'text',
			'email'      => 'email',
			'tel'        => 'tel',
			'tel*'       => 'tel',
			'phone'      => 'tel',
			'url'        => 'url',
			'number'     => 'number',
			'date'       => 'date',
			'textarea'   => 'textarea',
			'select'     => 'select',
			'checkbox'   => 'checkbox',
			'radio'      => 'radio',
			'acceptance' => 'acceptance',
			'file'       => 'file',
			'submit'     => 'submit',
			'captcha'    => '',
			'recaptcha'  => '',
			'quiz'       => '',
			'response'   => '',
		);

		if ( ! isset( $map[ $type ] ) ) {
			return $tag;
		}

		if ( '' === $map[ $type ] ) {
			return '';
		}

		$tokens[0] = $map[ $type ] . ( $star ? '*' : '' );
		$out       = array();
		$has_name  = ( 'submit' !== $map[ $type ] );

		foreach ( $tokens as $index => $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( 0 === strpos( $token, 'akismet:' ) ) {
				continue;
			}

			if ( 0 === strpos( $token, 'watermark:' ) ) {
				$token = 'placeholder:' . substr( $token, 10 );
			}

			if ( 0 === $index ) {
				$out[] = $token;
				continue;
			}

			if ( $has_name && false === strpos( $token, ':' ) ) {
				$out[]    = $token;
				$has_name = false;
				continue;
			}

			if ( 'optional' === strtolower( $token ) ) {
				$out[] = 'optional';
				continue;
			}

			if ( preg_match( '/^([a-z0-9_-]+):(.*)$/i', $token, $attr ) ) {
				$key = strtolower( $attr[1] );
				$val = $attr[2];

				if ( 'file' === $map[ $type ] && 'filetypes' === $key ) {
					$val = self::normalize_filetypes( $val );

					if ( '' === $val ) {
						continue;
					}
				}

				$out[] = $key . ':' . $val;
				continue;
			}

			$out[] = '"' . $token . '"';
		}

		return implode( ' ', $out );
	}

	/**
	 * Normalize a CF7 filetypes option to pipe-separated extensions.
	 *
	 * @param string $filetypes Raw filetypes value.
	 * @return string
	 */
	public static function normalize_filetypes( $filetypes ) {
		$filetypes = strtolower( trim( (string) $filetypes ) );

		$aliases = array(
			'image/*' => 'jpg|jpeg|png|gif|webp',
			'audio/*' => 'mp3|wav|ogg|m4a',
			'video/*' => 'mp4|mov|avi|mpg|wmv',
		);

		foreach ( $aliases as $alias => $exts ) {
			$filetypes = str_replace( $alias, $exts, $filetypes );
		}

		$parts = preg_split( '/[|,;\s]+/', $filetypes );
		$clean = array();

		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				$part = ltrim( trim( $part ), '.' );
				$part = preg_replace( '/[^a-z0-9]/', '', $part );

				if ( '' === $part || in_array( $part, Oli_CF_Uploads::BLOCKED_EXTENSIONS, true ) ) {
					continue;
				}

				$clean[] = $part;
			}
		}

		return implode( '|', array_values( array_unique( $clean ) ) );
	}

	/**
	 * Convert CF7 mail settings.
	 *
	 * @param mixed $cf7_mail CF7 mail settings.
	 * @param bool  $is_mail_2 Is this mail 2.
	 * @return array<string, mixed>
	 */
	public static function convert_mail( $cf7_mail, $is_mail_2 = false ) {
		$empty = array(
			'to'                 => $is_mail_2 ? '' : ( function_exists( 'get_option' ) ? get_option( 'admin_email' ) : '' ),
			'from'               => '',
			'subject'            => '',
			'body'               => '',
			'reply_to'           => '',
			'additional_headers' => '',
		);

		if ( $is_mail_2 ) {
			$empty['enabled'] = false;
		}

		if ( ! is_array( $cf7_mail ) || empty( $cf7_mail ) ) {
			return $empty;
		}

		$headers = isset( $cf7_mail['additional_headers'] ) ? (string) $cf7_mail['additional_headers'] : '';
		$reply   = '';

		if ( preg_match( '/^Reply-To:\s*(.+)$/mi', $headers, $matches ) ) {
			$reply   = trim( $matches[1] );
			$headers = trim( preg_replace( '/^Reply-To:\s*.+$/mi', '', $headers ) );
		}

		$mail = array(
			'to'                 => isset( $cf7_mail['recipient'] ) ? (string) $cf7_mail['recipient'] : $empty['to'],
			'from'               => isset( $cf7_mail['sender'] ) ? (string) $cf7_mail['sender'] : '',
			'subject'            => isset( $cf7_mail['subject'] ) ? (string) $cf7_mail['subject'] : '',
			'body'               => isset( $cf7_mail['body'] ) ? (string) $cf7_mail['body'] : '',
			'reply_to'           => $reply,
			'additional_headers' => $headers,
		);

		if ( $is_mail_2 ) {
			$mail['enabled'] = ! empty( $cf7_mail['active'] );
		}

		return $mail;
	}

	/**
	 * Convert CF7 messages.
	 *
	 * @param mixed $cf7_messages CF7 messages.
	 * @return array<string, string>
	 */
	public static function convert_messages( $cf7_messages ) {
		$defaults = Oli_CF_Validator::default_messages();

		if ( ! is_array( $cf7_messages ) ) {
			return $defaults;
		}

		$map = array(
			'mail_sent_ok'             => 'mail_sent_ok',
			'mail_sent_ng'             => 'mail_sent_ng',
			'validation_error'         => 'validation_error',
			'invalid_required'         => 'invalid_required',
			'invalid_email'            => 'invalid_email',
			'invalid_tel'              => 'invalid_tel',
			'invalid_url'              => 'invalid_url',
			'invalid_number'           => 'invalid_number',
			'invalid_date'             => 'invalid_date',
			'invalid_too_long'         => 'invalid_too_long',
			'invalid_too_short'        => 'invalid_too_short',
			'accept_terms'             => 'accept_terms',
			'upload_failed'            => 'upload_failed',
			'upload_file_type_invalid' => 'upload_file_type',
			'upload_file_too_large'    => 'upload_file_too_large',
		);

		foreach ( $map as $cf7_key => $oli_key ) {
			if ( isset( $cf7_messages[ $cf7_key ] ) && is_string( $cf7_messages[ $cf7_key ] ) && '' !== trim( $cf7_messages[ $cf7_key ] ) ) {
				$defaults[ $oli_key ] = $cf7_messages[ $cf7_key ];
			}
		}

		return $defaults;
	}
}
