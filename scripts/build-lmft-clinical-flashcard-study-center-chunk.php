<?php
/**
 * Build LMFT Clinical Flashcard Study Center deck from prompt export JSON.
 *
 * Usage:
 *   php scripts/build-lmft-clinical-flashcard-study-center-chunk.php --source=scripts/_lmft-clinical-flashcards-prompt-01.json
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root = dirname( __DIR__ );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', $key ), '-' );
	}
}

/**
 * @param string $label Domain label.
 * @return string
 */
function lmft_fsc_domain_key_from_label( $label ) {
	$label = trim( (string) $label );
	if ( '' === $label ) {
		return 'general';
	}

	return sanitize_key( str_replace( array( '&', '/' ), array( 'and', '-' ), $label ) );
}

/**
 * @param array<string,mixed> $row Export row.
 * @return array<string,mixed>
 */
function lmft_fsc_map_export_card( array $row ) {
	$domain_label = trim( (string) ( $row['Domain'] ?? '' ) );
	$domain_key   = lmft_fsc_domain_key_from_label( $domain_label );

	return array(
		'id'           => trim( (string) ( $row['Card ID'] ?? '' ) ),
		'sort_order'   => (int) ( $row['Sort Order'] ?? 0 ),
		'domain'       => $domain_key,
		'domain_label' => $domain_label,
		'front'        => (string) ( $row['Front'] ?? '' ),
		'back'         => (string) ( $row['Back'] ?? '' ),
		'memory_cue'   => (string) ( $row['Memory Cue'] ?? '' ),
		'meta'         => array(
			'domain_code'       => (string) ( $row['Domain Code'] ?? '' ),
			'blueprint_section' => (string) ( $row['Blueprint Section'] ?? '' ),
			'card_type'         => (string) ( $row['Card Type'] ?? '' ),
			'concept'           => (string) ( $row['Concept'] ?? '' ),
			'tags'              => (string) ( $row['Tags'] ?? '' ),
			'visibility'        => (string) ( $row['Visibility'] ?? '' ),
			'active'            => ! empty( $row['Active'] ),
			'content_version'   => (string) ( $row['Content Version'] ?? '1.0' ),
		),
	);
}

/**
 * @param array<int,array<string,mixed>> $cards Cards.
 * @return array<int,array<string,mixed>>
 */
function lmft_fsc_build_domains( array $cards ) {
	$domains = array();

	foreach ( $cards as $card ) {
		$key   = (string) ( $card['domain'] ?? '' );
		$label = (string) ( $card['domain_label'] ?? $key );
		if ( '' === $key ) {
			continue;
		}
		if ( ! isset( $domains[ $key ] ) ) {
			$domains[ $key ] = array(
				'key'   => $key,
				'label' => $label,
				'order' => count( $domains ) + 1,
			);
		}
	}

	usort(
		$domains,
		static function ( $a, $b ) {
			return (int) $a['order'] <=> (int) $b['order'];
		}
	);

	return array_values( $domains );
}

$options = getopt( '', array( 'source:', 'target:', 'merge' ) );
$source  = isset( $options['source'] ) ? (string) $options['source'] : $root . '/scripts/_lmft-clinical-flashcards-prompt-01.json';
$target  = isset( $options['target'] )
	? (string) $options['target']
	: $root . '/assets/course-materials/lmft-clinical/study-tools/flashcard-study-center.json';
$merge   = isset( $options['merge'] );

if ( ! is_readable( $source ) ) {
	fwrite( STDERR, "Source not readable: {$source}\n" );
	exit( 1 );
}

$raw  = file_get_contents( $source );
$data = json_decode( (string) $raw, true );
if ( ! is_array( $data ) || empty( $data['cards'] ) || ! is_array( $data['cards'] ) ) {
	fwrite( STDERR, "Invalid source JSON.\n" );
	exit( 1 );
}

$new_cards = array();
$seen_ids  = array();

foreach ( $data['cards'] as $index => $row ) {
	if ( ! is_array( $row ) ) {
		fwrite( STDERR, "Invalid card row at index {$index}\n" );
		exit( 1 );
	}

	$card = lmft_fsc_map_export_card( $row );
	if ( '' === $card['id'] || '' === trim( $card['front'] ) || '' === trim( $card['back'] ) ) {
		fwrite( STDERR, "Card at index {$index} missing id/front/back.\n" );
		exit( 1 );
	}
	if ( isset( $seen_ids[ $card['id'] ] ) ) {
		fwrite( STDERR, "Duplicate Card ID: {$card['id']}\n" );
		exit( 1 );
	}
	$seen_ids[ $card['id'] ] = true;
	$new_cards[]             = $card;
}

usort(
	$new_cards,
	static function ( $a, $b ) {
		$order = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
		if ( 0 !== $order ) {
			return $order;
		}
		return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
	}
);

$existing_cards = array();
if ( $merge && is_readable( $target ) ) {
	$existing = json_decode( (string) file_get_contents( $target ), true );
	if ( is_array( $existing ) && ! empty( $existing['cards'] ) && is_array( $existing['cards'] ) ) {
		foreach ( $existing['cards'] as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$id = trim( (string) ( $card['id'] ?? '' ) );
			if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
				continue;
			}
			$existing_cards[]   = $card;
			$seen_ids[ $id ]     = true;
		}
	}
}

$all_cards = array_merge( $existing_cards, $new_cards );
usort(
	$all_cards,
	static function ( $a, $b ) {
		$order = (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 );
		if ( 0 !== $order ) {
			return $order;
		}
		return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
	}
);

$expected_total   = (int) ( $data['expected_total'] ?? 180 );
$imported_through = 0;
foreach ( $all_cards as $card ) {
	$imported_through = max( $imported_through, (int) ( $card['sort_order'] ?? 0 ) );
}

$payload = array(
	'program'          => 'lmft-clinical',
	'title'            => 'LMFT California Clinical — Flashcard Study Center',
	'version'          => '1.0',
	'expected_total'   => $expected_total,
	'imported_through' => $imported_through,
	'card_range'       => (string) ( $data['card_range'] ?? '' ),
	'domains'          => lmft_fsc_build_domains( $all_cards ),
	'cards'            => array_values( $all_cards ),
);

$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
if ( false === $json ) {
	fwrite( STDERR, "Failed to encode deck JSON.\n" );
	exit( 1 );
}

file_put_contents( $target, $json . "\n" );

echo 'Wrote ' . $target . PHP_EOL;
echo 'Cards in deck: ' . count( $all_cards ) . PHP_EOL;
echo 'Imported through sort order: ' . $imported_through . PHP_EOL;
echo 'Expected total: ' . $expected_total . PHP_EOL;
echo 'Domains: ' . count( $payload['domains'] ) . PHP_EOL;
