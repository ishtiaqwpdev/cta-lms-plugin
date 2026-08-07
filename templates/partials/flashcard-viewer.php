<?php
/**
 * In-browser Exam Prep flashcard viewer (flip / prev / next / shuffle).
 *
 * @package CTA_LMS
 *
 * @var array $flashcard_deck Deck with title, count, cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $flashcard_deck ) || empty( $flashcard_deck['cards'] ) || ! is_array( $flashcard_deck['cards'] ) ) {
	return;
}

$deck_title = ! empty( $flashcard_deck['title'] )
	? (string) $flashcard_deck['title']
	: __( 'Flashcards', 'cta-lms' );
$deck_count = (int) ( $flashcard_deck['count'] ?? count( $flashcard_deck['cards'] ) );
$deck_id    = 'cta-flashcards-' . wp_unique_id();
?>
<section
	class="cta-flashcards"
	id="<?php echo esc_attr( $deck_id ); ?>"
	aria-labelledby="<?php echo esc_attr( $deck_id ); ?>-title"
	data-cta-flashcards
>
	<div class="cta-flashcards__header">
		<h3 class="cta-flashcards__title" id="<?php echo esc_attr( $deck_id ); ?>-title">
			<?php echo esc_html__( 'Study Flashcards Online', 'cta-lms' ); ?>
		</h3>
		<p class="cta-flashcards__subtitle">
			<?php
			printf(
				/* translators: 1: deck title, 2: card count */
				esc_html__( '%1$s — %2$d cards. Tap the card to reveal the answer. Printable DOCX download remains available in materials above.', 'cta-lms' ),
				esc_html( $deck_title ),
				$deck_count
			);
			?>
		</p>
	</div>

	<div class="cta-flashcards__stage">
		<p class="cta-flashcards__meta" data-cta-flash-meta aria-live="polite"></p>
		<button type="button" class="cta-flashcards__card" data-cta-flash-card aria-live="polite">
			<span class="cta-flashcards__tag" data-cta-flash-tag></span>
			<span class="cta-flashcards__label" data-cta-flash-label><?php echo esc_html__( 'Question', 'cta-lms' ); ?></span>
			<span class="cta-flashcards__text" data-cta-flash-text></span>
			<span class="cta-flashcards__hint" data-cta-flash-hint><?php echo esc_html__( 'Tap to reveal answer', 'cta-lms' ); ?></span>
		</button>
	</div>

	<div class="cta-flashcards__controls">
		<button type="button" class="btn btn-outline btn--sm" data-cta-flash-prev><?php echo esc_html__( 'Previous', 'cta-lms' ); ?></button>
		<button type="button" class="btn btn-outline btn--sm" data-cta-flash-shuffle><?php echo esc_html__( 'Shuffle', 'cta-lms' ); ?></button>
		<button type="button" class="btn btn-primary btn--sm" data-cta-flash-next><?php echo esc_html__( 'Next', 'cta-lms' ); ?></button>
	</div>

	<script type="application/json" data-cta-flash-deck><?php echo wp_json_encode( array_values( $flashcard_deck['cards'] ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</section>
