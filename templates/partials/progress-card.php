<?php
/**
 * In-progress course progress card.
 *
 * @package CTA_LMS
 *
 * @var object $item Enrollment bundle with course, modules, progress data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enrollment = $item->enrollment;
$course     = $item->course;
$progress   = (int) $enrollment->progress;
?>
<article class="card dashboard-course-card cta-progress-card" data-course-id="<?php echo esc_attr( $course->id ); ?>">
	<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
		<div class="dashboard-course-card__thumb">
			<img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="">
		</div>
	<?php else : ?>
		<div class="dashboard-course-card__thumb dashboard-course-card__thumb--placeholder" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="dashboard-course-card__header">
		<h3 class="dashboard-course-card__title">
			<?php if ( $item->player_url ) : ?>
				<a href="<?php echo esc_url( $item->player_url ); ?>"><?php echo esc_html( $course->title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $course->title ); ?>
			<?php endif; ?>
		</h3>
	</div>
	<div class="progress">
		<div class="progress__label">
			<span><?php echo esc_html__( 'Course progress', 'cta-lms' ); ?></span>
			<span class="progress__percent cta-progress-percent"><?php echo esc_html( (string) $progress ); ?>%</span>
		</div>
		<div class="progress__track">
			<div class="progress__bar cta-progress-bar" style="width: <?php echo esc_attr( (string) $progress ); ?>%;"></div>
		</div>
	</div>
	<p class="dashboard-course-card__meta cta-progress-meta">
		<?php
		printf(
			/* translators: 1: completed module count, 2: total module count */
			esc_html__( '%1$d of %2$d modules complete', 'cta-lms' ),
			(int) $item->completed_count,
			(int) $item->total_modules
		);
		?>
	</p>
	<div class="dashboard-course-card__actions">
		<?php if ( $progress >= 100 ) : ?>
			<span class="badge badge--primary"><?php echo esc_html__( 'Quiz Ready', 'cta-lms' ); ?></span>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<?php if ( $item->player_url ) : ?>
			<a href="<?php echo esc_url( $item->player_url ); ?>" class="btn btn-primary"><?php echo esc_html__( 'Continue', 'cta-lms' ); ?></a>
		<?php endif; ?>
	</div>
</article>
