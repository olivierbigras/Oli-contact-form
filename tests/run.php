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
	__DIR__ . '/MailSanitizeTest.php',
	__DIR__ . '/UploadCleanupTest.php',
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

$phpunit = '';

if ( function_exists( 'shell_exec' ) ) {
	$found_phpunit = shell_exec( 'command -v phpunit 2>/dev/null' );
	$phpunit       = is_string( $found_phpunit ) ? trim( $found_phpunit ) : '';
}

if ( '' !== $phpunit ) {
	echo "=== phpunit ===\n";
	passthru( escapeshellarg( $phpunit ) . ' -c ' . escapeshellarg( dirname( __DIR__ ) . '/phpunit.xml.dist' ), $code );
	echo "\n";

	if ( 0 !== (int) $code ) {
		++$failed;
	}
}

$node = '';

if ( function_exists( 'shell_exec' ) ) {
	$found = shell_exec( 'command -v node 2>/dev/null' );
	$node  = is_string( $found ) ? trim( $found ) : '';
}

if ( '' !== $node ) {
	echo "=== test-public-js.js ===\n";
	passthru( escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/test-public-js.js' ), $code );
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
