<?php
/**
 * Fields, notifications, tokens, migration, and import sources.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

if ( class_exists( '\PHPUnit\Framework\TestCase' ) ) {
	/**
	 * PHPUnit base when the framework is installed.
	 */
	abstract class Oli_CF_Form_Model_TestCase extends \PHPUnit\Framework\TestCase {}
} else {
	/**
	 * Minimal TestCase so this file also runs via `php tests/run.php`.
	 */
	abstract class Oli_CF_Form_Model_TestCase {

		/**
		 * @param mixed  $expected Expected.
		 * @param mixed  $actual   Actual.
		 * @param string $message  Message.
		 */
		protected function assertSame( $expected, $actual, $message = '' ) {
			oli_cf_assert_same( $expected, $actual, '' !== $message ? $message : 'assertSame' );
		}

		/**
		 * @param bool   $condition Condition.
		 * @param string $message   Message.
		 */
		protected function assertTrue( $condition, $message = '' ) {
			oli_cf_assert_true( $condition, '' !== $message ? $message : 'assertTrue' );
		}

		/**
		 * @param bool   $condition Condition.
		 * @param string $message   Message.
		 */
		protected function assertFalse( $condition, $message = '' ) {
			oli_cf_assert_false( $condition, '' !== $message ? $message : 'assertFalse' );
		}

		/**
		 * @param string $needle   Needle.
		 * @param string $haystack Haystack.
		 * @param string $message  Message.
		 */
		protected function assertStringContainsString( $needle, $haystack, $message = '' ) {
			oli_cf_assert_true( false !== strpos( (string) $haystack, (string) $needle ), '' !== $message ? $message : 'assertStringContainsString' );
		}

		/**
		 * @param string $needle   Needle.
		 * @param string $haystack Haystack.
		 * @param string $message  Message.
		 */
		protected function assertStringNotContainsString( $needle, $haystack, $message = '' ) {
			oli_cf_assert_true( false === strpos( (string) $haystack, (string) $needle ), '' !== $message ? $message : 'assertStringNotContainsString' );
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Post object for the editor save test.
	 */
	class WP_Post {
		/**
		 * @var int
		 */
		public $ID = 0;

		/**
		 * @var string
		 */
		public $post_type = '';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 * @return bool
	 */
	function wp_verify_nonce( $nonce, $action = '' ) {
		return 'oli-cf-test-nonce' === (string) $nonce && 'oli_cf_save_form' === (string) $action;
	}
}

/**
 * Form model tests.
 */
class FormModelTest extends Oli_CF_Form_Model_TestCase {

	/**
	 * Markup becomes a field and the public renderer prints it.
	 */
	public function test_markup_round_trip_renders() {
		$fields = Oli_CF_Fields::from_markup( '{{field type="email" name="courriel" label="Courriel" required placeholder="you@example.com"}}' );

		$this->assertSame( 'email', $fields[0]['type'] );
		$this->assertSame( 'courriel', $fields[0]['name'] );
		$this->assertTrue( $fields[0]['required'] );

		$html = Oli_CF_Form_Parser::parse( $fields, 8 );

		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'name="courriel"', $html );
		$this->assertStringContainsString( 'oli-cf-control', $html );
		$this->assertStringContainsString( 'Courriel', $html );
	}

	/**
	 * A previous template becomes fields, and unknown tags are reported.
	 */
	public function test_legacy_template_conversion() {
		$report = Oli_CF_Fields::legacy_report( '[text* your-name] [email* your-email] [captcha captcha-1] [acceptance consent] I agree. [/acceptance]' );

		$this->assertSame( 'your-name', $report['fields'][0]['name'] );
		$this->assertTrue( $report['fields'][0]['required'] );
		$this->assertSame( 'email', $report['fields'][1]['type'] );
		$this->assertSame( 'I agree.', $report['fields'][2]['label'] );
		$this->assertTrue( $report['fields'][2]['required'] );
		$this->assertSame( 'captcha', $report['unsupported'][0]['type'] );
	}

	/**
	 * Optional acceptance stays optional.
	 */
	public function test_optional_acceptance() {
		$fields = Oli_CF_Fields::from_legacy_template( '[acceptance consent optional] Yes [/acceptance]' );

		$this->assertFalse( $fields[0]['required'] );
	}

	/**
	 * Notification conditions and token rewrite.
	 */
	public function test_notification_condition_and_tokens() {
		$item                        = Oli_CF_Notifications::blank();
		$item['condition']['field']  = 'topic';
		$item['condition']['operator'] = 'equals';
		$item['condition']['value']  = 'Sales';

		$this->assertTrue( Oli_CF_Notifications::condition_matches( $item, array( 'topic' => 'Sales' ) ) );
		$this->assertFalse( Oli_CF_Notifications::condition_matches( $item, array( 'topic' => 'Help' ) ) );

		$rewritten = Oli_CF_Notifications::rewrite_tokens( 'To [_site_admin_email] from [your-name] on [_date]' );

		$this->assertSame( 'To {site:admin_email} from {field:your-name} on {submission:date}', $rewritten );
	}

	/**
	 * Mail tokens resolve, and a second notification is best-effort.
	 */
	public function test_mail_tokens() {
		$mail = Oli_CF_Mailer::build_mail(
			array(
				'to'      => '{site:admin_email}',
				'subject' => 'Hello {field:courriel}',
				'body'    => '{submission:date}',
			),
			array( 'courriel' => 'ada@example.com' )
		);

		$this->assertSame( 'admin@example.com', $mail['to'] );
		$this->assertSame( 'Hello ada@example.com', $mail['subject'] );
		$this->assertTrue( '' !== $mail['body'] );
	}

	/**
	 * Existing forms are converted once, can be rendered, and can be restored.
	 */
	public function test_migration_is_idempotent_and_renderable() {
		$GLOBALS['oli_cf_test_meta']    = array();
		$GLOBALS['oli_cf_test_options'] = array();
		$template                       = '<label> Your name [text* your-name] </label> [email* your-email]';
		$GLOBALS['oli_cf_test_meta'][4] = array(
			'_oli_cf_form_template' => $template,
			'_oli_cf_mail'          => array(
				'to'      => '[_site_admin_email]',
				'from'    => 'admin@example.com',
				'subject' => 'Hi [your-name]',
				'body'    => 'From [your-name]',
			),
			'_oli_cf_mail_2'        => array(
				'active'  => true,
				'to'      => '[your-email]',
				'subject' => 'Thanks',
				'body'    => 'Hello',
			),
			'_oli_cf_messages'      => array(
				'mail_sent_ok' => 'Sent',
			),
		);
		$GLOBALS['oli_cf_test_form_ids'] = array( 4 );

		$source = $this->legacy_source();
		$GLOBALS['oli_cf_test_options']['oli_cf_settings'] = array(
			$source->legacy_option_key() => true,
		);
		$GLOBALS['oli_cf_test_meta'][9] = array(
			$source->legacy_meta_key() => '55',
		);

		$report = Oli_CF_Upgrade::migrate_forms();

		$this->assertSame( 1, $report['converted'] );
		$this->assertSame( '2', get_post_meta( 4, '_oli_cf_schema', true ) );
		$this->assertSame( $template, get_post_meta( 4, '_oli_cf_legacy_template', true ) );

		$fields = Oli_CF_Fields::for_form( 4 );
		$this->assertSame( 'your-name', $fields[0]['name'] );

		$notes = Oli_CF_Notifications::for_form( 4 );
		$this->assertSame( '{site:admin_email}', $notes[0]['to'] );
		$this->assertSame( 'Hi {field:your-name}', $notes[0]['subject'] );
		$this->assertTrue( $notes[1]['enabled'] );
		$this->assertSame( '{field:your-email}', $notes[1]['to'] );
		$this->assertSame( 'Sent', get_post_meta( 4, '_oli_cf_messages', true )['success'] );

		$html = Oli_CF_Form_Parser::parse( $fields, 4 );
		$this->assertStringContainsString( 'name="your-name"', $html );
		$this->assertStringContainsString( 'name="your-email"', $html );

		$settings = get_option( 'oli_cf_settings' );
		$this->assertTrue( ! empty( $settings['legacy_shortcode_compat'] ) );
		$this->assertFalse( array_key_exists( $source->legacy_option_key(), $settings ) );
		$this->assertSame( $source->key(), get_post_meta( 9, '_oli_cf_imported_from', true ) );
		$this->assertSame( '55', get_post_meta( 9, '_oli_cf_import_source_id', true ) );
		$this->assertSame( '', get_post_meta( 9, $source->legacy_meta_key(), true ) );

		$GLOBALS['oli_cf_test_meta'][4]['_oli_cf_form_template'] = '[text different]';
		Oli_CF_Upgrade::migrate_form( 4 );
		$this->assertSame( $template, get_post_meta( 4, '_oli_cf_legacy_template', true ) );

		$this->assertTrue( Oli_CF_Upgrade::restore_form( 4 ) );
		$this->assertSame( 'your-name', Oli_CF_Fields::for_form( 4 )[0]['name'] );
		$this->assertSame( $template, get_post_meta( 4, '_oli_cf_legacy_template', true ) );
	}

	/**
	 * Each import source writes the field builder, not the old template syntax.
	 */
	public function test_import_sources_write_fields() {
		$tagged = $this->legacy_source();
		$tagged_result = $tagged->convert_record(
			array(
				'id'       => 3,
				'title'    => 'Hello',
				'form'     => '[text* your-name] [email* your-email] [captcha captcha-1]',
				'mail'     => array(
					'recipient'          => '[_site_admin_email]',
					'sender'             => 'Site <[_site_admin_email]>',
					'subject'            => 'Hi',
					'body'               => 'From [your-name]',
					'additional_headers' => "Reply-To: [your-email]\nBcc: copy@example.com",
				),
				'mail_2'   => array(
					'active'    => true,
					'recipient' => '[your-email]',
					'subject'   => 'Thanks',
					'body'      => 'OK',
				),
				'messages' => array(
					'mail_sent_ok' => 'Got it.',
				),
			)
		);

		$this->assertSame( 'your-email', $tagged_result['fields'][1]['name'] );
		$this->assertSame( 'captcha', $tagged_result['unsupported'][0]['type'] );
		$this->assertSame( '{site:admin_email}', $tagged_result['notifications'][0]['to'] );
		$this->assertSame( '{field:your-email}', $tagged_result['notifications'][0]['reply_to'] );
		$this->assertSame( '{field:your-email}', $tagged_result['notifications'][1]['to'] );
		$this->assertTrue( $tagged_result['notifications'][1]['enabled'] );
		$this->assertSame( 'Got it.', $tagged_result['messages']['success'] );
		$this->assertSame( '3', $tagged_result['source_id'] );

		$gravity = Oli_CF_Import_Registry::get( 'gravity' )->convert_record(
			array(
				'id'    => 7,
				'title' => 'Quote',
				'display_meta' => array(
					'fields' => array(
						array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'isRequired' => true ),
						array( 'id' => 2, 'type' => 'email', 'label' => 'Email', 'isRequired' => true ),
						array( 'id' => 3, 'type' => 'address', 'label' => 'Address' ),
					),
				),
				'notifications' => array(
					array(
						'to'      => '{admin_email}',
						'subject' => 'From {Email:2}',
						'message' => 'Name {1}',
					),
				),
				'confirmations' => array(
					array( 'message' => 'Thanks for the quote' ),
				),
			)
		);

		$this->assertSame( 'email', $gravity['fields'][1]['type'] );
		$this->assertSame( 'address', $gravity['unsupported'][0]['type'] );
		$this->assertSame( '{site:admin_email}', $gravity['notifications'][0]['to'] );
		$this->assertSame( 'From {field:email}', $gravity['notifications'][0]['subject'] );
		$this->assertSame( 'Thanks for the quote', $gravity['messages']['success'] );

		$wpforms = Oli_CF_Import_Registry::get( 'wpforms' )->convert_record(
			array(
				'id'     => 8,
				'title'  => 'Lead',
				'fields' => array(
					array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'required' => '1' ),
					array( 'id' => 2, 'type' => 'gdpr-checkbox', 'label' => 'I agree' ),
					array( 'id' => 4, 'type' => 'payment-single', 'label' => 'Fee' ),
				),
				'settings' => array(
					'notifications' => array(
						array(
							'email'   => '{admin_email}',
							'subject' => '{field_id="1"}',
							'message' => 'Hi',
						),
					),
				),
			)
		);

		$this->assertSame( 'acceptance', $wpforms['fields'][1]['type'] );
		$this->assertSame( 'payment-single', $wpforms['unsupported'][0]['type'] );
		$this->assertSame( '{field:name}', $wpforms['notifications'][0]['subject'] );

		$ninja = Oli_CF_Import_Registry::get( 'ninja' )->convert_record(
			array(
				'id'     => 9,
				'title'  => 'Ninja sample',
				'fields' => array(
					array( 'id' => 1, 'type' => 'textbox', 'key' => 'name', 'label' => 'Name' ),
					array( 'id' => 2, 'type' => 'html', 'key' => 'note', 'label' => 'Note' ),
				),
				'actions' => array(
					array( 'id' => 5, 'type' => 'email' ),
				),
				'action_meta' => array(
					array( 'parent_id' => 5, 'key' => 'to', 'value' => '{wp:admin_email}' ),
					array( 'parent_id' => 5, 'key' => 'email_subject', 'value' => '{field:name}' ),
					array( 'parent_id' => 5, 'key' => 'email_message', 'value' => 'Hi' ),
				),
			)
		);

		$this->assertSame( 'name', $ninja['fields'][0]['name'] );
		$this->assertSame( 'html', $ninja['unsupported'][0]['type'] );
		$this->assertSame( '{site:admin_email}', $ninja['notifications'][0]['to'] );

		$fluent = Oli_CF_Import_Registry::get( 'fluent' )->convert_record(
			array(
				'id'          => 10,
				'title'       => 'Fluent sample',
				'form_fields' => array(
					'fields' => array(
						array(
							'element'    => 'input_email',
							'attributes' => array( 'name' => 'email' ),
							'settings'   => array(
								'label'            => 'Email',
								'validation_rules' => array( 'required' => array( 'value' => true ) ),
							),
						),
						array(
							'element'  => 'custom_html',
							'settings' => array( 'label' => 'Blurb' ),
						),
					),
				),
				'meta' => array(
					array(
						'meta_key' => 'notifications',
						'value'    => array(
							array(
								'sendTo'    => array( 'email' => '{wp.admin_email}' ),
								'subject'   => '{inputs.email}',
								'message'   => 'Hi',
							),
						),
					),
				),
			)
		);

		$this->assertSame( 'email', $fluent['fields'][0]['type'] );
		$this->assertTrue( $fluent['fields'][0]['required'] );
		$this->assertSame( 'custom_html', $fluent['unsupported'][0]['type'] );
		$this->assertSame( '{field:email}', $fluent['notifications'][0]['subject'] );

		$formidable = Oli_CF_Import_Registry::get( 'formidable' )->convert_record(
			array(
				'id'      => 11,
				'name'    => 'Formidable sample',
				'options' => array(
					'success_msg'  => 'Saved',
					'notification' => array(
						'email_to'      => '[admin_email]',
						'email_subject' => 'Field [2]',
						'email_message' => 'Hi',
					),
				),
				'fields'  => array(
					array( 'id' => 2, 'type' => 'email', 'name' => 'Email', 'field_key' => 'email', 'required' => 1 ),
					array( 'id' => 3, 'type' => 'signature', 'name' => 'Sign' ),
				),
			)
		);

		$this->assertSame( 'email', $formidable['fields'][0]['name'] );
		$this->assertSame( 'signature', $formidable['unsupported'][0]['type'] );
		$this->assertSame( '{site:admin_email}', $formidable['notifications'][0]['to'] );
		$this->assertSame( 'Field {field:email}', $formidable['notifications'][0]['subject'] );
		$this->assertSame( 'Saved', $formidable['messages']['success'] );
	}

	/**
	 * A new form asks for consent before it can be sent.
	 */
	public function test_default_consent_is_required() {
		$found = false;

		foreach ( Oli_CF_Fields::defaults() as $field ) {
			if ( 'consent' === $field['name'] ) {
				$found = true;
				$this->assertTrue( $field['required'] );
				$this->assertSame( 'acceptance', $field['type'] );
				$this->assertSame( 'I consent to being contacted about this inquiry.', $field['label'] );
			}
		}

		$this->assertTrue( $found );

		$GLOBALS['oli_cf_test_privacy_url'] = 'https://example.com/privacy-policy';

		try {
			$linked = '';

			foreach ( Oli_CF_Fields::defaults() as $field ) {
				if ( 'consent' === $field['name'] ) {
					$linked = $field['label'];
				}
			}

			$this->assertStringContainsString( 'href="https://example.com/privacy-policy"', $linked );
			$this->assertStringContainsString( '>privacy policy<', $linked );
		} finally {
			unset( $GLOBALS['oli_cf_test_privacy_url'] );
		}

		$this->assertSame( 'Text', Oli_CF_Fields::type_label( 'text' ) );
		$this->assertSame( 'Phone', Oli_CF_Fields::type_label( 'tel' ) );
		$this->assertSame( 'Paragraph', Oli_CF_Fields::type_label( 'textarea' ) );
		$this->assertSame( 'Submit', Oli_CF_Fields::type_label( 'submit' ) );
	}

	/**
	 * Publishing keeps the privacy-policy link that a new form starts with.
	 */
	public function test_privacy_link_survives_the_editor_save() {
		require_once dirname( __DIR__ ) . '/admin/class-oli-cf-meta-boxes.php';

		$previous_post = isset( $_POST ) ? $_POST : array();
		$previous_can  = isset( $GLOBALS['oli_cf_test_can_manage'] ) ? $GLOBALS['oli_cf_test_can_manage'] : null;
		$GLOBALS['oli_cf_test_can_manage'] = true;
		$GLOBALS['oli_cf_test_privacy_url'] = 'https://example.com/privacy-policy';
		$form_id = 4242;
		$post = new WP_Post();
		$post->ID = $form_id;
		$post->post_type = 'oli_contact_form';
		$editor = new Oli_CF_Meta_Boxes();

		try {
			$href = 'href="https://example.com/privacy-policy"';
			$this->save_builder( $editor, $post, $this->rows_from_fields( Oli_CF_Fields::defaults() ) );
			$saved = $this->stored_fields( $form_id );
			$label = $this->consent_label( $saved );
			$this->assertStringContainsString( $href, $label, 'first publish keeps the privacy link' );
			$this->assertStringContainsString( '<a href=\\', (string) get_post_meta( $form_id, Oli_CF_Fields::META, true ), 'stored JSON keeps the anchor' );
			$this->assertStringContainsString( 'privacy-policy', (string) get_post_meta( $form_id, Oli_CF_Fields::META, true ), 'stored JSON keeps the privacy URL' );

			$this->save_builder( $editor, $post, $this->rows_from_fields( $saved ) );
			$again = $this->stored_fields( $form_id );
			$this->assertStringContainsString( $href, $this->consent_label( $again ), 'a second save keeps the privacy link' );

			$html = Oli_CF_Form_Parser::render_fields( $again, $form_id );
			$this->assertStringContainsString( $href, $html, 'the public form keeps the privacy link' );
			$this->assertStringContainsString( '>privacy policy</a>', $html, 'the public form shows the link text' );

			$markup = Oli_CF_Fields::to_markup( $again );
			$this->assertStringNotContainsString( 'href="https://example.com/privacy-policy"', $markup, 'markup escapes the quotes inside the label' );
			$_POST = array(
				'oli_cf_nonce'         => 'oli-cf-test-nonce',
				'oli_cf_use_markup'    => '1',
				'oli_cf_fields_markup' => $markup,
			);
			$editor->save_form_data( $form_id, $post );
			$from_markup = $this->stored_fields( $form_id );
			$this->assertStringContainsString( $href, $this->consent_label( $from_markup ), 'the markup path keeps the privacy link' );

			unset( $GLOBALS['oli_cf_test_privacy_url'] );
			$this->save_builder( $editor, $post, $this->rows_from_fields( Oli_CF_Fields::defaults() ) );
			$plain = $this->consent_label( $this->stored_fields( $form_id ) );
			$this->assertSame( 'I consent to being contacted about this inquiry.', $plain, 'no privacy page means no link' );
			$this->assertStringNotContainsString( '<a', $plain );

			$hostile = $this->rows_from_fields( Oli_CF_Fields::defaults() );
			foreach ( $hostile as $index => $row ) {
				if ( 'consent' === $row['name'] ) {
					$hostile[ $index ]['label'] = 'Agree <a href="javascript:alert(1)" onclick="alert(1)" target="_blank">here</a> <a href="https://example.com/privacy-policy" onclick="alert(2)" target="_blank">privacy policy</a>';
				}
			}
			$this->save_builder( $editor, $post, $hostile );
			$cleaned = $this->consent_label( $this->stored_fields( $form_id ) );
			$this->assertStringNotContainsString( 'javascript:', $cleaned, 'a javascript URL is rejected' );
			$this->assertStringNotContainsString( 'onclick', $cleaned, 'extra attributes are rejected' );
			$this->assertStringNotContainsString( 'target', $cleaned, 'extra attributes are rejected' );
			$this->assertStringContainsString( $href, $cleaned, 'an https link is kept without the extra attributes' );
			$hostile_html = Oli_CF_Form_Parser::render_fields( $this->stored_fields( $form_id ), $form_id );
			$this->assertStringNotContainsString( 'javascript:', $hostile_html );
			$this->assertStringNotContainsString( 'onclick', $hostile_html );
		} finally {
			$_POST = $previous_post;
			unset( $GLOBALS['oli_cf_test_privacy_url'] );
			if ( null === $previous_can ) {
				unset( $GLOBALS['oli_cf_test_can_manage'] );
			} else {
				$GLOBALS['oli_cf_test_can_manage'] = $previous_can;
			}
		}
	}

	/**
	 * Save the field builder the way the editor does.
	 *
	 * @param Oli_CF_Meta_Boxes    $editor Meta boxes.
	 * @param WP_Post              $post   Form.
	 * @param array<int, mixed>    $rows   Posted rows.
	 */
	private function save_builder( $editor, $post, $rows ) {
		$_POST = array(
			'oli_cf_nonce'  => 'oli-cf-test-nonce',
			'oli_cf_fields' => $rows,
		);
		$editor->save_form_data( $post->ID, $post );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function stored_fields( $form_id ) {
		return Oli_CF_Fields::decode( Oli_CF_Fields::read( $form_id ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return string
	 */
	private function consent_label( $fields ) {
		foreach ( $fields as $field ) {
			if ( 'consent' === $field['name'] ) {
				return (string) $field['label'];
			}
		}

		return '';
	}

	/**
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<int, array<string, string>>
	 */
	private function rows_from_fields( $fields ) {
		$rows = array();

		foreach ( $fields as $field ) {
			$rows[] = array(
				'type'        => (string) $field['type'],
				'name'        => (string) $field['name'],
				'label'       => (string) $field['label'],
				'required'    => ! empty( $field['required'] ) ? '1' : '',
				'placeholder' => (string) $field['placeholder'],
				'default'     => (string) $field['default'],
				'maxlength'   => (string) $field['maxlength'],
				'minlength'   => (string) $field['minlength'],
				'limit'       => (string) $field['limit'],
				'filetypes'   => (string) $field['filetypes'],
				'options'     => implode( "\n", $field['options'] ),
			);
		}

		return $rows;
	}

	/**
	 * A later edit survives another upgrade. The legacy copy is not reapplied.
	 */
	public function test_upgrade_keeps_later_editor_changes() {
		$GLOBALS['oli_cf_test_options']  = array();
		$GLOBALS['oli_cf_test_form_ids'] = array( 11 );
		$GLOBALS['oli_cf_test_meta']     = array(
			11 => array(
				'_oli_cf_form_template' => '[text* your-name]',
				'_oli_cf_mail'          => array(
					'to'      => 'admin@example.com',
					'subject' => 'Hi [your-name]',
					'body'    => "Hello\n[your-name]",
				),
			),
		);

		delete_option( Oli_CF_Upgrade::OPTION );
		Oli_CF_Upgrade::maybe_upgrade();

		$this->assertSame( 'your-name', Oli_CF_Fields::for_form( 11 )[0]['name'] );
		$this->assertSame( 'Hi {field:your-name}', Oli_CF_Notifications::for_form( 11 )[0]['subject'] );

		$fields   = Oli_CF_Fields::for_form( 11 );
		$fields[] = array(
			'type'     => 'tel',
			'name'     => 'phone',
			'label'    => 'Phone',
			'required' => false,
		);
		Oli_CF_Fields::save( 11, $fields );

		$notes                  = Oli_CF_Notifications::for_form( 11 );
		$notes[0]['subject']    = 'Sujet modifié — "après"';
		$notes[0]['body']       = "Ligne 1\nLigne 2";
		Oli_CF_Notifications::save( 11, $notes );

		Oli_CF_Upgrade::maybe_upgrade();

		$kept_fields = Oli_CF_Fields::for_form( 11 );
		$kept_notes  = Oli_CF_Notifications::for_form( 11 );

		$this->assertSame( 'phone', $kept_fields[1]['name'] );
		$this->assertSame( 'Sujet modifié — "après"', $kept_notes[0]['subject'] );
		$this->assertSame( "Ligne 1\nLigne 2", $kept_notes[0]['body'] );
		$this->assertSame( '2', get_post_meta( 11, '_oli_cf_schema', true ) );
		$this->assertSame( '[text* your-name]', get_post_meta( 11, '_oli_cf_legacy_template', true ) );
	}

	/**
	 * The source that still owns a previous storage key.
	 *
	 * @return Oli_CF_Import_Source
	 */
	private function legacy_source() {
		foreach ( Oli_CF_Import_Registry::sources() as $source ) {
			if ( '' !== $source->legacy_option_key() ) {
				return $source;
			}
		}

		$this->assertTrue( false, 'a legacy source exists' );

		return Oli_CF_Import_Registry::sources()[0];
	}
}

if ( isset( $argv[0] ) && is_string( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$suite = new FormModelTest();

	foreach ( get_class_methods( $suite ) as $method ) {
		if ( 0 === strpos( $method, 'test_' ) ) {
			$suite->$method();
		}
	}

	if ( $oli_cf_failures > 0 ) {
		fwrite( STDERR, "\n{$oli_cf_failures} form model test(s) failed.\n" );
		exit( 1 );
	}

	echo "\nAll form model tests passed.\n";
	exit( 0 );
}
