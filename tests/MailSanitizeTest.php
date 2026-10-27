<?php
/**
 * PHPUnit tests for mail field sanitization.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

if ( class_exists( '\PHPUnit\Framework\TestCase' ) ) {
	/**
	 * PHPUnit base when the framework is installed.
	 */
	abstract class Oli_CF_Mail_Sanitize_TestCase extends \PHPUnit\Framework\TestCase {}
} else {
	/**
	 * Minimal TestCase so this file also runs via `php tests/run.php`.
	 */
	abstract class Oli_CF_Mail_Sanitize_TestCase {

		/**
		 * @param mixed  $expected Expected.
		 * @param mixed  $actual   Actual.
		 * @param string $message  Message.
		 */
		protected function assertSame( $expected, $actual, $message = '' ) {
			oli_cf_assert_same( $expected, $actual, '' !== $message ? $message : 'assertSame' );
		}

		/**
		 * @param mixed  $actual  Actual.
		 * @param string $message Message.
		 */
		protected function assertFalse( $actual, $message = '' ) {
			oli_cf_assert_false( $actual, '' !== $message ? $message : 'assertFalse' );
		}

		/**
		 * @param string $needle   Needle.
		 * @param string $haystack Haystack.
		 * @param string $message  Message.
		 */
		protected function assertStringNotContainsString( $needle, $haystack, $message = '' ) {
			oli_cf_assert_same( false, false !== strpos( (string) $haystack, (string) $needle ), '' !== $message ? $message : 'assertStringNotContainsString' );
		}

		/**
		 * @param string $needle   Needle.
		 * @param string $haystack Haystack.
		 * @param string $message  Message.
		 */
		protected function assertStringContainsString( $needle, $haystack, $message = '' ) {
			oli_cf_assert_true( false !== strpos( (string) $haystack, (string) $needle ), '' !== $message ? $message : 'assertStringContainsString' );
		}
	}
}

/**
 * Mail sanitization tests.
 */
class MailSanitizeTest extends Oli_CF_Mail_Sanitize_TestCase {

	/**
	 * Default body keeps angle brackets around the email tag.
	 */
	public function test_body_keeps_from_field_tag_brackets() {
		$input = 'From: [your-name] <[your-email]>';
		$this->assertSame( $input, Oli_CF_Mailer::sanitize_mail_body( $input ) );
	}

	/**
	 * Default multi-line body is unchanged.
	 */
	public function test_body_keeps_default_template_newlines() {
		$input = "From: [your-name] <[your-email]>\nSubject: [your-subject]\n\nMessage:\n[your-message]";
		$this->assertSame( $input, Oli_CF_Mailer::sanitize_mail_body( $input ) );
	}

	/**
	 * Real HTML tags are stripped from the body.
	 */
	public function test_body_strips_script_and_img_onerror() {
		$clean = Oli_CF_Mailer::sanitize_mail_body(
			"From: [your-name] <[your-email]>\n<script>alert(1)</script>\n<img src=x onerror=alert(1)>"
		);

		$this->assertStringContainsString( 'From: [your-name] <[your-email]>', $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringNotContainsString( 'alert(1)', $clean );
		$this->assertStringNotContainsString( '<img', $clean );
		$this->assertStringNotContainsString( 'onerror', $clean );
	}

	/**
	 * From / Reply-To keep display-name email brackets.
	 */
	public function test_header_line_keeps_email_brackets() {
		$this->assertSame(
			'Site <admin@example.com>',
			Oli_CF_Mailer::sanitize_mail_line( 'Site <admin@example.com>' )
		);
		$this->assertSame(
			'Ada <[your-email]>',
			Oli_CF_Mailer::sanitize_mail_line( 'Ada <[your-email]>' )
		);
	}

	/**
	 * Single-line headers drop CR/LF to block injection.
	 */
	public function test_header_line_strips_crlf_injection() {
		$clean = Oli_CF_Mailer::sanitize_mail_line( "Ada <ada@example.com>\r\nBcc: evil@example.com" );
		$this->assertSame( 'Ada <ada@example.com>', $clean );
		$this->assertStringNotContainsString( 'evil', $clean );
		$this->assertStringNotContainsString( "\n", $clean );
		$this->assertStringNotContainsString( "\r", $clean );
	}

	/**
	 * Additional headers keep safe lines and email brackets.
	 */
	public function test_additional_headers_keep_email_and_newlines() {
		$input  = "Reply-To: Ada <[your-email]>\nBcc: copy@example.com";
		$clean  = Oli_CF_Mailer::sanitize_mail_headers( $input );
		$expect = "Reply-To: Ada <[your-email]>\nBcc: copy@example.com";
		$this->assertSame( $expect, $clean );
	}

	/**
	 * Posted mail settings (after wp_unslash) keep the default From line.
	 */
	public function test_sanitize_mail_settings_preserves_default_from_line() {
		$posted = array(
			'to'                 => 'admin@example.com',
			'from'               => 'Site <admin@example.com>',
			'subject'            => 'New message from [your-name]',
			'body'               => 'From: [your-name] <[your-email]>',
			'reply_to'           => '[your-email]',
			'additional_headers' => '',
		);

		$mail = Oli_CF_Mailer::sanitize_mail_settings( $posted );

		$this->assertSame( 'From: [your-name] <[your-email]>', $mail['body'] );
		$this->assertSame( 'Site <admin@example.com>', $mail['from'] );
	}

	/**
	 * Null bytes are removed.
	 */
	public function test_null_bytes_are_removed() {
		$this->assertSame(
			'From: [your-name] <[your-email]>',
			Oli_CF_Mailer::sanitize_mail_body( "From: [your-name] <[your-email]>\0<script>" )
		);
	}
}

if ( isset( $argv[0] ) && is_string( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$suite = new MailSanitizeTest();

	foreach ( get_class_methods( $suite ) as $method ) {
		if ( 0 === strpos( $method, 'test_' ) ) {
			$suite->$method();
		}
	}

	if ( $oli_cf_failures > 0 ) {
		fwrite( STDERR, "\n{$oli_cf_failures} mail sanitize test(s) failed.\n" );
		exit( 1 );
	}

	echo "\nAll mail sanitize tests passed.\n";
	exit( 0 );
}
