<?php
/**
 * Import existing forms into the field builder.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import screen: preview, then create drafts.
 */
class Oli_CF_Importer {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE = 'oli-cf-import';

	/**
	 * Add the import page.
	 */
	public function add_importer_page() {
		add_submenu_page(
			'edit.php?post_type=oli_contact_form',
			__( 'Import existing forms', 'oli-contact-form' ),
			__( 'Import', 'oli-contact-form' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Send a bookmarked old address to the new page.
	 */
	public function redirect_legacy_page() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page   = sanitize_key( (string) filter_input( INPUT_GET, 'page' ) );
		$legacy = Oli_CF_Import_Registry::legacy_page_slug();

		if ( '' === $legacy || $page !== $legacy ) {
			return;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=oli_contact_form&page=' . self::PAGE ) );
		exit;
	}

	/**
	 * An old form post no longer imports. It opens the new screen instead.
	 */
	public function handle_legacy_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ) );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=oli_contact_form&page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Scripts for the form list.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
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
	 * Render the import screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$step    = sanitize_key( (string) filter_input( INPUT_GET, 'step' ) );
		$token   = sanitize_key( (string) filter_input( INPUT_GET, 'token' ) );
		$preview = ( 'preview' === $step ) ? $this->preview_payload( $token ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import existing forms', 'oli-contact-form' ); ?></h1>
			<?php $this->render_notices( $step, $preview ); ?>
			<?php if ( ! empty( $preview ) ) : ?>
				<?php $this->render_preview( $token, $preview ); ?>
			<?php else : ?>
				<?php $this->render_picker(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Errors for an empty selection or an expired preview.
	 *
	 * @param string               $step    Requested step.
	 * @param array<string, mixed> $preview Stored preview, when it is still valid.
	 */
	private function render_notices( $step, $preview ) {
		$error     = sanitize_key( (string) filter_input( INPUT_GET, 'oli_cf_import_error' ) );
		$nonce_raw = filter_input( INPUT_GET, 'oli_cf_import_error_nonce' );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( $nonce_raw ) : '';

		if ( 'empty' === $error && wp_verify_nonce( $nonce, 'oli_cf_import_error' ) ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Select at least one form to preview.', 'oli-contact-form' );
			echo '</p></div>';
		}

		if ( 'preview' === $step && empty( $preview ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'This preview has expired. Select the forms again.', 'oli-contact-form' );
			echo '</p></div>';
		}
	}

	/**
	 * Source and form picker.
	 */
	private function render_picker() {
		$sources = Oli_CF_Import_Registry::available();

		if ( empty( $sources ) ) {
			echo '<p>' . esc_html__( 'No existing forms were found.', 'oli-contact-form' ) . '</p>';
			return;
		}

		$selected = sanitize_key( (string) filter_input( INPUT_GET, 'source' ) );

		if ( '' === $selected ) {
			$selected = $sources[0]->key();
		}
		$source = Oli_CF_Import_Registry::get( $selected );

		if ( ! $source || ! $source->is_available() ) {
			$source = $sources[0];
		}
		?>
		<p><?php esc_html_e( 'Choose a source, review the fields that cannot be converted, then import the forms you want as drafts.', 'oli-contact-form' ); ?></p>
		<ul class="subsubsub">
			<?php foreach ( $sources as $index => $item ) : ?>
				<?php
				$source_url = add_query_arg(
					array(
						'post_type' => 'oli_contact_form',
						'page'      => self::PAGE,
						'source'    => $item->key(),
					),
					admin_url( 'edit.php' )
				);
				?>
				<li>
					<a href="<?php echo esc_url( $source_url ); ?>" <?php echo $item->key() === $source->key() ? 'class="current" aria-current="page"' : ''; ?>>
						<?php echo esc_html( $item->label() ); ?>
					</a>
					<?php echo ( $index < count( $sources ) - 1 ) ? ' | ' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'oli_cf_import_preview', 'oli_cf_import_nonce' ); ?>
			<input type="hidden" name="action" value="oli_cf_import_preview" />
			<input type="hidden" name="oli_cf_source" value="<?php echo esc_attr( $source->key() ); ?>" />
			<?php $forms = $source->list_forms(); ?>
			<?php if ( empty( $forms ) ) : ?>
				<p><?php esc_html_e( 'This source has no forms to import.', 'oli-contact-form' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="oli-cf-import-all" aria-label="<?php esc_attr_e( 'Select all forms', 'oli-contact-form' ); ?>" /></td>
							<th><?php esc_html_e( 'Form', 'oli-contact-form' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $forms as $form ) : ?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="import_forms[]" value="<?php echo esc_attr( $form['id'] ); ?>" class="oli-cf-import-checkbox" />
								</th>
								<td><?php echo esc_html( $form['title'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><?php submit_button( __( 'Preview selected forms', 'oli-contact-form' ), 'primary', 'submit', false ); ?></p>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Preview of the forms about to be imported.
	 *
	 * @param string               $token   Preview token.
	 * @param array<string, mixed> $payload Stored preview.
	 */
	private function render_preview( $token, $payload ) {
		$items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'oli_cf_import_run', 'oli_cf_import_nonce' ); ?>
			<input type="hidden" name="action" value="oli_cf_import_run" />
			<input type="hidden" name="oli_cf_import_token" value="<?php echo esc_attr( $token ); ?>" />
			<?php foreach ( $items as $item ) : ?>
				<h2><?php echo esc_html( isset( $item['title'] ) ? (string) $item['title'] : '' ); ?></h2>
				<?php $unsupported = isset( $item['unsupported'] ) && is_array( $item['unsupported'] ) ? $item['unsupported'] : array(); ?>
				<?php if ( empty( $unsupported ) ) : ?>
					<p><?php esc_html_e( 'Every field can be imported.', 'oli-contact-form' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'These fields will be left out:', 'oli-contact-form' ); ?></p>
					<ul>
						<?php foreach ( $unsupported as $field ) : ?>
							<li><?php echo esc_html( trim( ( isset( $field['label'] ) ? (string) $field['label'] : '' ) . ' (' . ( isset( $field['type'] ) ? (string) $field['type'] : '' ) . ')' ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endforeach; ?>
			<p><?php submit_button( __( 'Import as drafts', 'oli-contact-form' ), 'primary', 'submit', false ); ?></p>
		</form>
		<?php
	}

	/**
	 * Store a preview and open it.
	 */
	public function handle_preview() {
		if ( ! isset( $_POST['oli_cf_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_cf_import_nonce'] ) ), 'oli_cf_import_preview' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-contact-form' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ) );
		}

		$source_key = isset( $_POST['oli_cf_source'] ) ? sanitize_key( wp_unslash( $_POST['oli_cf_source'] ) ) : '';
		$source     = Oli_CF_Import_Registry::get( $source_key );
		$ids        = isset( $_POST['import_forms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['import_forms'] ) ) : array();
		$items      = array();

		if ( $source && is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$converted = $source->convert( $id );

				if ( is_array( $converted ) ) {
					$items[] = $converted;
				}
			}
		}

		if ( empty( $items ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type'                 => 'oli_contact_form',
						'page'                      => self::PAGE,
						'source'                    => $source_key,
						'oli_cf_import_error'       => 'empty',
						'oli_cf_import_error_nonce' => wp_create_nonce( 'oli_cf_import_error' ),
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		$token = wp_generate_password( 20, false );
		set_transient(
			$this->transient_key(),
			array(
				'token'  => $token,
				'source' => $source_key,
				'items'  => $items,
			),
			15 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'oli_contact_form',
					'page'      => self::PAGE,
					'step'      => 'preview',
					'token'     => $token,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Create drafts from the stored preview.
	 */
	public function handle_run() {
		if ( ! isset( $_POST['oli_cf_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_cf_import_nonce'] ) ), 'oli_cf_import_run' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-contact-form' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ) );
		}

		$token   = isset( $_POST['oli_cf_import_token'] ) ? sanitize_key( wp_unslash( $_POST['oli_cf_import_token'] ) ) : '';
		$payload = $this->preview_payload( $token );
		$count   = 0;
		$skipped = 0;

		if ( ! empty( $payload['items'] ) && is_array( $payload['items'] ) ) {
			$source = Oli_CF_Import_Registry::get( isset( $payload['source'] ) ? (string) $payload['source'] : '' );

			foreach ( $payload['items'] as $item ) {
				if ( ! is_array( $item ) || ! $source ) {
					continue;
				}

				if ( $this->already_imported( $source->key(), isset( $item['source_id'] ) ? (string) $item['source_id'] : '' ) ) {
					++$skipped;
					continue;
				}

				if ( $this->insert_draft( $source->key(), $item ) ) {
					++$count;
				}
			}

			delete_transient( $this->transient_key() );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'             => 'oli_contact_form',
					'oli_cf_imported'       => (int) $count,
					'oli_cf_import_skipped' => (int) $skipped,
					'oli_cf_imported_nonce' => wp_create_nonce( 'oli_cf_imported' ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Preview stored for the current user.
	 *
	 * @param string $token Token from the URL.
	 * @return array<string, mixed>
	 */
	private function preview_payload( $token ) {
		$stored = get_transient( $this->transient_key() );

		if ( ! is_array( $stored ) || ! isset( $stored['token'] ) || ! hash_equals( (string) $stored['token'], (string) $token ) ) {
			return array();
		}

		return $stored;
	}

	/**
	 * Transient name for the current user.
	 *
	 * @return string
	 */
	private function transient_key() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return 'oli_cf_import_' . $user_id;
	}

	/**
	 * Whether this source form was already imported.
	 *
	 * @param string $source_key Source key.
	 * @param string $source_id  Source form ID.
	 * @return bool
	 */
	private function already_imported( $source_key, $source_id ) {
		if ( '' === $source_id || ! function_exists( 'get_posts' ) ) {
			return false;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'oli_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- duplicate check before creating an imported draft.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_oli_cf_imported_from',
						'value' => $source_key,
					),
					array(
						'key'   => '_oli_cf_import_source_id',
						'value' => $source_id,
					),
				),
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Insert one draft. The source record is not modified.
	 *
	 * @param string               $source_key Source key.
	 * @param array<string, mixed> $item       Converted form.
	 * @return bool
	 */
	private function insert_draft( $source_key, $item ) {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return false;
		}

		$title   = isset( $item['title'] ) ? (string) $item['title'] : __( 'Imported form', 'oli-contact-form' );
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'oli_contact_form',
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		Oli_CF_Fields::save( $post_id, isset( $item['fields'] ) ? $item['fields'] : array() );
		Oli_CF_Notifications::save( $post_id, isset( $item['notifications'] ) ? $item['notifications'] : array() );
		update_post_meta( $post_id, '_oli_cf_messages', Oli_CF_Validator::normalize_messages( isset( $item['messages'] ) ? $item['messages'] : array() ) );
		update_post_meta( $post_id, '_oli_cf_schema', Oli_CF_Fields::SCHEMA );
		update_post_meta( $post_id, '_oli_cf_imported_from', $source_key );
		update_post_meta( $post_id, '_oli_cf_import_source_id', isset( $item['source_id'] ) ? (string) $item['source_id'] : '' );

		return true;
	}
}
