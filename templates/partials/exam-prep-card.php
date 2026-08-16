<?php
/**
 * Exam preparation program card for the student dashboard.
 *
 * @package CTA_LMS
 *
 * @var object $item Enrollment bundle with course, access, resources, progress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enrollment   = $item->enrollment;
$course       = $item->course;
$progress     = (int) $enrollment->progress;
$has_access   = ! empty( $item->has_active_access );
$expires_label = '';
$syllabus_meta = array();
if ( ! empty( $course->syllabus_meta ) ) {
	$decoded = json_decode( (string) $course->syllabus_meta, true );
	$syllabus_meta = is_array( $decoded ) ? $decoded : array();
}
$dashboard_card = ! empty( $syllabus_meta['dashboard_card'] ) && is_array( $syllabus_meta['dashboard_card'] )
	? $syllabus_meta['dashboard_card']
	: array();
$unit_total = ! empty( $syllabus_meta['unit_total'] ) ? absint( $syllabus_meta['unit_total'] ) : 0;

if ( ! empty( $item->expires_at ) ) {
	$expires_label = cta_lms_format_local_date( $item->expires_at, 'F j, Y' );
}

$workbooks = array();
$tests     = array();
$display_title = ! empty( $dashboard_card['title'] )
	? (string) $dashboard_card['title']
	: ( function_exists( 'cta_lms_get_course_display_title' )
		? cta_lms_get_course_display_title( $course )
		: (string) $course->title );

foreach ( (array) ( $item->resources ?? array() ) as $resource ) {
	if ( ! empty( $resource->is_practice_test ) ) {
		$tests[] = $resource;
	} else {
		$workbooks[] = $resource;
	}
}
?>
<article class="card dashboard-course-card cta-progress-card cta-exam-prep-card" data-course-id="<?php echo esc_attr( $course->id ); ?>">
	<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
		<div class="dashboard-course-card__thumb">
			<img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="" class="cta-exam-prep-artwork">
		</div>
	<?php else : ?>
		<div class="dashboard-course-card__thumb dashboard-course-card__thumb--placeholder" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="dashboard-course-card__header">
		<h3 class="dashboard-course-card__title">
			<?php if ( $has_access && $item->player_url ) : ?>
				<a href="<?php echo esc_url( $item->player_url ); ?>"><?php echo esc_html( $display_title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $display_title ); ?>
			<?php endif; ?>
		</h3>
		<p class="dashboard-course-card__meta">
			<?php if ( ! empty( $dashboard_card['subtitle'] ) ) : ?>
				<?php echo esc_html( (string) $dashboard_card['subtitle'] ); ?>
				<br>
			<?php endif; ?>
			<?php if ( $expires_label ) : ?>
				<?php
				if ( ! empty( $dashboard_card['access_template'] ) ) {
					echo esc_html( str_replace( '[DATE]', $expires_label, (string) $dashboard_card['access_template'] ) );
				} else {
					printf(
						/* translators: %s: expiration date */
						esc_html__( 'Access expires: %s', 'cta-lms' ),
						esc_html( $expires_label )
					);
				}
				?>
			<?php endif; ?>
			<?php if ( ! $has_access ) : ?>
				<span class="badge badge--warning"><?php echo esc_html__( 'Expired', 'cta-lms' ); ?></span>
			<?php endif; ?>
		</p>
	</div>

	<div class="progress">
		<div class="progress__label">
			<span><?php echo esc_html__( 'Program progress', 'cta-lms' ); ?></span>
			<span class="progress__percent"><?php echo esc_html( (string) $progress ); ?>%</span>
		</div>
		<div class="progress__track">
			<div class="progress__bar" style="width: <?php echo esc_attr( (string) $progress ); ?>%;"></div>
		</div>
	</div>

	<p class="dashboard-course-card__meta">
		<?php
		$total_units = $unit_total > 0 ? $unit_total : (int) $item->total_modules;
		if ( ! empty( $dashboard_card['progress_template'] ) ) {
			$progress_line = str_replace(
				array( '[X]', '16' ),
				array( (string) (int) $item->completed_count, (string) $total_units ),
				(string) $dashboard_card['progress_template']
			);
			// Prefer exact approved pattern with dynamic total when unit_total is set.
			if ( $unit_total > 0 ) {
				$progress_line = sprintf( '%d of %d units complete', (int) $item->completed_count, $unit_total );
			}
			echo esc_html( $progress_line );
		} else {
			printf(
				/* translators: 1: completed module count, 2: total module count */
				esc_html__( '%1$d of %2$d modules complete', 'cta-lms' ),
				(int) $item->completed_count,
				(int) $item->total_modules
			);
		}
		?>
	</p>

	<?php if ( $has_access && ( ! empty( $workbooks ) || ! empty( $tests ) ) ) : ?>
		<div class="cta-exam-resources" style="margin:0.75rem 0;">
			<?php if ( ! empty( $workbooks ) ) : ?>
				<p><strong><?php echo esc_html__( 'Workbooks & materials', 'cta-lms' ); ?></strong></p>
				<ul>
					<?php foreach ( $workbooks as $resource ) : ?>
						<?php
						$can_dl   = class_exists( 'CTA_Course_Materials' ) && CTA_Course_Materials::user_can_access( get_current_user_id(), $resource );
						$lock_msg = ( ! $can_dl && class_exists( 'CTA_Course_Materials' ) )
							? CTA_Course_Materials::get_unlock_lock_message( get_current_user_id(), $resource )
							: '';
						?>
						<li>
							<?php if ( $can_dl ) : ?>
								<a class="button-link" href="<?php echo esc_url( CTA_Course_Materials::get_serve_url( (int) $resource->id ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $resource->title ); ?>
									<?php if ( ! empty( $resource->file_type ) ) : ?>
										(<?php echo esc_html( strtoupper( (string) $resource->file_type ) ); ?>)
									<?php endif; ?>
								</a>
							<?php else : ?>
								<span><?php echo esc_html( $resource->title ); ?></span>
								<?php if ( $lock_msg ) : ?>
									<em class="text-small"> — <?php echo esc_html( $lock_msg ); ?></em>
								<?php endif; ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $tests ) ) : ?>
				<p><strong><?php echo esc_html__( 'Practice tests', 'cta-lms' ); ?></strong></p>
				<ul>
					<?php foreach ( $tests as $resource ) : ?>
						<?php
						$can_dl   = class_exists( 'CTA_Course_Materials' ) && CTA_Course_Materials::user_can_access( get_current_user_id(), $resource );
						$lock_msg = ( ! $can_dl && class_exists( 'CTA_Course_Materials' ) )
							? CTA_Course_Materials::get_unlock_lock_message( get_current_user_id(), $resource )
							: '';
						?>
						<li>
							<?php if ( $can_dl ) : ?>
								<a class="button-link" href="<?php echo esc_url( CTA_Course_Materials::get_serve_url( (int) $resource->id ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $resource->title ); ?>
								</a>
							<?php else : ?>
								<span><?php echo esc_html( $resource->title ); ?></span>
								<?php if ( $lock_msg ) : ?>
									<em class="text-small"> — <?php echo esc_html( $lock_msg ); ?></em>
								<?php endif; ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="dashboard-course-card__actions">
		<?php if ( $has_access && ! empty( $item->quiz_url ) ) : ?>
			<a href="<?php echo esc_url( $item->quiz_url ); ?>" class="btn btn-secondary"><?php echo esc_html__( 'Practice / Mock Exam', 'cta-lms' ); ?></a>
		<?php endif; ?>
		<?php if ( $has_access && $item->player_url ) : ?>
			<a href="<?php echo esc_url( $item->player_url ); ?>" class="btn btn-primary"><?php
				echo esc_html(
					! empty( $dashboard_card['button'] )
						? (string) $dashboard_card['button']
						: __( 'Continue Studying', 'cta-lms' )
				);
			?></a>
		<?php elseif ( ! $has_access ) : ?>
			<span class="text-small"><?php
				echo esc_html(
					! empty( $syllabus_meta['lms_trigger_messages']['expired_access'] )
						? (string) $syllabus_meta['lms_trigger_messages']['expired_access']
						: __( 'Progress preserved — renew access to continue.', 'cta-lms' )
				);
			?></span>
		<?php endif; ?>
	</div>
</article>
