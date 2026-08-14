<?php
/**
 * Exam Prep Audio Review landing page.
 *
 * @package CTA_LMS
 *
 * @var array $audio_review_data Audio center payload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$center        = isset( $audio_review_data ) && is_array( $audio_review_data )
	? $audio_review_data
	: CTA_Exam_Prep_Audio_Review::empty_data();
$groups        = (array) ( $center['groups'] ?? array() );
$track_count   = (int) ( $center['track_count'] ?? 0 );
$total_runtime = (string) ( $center['total_runtime'] ?? '' );
$has_audio     = ! empty( $center['has_audio'] ) && ! empty( $groups );
?>
<div class="cta-ar" data-cta-audio-review>
	<p class="cta-ep-home-section__lede">
		<?php esc_html_e( 'Listen to recorded reviews without leaving the course. Use the player controls to pause, seek, adjust volume, or change playback speed.', 'cta-lms' ); ?>
	</p>

	<?php if ( $has_audio ) : ?>
		<div class="cta-ar__stats">
			<div class="cta-ar__stat">
				<strong><?php echo esc_html( (string) $track_count ); ?></strong>
				<span><?php esc_html_e( 'Audio reviews', 'cta-lms' ); ?></span>
			</div>
			<?php if ( '' !== $total_runtime ) : ?>
				<div class="cta-ar__stat">
					<strong><?php echo esc_html( $total_runtime ); ?></strong>
					<span><?php esc_html_e( 'Total listening time', 'cta-lms' ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php foreach ( $groups as $group ) : ?>
			<?php
			$key    = sanitize_key( (string) ( $group['key'] ?? 'reviews' ) );
			$tracks = (array) ( $group['tracks'] ?? array() );
			if ( empty( $tracks ) ) {
				continue;
			}
			?>
			<section class="cta-ar__section" id="cta-ar-<?php echo esc_attr( $key ); ?>" aria-labelledby="cta-ar-<?php echo esc_attr( $key ); ?>-title">
				<header class="cta-ar__section-head">
					<div>
						<h3 id="cta-ar-<?php echo esc_attr( $key ); ?>-title"><?php echo esc_html( (string) ( $group['label'] ?? '' ) ); ?></h3>
						<?php if ( ! empty( $group['description'] ) ) : ?>
							<p><?php echo esc_html( (string) $group['description'] ); ?></p>
						<?php endif; ?>
					</div>
					<span class="cta-ar__count">
						<?php
						printf(
							/* translators: %d: recording count */
							esc_html( _n( '%d recording', '%d recordings', count( $tracks ), 'cta-lms' ) ),
							count( $tracks )
						);
						?>
					</span>
				</header>

				<div class="cta-ar__list">
					<?php foreach ( $tracks as $track ) : ?>
						<?php
						$track_number = (int) ( $track['track_number'] ?? 0 );
						$filename     = (string) ( $track['filename'] ?? '' );
						$extension    = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
						$mime         = 'audio/mpeg';
						if ( 'm4a' === $extension ) {
							$mime = 'audio/mp4';
						} elseif ( 'wav' === $extension ) {
							$mime = 'audio/wav';
						} elseif ( 'ogg' === $extension ) {
							$mime = 'audio/ogg';
						}
						?>
						<article class="cta-ar__track" data-cta-audio-track>
							<div class="cta-ar__track-head">
								<div class="cta-ar__track-number" aria-hidden="true">
									<?php echo $track_number > 0 ? esc_html( (string) $track_number ) : '&#9835;'; ?>
								</div>
								<div class="cta-ar__track-copy">
									<h4><?php echo esc_html( (string) ( $track['title'] ?? '' ) ); ?></h4>
									<p>
										<?php if ( ! empty( $track['module_title'] ) ) : ?>
											<span><?php echo esc_html( (string) $track['module_title'] ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $track['runtime'] ) ) : ?>
											<span class="cta-ar__duration" data-cta-audio-duration data-known-duration="1"><?php echo esc_html( (string) $track['runtime'] ); ?></span>
										<?php else : ?>
											<span class="cta-ar__duration" data-cta-audio-duration><?php esc_html_e( 'Duration loading…', 'cta-lms' ); ?></span>
										<?php endif; ?>
									</p>
								</div>
							</div>

							<audio class="cta-ar__player" controls preload="metadata" data-cta-audio-player>
								<source src="<?php echo esc_url( (string) ( $track['stream_url'] ?? '' ) ); ?>" type="<?php echo esc_attr( $mime ); ?>" />
								<?php esc_html_e( 'Your browser does not support the audio player.', 'cta-lms' ); ?>
							</audio>

							<div class="cta-ar__track-actions">
								<label class="cta-ar__speed">
									<span><?php esc_html_e( 'Playback speed', 'cta-lms' ); ?></span>
									<select data-cta-audio-speed aria-label="<?php esc_attr_e( 'Playback speed', 'cta-lms' ); ?>">
										<option value="0.75">0.75×</option>
										<option value="1" selected>1×</option>
										<option value="1.25">1.25×</option>
										<option value="1.5">1.5×</option>
										<option value="2">2×</option>
									</select>
								</label>
								<?php if ( ! empty( $track['download_url'] ) ) : ?>
									<a class="btn btn-outline btn--sm cta-ar__download" href="<?php echo esc_url( (string) $track['download_url'] ); ?>">
										<span aria-hidden="true">↓</span>
										<?php esc_html_e( 'Download audio', 'cta-lms' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="cta-ar__empty" role="status">
			<p><?php esc_html_e( 'No audio review recordings are published for this program.', 'cta-lms' ); ?></p>
		</div>
	<?php endif; ?>
</div>
