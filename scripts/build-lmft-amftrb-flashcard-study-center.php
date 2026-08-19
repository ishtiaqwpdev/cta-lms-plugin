<?php
/**
 * Build LMFT AMFTRB Flashcard Study Center JSON from approved xlsx export.
 *
 * Usage:
 *   php scripts/build-lmft-amftrb-flashcard-study-center.php --source=path/to/export.xlsx
 *   php scripts/build-lmft-amftrb-flashcard-study-center.php --source=... --dry-run
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root    = dirname( __DIR__ );
$options = getopt( '', array( 'source:', 'dry-run', 'output:' ) );
$source  = isset( $options['source'] ) ? (string) $options['source'] : '';
$dry_run = isset( $options['dry-run'] );
$output  = isset( $options['output'] )
	? (string) $options['output']
	: $root . '/assets/course-materials/lmft-amftrb/study-tools/flashcard-study-center.json';

if ( '' === $source || ! is_readable( $source ) ) {
	fwrite( STDERR, "Usage: php scripts/build-lmft-amftrb-flashcard-study-center.php --source=path/to/export.xlsx [--dry-run]\n" );
	exit( 1 );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', $key ), '-' );
	}
}

require_once __DIR__ . '/flashcard-study-center-xlsx-helpers.php';

try {
	$sheet_rows = cta_fsc_read_xlsx_sheet( $source, 'Flashcards' );
} catch ( Throwable $e ) {
	fwrite( STDERR, $e->getMessage() . PHP_EOL );
	exit( 1 );
}

if ( 180 !== count( $sheet_rows ) ) {
	fwrite( STDERR, 'Expected 180 flashcard rows; found ' . count( $sheet_rows ) . PHP_EOL );
	exit( 1 );
}

$official_domains = array(
	'practice-of-systemic-therapy'                 => 'The Practice of Systemic Therapy',
	'assessing-hypothesizing-and-diagnosing'       => 'Assessing, Hypothesizing, and Diagnosing',
	'designing-and-conducting-treatment'           => 'Designing and Conducting Treatment',
	'evaluating-process-and-terminating-treatment' => 'Evaluating Ongoing Process and Terminating Treatment',
	'managing-crisis-situations'                   => 'Managing Crisis Situations',
	'ethical-legal-and-professional-standards'     => 'Maintaining Ethical, Legal, and Professional Standards',
);

$domain_order = array();
$domain_map   = array();
$cards        = array();

foreach ( $sheet_rows as $index => $row ) {
	$card_id     = trim( (string) ( $row['Card ID'] ?? '' ) );
	$domain_code = trim( (string) ( $row['Domain'] ?? '' ) );
	$domain_name = trim( (string) ( $row['Domain Name'] ?? '' ) );
	$front       = (string) ( $row['Front'] ?? '' );
	$back        = (string) ( $row['Back'] ?? '' );
	$memory_cue  = trim( (string) ( $row['Memory Cue'] ?? $row['Memory cue'] ?? '' ) );

	if ( '' === $card_id || '' === $front || '' === $back ) {
		fwrite( STDERR, 'Row ' . ( $index + 2 ) . ' missing Card ID, Front, or Back.' . PHP_EOL );
		exit( 1 );
	}

	$domain_key = sanitize_key( $domain_name !== '' ? $domain_name : $domain_code );
	if ( '' === $domain_key ) {
		fwrite( STDERR, 'Row ' . ( $index + 2 ) . ' missing domain.' . PHP_EOL );
		exit( 1 );
	}

	if ( ! isset( $domain_map[ $domain_key ] ) ) {
		$label = $domain_name !== '' ? $domain_name : $domain_code;
		if ( isset( $official_domains[ $domain_key ] ) ) {
			$label = $official_domains[ $domain_key ];
		}
		$domain_map[ $domain_key ] = array(
			'key'   => $domain_key,
			'label' => $label,
			'order' => count( $domain_order ) + 1,
		);
		$domain_order[] = $domain_key;
	}

	$card = array(
		'id'           => $card_id,
		'sort_order'   => $index + 1,
		'domain'       => $domain_key,
		'domain_label' => $domain_map[ $domain_key ]['label'],
		'front'        => $front,
		'back'         => $back,
		'meta'         => array(
			'domain_code'     => $domain_code,
			'visibility'      => 'Learner',
			'active'          => true,
			'content_version' => '1.0',
		),
	);

	if ( '' !== $memory_cue ) {
		$card['memory_cue'] = $memory_cue;
	}

	$cards[] = $card;
}

if ( count( $domain_map ) > 12 ) {
	fwrite( STDERR, 'WARNING: ' . count( $domain_map ) . ' domains found — expected a small AMFTRB taxonomy (typically 6).' . PHP_EOL );
}

$payload = array(
	'program'          => 'lmft-amftrb',
	'title'            => 'LMFT AMFTRB National — Flashcard Study Center',
	'version'          => '1.0',
	'expected_total'   => 180,
	'imported_through' => 180,
	'domains'          => array_values( $domain_map ),
	'cards'            => $cards,
);

$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( false === $json ) {
	fwrite( STDERR, "JSON encode failed.\n" );
	exit( 1 );
}

echo 'Cards: ' . count( $cards ) . PHP_EOL;
echo 'Domains: ' . count( $domain_map ) . PHP_EOL;
echo 'First card: ' . $cards[0]['id'] . ' | ' . substr( $cards[0]['front'], 0, 60 ) . '...' . PHP_EOL;
echo 'Last card: ' . $cards[ count( $cards ) - 1 ]['id'] . ' | ' . substr( $cards[ count( $cards ) - 1 ]['front'], 0, 60 ) . '...' . PHP_EOL;

if ( $dry_run ) {
	echo "Dry run — no file written.\n";
	exit( 0 );
}

file_put_contents( $output, $json . PHP_EOL );
echo 'Wrote ' . $output . PHP_EOL;
