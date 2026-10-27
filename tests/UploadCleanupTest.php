<?php
/**
 * PHPUnit tests for upload staging, type checks, and guaranteed deletion.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

if ( class_exists( '\PHPUnit\Framework\TestCase' ) ) {
	/**
	 * PHPUnit base when the framework is installed.
	 */
	abstract class Oli_CF_Upload_Cleanup_TestCase extends \PHPUnit\Framework\TestCase {}
} else {
	/**
	 * Minimal TestCase so this file also runs via `php tests/run.php`.
	 */
	abstract class Oli_CF_Upload_Cleanup_TestCase {

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
		protected function assertTrue( $actual, $message = '' ) {
			oli_cf_assert_true( $actual, '' !== $message ? $message : 'assertTrue' );
		}

		/**
		 * @param mixed  $actual  Actual.
		 * @param string $message Message.
		 */
		protected function assertFalse( $actual, $message = '' ) {
			oli_cf_assert_false( $actual, '' !== $message ? $message : 'assertFalse' );
		}

		/**
		 * @param string $filename File.
		 * @param string $message  Message.
		 */
		protected function assertFileExists( $filename, $message = '' ) {
			oli_cf_assert_true( file_exists( $filename ), '' !== $message ? $message : 'assertFileExists' );
		}

		/**
		 * @param string $filename File.
		 * @param string $message  Message.
		 */
		protected function assertFileDoesNotExist( $filename, $message = '' ) {
			oli_cf_assert_false( file_exists( $filename ), '' !== $message ? $message : 'assertFileDoesNotExist' );
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
 * Upload cleanup and type-check tests.
 */
class UploadCleanupTest extends Oli_CF_Upload_Cleanup_TestCase {

	/**
	 * Reset test doubles.
	 */
	public function reset_test_state() {
		$GLOBALS['oli_cf_test_filters']           = array();
		$GLOBALS['oli_cf_test_posts']             = array();
		$GLOBALS['oli_cf_test_meta']              = array();
		$GLOBALS['oli_cf_test_wp_mail_calls']     = array();
		$GLOBALS['oli_cf_test_wp_mail_fail']      = false;
		$GLOBALS['oli_cf_test_wp_mail_exception'] = '';
		unset( $GLOBALS['oli_cf_test_temp_dir'] );
	}

	/**
	 * Reset test doubles (PHPUnit).
	 */
	protected function setUp(): void {
		$this->reset_test_state();
	}

	/**
	 * Allow local temp files to pass is_uploaded_file() during tests.
	 */
	private function allow_local_uploads() {
		add_filter(
			'oli_cf_is_uploaded_file',
			static function ( $override, $path ) {
				unset( $override );
				return is_file( $path );
			},
			10,
			2
		);
	}

	/**
	 * Seed a form that accepts a text attachment.
	 *
	 * @param int $form_id Form ID.
	 */
	private function seed_form( $form_id = 12 ) {
		$GLOBALS['oli_cf_test_posts'][ $form_id ] = (object) array(
			'ID'        => $form_id,
			'post_type' => 'oli_contact_form',
		);
		$GLOBALS['oli_cf_test_meta'][ $form_id ]  = array(
			'_oli_cf_form_template' => '[text* your-name] [file your-file filetypes:txt|jpg]',
			'_oli_cf_messages'      => array(),
			'_oli_cf_mail'          => array(
				'to'                 => 'admin@example.com',
				'from'               => 'admin@example.com',
				'subject'            => 'Test',
				'body'               => 'Hi',
				'reply_to'           => '',
				'additional_headers' => '',
			),
		);
	}

	/**
	 * Create a local file and a $_FILES-like array.
	 *
	 * @param string $name    Filename.
	 * @param string $contents Contents.
	 * @return array<string, mixed>
	 */
	private function make_file( $name, $contents ) {
		$tmp = tempnam( sys_get_temp_dir(), 'oli-cf-up-' );
		file_put_contents( $tmp, $contents );

		return array(
			'path' => $tmp,
			'file' => array(
				'name'     => $name,
				'type'     => 'text/plain',
				'tmp_name' => $tmp,
				'error'    => UPLOAD_ERR_OK,
				'size'     => strlen( $contents ),
			),
		);
	}

	/**
	 * A hook on phpmailer_init that throws still deletes the staged file.
	 */
	public function test_phpmailer_exception_still_deletes_file() {
		$this->allow_local_uploads();
		$this->seed_form();

		$upload   = $this->make_file( 'notes.txt', 'hello attachment' );
		$captured = array();

		add_action(
			'oli_cf_before_send',
			static function ( $form_id, $data, $staged ) use ( &$captured ) {
				unset( $form_id, $data );
				$captured = $staged;
			},
			10,
			3
		);
		add_action(
			'phpmailer_init',
			static function () {
				throw new RuntimeException( 'smtp plugin crash' );
			}
		);

		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'mail_failed', $result['status'] );
		$this->assertFalse( empty( $captured['your-file'] ) );
		$this->assertFileDoesNotExist( $captured['your-file'] );
		$this->assertFalse( is_dir( dirname( $captured['your-file'] ) ) );
	}

	/**
	 * A successful wp_mail() deletes the staged file.
	 */
	public function test_successful_send_deletes_file() {
		$this->allow_local_uploads();
		$this->seed_form();

		$upload   = $this->make_file( 'notes.txt', 'hello attachment' );
		$captured = array();

		$existed_at_send = false;

		add_action(
			'oli_cf_before_send',
			static function ( $form_id, $data, $staged ) use ( &$captured, &$existed_at_send ) {
				unset( $form_id, $data );
				$captured        = $staged;
				$existed_at_send = ! empty( $staged['your-file'] ) && file_exists( $staged['your-file'] );
			},
			10,
			3
		);

		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $existed_at_send );
		$this->assertFalse( empty( $captured['your-file'] ) );
		$this->assertFileDoesNotExist( $captured['your-file'] );
		$this->assertFalse( is_dir( dirname( $captured['your-file'] ) ) );
		$this->assertFalse( file_exists( $upload['path'] ) );
	}

	/**
	 * A false wp_mail() still deletes the staged file.
	 */
	public function test_wp_mail_failure_still_deletes_file() {
		$this->allow_local_uploads();
		$this->seed_form();
		$GLOBALS['oli_cf_test_wp_mail_fail'] = true;

		$upload   = $this->make_file( 'notes.txt', 'hello attachment' );
		$captured = array();

		add_action(
			'oli_cf_before_send',
			static function ( $form_id, $data, $staged ) use ( &$captured ) {
				unset( $form_id, $data );
				$captured = $staged;
			},
			10,
			3
		);

		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'mail_failed', $result['status'] );
		$this->assertFalse( empty( $captured['your-file'] ) );
		$this->assertFileDoesNotExist( $captured['your-file'] );
		$this->assertFalse( is_dir( dirname( $captured['your-file'] ) ) );
	}

	/**
	 * A .jpg whose contents are PHP or plain text is rejected.
	 */
	public function test_spoofed_jpg_content_is_rejected() {
		$this->allow_local_uploads();
		$php = $this->make_file( 'photo.jpg', '<?php echo 1;' );
		$txt = $this->make_file( 'photo.jpg', 'not an image at all' );

		$messages = Oli_CF_Validator::default_messages();

		$this->assertSame(
			$messages['upload_file_type'],
			Oli_CF_Validator::validate_file(
				$php['file'],
				array( 'filetypes' => 'jpg|jpeg|png' ),
				$messages
			)
		);
		$this->assertSame(
			$messages['upload_file_type'],
			Oli_CF_Validator::validate_file(
				$txt['file'],
				array( 'filetypes' => 'jpg|jpeg|png' ),
				$messages
			)
		);

		$this->allow_local_uploads();
		$this->assertSame( '', Oli_CF_Uploads::stage_file( $php['file'] ) );

		if ( file_exists( $php['path'] ) ) {
			unlink( $php['path'] );
		}

		if ( file_exists( $txt['path'] ) ) {
			unlink( $txt['path'] );
		}
	}

	/**
	 * A path that is not a PHP upload is rejected.
	 */
	public function test_non_uploaded_file_is_rejected() {
		$upload   = $this->make_file( 'notes.txt', 'hello attachment' );
		$messages = Oli_CF_Validator::default_messages();

		$this->assertSame(
			$messages['upload_failed'],
			Oli_CF_Validator::validate_file(
				$upload['file'],
				array( 'filetypes' => 'txt' ),
				$messages
			)
		);
		$this->assertSame( '', Oli_CF_Uploads::stage_file( $upload['file'] ) );
		$this->assertFileExists( $upload['path'] );

		unlink( $upload['path'] );
	}
}

if ( isset( $argv[0] ) && is_string( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$suite = new UploadCleanupTest();

	foreach ( get_class_methods( $suite ) as $method ) {
		if ( 0 === strpos( $method, 'test_' ) ) {
			$suite->reset_test_state();

			$suite->$method();
		}
	}

	if ( $oli_cf_failures > 0 ) {
		fwrite( STDERR, "\n{$oli_cf_failures} upload cleanup test(s) failed.\n" );
		exit( 1 );
	}

	echo "\nAll upload cleanup tests passed.\n";
	exit( 0 );
}
