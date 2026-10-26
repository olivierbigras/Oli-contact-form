<?php
/**
 * Run all CLI tests.
 *
 * @package Oli_Contact_Form
 */

$tests = array(
	__DIR__ . '/test-validator.php',
	__DIR__ . '/test-mailer.php',
	__DIR__ . '/test-uploads.php',
	__DIR__ . '/test-cf7-import.php',
	__DIR__ . '/test-anti-spam.php',
	__DIR__ . '/test-front.php',
);

$failed = 0;

foreach ( $tests as $test ) {
	echo "=== " . basename( $test ) . " ===\n";
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test ), $code );
	echo "\n";

	if ( 0 !== (int) $code ) {
		++$failed;
	}
}

if ( $failed > 0 ) {
	fwrite( STDERR, "{$failed} test file(s) failed.\n" );
	exit( 1 );
}

echo "All test files passed.\n";
exit( 0 );
