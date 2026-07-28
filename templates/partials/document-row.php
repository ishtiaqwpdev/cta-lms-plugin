<?php
/**
 * Uploaded document row partial.
 *
 * @package CTA_LMS
 *
 * @var object                   $document  Document row.
 * @var CTA_Supervision_Dashboard $dashboard Dashboard instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$review   = $dashboard->get_review_badge( $document );
$is_pdf   = 'pdf' === $document->file_type;
$can_delete = ( 'pending' === $document->review_status );
?>
<div class="document-row cta-document-row" data-document-id="<?php echo esc_attr( $document->id ); ?>">
	<div class="document-row__info">
		<span class="document-row__icon" aria-hidden="true">
			<?php if ( $is_pdf ) : ?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
			<?php else : ?>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
			<?php endif; ?>
		</span>
		<div>
			<p class="document-row__name" title="<?php echo esc_attr( $document->file_name ); ?>">
				<?php echo esc_html( $dashboard->truncate_filename( $document->file_name ) ); ?>
			</p>
			<p class="document-row__date">
				<?php
				printf(
					/* translators: 1: upload date, 2: file size */
					esc_html__( 'Uploaded %1$s · %2$s', 'cta-lms' ),
					esc_html( cta_lms_format_local_date( $document->uploaded_at, 'F j, Y' ) ),
					esc_html( $dashboard->format_file_size( $document->file_size ) )
				);
				?>
			</p>
			<span class="badge badge--outline document-row__category"><?php echo esc_html( $dashboard->get_category_label( $document->doc_category ) ); ?></span>
		</div>
	</div>

	<div class="document-row__actions">
		<span class="badge <?php echo esc_attr( $review['class'] ); ?> document-row__status"><?php echo esc_html( $review['label'] ); ?></span>
		<a href="<?php echo esc_url( $document->file_url ); ?>" class="btn btn-outline btn--sm" download>
			<?php echo esc_html__( 'Download', 'cta-lms' ); ?>
		</a>
		<?php if ( $can_delete ) : ?>
			<button type="button" class="btn btn-outline btn--sm cta-delete-doc" data-document-id="<?php echo esc_attr( $document->id ); ?>">
				<?php echo esc_html__( 'Delete', 'cta-lms' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>
