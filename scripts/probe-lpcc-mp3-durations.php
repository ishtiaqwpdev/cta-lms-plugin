<?php
/**
 * Probe LPCC MP3 durations (Xing/Info or frame scan).
 *
 * Run: C:\xampp\php\php.exe scripts/probe-lpcc-mp3-durations.php
 */

$dir   = dirname( __DIR__ ) . '/assets/course-materials/lpcc-ncmhce/audio';
$files = glob( $dir . '/CTA_LPCC_Audio_Track_*.mp3' );
sort( $files );

$expected = array( '3:58', '10:37', '4:13', '4:05', '4:09', '7:30', '7:26', '6:47' );

/**
 * Estimate MP3 duration in seconds.
 *
 * @param string $path File path.
 * @return float|null
 */
function cta_mp3_duration_sec( $path ) {
	$fh = fopen( $path, 'rb' );
	if ( ! $fh ) {
		return null;
	}

	$hdr    = fread( $fh, 10 );
	$offset = 0;
	if ( strlen( $hdr ) >= 10 && 'ID3' === substr( $hdr, 0, 3 ) ) {
		$offset = 10
			+ ( ( ord( $hdr[6] ) & 0x7F ) << 21 )
			+ ( ( ord( $hdr[7] ) & 0x7F ) << 14 )
			+ ( ( ord( $hdr[8] ) & 0x7F ) << 7 )
			+ ( ord( $hdr[9] ) & 0x7F );
	}
	fseek( $fh, $offset );

	$bitrates = array(
		1 => array( 0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320 ),
		2 => array( 0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160 ),
	);

	$total_frames  = 0;
	$total_samples = 0;
	$sr            = 0;
	$xing          = false;
	$frames_xing   = 0;
	$ver_used      = 3;
	$max           = 200000;
	$n             = 0;

	while ( ! feof( $fh ) && $n < $max ) {
		$b = fread( $fh, 4 );
		if ( strlen( $b ) < 4 ) {
			break;
		}
		$h = ( ord( $b[0] ) << 24 ) | ( ord( $b[1] ) << 16 ) | ( ord( $b[2] ) << 8 ) | ord( $b[3] );
		if ( ( ( $h >> 21 ) & 0x7FF ) !== 0x7FF ) {
			fseek( $fh, -3, SEEK_CUR );
			++$n;
			continue;
		}
		$ver    = ( $h >> 19 ) & 3;
		$layer  = ( $h >> 17 ) & 3;
		$br_idx = ( $h >> 12 ) & 15;
		$sr_idx = ( $h >> 10 ) & 3;
		$pad    = ( $h >> 9 ) & 1;
		if ( 1 !== $layer || 0 === $br_idx || 15 === $br_idx || 3 === $sr_idx ) {
			fseek( $fh, -3, SEEK_CUR );
			++$n;
			continue;
		}
		$mpeg     = ( 3 === $ver ) ? 1 : 2;
		$sr_table = ( 3 === $ver )
			? array( 44100, 48000, 32000 )
			: ( ( 2 === $ver ) ? array( 22050, 24000, 16000 ) : array( 11025, 12000, 8000 ) );
		$sr       = $sr_table[ $sr_idx ];
		$br       = $bitrates[ $mpeg ][ $br_idx ] * 1000;
		$spf      = ( 3 === $ver ) ? 1152 : 576;
		$flen     = (int) floor( ( $spf / 8 ) * $br / $sr ) + $pad;
		if ( $flen < 24 ) {
			fseek( $fh, -3, SEEK_CUR );
			++$n;
			continue;
		}

		if ( 0 === $total_frames ) {
			$pos  = ftell( $fh );
			$side = ( 3 === $ver ) ? ( ( ( $h & 0xC0 ) === 0xC0 ) ? 17 : 32 ) : ( ( ( $h & 0xC0 ) === 0xC0 ) ? 9 : 17 );
			fseek( $fh, $side, SEEK_CUR );
			$tag = fread( $fh, 4 );
			if ( 'Xing' === $tag || 'Info' === $tag ) {
				$flags = unpack( 'N', fread( $fh, 4 ) )[1];
				if ( $flags & 1 ) {
					$frames_xing = unpack( 'N', fread( $fh, 4 ) )[1];
					$xing        = true;
					$ver_used    = $ver;
				}
			}
			fseek( $fh, $pos );
		}

		++$total_frames;
		$total_samples += $spf;
		$ver_used       = $ver;
		fseek( $fh, $flen - 4, SEEK_CUR );
		++$n;
		if ( $xing && $frames_xing ) {
			break;
		}
	}
	fclose( $fh );

	$spf_final = ( 3 === $ver_used ) ? 1152 : 576;
	if ( $xing && $frames_xing && $sr ) {
		return $frames_xing * $spf_final / $sr;
	}
	if ( $sr && $total_samples ) {
		return $total_samples / $sr;
	}
	return null;
}

$sum = 0.0;
foreach ( $files as $i => $f ) {
	$dur  = cta_mp3_duration_sec( $f );
	$fmt  = 'n/a';
	$diff = 'n/a';
	if ( null !== $dur ) {
		$mins = (int) floor( $dur / 60 );
		$secs = (int) round( fmod( $dur, 60 ) );
		if ( 60 === $secs ) {
			++$mins;
			$secs = 0;
		}
		$fmt  = sprintf( '%d:%02d', $mins, $secs );
		$sum += $dur;
		$exp_parts = explode( ':', $expected[ $i ] );
		$exp_sec   = ( (int) $exp_parts[0] * 60 ) + (int) $exp_parts[1];
		$diff      = sprintf( '%+.1fs', $dur - $exp_sec );
	}
	$ok = ( $fmt === $expected[ $i ] ) || ( abs( (float) str_replace( array( '+', 's' ), '', $diff ) ) <= 1.5 );
	echo basename( $f ) . "\tsize=" . filesize( $f ) . "\tdur={$fmt}\texpected={$expected[ $i ]}\tdelta={$diff}\t" . ( $ok ? 'OK' : 'CHECK' ) . "\n";
}

$sum_m = (int) floor( $sum / 60 );
$sum_s = (int) round( fmod( $sum, 60 ) );
echo "SUM={$sum_m}:" . sprintf( '%02d', $sum_s ) . " (" . round( $sum, 3 ) . "s) file_count=" . count( $files ) . "\n";
