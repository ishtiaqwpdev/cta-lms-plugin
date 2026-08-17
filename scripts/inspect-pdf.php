<?php
$pdf = file_get_contents( dirname( __DIR__ ) . '/tmp/CTA-2026-151748.pdf' );
echo 'starts_pdf=' . ( 0 === strpos( $pdf, '%PDF' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'size=' . strlen( $pdf ) . PHP_EOL;
if ( preg_match_all( '/MediaBox\s*\[\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\]/', $pdf, $m, PREG_SET_ORDER ) ) {
	foreach ( $m as $i => $box ) {
		$w = (float) $box[3] - (float) $box[1];
		$h = (float) $box[4] - (float) $box[2];
		echo "MediaBox[$i] width=$w height=$h landscape=" . ( $w > $h ? 'yes' : 'no' ) . PHP_EOL;
	}
}
foreach ( array( 'Certificate', 'Test Learner', 'CTA-2026-151748', 'Continuing Education', 'CE Hours', 'Candice' ) as $needle ) {
	echo 'plain_' . str_replace( ' ', '_', $needle ) . '=' . ( false !== strpos( $pdf, $needle ) ? 'yes' : 'compressed_or_missing' ) . PHP_EOL;
}
echo 'page_count_hint=' . substr_count( $pdf, '/Type /Page' ) . PHP_EOL;
