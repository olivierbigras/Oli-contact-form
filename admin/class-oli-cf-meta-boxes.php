<?php
/**
 * Meta boxes for the form editor.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta boxes class.
 */
class Oli_CF_Meta_Boxes {

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'oli_cf_form_editor',
			__( 'Fields', 'oli-contact-form' ),
			array( $this, 'render_form_editor' ),
			'oli_contact_form',
			'normal',
			'high'
		);

		add_meta_box(
			'oli_cf_notifications',
			__( 'Notifications', 'oli-contact-form' ),
			array( $this, 'render_notifications' ),
			'oli_contact_form',
			'normal',
			'high'
		);

		add_meta_box(
			'oli_cf_messages',
			__( 'Messages', 'oli-contact-form' ),
			array( $this, 'render_messages' ),
			'oli_contact_form',
			'normal',
			'default'
		);

		add_meta_box(
			'oli_cf_shortcode',
			__( 'Shortcode', 'oli-contact-form' ),
			array( $this, 'render_shortcode' ),
			'oli_contact_form',
			'side',
			'default'
		);
	}

	/**
	 * Render the field builder.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_form_editor( $post ) {
		wp_nonce_field( 'oli_cf_save_form', 'oli_cf_nonce' );

		$fields = Oli_CF_Fields::for_form( $post->ID );

		if ( empty( $fields ) ) {
			$fields = Oli_CF_Fields::defaults();
		}

		$fields[] = Oli_CF_Fields::blank();
		?>
		<div class="oli-cf-builder" id="oli-cf-fields" tabindex="-1">
			<p class="description">
				<?php esc_html_e( 'Add each field below. Required fields are marked on the form. Choices go on separate lines.', 'oli-contact-form' ); ?>
			</p>
			<div class="oli-cf-repeater" data-oli-repeater="fields">
				<?php foreach ( $fields as $index => $field ) : ?>
					<?php $this->render_field_row( $index, $field, false ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button oli-cf-add-row" data-oli-target="fields"><?php esc_html_e( 'Add field', 'oli-contact-form' ); ?></button>
			</p>
			<details class="oli-cf-advanced">
				<summary><?php esc_html_e( 'Advanced text definition', 'oli-contact-form' ); ?></summary>
				<p class="description">
					<?php esc_html_e( 'The field list above is what gets saved. You can replace it from this text form, for example {{field type="email" name="email" label="Email" required}}.', 'oli-contact-form' ); ?>
				</p>
				<textarea name="oli_cf_fields_markup" rows="8" class="large-text code"><?php echo esc_textarea( Oli_CF_Fields::to_markup( Oli_CF_Fields::for_form( $post->ID ) ? Oli_CF_Fields::for_form( $post->ID ) : Oli_CF_Fields::defaults() ) ); ?></textarea>
				<p>
					<label>
						<input type="checkbox" name="oli_cf_use_markup" value="1" />
						<?php esc_html_e( 'Replace the fields from this text definition on save', 'oli-contact-form' ); ?>
					</label>
				</p>
			</details>
			<?php $this->render_restore_control( $post->ID ); ?>
		</div>
		<?php
	}

	/**
	 * Render one field row.
	 *
	 * @param int                  $index    Row index.
	 * @param array<string, mixed> $field    Field.
	 * @param bool                 $template Whether this row is a clone template.
	 */
	private function render_field_row( $index, $field, $template ) {
		$name = $template ? '__INDEX__' : (string) $index;
		$type = isset( $field['type'] ) ? Oli_CF_Fields::normalize_type( $field['type'] ) : 'text';

		if ( ! in_array( $type, Oli_CF_Fields::types(), true ) ) {
			$type = 'text';
		}
		?>
		<div class="oli-cf-repeater-row oli-cf-type-<?php echo esc_attr( $type ); ?>">
			<p>
				<label>
					<?php esc_html_e( 'Type', 'oli-contact-form' ); ?>
					<select name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][type]">
						<?php foreach ( Oli_CF_Fields::type_labels() as $type_id => $type_label ) : ?>
							<option value="<?php echo esc_attr( $type_id ); ?>" <?php selected( $field['type'], $type_id ); ?>><?php echo esc_html( $type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Name', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][name]" value="<?php echo esc_attr( $field['name'] ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Label', 'oli-contact-form' ); ?>
					<input type="text" class="regular-text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" />
				</label>
				<label class="oli-cf-required-option">
					<input type="checkbox" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> />
					<?php esc_html_e( 'Required', 'oli-contact-form' ); ?>
				</label>
				<button type="button" class="button-link oli-cf-remove-row"><?php esc_html_e( 'Remove', 'oli-contact-form' ); ?></button>
			</p>
			<p>
				<label class="oli-cf-placeholder-options">
					<?php esc_html_e( 'Placeholder', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>" />
				</label>
				<label class="oli-cf-default-option">
					<?php esc_html_e( 'Default', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][default]" value="<?php echo esc_attr( $field['default'] ); ?>" />
				</label>
				<label class="oli-cf-length-options">
					<?php esc_html_e( 'Min length', 'oli-contact-form' ); ?>
					<input type="text" class="small-text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][minlength]" value="<?php echo esc_attr( $field['minlength'] ); ?>" />
				</label>
				<label class="oli-cf-length-options">
					<?php esc_html_e( 'Max length', 'oli-contact-form' ); ?>
					<input type="text" class="small-text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][maxlength]" value="<?php echo esc_attr( $field['maxlength'] ); ?>" />
				</label>
			</p>
			<p class="oli-cf-choice-options">
				<label>
					<?php esc_html_e( 'Choices (one per line)', 'oli-contact-form' ); ?>
					<textarea name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][options]" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $field['options'] ) ); ?></textarea>
				</label>
			</p>
			<p class="oli-cf-file-options">
				<label>
					<?php esc_html_e( 'File size limit', 'oli-contact-form' ); ?>
					<input type="text" class="small-text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][limit]" value="<?php echo esc_attr( $field['limit'] ); ?>" placeholder="2mb" />
				</label>
				<label>
					<?php esc_html_e( 'File types', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_fields[<?php echo esc_attr( $name ); ?>][filetypes]" value="<?php echo esc_attr( $field['filetypes'] ); ?>" placeholder="pdf|jpg" />
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the notification list.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_notifications( $post ) {
		$notifications   = Oli_CF_Notifications::for_editor( $post->ID );
		$notifications[] = Oli_CF_Notifications::blank();
		?>
		<div class="oli-cf-builder" id="oli-cf-notifications">
			<p class="description">
				<?php esc_html_e( 'Each notification is a separate email. Use tokens such as {field:email}, {site:title}, {site:admin_email}, and {submission:date}. Put the visitor address in Reply-To, not From, so mail is less likely to be rejected.', 'oli-contact-form' ); ?>
			</p>
			<div class="oli-cf-repeater" data-oli-repeater="notifications">
				<?php foreach ( $notifications as $index => $notification ) : ?>
					<?php $this->render_notification_row( $index, $notification ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button oli-cf-add-row" data-oli-target="notifications"><?php esc_html_e( 'Add notification', 'oli-contact-form' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render one notification.
	 *
	 * @param int                  $index        Row index.
	 * @param array<string, mixed> $notification Notification.
	 */
	private function render_notification_row( $index, $notification ) {
		$name      = (string) $index;
		$condition = isset( $notification['condition'] ) && is_array( $notification['condition'] ) ? $notification['condition'] : array();
		$operator  = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'always';

		if ( ! array_key_exists( $operator, Oli_CF_Notifications::operators() ) ) {
			$operator = 'always';
		}
		?>
		<fieldset class="oli-cf-repeater-row oli-cf-condition-<?php echo esc_attr( $operator ); ?>">
			<legend>
				<?php echo esc_html( '' !== $notification['label'] ? $notification['label'] : __( 'Notification', 'oli-contact-form' ) ); ?>
			</legend>
			<input type="hidden" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][id]" value="<?php echo esc_attr( $notification['id'] ); ?>" />
			<p>
				<label>
					<?php esc_html_e( 'Label', 'oli-contact-form' ); ?>
					<input type="text" class="regular-text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][label]" value="<?php echo esc_attr( $notification['label'] ); ?>" />
				</label>
				<label>
					<input type="checkbox" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][enabled]" value="1" <?php checked( ! empty( $notification['enabled'] ) ); ?> />
					<?php esc_html_e( 'Enabled', 'oli-contact-form' ); ?>
				</label>
				<button type="button" class="button-link oli-cf-remove-row"><?php esc_html_e( 'Remove', 'oli-contact-form' ); ?></button>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'To', 'oli-contact-form' ); ?>
					<input type="text" class="large-text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][to]" value="<?php echo esc_attr( $notification['to'] ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'From', 'oli-contact-form' ); ?>
					<input type="text" class="large-text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][from]" value="<?php echo esc_attr( $notification['from'] ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Reply-To', 'oli-contact-form' ); ?>
					<input type="text" class="large-text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][reply_to]" value="<?php echo esc_attr( $notification['reply_to'] ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Subject', 'oli-contact-form' ); ?>
					<input type="text" class="large-text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][subject]" value="<?php echo esc_attr( $notification['subject'] ); ?>" />
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Body', 'oli-contact-form' ); ?>
					<textarea name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][body]" rows="6" class="large-text code"><?php echo esc_textarea( $notification['body'] ); ?></textarea>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Extra headers', 'oli-contact-form' ); ?>
					<textarea name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][headers]" rows="2" class="large-text code"><?php echo esc_textarea( $notification['headers'] ); ?></textarea>
				</label>
			</p>
			<p class="oli-cf-condition">
				<label>
					<?php esc_html_e( 'Condition', 'oli-contact-form' ); ?>
					<select name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][condition_operator]">
						<?php foreach ( Oli_CF_Notifications::operators() as $operator_id => $operator_label ) : ?>
							<option value="<?php echo esc_attr( $operator_id ); ?>" <?php selected( $operator, $operator_id ); ?>><?php echo esc_html( $operator_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="oli-cf-condition-field">
					<?php esc_html_e( 'Field name', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][condition_field]" value="<?php echo esc_attr( isset( $condition['field'] ) ? $condition['field'] : '' ); ?>" />
				</label>
				<label class="oli-cf-condition-value">
					<?php esc_html_e( 'Value', 'oli-contact-form' ); ?>
					<input type="text" name="oli_cf_notifications[<?php echo esc_attr( $name ); ?>][condition_value]" value="<?php echo esc_attr( isset( $condition['value'] ) ? $condition['value'] : '' ); ?>" />
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Offer a restore when an original copy was saved.
	 *
	 * @param int $post_id Form ID.
	 */
	private function render_restore_control( $post_id ) {
		$backup = get_post_meta( $post_id, '_oli_cf_legacy_template', true );

		if ( ! is_string( $backup ) || '' === $backup ) {
			return;
		}

		$action = admin_url( 'admin-post.php' );
		?>
		<p>
			<button type="submit" class="button" form="oli-cf-restore-form" formaction="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Restore the original form', 'oli-contact-form' ); ?></button>
			<span class="description"><?php esc_html_e( 'Rebuilds the fields and notifications from the copy saved before the update. The copy itself is kept.', 'oli-contact-form' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Print the restore form outside the editor form.
	 *
	 * The field meta box is rendered inside form#post. A nested form is discarded
	 * by the browser, so the button above submits this one through the form attribute.
	 */
	public function render_restore_form() {
		if ( ! $this->is_form_edit_screen() ) {
			return;
		}

		$post_id = $this->edited_form_id();
		$backup  = $post_id ? get_post_meta( $post_id, '_oli_cf_legacy_template', true ) : '';

		if ( ! is_string( $backup ) || '' === $backup ) {
			return;
		}

		$action = admin_url( 'admin-post.php' );
		?>
		<form id="oli-cf-restore-form" method="post" action="<?php echo esc_url( $action ); ?>" data-oli-confirm="<?php echo esc_attr__( 'Restore the fields and notifications from the saved copy?', 'oli-contact-form' ); ?>">
			<?php wp_nonce_field( 'oli_cf_restore_form_' . $post_id ); ?>
			<input type="hidden" name="action" value="oli_cf_restore_form" />
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) absint( $post_id ) ); ?>" />
		</form>
		<?php
	}

	/**
	 * Whether the current admin screen edits one form.
	 *
	 * @return bool
	 */
	private function is_form_edit_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return is_object( $screen )
			&& isset( $screen->base, $screen->post_type )
			&& 'post' === $screen->base
			&& 'oli_contact_form' === $screen->post_type;
	}

	/**
	 * Form being edited, when the screen is the form editor.
	 *
	 * @return int
	 */
	private function edited_form_id() {
		global $post;

		if ( is_object( $post ) && isset( $post->ID ) ) {
			return absint( $post->ID );
		}

		return 0;
	}

	/**
	 * Render messages meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_messages( $post ) {
		$messages = Oli_CF_Validator::normalize_messages( get_post_meta( $post->ID, '_oli_cf_messages', true ) );
		$labels   = array(
			'success'          => __( 'Success message', 'oli-contact-form' ),
			'send_error'       => __( 'Send error', 'oli-contact-form' ),
			'validation_error' => __( 'Validation error', 'oli-contact-form' ),
			'required'         => __( 'Required field', 'oli-contact-form' ),
			'email'            => __( 'Invalid email', 'oli-contact-form' ),
			'phone'            => __( 'Invalid phone', 'oli-contact-form' ),
			'url'              => __( 'Invalid URL', 'oli-contact-form' ),
			'number'           => __( 'Invalid number', 'oli-contact-form' ),
			'date'             => __( 'Invalid date', 'oli-contact-form' ),
			'too_long'         => __( 'Too long', 'oli-contact-form' ),
			'too_short'        => __( 'Too short', 'oli-contact-form' ),
			'acceptance'       => __( 'Acceptance required', 'oli-contact-form' ),
			'upload_failed'    => __( 'Upload failed', 'oli-contact-form' ),
			'file_too_large'   => __( 'File too large', 'oli-contact-form' ),
			'file_type'        => __( 'File type not allowed', 'oli-contact-form' ),
		);
		?>
		<table class="form-table">
			<?php foreach ( $labels as $key => $label ) : ?>
				<tr>
					<th><label for="oli-cf-msg-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" name="oli_cf_messages[<?php echo esc_attr( $key ); ?>]" id="oli-cf-msg-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $messages[ $key ] ); ?>" class="large-text" /></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Render shortcode meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_shortcode( $post ) {
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Publish the form to get the shortcode.', 'oli-contact-form' ) . '</p>';
			return;
		}
		?>
		<p>
			<code>[oli_contact_form id="<?php echo esc_html( (string) absint( $post->ID ) ); ?>"]</code>
		</p>
		<p class="description">
			<?php esc_html_e( 'Copy this shortcode and paste it into your post, page, or text widget.', 'oli-contact-form' ); ?>
		</p>
		<?php
	}

	/**
	 * Save form data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_form_data( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['oli_cf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_cf_nonce'] ) ), 'oli_cf_save_form' ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || 'oli_contact_form' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$use_markup = isset( $_POST['oli_cf_use_markup'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['oli_cf_use_markup'] ) );

		if ( $use_markup && isset( $_POST['oli_cf_fields_markup'] ) ) {
			$fields = Oli_CF_Fields::from_markup( sanitize_textarea_field( wp_unslash( $_POST['oli_cf_fields_markup'] ) ) );
		} else {
			$posted = isset( $_POST['oli_cf_fields'] ) ? map_deep( wp_unslash( $_POST['oli_cf_fields'] ), 'sanitize_textarea_field' ) : array();
			$fields = Oli_CF_Fields::from_posted( is_array( $posted ) ? $posted : array() );
		}

		Oli_CF_Fields::save( $post_id, $fields );
		update_post_meta( $post_id, '_oli_cf_schema', Oli_CF_Fields::SCHEMA );

		$posted_notifications = isset( $_POST['oli_cf_notifications'] ) ? map_deep( wp_unslash( $_POST['oli_cf_notifications'] ), 'sanitize_textarea_field' ) : array();
		$notifications        = Oli_CF_Notifications::from_posted( is_array( $posted_notifications ) ? $posted_notifications : array() );
		Oli_CF_Notifications::save( $post_id, $notifications );

		if ( isset( $_POST['oli_cf_messages'] ) ) {
			$messages = map_deep( wp_unslash( $_POST['oli_cf_messages'] ), 'sanitize_text_field' );
			update_post_meta( $post_id, '_oli_cf_messages', Oli_CF_Validator::normalize_messages( is_array( $messages ) ? $messages : array() ) );
		}
	}

	/**
	 * Rebuild one form from the copy saved before the update.
	 */
	public function restore_form() {
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $form_id || ! wp_verify_nonce( $nonce, 'oli_cf_restore_form_' . $form_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-contact-form' ) );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ) );
		}

		$restored = Oli_CF_Upgrade::restore_form( $form_id );
		$link     = function_exists( 'get_edit_post_link' ) ? get_edit_post_link( $form_id, 'raw' ) : '';

		if ( ! is_string( $link ) || '' === $link ) {
			$link = admin_url( 'post.php?post=' . $form_id . '&action=edit' );
		}

		$link = add_query_arg(
			array(
				'oli_cf_restored'       => $restored ? '1' : '0',
				'oli_cf_restored_nonce' => wp_create_nonce( 'oli_cf_restored_' . $form_id ),
			),
			$link
		);

		wp_safe_redirect( $link . '#oli-cf-fields' );
		exit;
	}

	/**
	 * Confirm a restore when the editor opens again.
	 */
	public function maybe_restore_notice() {
		if ( ! is_admin() ) {
			return;
		}

		$restored = filter_input( INPUT_GET, 'oli_cf_restored', FILTER_SANITIZE_NUMBER_INT );
		$nonce    = filter_input( INPUT_GET, 'oli_cf_restored_nonce' );
		$post_id  = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );
		$nonce    = is_string( $nonce ) ? sanitize_text_field( $nonce ) : '';

		if ( '1' !== (string) $restored || ! $post_id || ! wp_verify_nonce( $nonce, 'oli_cf_restored_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'The original form was restored.', 'oli-contact-form' );
		echo '</p></div>';
	}
}
