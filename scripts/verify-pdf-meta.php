<?php
$pdf = file_get_contents( __DIR__ . '/../tmp/CTA-2026-151748.pdf' );
echo 'magic: ' . substr( $pdf, 0, 5 ) . PHP_EOL;
echo 'bytes: ' . strlen( $pdf ) . PHP_EOL;
if ( preg_match( '/\/MediaBox\s*\[\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\]/', $pdf, $m ) ) {
	$w = (float) $m[3] - (float) $m[1];
	$h = (float) $m[4] - (float) $m[2];
	echo 'MediaBox pts: ' . $w . ' x ' . $h . PHP_EOL;
	echo 'landscape: ' . ( $w > $h ? 'yes' : 'no' ) . PHP_EOL;
}
echo 'pages approx: ' . preg_match_all( '/\/Type\s*\/Page[^s]/', $pdf ) . PHP_EOL;
echo 'filename ok: ' . ( is_file( __DIR__ . '/../tmp/CTA-2026-151748.pdf' ) ? 'CTA-2026-151748.pdf' : 'missing' ) . PHP_EOL;
