<?php
/**
 * Upload staging and deletion tests.
 *
 * @package Oli_Contact_Form
 */

require_once __DIR__ . '/bootstrap.php';

oli_cf_assert_same( 2 * 1024 * 1024, Oli_CF_Uploads::parse_size( '2mb' ), '2mb parses to bytes' );
oli_cf_assert_same( 500 * 1024, Oli_CF_Uploads::parse_size( '500kb' ), '500kb parses to bytes' );
oli_cf_assert_same( array( 'jpg', 'png', 'pdf' ), Oli_CF_Uploads::parse_filetypes( 'jpg|png|pdf|php' ), 'php is stripped from allowed types' );
oli_cf_assert_same( '.jpg,.png,.pdf', Oli_CF_Uploads::accept_attribute( 'jpg|png|pdf' ), 'accept attribute uses dotted extensions' );

oli_cf_assert_false(
	Oli_CF_Uploads::file_was_uploaded(
		array(
			'name'  => '',
			'error' => UPLOAD_ERR_NO_FILE,
		)
	),
	'empty upload is not treated as present'
);

add_filter(
	'oli_cf_is_uploaded_file',
	static function ( $override, $path ) {
		unset( $override );
		return is_file( $path );
	},
	10,
	2
);

$source = tempnam( sys_get_temp_dir(), 'oli-cf-src-' );
file_put_contents( $source, 'hello attachment' );

$staged = Oli_CF_Uploads::stage_file(
	array(
		'name'     => 'notes.txt',
		'type'     => 'text/plain',
		'tmp_name' => $source,
		'error'    => UPLOAD_ERR_OK,
		'size'     => 16,
	)
);

oli_cf_assert_true( is_string( $staged ) && '' !== $staged, 'stage_file returns a path' );
oli_cf_assert_true( file_exists( $staged ), 'staged file exists' );
oli_cf_assert_same( 'bin', pathinfo( $staged, PATHINFO_EXTENSION ), 'staged name is a random .bin' );
oli_cf_assert_same( 32, strlen( pathinfo( $staged, PATHINFO_FILENAME ) ), 'staged basename is 32 hex characters' );
oli_cf_assert_false( false !== strpos( basename( $staged ), 'notes.txt' ), 'original filename is not stored on disk' );
oli_cf_assert_same( array( 'notes.txt' => $staged ), Oli_CF_Uploads::mail_attachments( array( $staged ) ), 'original name is kept for the mail attachment' );
oli_cf_assert_same( 'hello attachment', file_get_contents( $staged ), 'staged file has the original contents' );
oli_cf_assert_true( 0 === strpos( basename( dirname( $staged ) ), 'oli-cf-' ), 'staged file lives in a random oli-cf-* directory' );

$deleted = Oli_CF_Uploads::delete_file( $staged );
oli_cf_assert_true( $deleted, 'delete_file reports success' );
oli_cf_assert_false( file_exists( $staged ), 'staged file is gone after delete' );

$again = Oli_CF_Uploads::delete_file( $staged );
oli_cf_assert_true( $again, 'deleting an already-removed file is still success' );

$one = tempnam( sys_get_temp_dir(), 'oli-cf-a-' );
$two = tempnam( sys_get_temp_dir(), 'oli-cf-b-' );
file_put_contents( $one, 'a' );
file_put_contents( $two, 'b' );

$count = Oli_CF_Uploads::delete_files( array( $one, $two ) );
oli_cf_assert_same( 2, $count, 'delete_files removes every path' );
oli_cf_assert_false( file_exists( $one ) || file_exists( $two ), 'both files are gone' );

if ( file_exists( $source ) ) {
	unlink( $source );
}

if ( ! empty( $staged ) ) {
	Oli_CF_Uploads::delete_staging_directory( dirname( $staged ) );
}

$not_upload = tempnam( sys_get_temp_dir(), 'oli-cf-not-' );
file_put_contents( $not_upload, 'hello attachment' );
$GLOBALS['oli_cf_test_filters']['oli_cf_is_uploaded_file'] = array();

$rejected = Oli_CF_Uploads::stage_file(
	array(
		'name'     => 'notes.txt',
		'type'     => 'text/plain',
		'tmp_name' => $not_upload,
		'error'    => UPLOAD_ERR_OK,
		'size'     => 16,
	)
);

oli_cf_assert_same( '', $rejected, 'non-uploaded path is not staged' );
oli_cf_assert_true( file_exists( $not_upload ), 'non-uploaded source is left in place' );
unlink( $not_upload );

$GLOBALS['oli_cf_test_temp_dir'] = WP_CONTENT_DIR;
$parent                          = Oli_CF_Uploads::staging_parent();
oli_cf_assert_false( rtrim( (string) WP_CONTENT_DIR, '/\\' ) === rtrim( $parent, '/\\' ), 'staging parent is never wp-content/' );
oli_cf_assert_true( Oli_CF_Uploads::is_usable_private_temp( $parent ), 'default staging parent is a private temp directory' );
unset( $GLOBALS['oli_cf_test_temp_dir'] );

$web_tmp = rtrim( ABSPATH, '/\\' ) . '/web-staging';
if ( ! is_dir( $web_tmp ) ) {
	mkdir( $web_tmp, 0700, true );
}
add_filter(
	'oli_cf_staging_parent',
	static function () use ( $web_tmp ) {
		return $web_tmp;
	}
);
oli_cf_assert_same( '', Oli_CF_Uploads::staging_parent(), 'a temp dir under ABSPATH is refused' );
$GLOBALS['oli_cf_test_filters']['oli_cf_staging_parent'] = array();
@rmdir( $web_tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} upload test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll upload tests passed.\n";
exit( 0 );
