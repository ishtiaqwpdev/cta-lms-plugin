<?php
/**
 * Exam Prep Downloads landing page.
 *
 * @package CTA_LMS
 *
 * @var array $downloads_data Categorized downloads payload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$center         = isset( $downloads_data ) && is_array( $downloads_data )
	? $downloads_data
	: CTA_Exam_Prep_Downloads::empty_data();
$categories     = (array) ( $center['categories'] ?? array() );
$item_count     = (int) ( $center['item_count'] ?? 0 );
$category_count = (int) ( $center['category_count'] ?? 0 );
$has_downloads  = ! empty( $center['has_downloads'] ) && ! empty( $categories );
?>
<div class="cta-dl" data-cta-downloads>
	<p class="cta-ep-home-section__lede">
		<?php esc_html_e( 'Download printable workbooks, assessment files, study toolkits, audio, and other program materials for offline use.', 'cta-lms' ); ?>
	</p>

	<div class="cta-dl__stats">
		<div class="cta-dl__stat">
			<strong><?php echo esc_html( (string) $item_count ); ?></strong>
			<span><?php esc_html_e( 'Downloadable files', 'cta-lms' ); ?></span>
		</div>
		<div class="cta-dl__stat">
			<strong><?php echo esc_html( (string) $category_count ); ?></strong>
			<span><?php esc_html_e( 'File categories', 'cta-lms' ); ?></span>
		</div>
	</div>

	<?php if ( ! $has_downloads ) : ?>
		<div class="cta-dl__empty" role="status">
			<h3><?php esc_html_e( 'No downloadable files available', 'cta-lms' ); ?></h3>
			<p><?php esc_html_e( 'Downloadable program materials will appear here when published.', 'cta-lms' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $categories as $category ) : ?>
			<?php
			$key   = sanitize_key( (string) ( $category['key'] ?? 'other' ) );
			$items = (array) ( $category['items'] ?? array() );
			if ( empty( $items ) ) {
				continue;
			}
			?>
			<section class="cta-dl__section" id="cta-dl-<?php echo esc_attr( $key ); ?>" aria-labelledby="cta-dl-<?php echo esc_attr( $key ); ?>-title">
				<header class="cta-dl__section-head">
					<div>
						<h3 id="cta-dl-<?php echo esc_attr( $key ); ?>-title"><?php echo esc_html( (string) ( $category['label'] ?? '' ) ); ?></h3>
						<?php if ( ! empty( $category['description'] ) ) : ?>
							<p><?php echo esc_html( (string) $category['description'] ); ?></p>
						<?php endif; ?>
					</div>
					<span class="cta-dl__count">
						<?php
						printf(
							/* translators: %d: file count */
							esc_html( _n( '%d file', '%d files', count( $items ), 'cta-lms' ) ),
							count( $items )
						);
						?>
					</span>
				</header>

				<div class="cta-dl__list">
					<?php foreach ( $items as $item ) : ?>
						<article class="cta-dl__item<?php echo ! empty( $item['locked'] ) ? ' cta-dl__item--locked' : ''; ?>">
							<div class="cta-dl__file-icon cta-dl__file-icon--<?php echo esc_attr( strtolower( (string) ( $item['extension'] ?? 'file' ) ) ); ?>" aria-hidden="true">
								<?php echo esc_html( (string) ( $item['extension'] ?? __( 'FILE', 'cta-lms' ) ) ); ?>
							</div>
							<div class="cta-dl__file-copy">
								<h4><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h4>
								<?php if ( ! empty( $item['filename'] ) ) : ?>
									<p class="cta-dl__filename"><?php echo esc_html( (string) $item['filename'] ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $item['locked'] ) && ! empty( $item['lock_message'] ) ) : ?>
									<p class="cta-dl__lock-message"><?php echo esc_html( (string) $item['lock_message'] ); ?></p>
								<?php endif; ?>
								<p class="cta-dl__meta">
									<span><?php echo esc_html( (string) ( $item['extension'] ?? __( 'File', 'cta-lms' ) ) ); ?></span>
									<?php if ( ! empty( $item['size_label'] ) ) : ?>
										<span aria-hidden="true">•</span>
										<span><?php echo esc_html( (string) $item['size_label'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $item['is_external'] ) ) : ?>
										<span aria-hidden="true">•</span>
										<span><?php esc_html_e( 'Secure external file', 'cta-lms' ); ?></span>
									<?php endif; ?>
								</p>
							</div>
							<?php if ( ! empty( $item['locked'] ) ) : ?>
								<span class="btn btn-outline btn--sm cta-dl__download cta-dl__download--locked" aria-disabled="true">
									<span aria-hidden="true">🔒</span>
									<?php esc_html_e( 'Locked', 'cta-lms' ); ?>
								</span>
							<?php else : ?>
								<a
									class="btn btn-primary btn--sm cta-dl__download"
									href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Download %s', 'cta-lms' ), (string) ( $item['title'] ?? '' ) ) ); ?>"
								>
									<span aria-hidden="true">↓</span>
									<?php esc_html_e( 'Download', 'cta-lms' ); ?>
								</a>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
