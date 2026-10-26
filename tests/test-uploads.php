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

$source = tempnam( sys_get_temp_dir(), 'oli-cf-src-' );
file_put_contents( $source, 'hello attachment' );

$staged = Oli_CF_Uploads::stage_file(
	array(
		'name'     => 'notes.pdf',
		'type'     => 'application/pdf',
		'tmp_name' => $source,
		'error'    => UPLOAD_ERR_OK,
		'size'     => 16,
	)
);

oli_cf_assert_true( is_string( $staged ) && '' !== $staged, 'stage_file returns a path' );
oli_cf_assert_true( file_exists( $staged ), 'staged file exists' );
oli_cf_assert_true( false !== strpos( basename( $staged ), 'notes.pdf' ), 'staged name keeps the original filename' );
oli_cf_assert_same( 'hello attachment', file_get_contents( $staged ), 'staged file has the original contents' );

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

if ( $oli_cf_failures > 0 ) {
	fwrite( STDERR, "\n{$oli_cf_failures} upload test(s) failed.\n" );
	exit( 1 );
}

echo "\nAll upload tests passed.\n";
exit( 0 );
