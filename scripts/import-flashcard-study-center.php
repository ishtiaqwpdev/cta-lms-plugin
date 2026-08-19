<?php
/**
 * Import and validate a Flashcard Study Center JSON deck.
 *
 * Usage:
 *   php scripts/import-flashcard-study-center.php --program=lmft-clinical --source=path/to/deck.json
 *   php scripts/import-flashcard-study-center.php --program=lmft-clinical --source=path/to/deck.json --dry-run
 *
 * Expected source JSON:
 * {
 *   "title": "LMFT California Clinical — Flashcard Study Center",
 *   "version": "1.0",
 *   "domains": [
 *     { "key": "clinical-evaluation", "label": "Clinical Evaluation", "order": 1 }
 *   ],
 *   "cards": [
 *     {
 *       "id": "001",
 *       "domain": "clinical-evaluation",
 *       "front": "Question text",
 *       "back": "Answer text",
 *       "memory_cue": "Memory cue text"
 *     }
 *   ]
 * }
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', $key ), '-' );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^a-z0-9_\-]+/i', '-', (string) $filename );
	}
}

if ( ! function_exists( 'wp_json_encode_pretty' ) ) {
	function wp_json_encode_pretty( $data ) {
		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}

$root = dirname( __DIR__ );

$options = getopt( '', array( 'program:', 'source:', 'dry-run' ) );
$program = isset( $options['program'] ) ? sanitize_file_name( (string) $options['program'] ) : '';
$source  = isset( $options['source'] ) ? (string) $options['source'] : '';
$dry_run = isset( $options['dry-run'] );

$targets = array(
	'lmft-clinical' => $root . '/assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json',
	'lpcc-ncmhce'   => $root . '/assets/course-materials/lpcc-ncmhce/study-tools/flashcard-study-center.json',
	'lcsw-aswb'     => $root . '/assets/course-materials/lcsw-aswb/study-tools/flashcard-study-center.json',
);

if ( '' === $program || ! isset( $targets[ $program ] ) ) {
	fwrite( STDERR, "Usage: php scripts/import-flashcard-study-center.php --program=lmft-clinical --source=path/to/deck.json [--dry-run]\n" );
	fwrite( STDERR, 'Supported programs: ' . implode( ', ', array_keys( $targets ) ) . "\n" );
	exit( 1 );
}

if ( '' === $source || ! is_readable( $source ) ) {
	fwrite( STDERR, "Source JSON not found or unreadable: {$source}\n" );
	exit( 1 );
}

$raw  = file_get_contents( $source );
$data = json_decode( (string) $raw, true );
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "Invalid JSON in source file.\n" );
	exit( 1 );
}

$cards   = isset( $data['cards'] ) && is_array( $data['cards'] ) ? $data['cards'] : array();
$domains = isset( $data['domains'] ) && is_array( $data['domains'] ) ? $data['domains'] : array();

if ( empty( $cards ) ) {
	fwrite( STDERR, "Source deck contains 0 cards. Aborting.\n" );
	exit( 1 );
}

$domain_keys = array();
foreach ( $domains as $domain ) {
	if ( ! is_array( $domain ) ) {
		continue;
	}
	$key = sanitize_key( (string) ( $domain['key'] ?? '' ) );
	if ( '' !== $key ) {
		$domain_keys[ $key ] = true;
	}
}

$missing_domain = 0;
$missing_cue    = 0;
$seen_ids       = array();

foreach ( $cards as $index => $card ) {
	if ( ! is_array( $card ) ) {
		fwrite( STDERR, 'Invalid card row at index ' . $index . "\n" );
		exit( 1 );
	}

	$id    = trim( (string) ( $card['id'] ?? '' ) );
	$front = trim( (string) ( $card['front'] ?? '' ) );
	$back  = trim( (string) ( $card['back'] ?? '' ) );
	$key   = sanitize_key( (string) ( $card['domain'] ?? $card['category'] ?? '' ) );
	$cue   = trim( (string) ( $card['memory_cue'] ?? $card['memoryCue'] ?? '' ) );

	if ( '' === $front || '' === $back ) {
		fwrite( STDERR, "Card {$id} is missing front/back text.\n" );
		exit( 1 );
	}

	if ( '' === $key ) {
		fwrite( STDERR, "Card {$id} is missing a domain key.\n" );
		exit( 1 );
	}

	if ( ! empty( $domain_keys ) && ! isset( $domain_keys[ $key ] ) ) {
		++$missing_domain;
	}

	if ( '' === $cue ) {
		++$missing_cue;
	}

	if ( '' !== $id ) {
		if ( isset( $seen_ids[ $id ] ) ) {
			fwrite( STDERR, "Duplicate card id: {$id}\n" );
			exit( 1 );
		}
		$seen_ids[ $id ] = true;
	}
}

$payload = array(
	'program' => $program,
	'title'   => ! empty( $data['title'] )
		? (string) $data['title']
		: 'LMFT California Clinical — Flashcard Study Center',
	'version' => ! empty( $data['version'] ) ? (string) $data['version'] : '1.0',
	'domains' => array_values( $domains ),
	'cards'   => array_values( $cards ),
);

$target = $targets[ $program ];
$json   = wp_json_encode_pretty( $payload );

echo 'Cards: ' . count( $cards ) . PHP_EOL;
echo 'Domains declared: ' . count( $domains ) . PHP_EOL;
echo 'Cards with undeclared domain keys: ' . $missing_domain . PHP_EOL;
echo 'Cards missing memory_cue: ' . $missing_cue . PHP_EOL;

if ( 'lmft-clinical' === $program && 180 !== count( $cards ) ) {
	echo 'WARNING: LMFT California Clinical deck expected 180 cards; source has ' . count( $cards ) . '.' . PHP_EOL;
}

if ( 'lpcc-ncmhce' === $program && 180 !== count( $cards ) ) {
	echo 'WARNING: LPCC NCMHCE deck expected 180 cards; source has ' . count( $cards ) . '.' . PHP_EOL;
}

if ( $dry_run ) {
	echo "Dry run only — no file written.\n";
	exit( 0 );
}

file_put_contents( $target, $json . PHP_EOL );
echo 'Wrote ' . $target . PHP_EOL;
