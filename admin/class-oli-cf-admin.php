<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Oli_CF_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		if ( 'oli_contact_form' === $screen->post_type ) {
			return true;
		}

		$extra_screens = array(
			'oli_contact_form_page_oli-cf-settings',
			'oli-contact-forms_page_oli-cf-settings',
			'oli_contact_form_page_oli-cf-import',
			'oli-contact-forms_page_oli-cf-import',
		);

		if ( class_exists( 'Oli_CF_Import_Registry' ) ) {
			$extra_screens = array_merge( $extra_screens, Oli_CF_Import_Registry::legacy_screen_ids() );
		}

		return in_array( $screen->id, $extra_screens, true );
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			OLI_CF_PLUGIN_URL . 'assets/css/oli-cf-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen || 'oli_contact_form' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			OLI_CF_PLUGIN_URL . 'assets/js/oli-cf-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'oliCfAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'dismissNonce' => wp_create_nonce( 'oli_cf_dismiss_migration' ),
				'strings'      => array(
					'remove' => __( 'Remove', 'oli-contact-form' ),
				),
			)
		);
	}

	/**
	 * Show a dismissible notice when file uploads cannot be staged privately.
	 */
	public function maybe_staging_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$flagged = Oli_CF_Uploads::has_unavailable_notice();
		$live    = $this->is_plugin_screen() && '' === Oli_CF_Uploads::staging_parent();

		if ( ! $flagged && ! $live ) {
			return;
		}

		$dismiss = '';

		if ( function_exists( 'wp_nonce_url' ) && function_exists( 'add_query_arg' ) ) {
			$dismiss = wp_nonce_url( add_query_arg( 'oli_cf_dismiss_staging', '1' ), 'oli_cf_dismiss_staging' );
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html( Oli_CF_Uploads::admin_notice_message() );

		if ( '' !== $dismiss ) {
			echo ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'oli-contact-form' ) . '</a>';
		}

		echo '</p></div>';
	}

	/**
	 * Persistently dismiss the staging notice.
	 */
	public function maybe_dismiss_staging_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['oli_cf_dismiss_staging'], $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oli_cf_dismiss_staging' ) ) {
			return;
		}

		Oli_CF_Uploads::clear_unavailable_notice();

		if ( function_exists( 'wp_get_referer' ) && function_exists( 'wp_safe_redirect' ) && function_exists( 'remove_query_arg' ) ) {
			$target = wp_get_referer();

			if ( ! is_string( $target ) || '' === $target ) {
				$target = function_exists( 'admin_url' ) ? admin_url() : '/';
			}

			wp_safe_redirect( remove_query_arg( array( 'oli_cf_dismiss_staging', '_wpnonce' ), $target ) );
			exit;
		}
	}

	/**
	 * Show how many forms the last update converted.
	 */
	public function maybe_migration_notice() {
		if ( ! $this->is_plugin_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), 'oli_cf_migration_dismissed', true ) || get_option( 'oli_cf_migration_report_dismissed' ) ) {
			return;
		}

		$report = get_option( Oli_CF_Upgrade::REPORT, array() );

		if ( ! is_array( $report ) || empty( $report['converted'] ) ) {
			return;
		}

		$dismiss = wp_nonce_url(
			add_query_arg( 'oli_cf_dismiss_migration', '1' ),
			'oli_cf_dismiss_migration'
		);
		?>
		<div class="notice notice-info is-dismissible" data-oli-dismiss="migration">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of forms converted, 2: number of fields, 3: number of notifications. */
						__( 'Form update: %1$d forms now use the field builder (%2$d fields, %3$d notifications). The previous content was kept so it can be restored.', 'oli-contact-form' ),
						(int) $report['converted'],
						(int) $report['fields'],
						(int) $report['notifications']
					)
				);
				?>
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'oli-contact-form' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Hide the migration report.
	 */
	public function maybe_dismiss_migration_notice() {
		if ( ! isset( $_GET['oli_cf_dismiss_migration'], $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oli_cf_dismiss_migration' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), 'oli_cf_migration_dismissed', '1' );

		if ( function_exists( 'wp_get_referer' ) && function_exists( 'wp_safe_redirect' ) && function_exists( 'remove_query_arg' ) ) {
			$target = wp_get_referer();

			if ( ! is_string( $target ) || '' === $target ) {
				$target = function_exists( 'admin_url' ) ? admin_url() : '/';
			}

			wp_safe_redirect( remove_query_arg( array( 'oli_cf_dismiss_migration', '_wpnonce' ), $target ) );
			exit;
		}
	}

	/**
	 * Warn when a field label lost a link that was not a full http(s) URL.
	 *
	 * The list is a per-user transient written during save. It is shown once,
	 * on the next admin page load, and then deleted.
	 */
	public function maybe_label_link_notice() {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_transient' ) ) {
			return;
		}

		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		$key    = Oli_CF_Fields::label_link_notice_key( $user_id );
		$labels = get_transient( $key );

		if ( ! is_array( $labels ) || ! $labels ) {
			return;
		}

		delete_transient( $key );

		$messages = array();

		foreach ( $labels as $label ) {
			$label = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $label ) ) );

			if ( '' === $label ) {
				continue;
			}

			$messages[] = sprintf(
				/* translators: %s: field label. */
				__( 'A link was removed from the label of "%s" because its address is not a full http or https URL.', 'oli-contact-form' ),
				$label
			);
		}

		if ( ! $messages ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible">';

		foreach ( $messages as $message ) {
			echo '<p>' . esc_html( $message ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Show the import result on this plugin's screens only.
	 */
	public function maybe_import_notice() {
		if ( ! $this->is_plugin_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['oli_cf_imported'], $_GET['oli_cf_imported_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['oli_cf_imported_nonce'] ) ), 'oli_cf_imported' ) ) {
			return;
		}

		$count   = absint( wp_unslash( $_GET['oli_cf_imported'] ) );
		$skipped = isset( $_GET['oli_cf_import_skipped'] ) ? absint( wp_unslash( $_GET['oli_cf_import_skipped'] ) ) : 0;
		$message = sprintf(
			/* translators: %d: number of imported forms */
			_n( '%d form imported.', '%d forms imported.', $count, 'oli-contact-form' ),
			$count
		);

		if ( $skipped > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of forms skipped because they were already imported */
				_n( '%d duplicate was skipped.', '%d duplicates were skipped.', $skipped, 'oli-contact-form' ),
				$skipped
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html( $message );
		echo '</p></div>';
	}

	/**
	 * Remember that this user closed the conversion notice.
	 */
	public function ajax_dismiss_migration() {
		check_ajax_referer( 'oli_cf_dismiss_migration' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'oli-contact-form' ), '', array( 'response' => 403 ) );
		}

		update_user_meta( get_current_user_id(), 'oli_cf_migration_dismissed', '1' );
		wp_die();
	}
}
