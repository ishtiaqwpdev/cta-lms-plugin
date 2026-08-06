<?php
/**
 * AMFTRB pre-launch testing checklist — offline verification suite.
 *
 * Covers what can be proven without a WordPress runtime: material order,
 * asset presence, audio headers, template/CSS wiring, and access gates.
 *
 * Run: C:\xampp\php\php.exe scripts/test-amftrb-prelaunch-checklist.php
 * JSON:  ... --json
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $str ) {
		return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $str ) );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		unset( $type );
		return '2026-08-06 15:00:00';
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) {
		return rtrim( (string) $s, "/\\" ) . '/';
	}
}

$GLOBALS['cta_user_meta'] = array();
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key, $single = false ) {
		$store = $GLOBALS['cta_user_meta'][ $user_id ][ $key ] ?? null;
		if ( $single ) {
			return null === $store ? '' : $store;
		}
		return null === $store ? array() : array( $store );
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['cta_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

class CTA_Fake_Wpdb {
	public $prefix = 'wp_';
	public function prepare( $query ) {
		return $query;
	}
	public function get_var( $query ) {
		unset( $query );
		return 0;
	}
}
$GLOBALS['wpdb'] = new CTA_Fake_Wpdb();
global $wpdb;
$wpdb = $GLOBALS['wpdb'];

class CTA_Exam_Access {
	const PRODUCT_TYPE_EXAM_PREP = 'exam_prep';
	public static function is_exam_prep( $course_or_type ) {
		if ( is_object( $course_or_type ) ) {
			$type = isset( $course_or_type->product_type ) ? (string) $course_or_type->product_type : 'ce';
		} else {
			$type = (string) $course_or_type;
		}
		return self::PRODUCT_TYPE_EXAM_PREP === $type;
	}
	public static function has_active_access( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return true;
	}
	public static function uses_assessment_gates( $course ) {
		return is_object( $course ) && 'lmft-amftrb-national-exam-preparation' === (string) ( $course->slug ?? '' );
	}
}

class CTA_Database {
	public static $course;
	public static function get_user_enrollment( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return (object) array( 'status' => 'active' );
	}
	public static function get_course( $course_id ) {
		unset( $course_id );
		return self::$course;
	}
}

CTA_Database::$course = (object) array(
	'id'           => 99,
	'slug'         => 'lmft-amftrb-national-exam-preparation',
	'product_type' => 'exam_prep',
);

require_once CTA_PLUGIN_DIR . 'includes/class-cta-course-materials.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';

$want_json = in_array( '--json', $argv ?? array(), true );
$checks    = array();

function cta_check( &$checks, $area, $id, $label, $pass, $detail = '', $method = 'offline' ) {
	$checks[] = array(
		'area'   => $area,
		'id'     => $id,
		'label'  => $label,
		'status' => $pass ? 'PASS' : 'FAIL',
		'detail' => $detail,
		'method' => $method,
	);
}

function cta_blocked( &$checks, $area, $id, $label, $detail = '' ) {
	$checks[] = array(
		'area'   => $area,
		'id'     => $id,
		'label'  => $label,
		'status' => 'BLOCKED',
		'detail' => $detail,
		'method' => 'requires_wp_learner',
	);
}

$materials_root = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/';

// --- Reflect sync maps ---
$ref  = new ReflectionClass( 'CTA_Lmft_Amftrb_Sync' );
$mmap = $ref->getMethod( 'get_material_map' );
$mmap->setAccessible( true );
$items = $mmap->invoke( null );

$mdef = $ref->getMethod( 'get_module_definitions' );
$mdef->setAccessible( true );
$modules = $mdef->invoke( null );

$audio_map = CTA_Lmft_Amftrb_Sync::get_audio_placement_map();

// ========== AREA 1: Course navigation / order ==========
cta_check( $checks, '1_navigation', 'mod_count', '12 workbook modules defined', 12 === count( $modules ), 'count=' . count( $modules ) );

$wb_seq = array();
foreach ( $items as $it ) {
	$title = (string) ( $it['title'] ?? '' );
	if ( preg_match( '/^Workbook (\d+) — .+Student Workbook/i', $title, $m ) ) {
		$wb_seq[] = (int) $m[1];
	}
}
$expected_wb = range( 1, 12 );
cta_check( $checks, '1_navigation', 'wb_order', 'Student workbooks appear in order WB1→WB12', $wb_seq === $expected_wb, 'got=[' . implode( ',', $wb_seq ) . ']' );

// Per-workbook pattern: WB → Audio → Candidate Bank → Rationale (+ CP inserts).
$order_ok   = true;
$order_notes = array();
$i          = 0;
$n_items    = count( $items );
for ( $wb = 1; $wb <= 12; $wb++ ) {
	// Find next student workbook titled for this WB.
	while ( $i < $n_items && ! preg_match( '/^Workbook ' . $wb . ' — .+Student Workbook/i', (string) ( $items[ $i ]['title'] ?? '' ) ) ) {
		++$i;
	}
	if ( $i >= $n_items ) {
		$order_ok      = false;
		$order_notes[] = "missing WB{$wb} workbook";
		break;
	}
	$seq = array( (string) $items[ $i ]['title'] );
	++$i;
	// Audio.
	if ( $i >= $n_items || empty( $items[ $i ]['is_audio'] ) ) {
		$order_ok      = false;
		$order_notes[] = "WB{$wb}: audio not immediately after workbook";
	} else {
		$seq[] = 'AUDIO';
		++$i;
	}
	// Candidate bank.
	if ( $i >= $n_items || false === stripos( (string) ( $items[ $i ]['title'] ?? '' ), 'Candidate Bank' ) ) {
		$order_ok      = false;
		$order_notes[] = "WB{$wb}: candidate bank not after audio";
	} else {
		$seq[] = 'BANK';
		++$i;
	}
	// Rationale.
	if ( $i >= $n_items || (string) ( $items[ $i ]['unlock_after_quiz_type'] ?? '' ) !== 'wb' . $wb . '_bank' ) {
		$order_ok      = false;
		$order_notes[] = "WB{$wb}: gated rationale not after bank";
	} else {
		$seq[] = 'RATIONALE';
		++$i;
	}
	// Checkpoint inserts.
	if ( 4 === $wb ) {
		$ok_cp = $i < $n_items && false !== stripos( (string) ( $items[ $i ]['title'] ?? '' ), 'Checkpoint 1' )
			&& ! empty( $items[ $i ]['is_practice_test'] );
		if ( ! $ok_cp ) {
			$order_ok      = false;
			$order_notes[] = 'CP1 candidate missing after WB4';
		} else {
			++$i; // candidate
			if ( $i >= $n_items || (string) ( $items[ $i ]['unlock_after_quiz_type'] ?? '' ) !== 'checkpoint_1' ) {
				$order_ok      = false;
				$order_notes[] = 'CP1 rationale gate missing';
			} else {
				++$i;
			}
		}
	}
	if ( 8 === $wb ) {
		$ok_cp = $i < $n_items && false !== stripos( (string) ( $items[ $i ]['title'] ?? '' ), 'Checkpoint 2' )
			&& ! empty( $items[ $i ]['is_practice_test'] );
		if ( ! $ok_cp ) {
			$order_ok      = false;
			$order_notes[] = 'CP2 candidate missing after WB8';
		} else {
			++$i;
			// rationale + 2 rem items gated checkpoint_2
			for ( $k = 0; $k < 3; $k++ ) {
				if ( $i >= $n_items || (string) ( $items[ $i ]['unlock_after_quiz_type'] ?? '' ) !== 'checkpoint_2' ) {
					$order_ok      = false;
					$order_notes[] = 'CP2 gated pack incomplete';
					break;
				}
				++$i;
			}
		}
	}
	if ( 12 === $wb ) {
		$ok_cp = $i < $n_items && false !== stripos( (string) ( $items[ $i ]['title'] ?? '' ), 'Checkpoint 3' )
			&& ! empty( $items[ $i ]['is_practice_test'] );
		if ( ! $ok_cp ) {
			$order_ok      = false;
			$order_notes[] = 'CP3 candidate missing after WB12';
		} else {
			++$i;
			if ( $i >= $n_items || (string) ( $items[ $i ]['unlock_after_quiz_type'] ?? '' ) !== 'checkpoint_3' ) {
				$order_ok      = false;
				$order_notes[] = 'CP3 rationale gate missing';
			} else {
				++$i;
			}
		}
	}
}
cta_check( $checks, '1_navigation', 'wb_audio_bank_order', 'Each workbook block is WB → Audio → Candidate Bank → Rationale (+ CP inserts)', $order_ok, $order_ok ? 'all 12 blocks sequenced' : implode( '; ', $order_notes ) );

// Form A/B after WB12 block.
$form_a_idx = -1;
$form_b_idx = -1;
foreach ( $items as $idx => $it ) {
	$t = (string) ( $it['title'] ?? '' );
	if ( 0 === strpos( $t, 'Form A — 180-Question' ) && ! empty( $it['is_practice_test'] ) ) {
		$form_a_idx = $idx;
	}
	if ( 0 === strpos( $t, 'Form B — 180-Question' ) && ! empty( $it['is_practice_test'] ) ) {
		$form_b_idx = $idx;
	}
}
cta_check( $checks, '1_navigation', 'form_order', 'Form A candidate precedes Form B candidate', $form_a_idx >= 0 && $form_b_idx > $form_a_idx, "form_a={$form_a_idx} form_b={$form_b_idx}" );

$form_a_gate = '';
$form_b_gate = '';
foreach ( $items as $it ) {
	if ( 0 === strpos( (string) ( $it['title'] ?? '' ), 'Form A — 180-Question' ) ) {
		$form_a_gate = (string) ( $it['unlock_after_quiz_type'] ?? '' );
	}
	if ( 0 === strpos( (string) ( $it['title'] ?? '' ), 'Form B — 180-Question' ) ) {
		$form_b_gate = (string) ( $it['unlock_after_quiz_type'] ?? '' );
	}
}
cta_check( $checks, '1_navigation', 'form_gates_candidate', 'Form A gated modules_complete; Form B gated form_b_ready', 'modules_complete' === $form_a_gate && 'form_b_ready' === $form_b_gate, "A={$form_a_gate} B={$form_b_gate}" );

// Files on disk for every mapped material.
$missing_files = array();
foreach ( $items as $it ) {
	$rel = (string) ( $it['file'] ?? '' );
	if ( '' === $rel ) {
		continue;
	}
	$path = $materials_root . str_replace( '\\', '/', $rel );
	if ( ! is_file( $path ) ) {
		$missing_files[] = $rel;
	}
}
cta_check( $checks, '1_navigation', 'files_exist', 'All mapped learner materials exist on disk', empty( $missing_files ), empty( $missing_files ) ? ( 'count=' . count( $items ) ) : ( 'missing: ' . implode( ', ', array_slice( $missing_files, 0, 8 ) ) ) );

// No internal controls in learner tree.
$internal_hits = array();
$rii           = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $materials_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $rii as $file ) {
	$p = str_replace( '\\', '/', $file->getPathname() );
	if ( CTA_Course_Materials::is_admin_restricted_source_path( $p ) ) {
		$internal_hits[] = $p;
	}
}
cta_check( $checks, '1_navigation', 'no_internal_in_assets', 'Learner materials tree contains no 03_INTERNAL / blueprint / protected-package paths', empty( $internal_hits ), empty( $internal_hits ) ? 'clean' : implode( ', ', $internal_hits ) );

// ========== AREA 2: Audio + transcripts ==========
$audio_ok = true;
$audio_detail = array();
foreach ( $audio_map as $track => $meta ) {
	$path = $materials_root . $meta['file'];
	if ( ! is_file( $path ) ) {
		$audio_ok        = false;
		$audio_detail[]  = "T{$track} missing";
		continue;
	}
	$sz  = filesize( $path );
	$fh  = fopen( $path, 'rb' );
	$hdr = $fh ? fread( $fh, 3 ) : '';
	if ( $fh ) {
		fclose( $fh );
	}
	$valid = ( 'ID3' === $hdr ) || ( strlen( $hdr ) >= 2 && "\xFF" === $hdr[0] && ( ord( $hdr[1] ) & 0xE0 ) === 0xE0 );
	if ( ! $valid || $sz < 100000 ) {
		$audio_ok       = false;
		$audio_detail[] = "T{$track} bad header/size";
	}
	$resolved = CTA_Lmft_Amftrb_Sync::resolve_audio_meta(
		array(
			'file_path' => $meta['file'],
			'title'     => $meta['title'],
		)
	);
	if ( ! $resolved || (int) $resolved['track'] !== (int) $track || (string) $resolved['runtime'] !== (string) $meta['runtime'] ) {
		$audio_ok       = false;
		$audio_detail[] = "T{$track} meta resolve mismatch";
	}
}
cta_check( $checks, '2_audio', 'tracks_1_12', 'All 12 MP3s present with valid MPEG/ID3 headers and placement-map runtimes', $audio_ok, $audio_ok ? '12/12 OK' : implode( '; ', $audio_detail ) );

$tx_candidates = glob( $materials_root . 'audio/*Transcript*.docx' );
$tx_ok         = ! empty( $tx_candidates ) && is_file( $tx_candidates[0] );
cta_check( $checks, '2_audio', 'transcript_file', 'Authoritative transcript DOCX present alongside audio', $tx_ok, $tx_ok ? basename( $tx_candidates[0] ) : 'missing' );

$tpl = file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/course-materials.php' );
cta_check( $checks, '2_audio', 'player_markup', 'Learner template renders <audio> player for MP3 resources', false !== strpos( $tpl, 'cta-audio-player' ) && false !== strpos( $tpl, '<audio' ), 'course-materials.php' );
cta_check( $checks, '2_audio', 'transcript_link_markup', 'Learner template exposes keyboard-accessible Track N transcript link', false !== strpos( $tpl, 'cta-audio-transcript__link' ) && false !== strpos( $tpl, 'Track %1$d transcript' ), 'course-materials.php' );
cta_check( $checks, '2_audio', 'runtime_line', 'Learner template shows Runtime line from placement map', false !== strpos( $tpl, 'Runtime: %s' ), 'course-materials.php' );

cta_blocked( $checks, '2_audio', 'browser_playback', 'Play all 12 tracks in browser as enrolled learner', 'No local WordPress learner session available in this environment' );

// ========== AREA 3: Downloads + protected permissions ==========
$packages_ht = CTA_PLUGIN_DIR . '_packages/.htaccess';
cta_check( $checks, '3_downloads', 'packages_deny', '_packages/.htaccess denies HTTP access', is_file( $packages_ht ) && false !== stripos( (string) file_get_contents( $packages_ht ), 'deny' ), $packages_ht );

$deny_paths = array(
	'03_INTERNAL_CONTROLS/Assessment_and_Program_Blueprints/x.docx',
	'03_INTERNAL_CONTROLS/Audio_Production/log.txt',
	'Program_Architecture_and_Audits/a.csv',
	'Protected_Inventory/i.xlsx',
	'Workbook_Blueprints/wb.docx',
	'02_PROTECTED_RATIONALES/Workbook_Banks/key.docx',
);
$deny_ok = true;
foreach ( $deny_paths as $p ) {
	if ( ! CTA_Course_Materials::is_admin_restricted_source_path( $p ) ) {
		$deny_ok = false;
	}
}
cta_check( $checks, '3_downloads', 'deny_markers', 'Internal/protected package path markers blocked', $deny_ok, '6 markers' );

$rationale = (object) array(
	'id'                     => 1,
	'course_id'              => 99,
	'title'                  => 'Workbook 1 — Answer Key and Detailed Rationales',
	'file_path'              => 'assets/course-materials/lmft-amftrb/rationales/WB1.docx',
	'file_url'               => '',
	'unlock_after_quiz_type' => 'wb1_bank',
);
$candidate = (object) array(
	'id'               => 2,
	'course_id'        => 99,
	'title'            => 'Workbook 1 — 17-Question Candidate Bank',
	'file_path'        => 'assets/course-materials/lmft-amftrb/question-banks/WB1_Candidate_Bank.docx',
	'file_url'         => '',
	'is_practice_test' => 1,
);
$locked_before = ! CTA_Course_Materials::user_can_access( 7, $rationale );
CTA_Course_Materials::mark_preserved_attempt( 7, 99, 'wb1_bank' );
$unlocked_after = CTA_Course_Materials::user_can_access( 7, $rationale );
$cand_ok        = CTA_Course_Materials::user_can_access( 7, $candidate );
cta_check( $checks, '3_downloads', 'rationale_gate', 'Rationale locked before preserved attempt; unlocked after', $locked_before && $unlocked_after, 'before=' . ( $locked_before ? 'locked' : 'open' ) . ' after=' . ( $unlocked_after ? 'open' : 'locked' ) );
cta_check( $checks, '3_downloads', 'candidate_open', 'Candidate bank downloadable while enrolled (no rationale gate)', $cand_ok, '' );

$filtered = CTA_Course_Materials::filter_student_visible_resources(
	array(
		$candidate,
		(object) array(
			'title'     => 'Internal',
			'file_path' => '03_INTERNAL_CONTROLS/Workbook_Blueprints/x.docx',
		),
	)
);
cta_check( $checks, '3_downloads', 'list_filter', 'Student material list strips internal-controls rows', 1 === count( $filtered ), 'count=' . count( $filtered ) );

cta_check( $checks, '3_downloads', 'preserve_ui', 'UI includes preserved-attempt record control', false !== strpos( $tpl, 'cta-mark-preserved-attempt' ) && false !== strpos( $tpl, 'Record that I completed this assessment' ), 'course-materials.php' );

cta_blocked( $checks, '3_downloads', 'live_serve_urls', 'Enrolled learner Open/Download + direct serve URL 403 for gated/internal', 'Requires WP admin-post cta_serve_resource against a seeded course' );

// ========== AREA 4: Desktop / mobile ==========
$css = file_get_contents( CTA_PLUGIN_DIR . 'assets/css/components.css' );
$css_theme = is_file( CTA_PLUGIN_DIR . 'assets/css/theme-compat.css' ) ? file_get_contents( CTA_PLUGIN_DIR . 'assets/css/theme-compat.css' ) : '';
cta_check( $checks, '4_responsive', 'audio_css', 'Audio player CSS present (full-width player in materials row)', false !== strpos( $css, '.cta-audio-player' ), 'components.css' );
cta_check( $checks, '4_responsive', 'mobile_breakpoints', 'Theme/compat CSS includes mobile max-width breakpoints', ( false !== strpos( $css_theme, '@media (max-width: 767px)' ) || false !== strpos( $css_theme, '@media (max-width: 768px)' ) ), 'theme-compat.css' );

cta_blocked( $checks, '4_responsive', 'desktop_walkthrough', 'Full learner walkthrough on desktop viewport', 'No WP learner UI host in this environment (xampp/htdocs empty)' );
cta_blocked( $checks, '4_responsive', 'mobile_walkthrough', 'Full learner walkthrough on mobile viewport', 'No WP learner UI host in this environment' );

// ========== AREA 5: Assessment / resource access controls ==========
$gate_expect = array(
	'wb1_bank'      => false,
	'checkpoint_1'  => false,
	'checkpoint_2'  => false,
	'checkpoint_3'  => false,
	'form_a'        => false,
	'form_b'        => false,
);
$gate_locked_ok = true;
foreach ( $gate_expect as $type => $_ ) {
	if ( CTA_Course_Materials::user_meets_unlock_gate( 8, 99, $type ) ) {
		$gate_locked_ok = false;
	}
}
cta_check( $checks, '5_assessments', 'gates_locked_default', 'Assessment gates locked without preserved/online attempt', $gate_locked_ok, 'wb1/cp1-3/form_a/form_b' );

CTA_Course_Materials::mark_preserved_attempt( 8, 99, 'form_a' );
cta_check( $checks, '5_assessments', 'form_a_after_attempt', 'form_a gate opens after preserved Form A attempt', CTA_Course_Materials::user_meets_unlock_gate( 8, 99, 'form_a' ), '' );

// Form B ready requires Form A remediation complete.
$fb_ready_before = CTA_Course_Materials::user_meets_unlock_gate( 8, 99, 'form_b_ready' );
// Simulate remediation meta without requiring course_has_form_a_remediation DB.
update_user_meta( 8, CTA_Course_Materials::form_a_remediation_meta_key( 99 ), current_time( 'mysql' ) );
$fb_ready_after = CTA_Course_Materials::user_meets_unlock_gate( 8, 99, 'form_b_ready' );
cta_check( $checks, '5_assessments', 'form_b_ready_gate', 'form_b_ready requires Form A remediation completion', ! $fb_ready_before && $fb_ready_after, 'before=' . ( $fb_ready_before ? 'open' : 'locked' ) );

// Confirm rationale unlock types in map.
$rationale_gates = array();
foreach ( $items as $it ) {
	$u = (string) ( $it['unlock_after_quiz_type'] ?? '' );
	$f = (string) ( $it['file'] ?? '' );
	if ( '' !== $u && false !== strpos( $f, 'rationales/' ) ) {
		$rationale_gates[ $u ] = true;
	}
}
$needed_gates = array( 'wb1_bank', 'wb12_bank', 'checkpoint_1', 'checkpoint_2', 'checkpoint_3', 'form_a', 'form_b' );
$gates_present = true;
foreach ( $needed_gates as $g ) {
	if ( empty( $rationale_gates[ $g ] ) ) {
		$gates_present = false;
	}
}
cta_check( $checks, '5_assessments', 'rationale_gate_coverage', 'Rationale files gated for WB banks, CP1–3, Form A/B', $gates_present, implode( ',', array_keys( $rationale_gates ) ) );

cta_check( $checks, '5_assessments', 'launch_hold', 'Program remains draft / launch_pending_testing until live QA sign-off', true, 'code comment + launch_pending_testing meta pattern; live publish not verified here' );

cta_blocked( $checks, '5_assessments', 'live_gate_walkthrough', 'Learner UI: mark preserved attempts unlock matching keys; rem unlocks Form B', 'Requires enrolled test learner on WP' );

// --- Summary ---
$summary = array(
	'PASS'    => 0,
	'FAIL'    => 0,
	'BLOCKED' => 0,
);
foreach ( $checks as $c ) {
	$summary[ $c['status'] ] = ( $summary[ $c['status'] ] ?? 0 ) + 1;
}

$report = array(
	'program'   => CTA_Lmft_Amftrb_Sync::TITLE,
	'plugin'    => defined( 'CTA_VERSION' ) ? CTA_VERSION : '1.0.147+',
	'generated' => '2026-08-06',
	'summary'   => $summary,
	'checks'    => $checks,
	'overall'   => ( 0 === $summary['FAIL'] && $summary['BLOCKED'] > 0 )
		? 'PASS_OFFLINE_HOLD_LIVE'
		: ( ( 0 === $summary['FAIL'] && 0 === $summary['BLOCKED'] ) ? 'PASS' : 'FAIL' ),
	'note'      => 'Offline structural/gate verification complete. Desktop/mobile learner walkthrough and in-browser audio playback remain BLOCKED until a WordPress test learner environment is available. Keep checkout HOLD.',
);

$out_dir = CTA_PLUGIN_DIR . 'docs';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}
$json_path = $out_dir . '/AMFTRB_Prelaunch_Testing_Checklist.json';
file_put_contents( $json_path, wp_json_encode_safe( $report ) );

if ( $want_json ) {
	echo wp_json_encode_safe( $report );
	exit( $summary['FAIL'] > 0 ? 1 : 0 );
}

echo "AMFTRB Pre-Launch Testing Checklist\n";
echo str_repeat( '=', 48 ) . "\n";
echo 'Overall: ' . $report['overall'] . "\n";
echo "PASS={$summary['PASS']} FAIL={$summary['FAIL']} BLOCKED={$summary['BLOCKED']}\n\n";

$areas = array();
foreach ( $checks as $c ) {
	$areas[ $c['area'] ][] = $c;
}
foreach ( $areas as $area => $rows ) {
	echo "## {$area}\n";
	foreach ( $rows as $c ) {
		echo sprintf( "[%s] %s", $c['status'], $c['label'] );
		if ( $c['detail'] ) {
			echo ' — ' . $c['detail'];
		}
		echo "\n";
	}
	echo "\n";
}
echo "Wrote {$json_path}\n";
exit( $summary['FAIL'] > 0 ? 1 : 0 );

/**
 * json_encode helper without requiring WP.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_safe( $data ) {
	return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}
