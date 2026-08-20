<?php
/**
 * Build deploy-bridge/aaa-cta-lms-recover.zip for wp-admin upload.
 *
 * Usage: php scripts/build-aaa-cta-lms-recover-zip.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root    = dirname( __DIR__ );
$source  = $root . '/deploy-bridge/aaa-cta-lms-recover';
$zipPath = $root . '/deploy-bridge/aaa-cta-lms-recover.zip';

if ( ! is_dir( $source ) ) {
	fwrite( STDERR, "Missing {$source}\n" );
	exit( 1 );
}

if ( file_exists( $zipPath ) ) {
	unlink( $zipPath );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive not available.\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create {$zipPath}\n" );
	exit( 1 );
}

$main = $source . '/aaa-cta-lms-recover.php';
$zip->addFile( $main, 'aaa-cta-lms-recover/aaa-cta-lms-recover.php' );
$zip->close();

echo "Built: {$zipPath}\n";
echo "Upload via WordPress: Plugins → Add New → Upload Plugin\n";
