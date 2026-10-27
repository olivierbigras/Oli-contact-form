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

		/**
		 * @param string|int $key     Key.
		 * @param array      $array   Array.
		 * @param string     $message Message.
		 */
		protected function assertArrayHasKey( $key, $array, $message = '' ) {
			oli_cf_assert_true( is_array( $array ) && array_key_exists( $key, $array ), '' !== $message ? $message : 'assertArrayHasKey' );
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
		$GLOBALS['oli_cf_test_options']           = array();
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
		$this->assertSame( 'bin', pathinfo( $captured['your-file'], PATHINFO_EXTENSION ) );
		$this->assertFileDoesNotExist( $captured['your-file'] );
		$this->assertFalse( is_dir( dirname( $captured['your-file'] ) ) );
		$this->assertFalse( file_exists( $upload['path'] ) );
		$this->assertFalse( empty( $GLOBALS['oli_cf_test_wp_mail_calls'] ) );
		$this->assertArrayHasKey( 'notes.txt', $GLOBALS['oli_cf_test_wp_mail_calls'][0]['attachments'] );
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

	/**
	 * Staging dir is 0700 and the file is 0600 when the filesystem allows it.
	 */
	public function test_staging_permissions_are_private() {
		$this->allow_local_uploads();
		$upload = $this->make_file( 'notes.txt', 'hello attachment' );
		$staged = Oli_CF_Uploads::stage_file( $upload['file'] );

		$this->assertTrue( is_string( $staged ) && '' !== $staged );
		$this->assertFileExists( $staged );

		$dir = dirname( $staged );
		$this->assert_mode_when_supported( $dir, 0700 );
		$this->assert_mode_when_supported( $staged, 0600 );

		Oli_CF_Uploads::cleanup_submission( $dir, array( $staged ) );
	}

	/**
	 * Staged path is random .bin; wp_mail keeps the original name.
	 */
	public function test_random_staging_name_and_original_mail_name() {
		$this->allow_local_uploads();
		$upload = $this->make_file( 'notes.txt', 'hello attachment' );
		$staged = Oli_CF_Uploads::stage_file( $upload['file'] );

		$this->assertSame( 'bin', pathinfo( $staged, PATHINFO_EXTENSION ) );
		$this->assertSame( 32, strlen( pathinfo( $staged, PATHINFO_FILENAME ) ) );
		$this->assertFalse( false !== strpos( basename( $staged ), 'notes' ) );
		$this->assertSame( array( 'notes.txt' => $staged ), Oli_CF_Uploads::mail_attachments( array( $staged ) ) );

		Oli_CF_Uploads::cleanup_submission( dirname( $staged ), array( $staged ) );
	}

	/**
	 * A temp directory under ABSPATH refuses the upload: no copy, no mail, admin notice.
	 */
	public function test_web_root_temp_refuses_upload() {
		$this->allow_local_uploads();
		$this->seed_form();

		$web = rtrim( ABSPATH, '/\\' ) . '/oli-cf-web-temp';
		if ( ! is_dir( $web ) ) {
			mkdir( $web, 0700, true );
		}

		add_filter(
			'oli_cf_staging_parent',
			static function () use ( $web ) {
				return $web;
			}
		);

		$upload = $this->make_file( 'notes.txt', 'hello attachment' );
		$before = $this->list_files( $web );

		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( Oli_CF_Uploads::unavailable_message(), $result['message'] );
		$this->assertSame( $before, $this->list_files( $web ) );
		$this->assertFileExists( $upload['path'] );
		$this->assertTrue( empty( $GLOBALS['oli_cf_test_wp_mail_calls'] ) );
		$this->assertTrue( Oli_CF_Uploads::has_unavailable_notice() );

		unlink( $upload['path'] );
		@rmdir( $web ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * An unwritable private temp refuses the upload the same way.
	 */
	public function test_unwritable_temp_refuses_upload() {
		$this->allow_local_uploads();
		$this->seed_form();

		$locked = sys_get_temp_dir() . '/oli-cf-locked-' . wp_generate_password( 8, false, false );
		mkdir( $locked, 0700, true );
		chmod( $locked, 0555 );

		add_filter(
			'oli_cf_staging_parent',
			static function () use ( $locked ) {
				return $locked;
			}
		);

		$upload = $this->make_file( 'notes.txt', 'hello attachment' );

		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( Oli_CF_Uploads::unavailable_message(), $result['message'] );
		$this->assertFileExists( $upload['path'] );
		$this->assertTrue( empty( $GLOBALS['oli_cf_test_wp_mail_calls'] ) );
		$this->assertTrue( Oli_CF_Uploads::has_unavailable_notice() );
		$this->assertSame( array(), $this->list_files( $locked ) );

		unlink( $upload['path'] );
		chmod( $locked, 0700 );
		@rmdir( $locked ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * The default /tmp parent is accepted and a send succeeds.
	 */
	public function test_private_tmp_parent_allows_send() {
		$this->allow_local_uploads();
		$this->seed_form();

		$parent = Oli_CF_Uploads::staging_parent();
		$this->assertTrue( Oli_CF_Uploads::is_usable_private_temp( $parent ) );
		$this->assertFalse( 0 === strpos( $parent . '/', rtrim( ABSPATH, '/\\' ) . '/' ) );

		$upload  = $this->make_file( 'notes.txt', 'hello attachment' );
		$handler = new Oli_CF_Submission();
		$result  = $handler->process_submission(
			12,
			array( 'your-name' => 'Ada' ),
			array( 'your-file' => $upload['file'] )
		);

		$this->assertTrue( $result['success'] );
		$this->assertFalse( empty( $GLOBALS['oli_cf_test_wp_mail_calls'] ) );
		$this->assertArrayHasKey( 'notes.txt', $GLOBALS['oli_cf_test_wp_mail_calls'][0]['attachments'] );
		$this->assertFalse( Oli_CF_Uploads::has_unavailable_notice() );
	}

	/**
	 * Assert octal permissions when the filesystem honors chmod.
	 *
	 * @param string $path     Path.
	 * @param int    $expected Mode.
	 */
	private function assert_mode_when_supported( $path, $expected ) {
		clearstatcache( true, $path );
		$mode = fileperms( $path ) & 0777;

		if ( $mode === $expected ) {
			$this->assertSame( $expected, $mode );
			return;
		}

		$this->assertTrue( file_exists( $path ) );
	}

	/**
	 * Relative filenames inside a directory (excluding . and ..).
	 *
	 * @param string $dir Directory.
	 * @return string[]
	 */
	private function list_files( $dir ) {
		$items = is_dir( $dir ) ? scandir( $dir ) : array();

		return array_values(
			array_filter(
				is_array( $items ) ? $items : array(),
				static function ( $item ) {
					return '.' !== $item && '..' !== $item;
				}
			)
		);
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
