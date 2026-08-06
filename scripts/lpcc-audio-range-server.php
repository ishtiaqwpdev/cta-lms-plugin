<?php
/**
 * Range-aware static server for LPCC audio playback testing.
 *
 * Run from repo root:
 *   C:\xampp\php\php.exe -S 127.0.0.1:8766 scripts/lpcc-audio-range-server.php
 */

$root = realpath( dirname( __DIR__ ) );
$uri  = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$path = realpath( $root . $uri );

if ( false === $path || 0 !== strpos( $path, $root ) || ! is_file( $path ) ) {
	http_response_code( 404 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Not found\n";
	return true;
}

$mime = 'application/octet-stream';
$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
$map  = array(
	'html' => 'text/html; charset=utf-8',
	'js'   => 'application/javascript; charset=utf-8',
	'css'  => 'text/css; charset=utf-8',
	'mp3'  => 'audio/mpeg',
	'json' => 'application/json; charset=utf-8',
);
if ( isset( $map[ $ext ] ) ) {
	$mime = $map[ $ext ];
}

$size  = filesize( $path );
$start = 0;
$end   = $size - 1;
$code  = 200;

header( 'Accept-Ranges: bytes' );
header( 'Content-Type: ' . $mime );
header( 'Cache-Control: no-cache' );

if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m ) ) {
	if ( '' !== $m[1] ) {
		$start = (int) $m[1];
	}
	if ( '' !== $m[2] ) {
		$end = (int) $m[2];
	}
	if ( $end >= $size ) {
		$end = $size - 1;
	}
	if ( $start > $end || $start >= $size ) {
		http_response_code( 416 );
		header( "Content-Range: bytes */{$size}" );
		return true;
	}
	$code = 206;
	header( "Content-Range: bytes {$start}-{$end}/{$size}" );
}

$length = $end - $start + 1;
http_response_code( $code );
header( 'Content-Length: ' . $length );

$fp = fopen( $path, 'rb' );
fseek( $fp, $start );
$left = $length;
while ( $left > 0 && ! feof( $fp ) ) {
	$chunk = fread( $fp, min( 8192, $left ) );
	if ( false === $chunk ) {
		break;
	}
	echo $chunk;
	$left -= strlen( $chunk );
}
fclose( $fp );
return true;
