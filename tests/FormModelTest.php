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
			}
		}

		$this->assertTrue( $found );
		$this->assertSame( 'Text', Oli_CF_Fields::type_label( 'text' ) );
		$this->assertSame( 'Phone', Oli_CF_Fields::type_label( 'tel' ) );
		$this->assertSame( 'Paragraph', Oli_CF_Fields::type_label( 'textarea' ) );
		$this->assertSame( 'Submit', Oli_CF_Fields::type_label( 'submit' ) );
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
