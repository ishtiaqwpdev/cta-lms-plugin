<?php
/**
 * Exam Prep Flashcard Study Center — landing, study, and browse modes.
 *
 * @package CTA_LMS
 *
 * @var array       $flashcard_center_deck Deck from CTA_Exam_Prep_Flashcard_Center.
 * @var object      $course                Course row.
 * @var int         $course_id             Course ID (optional).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$deck = isset( $flashcard_center_deck ) && is_array( $flashcard_center_deck )
	? $flashcard_center_deck
	: ( class_exists( 'CTA_Exam_Prep_Flashcard_Center' ) && ! empty( $course )
		? CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course )
		: array(
			'title'       => __( 'Flashcard Study Center', 'cta-lms' ),
			'count'       => 0,
			'cards'       => array(),
			'domains'     => array(),
			'has_content' => false,
		) );

$deck_title    = (string) ( $deck['title'] ?? __( 'Flashcard Study Center', 'cta-lms' ) );
$deck_count    = (int) ( $deck['count'] ?? 0 );
$deck_domains  = isset( $deck['domains'] ) ? (array) $deck['domains'] : array();
$has_content   = ! empty( $deck['has_content'] ) && $deck_count > 0;
$domain_total  = count( array_filter( $deck_domains, static function ( $d ) {
	return (int) ( $d['count'] ?? 0 ) > 0;
} ) );
$cid           = isset( $course_id ) ? (int) $course_id : ( ! empty( $course->id ) ? (int) $course->id : 0 );
$user_id       = get_current_user_id();
$storage_key   = 'cta_fsc_' . $cid . '_' . $user_id;
$deck_id       = 'cta-fsc-' . wp_unique_id();

$deck_json = wp_json_encode(
	array(
		'title'       => $deck_title,
		'count'       => $deck_count,
		'cards'       => array_values(
			array_map(
				static function ( $card ) {
					$row = array(
						'id'     => (string) ( $card['id'] ?? '' ),
						'domain' => (string) ( $card['domain'] ?? '' ),
						'front'  => (string) ( $card['front'] ?? '' ),
						'back'   => (string) ( $card['back'] ?? '' ),
					);
					$cue = trim( (string) ( $card['memory_cue'] ?? '' ) );
					if ( '' !== $cue ) {
						$row['memory_cue'] = $cue;
					}
					return $row;
				},
				(array) ( $deck['cards'] ?? array() )
			)
		),
		'domains'     => array_values( $deck_domains ),
		'has_content' => $has_content,
	),
	JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<div
	class="cta-fsc"
	id="<?php echo esc_attr( $deck_id ); ?>"
	data-cta-fsc
	data-course-id="<?php echo esc_attr( (string) $cid ); ?>"
	data-storage-key="<?php echo esc_attr( $storage_key ); ?>"
	<?php echo $has_content ? '' : 'data-cta-fsc-empty="1"'; ?>
>
	<!-- Landing -->
	<div class="cta-fsc__panel cta-fsc__landing" data-cta-fsc-panel="landing">
		<p class="cta-ep-home-section__lede">
			<?php esc_html_e( 'Study blueprint-aligned flashcards by official exam domain — one card at a time or in browse mode for quick review.', 'cta-lms' ); ?>
		</p>

		<div class="cta-fsc__stats">
			<div class="cta-fsc__stat-card">
				<span class="cta-fsc__stat-value"><?php echo esc_html( (string) $deck_count ); ?></span>
				<span class="cta-fsc__stat-label"><?php esc_html_e( 'Total cards', 'cta-lms' ); ?></span>
			</div>
			<div class="cta-fsc__stat-card">
				<span class="cta-fsc__stat-value"><?php echo esc_html( (string) max( 0, $domain_total ) ); ?></span>
				<span class="cta-fsc__stat-label"><?php esc_html_e( 'Exam domains', 'cta-lms' ); ?></span>
			</div>
		</div>

		<?php if ( $has_content && ! empty( $deck_domains ) ) : ?>
			<div class="cta-fsc__domain-summary">
				<h3 class="cta-fsc__domain-summary-title"><?php esc_html_e( 'Domain breakdown', 'cta-lms' ); ?></h3>
				<ul class="cta-fsc__domain-summary-list">
					<?php foreach ( $deck_domains as $domain ) : ?>
						<?php if ( (int) ( $domain['count'] ?? 0 ) <= 0 ) : continue; endif; ?>
						<li class="cta-fsc__domain-summary-item">
							<span class="cta-fsc__domain-summary-label"><?php echo esc_html( (string) ( $domain['label'] ?? '' ) ); ?></span>
							<span class="cta-fsc__domain-summary-count">
								<?php
								printf(
									/* translators: %d: card count */
									esc_html( _n( '%d card', '%d cards', (int) $domain['count'], 'cta-lms' ) ),
									(int) $domain['count']
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="cta-fsc__domain-summary-meta">
					<?php
					printf(
						/* translators: 1: total cards, 2: domain count */
						esc_html__( '%1$d cards across %2$d exam domains', 'cta-lms' ),
						$deck_count,
						$domain_total
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! $has_content ) : ?>
			<div class="cta-fsc__empty" role="status">
				<p class="cta-fsc__empty-title"><?php esc_html_e( 'Flashcard deck coming soon', 'cta-lms' ); ?></p>
				<p class="cta-fsc__empty-text">
					<?php esc_html_e( 'The approved blueprint-aligned flashcard deck for this program is being finalized. Check back soon — Study Mode and Browse Mode will unlock automatically when cards are published.', 'cta-lms' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<div class="cta-fsc__landing-actions">
			<button type="button" class="btn btn-primary" data-cta-fsc-start="study">
				<?php esc_html_e( 'Start Study Mode', 'cta-lms' ); ?>
			</button>
			<button type="button" class="btn btn-outline" data-cta-fsc-start="browse">
				<?php esc_html_e( 'Browse / Review Mode', 'cta-lms' ); ?>
			</button>
		</div>
	</div>

	<!-- Study Mode -->
	<div class="cta-fsc__panel cta-fsc__study" data-cta-fsc-panel="study" hidden>
		<div class="cta-fsc__toolbar">
			<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-nav-back><?php esc_html_e( '← Back', 'cta-lms' ); ?></button>
			<div class="cta-fsc__mode-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Flashcard mode', 'cta-lms' ); ?>">
				<button type="button" class="cta-fsc__mode-tab is-active" role="tab" aria-selected="true" data-cta-fsc-mode="study"><?php esc_html_e( 'Study Mode', 'cta-lms' ); ?></button>
				<button type="button" class="cta-fsc__mode-tab" role="tab" aria-selected="false" data-cta-fsc-mode="browse"><?php esc_html_e( 'Browse / Review', 'cta-lms' ); ?></button>
			</div>
		</div>

		<div class="cta-fsc__filters">
			<label class="cta-fsc__search-wrap">
				<span class="screen-reader-text"><?php esc_html_e( 'Search flashcards', 'cta-lms' ); ?></span>
				<input type="search" class="cta-fsc__search" data-cta-fsc-search placeholder="<?php esc_attr_e( 'Search by keyword or term…', 'cta-lms' ); ?>" autocomplete="off" />
			</label>
			<div class="cta-fsc__domain-filters" data-cta-fsc-domain-filters role="group" aria-label="<?php esc_attr_e( 'Filter by exam domain', 'cta-lms' ); ?>">
				<button type="button" class="cta-fsc__domain-chip is-active" data-cta-fsc-domain="all"><?php esc_html_e( 'All domains', 'cta-lms' ); ?></button>
			</div>
		</div>

		<div class="cta-fsc__progress" aria-live="polite">
			<div class="cta-fsc__progress-meta">
				<span data-cta-fsc-progress-label><?php esc_html_e( 'Card 1 of 1', 'cta-lms' ); ?></span>
				<span class="cta-fsc__progress-domain" data-cta-fsc-progress-domain></span>
			</div>
			<div class="progress__track cta-fsc__progress-track">
				<div class="progress__bar cta-fsc__progress-bar" data-cta-fsc-progress-bar style="width: 0%;"></div>
			</div>
		</div>

		<div class="cta-fsc__flip-scene">
			<button type="button" class="cta-fsc__flip-trigger" data-cta-fsc-flip aria-pressed="false">
				<div class="cta-fsc__flip-inner">
					<div class="cta-fsc__flip-face cta-fsc__flip-face--front">
						<span class="cta-fsc__flip-badge" data-cta-fsc-front-domain></span>
						<span class="cta-fsc__flip-label"><?php esc_html_e( 'Question', 'cta-lms' ); ?></span>
						<span class="cta-fsc__flip-text" data-cta-fsc-front></span>
						<span class="cta-fsc__flip-hint"><?php esc_html_e( 'Tap or click to reveal answer', 'cta-lms' ); ?></span>
					</div>
					<div class="cta-fsc__flip-face cta-fsc__flip-face--back">
						<span class="cta-fsc__flip-badge" data-cta-fsc-back-domain></span>
						<span class="cta-fsc__flip-label"><?php esc_html_e( 'Answer', 'cta-lms' ); ?></span>
						<span class="cta-fsc__flip-text" data-cta-fsc-answer></span>
						<div class="cta-fsc__memory-cue" data-cta-fsc-memory-cue-wrap hidden>
							<span class="cta-fsc__memory-cue-label"><?php esc_html_e( 'Memory Cue', 'cta-lms' ); ?></span>
							<span class="cta-fsc__memory-cue-text" data-cta-fsc-memory-cue></span>
						</div>
						<span class="cta-fsc__flip-hint"><?php esc_html_e( 'Tap or click to show question', 'cta-lms' ); ?></span>
					</div>
				</div>
			</button>
		</div>

		<div class="cta-fsc__self-assess" data-cta-fsc-self-assess>
			<span class="cta-fsc__self-assess-label"><?php esc_html_e( 'How well did you know this?', 'cta-lms' ); ?></span>
			<div class="cta-fsc__self-assess-actions">
				<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-know><?php esc_html_e( 'Know It', 'cta-lms' ); ?></button>
				<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-review><?php esc_html_e( 'Review Again', 'cta-lms' ); ?></button>
			</div>
		</div>

		<div class="cta-fsc__controls">
			<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-prev><?php esc_html_e( 'Previous', 'cta-lms' ); ?></button>
			<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-shuffle><?php esc_html_e( 'Shuffle', 'cta-lms' ); ?></button>
			<button type="button" class="btn btn-primary btn--sm" data-cta-fsc-next><?php esc_html_e( 'Next', 'cta-lms' ); ?></button>
		</div>
	</div>

	<!-- Browse Mode -->
	<div class="cta-fsc__panel cta-fsc__browse" data-cta-fsc-panel="browse" hidden>
		<div class="cta-fsc__toolbar">
			<button type="button" class="btn btn-outline btn--sm" data-cta-fsc-nav-back><?php esc_html_e( '← Back', 'cta-lms' ); ?></button>
			<div class="cta-fsc__mode-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Flashcard mode', 'cta-lms' ); ?>">
				<button type="button" class="cta-fsc__mode-tab" role="tab" aria-selected="false" data-cta-fsc-mode="study"><?php esc_html_e( 'Study Mode', 'cta-lms' ); ?></button>
				<button type="button" class="cta-fsc__mode-tab is-active" role="tab" aria-selected="true" data-cta-fsc-mode="browse"><?php esc_html_e( 'Browse / Review', 'cta-lms' ); ?></button>
			</div>
		</div>

		<div class="cta-fsc__filters">
			<label class="cta-fsc__search-wrap">
				<span class="screen-reader-text"><?php esc_html_e( 'Search flashcards', 'cta-lms' ); ?></span>
				<input type="search" class="cta-fsc__search" data-cta-fsc-search placeholder="<?php esc_attr_e( 'Search by keyword or term…', 'cta-lms' ); ?>" autocomplete="off" />
			</label>
			<div class="cta-fsc__domain-filters" data-cta-fsc-domain-filters role="group" aria-label="<?php esc_attr_e( 'Filter by exam domain', 'cta-lms' ); ?>">
				<button type="button" class="cta-fsc__domain-chip is-active" data-cta-fsc-domain="all"><?php esc_html_e( 'All domains', 'cta-lms' ); ?></button>
			</div>
		</div>

		<p class="cta-fsc__browse-meta" data-cta-fsc-browse-meta aria-live="polite"></p>

		<div class="cta-fsc__browse-grid" data-cta-fsc-browse-grid></div>
	</div>

	<?php if ( $deck_json ) : ?>
		<script type="application/json" data-cta-fsc-deck><?php echo $deck_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<?php endif; ?>
</div>
