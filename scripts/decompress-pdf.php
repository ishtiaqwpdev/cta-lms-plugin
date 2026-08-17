<?php
$p = file_get_contents( dirname( __DIR__ ) . '/tmp/CTA-2026-151748.pdf' );
if ( ! preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $p, $m ) ) {
	echo "no streams\n";
	exit( 1 );
}
echo 'streams=' . count( $m[1] ) . PHP_EOL;
foreach ( $m[1] as $i => $raw ) {
	$d = @gzuncompress( $raw );
	if ( false === $d ) {
		$d = @gzinflate( $raw );
	}
	if ( false === $d ) {
		$d = $raw;
	}
	$has_text = ( false !== strpos( $d, 'Tj' ) || false !== strpos( $d, 'TJ' ) || false !== strpos( $d, "'" ) );
	$has_path = ( false !== strpos( $d, ' re' ) || false !== strpos( $d, ' m ' ) );
	echo "stream$i raw=" . strlen( $raw ) . ' dec=' . strlen( $d ) . ' textOps=' . ( $has_text ? 'yes' : 'no' ) . ' pathOps=' . ( $has_path ? 'yes' : 'no' ) . PHP_EOL;
	if ( $has_text || $i < 2 ) {
		echo 'preview: ' . substr( preg_replace( '/\s+/', ' ', $d ), 0, 300 ) . PHP_EOL;
	}
	// Extract literal strings
	if ( preg_match_all( '/\((?:\\\\.|[^\\\\)]){2,}\)/', $d, $sm ) ) {
		echo 'strings: ' . implode( ' || ', array_slice( $sm[0], 0, 8 ) ) . PHP_EOL;
	}
}
