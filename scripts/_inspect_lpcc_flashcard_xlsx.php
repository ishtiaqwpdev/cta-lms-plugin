<?php
/**
 * Inspect LPCC NCMHCE flashcard xlsx structure (CLI).
 */
if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

$path = $argv[1] ?? '';
if ( '' === $path || ! is_readable( $path ) ) {
	fwrite( STDERR, "Usage: php scripts/_inspect_lpcc_flashcard_xlsx.php path/to/file.xlsx\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $path ) ) {
	fwrite( STDERR, "Cannot open xlsx\n" );
	exit( 1 );
}

for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	echo $zip->getNameIndex( $i ) . PHP_EOL;
}

$workbook = $zip->getFromName( 'xl/workbook.xml' );
echo "\n--- workbook.xml (first 2000 chars) ---\n";
echo substr( (string) $workbook, 0, 2000 ) . PHP_EOL;

$zip->close();
