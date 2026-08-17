<?php
/**
 * Simulate learner Practice Bank tab HTML for LCSW WB1/WB2 with online quizzes present.
 *
 * Usage: php scripts/test-lcsw-aswb-practice-bank-tab-render.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$fail = 0;
$pass = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function assert_true( $cond, $msg ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "PASS: {$msg}\n";
		++$pass;
	} else {
		echo "FAIL: {$msg}\n";
		++$fail;
	}
}

/**
 * Minimal WordPress escape stubs for template include.
 */
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $t ) { return $t; }
	function esc_html_e( $t ) { echo $t; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $t ) { return (string) $t; }
	function __( $t ) { return $t; }
	function absint( $v ) { return abs( (int) $v ); }
	function apply_filters( $tag, $value ) { return $value; }
	function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/includes/class-cta-exam-prep-workbook-sections.php';
require_once $root . '/includes/class-cta-exam-prep-workbooks.php';

$placeholder = 'check back when the online quiz is published';

foreach ( array( 1, 2 ) as $wb ) {
	$module = (object) array(
		'id'    => $wb,
		'title' => 'Workbook ' . $wb . ': Test',
	);
	$quiz = (object) array(
		'id'        => 1000 + $wb,
		'title'     => 'Workbook ' . $wb . ' — 17-Question Practice Bank',
		'quiz_type' => 'wb' . $wb . '_bank',
	);
	$cards = array(
		array(
			'quiz'    => $quiz,
			'url'     => 'https://example.test/quiz/?quiz_id=' . ( 1000 + $wb ),
			'passed'  => false,
			'best'    => null,
			'locked'  => false,
			'active'  => false,
			'lock_msg'=> '',
		),
	);

	$tabs = CTA_Exam_Prep_Workbook_Sections::build_tabs(
		'',
		array(
			'quiz_cards'         => $cards,
			'bank_download_url'  => 'https://example.test/download/bank.docx',
			'quiz_page_id'       => 55,
			'module'             => $module,
		)
	);

	$practice = null;
	foreach ( $tabs as $tab ) {
		if ( ( $tab['key'] ?? '' ) === 'practice' ) {
			$practice = $tab;
			break;
		}
	}

	assert_true( is_array( $practice ), "WB{$wb} practice tab exists" );
	assert_true( ! empty( $practice['quiz_cards'] ), "WB{$wb} practice tab has online quiz cards" );
	assert_true( ! empty( $practice['bank_url'] ), "WB{$wb} downloadable bank URL retained" );

	// Render the same partial branch used on learner workbook pages.
	$course  = (object) array( 'id' => 1 );
	$module  = $module;
	$qpid    = 55;
	$module_complete = false;
	$workbook_tabs = array( $practice );
	$tab = $practice;

	ob_start();
	$cards = isset( $tab['quiz_cards'] ) ? (array) $tab['quiz_cards'] : array();
	?>
	<div class="cta-ep-workbook-section" data-tab="practice">
		<?php if ( ! empty( $cards ) ) : ?>
			<ul class="cta-exam-assessment-list">
				<?php foreach ( $cards as $card ) : ?>
					<li>
						<a class="btn btn-primary" href="<?php echo esc_url( $card['url'] ); ?>">Start Practice Bank</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( ! empty( $tab['bank_url'] ) ) : ?>
			<p><?php esc_html_e( 'Use the downloadable practice bank link above, or check back when the online quiz is published for this program.' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	$html = ob_get_clean();

	assert_true( false === strpos( $html, $placeholder ), "WB{$wb} learner HTML has no placeholder" );
	assert_true( false !== strpos( $html, 'Start Practice Bank' ), "WB{$wb} learner HTML shows Start Practice Bank" );
	assert_true( false !== strpos( $html, 'quiz_id=' . ( 1000 + $wb ) ), "WB{$wb} quiz link present" );

	// Seed content for quiz attempt payload security (answers not in stem fields).
	$seed = include $root . '/includes/quiz-seeds/lcsw-aswb-wb' . $wb . '-bank.php';
	$learner_blob = '';
	foreach ( $seed as $q ) {
		$learner_blob .= $q['question_text'] . "\n" . $q['option_a'] . "\n" . $q['option_b'] . "\n" . $q['option_c'] . "\n" . $q['option_d'] . "\n";
	}
	assert_true( false === stripos( $learner_blob, 'Correct Answer' ), "WB{$wb} learner fields omit Correct Answer labels" );
	assert_true( false === stripos( $learner_blob, 'Incorrect.' ), "WB{$wb} learner fields omit rationale text" );
	assert_true( '' !== (string) $seed[0]['explanation'], "WB{$wb} rationale stored server-side in explanation" );
}

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
