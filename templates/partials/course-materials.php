<?php
/**
 * Course materials / downloads list for enrolled learners.
 *
 * @package CTA_LMS
 *
 * @var array  $resources   Resource rows.
 * @var array  $modules     Optional module rows for grouping labels.
 * @var string $heading     Optional section heading.
 * @var bool   $is_enrolled Whether the current user is enrolled.
 * @var bool   $show_locked When true, show a locked message for unenrolled users if resources exist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$resources     = isset( $resources ) ? (array) $resources : array();
$modules       = isset( $modules ) ? (array) $modules : array();
$heading       = isset( $heading ) ? $heading : __( 'Course Materials', 'cta-lms' );
$is_enrolled   = ! empty( $is_enrolled );
$show_locked   = ! empty( $show_locked );
$has_resources = ! empty( $resources );

if ( ! $has_resources && ! $show_locked ) {
	return;
}

$grouped = class_exists( 'CTA_Course_Materials' )
	? CTA_Course_Materials::group_for_display( $resources, $modules )
	: array(
		'course'  => $resources,
		'modules' => array(),
	);

// Resolve AMFTRB (or shared) authoritative transcript serve URL for enrolled learners.
$cta_transcript_url   = '';
$cta_transcript_label = '';
if ( $is_enrolled && class_exists( 'CTA_Course_Materials' ) ) {
	$user_id_for_tx = get_current_user_id();
	foreach ( $resources as $tx_res ) {
		$tx_title = (string) ( $tx_res->title ?? '' );
		if ( false === stripos( $tx_title, 'Authoritative Audio Transcript' )
			&& false === stripos( (string) ( $tx_res->file_path ?? '' ), 'Authoritative_Audio_Recording_Script_and_Transcript' ) ) {
			continue;
		}
		if ( ! CTA_Course_Materials::user_can_access( $user_id_for_tx, $tx_res ) ) {
			continue;
		}
		$cta_transcript_url   = CTA_Course_Materials::get_serve_url( (int) $tx_res->id );
		$cta_transcript_label = $tx_title ? $tx_title : __( 'Authoritative Audio Transcript v1.1', 'cta-lms' );
		break;
	}
}

/**
 * Render one resource download row.
 *
 * @param object $resource Resource.
 */
$cta_render_material_item = static function ( $resource ) use ( $is_enrolled, $cta_transcript_url, $cta_transcript_label ) {
	$user_id    = get_current_user_id();
	$can_access = $is_enrolled && class_exists( 'CTA_Course_Materials' )
		? CTA_Course_Materials::user_can_access( $user_id, $resource )
		: false;
	$lock_msg   = ( $is_enrolled && ! $can_access && class_exists( 'CTA_Course_Materials' ) )
		? CTA_Course_Materials::get_unlock_lock_message( $user_id, $resource )
		: '';
	$serve_url  = ( $can_access && class_exists( 'CTA_Course_Materials' ) )
		? CTA_Course_Materials::get_serve_url( (int) $resource->id )
		: '';
	$type_label = ! empty( $resource->file_type ) ? strtoupper( (string) $resource->file_type ) : '';
	$is_audio   = ( 'mp3' === strtolower( (string) ( $resource->file_type ?? '' ) ) )
		|| ( false !== stripos( (string) ( $resource->title ?? '' ), 'Audio Review' ) );

	$audio_meta = null;
	if ( $is_audio && class_exists( 'CTA_Lmft_Amftrb_Sync' ) && method_exists( 'CTA_Lmft_Amftrb_Sync', 'resolve_audio_meta' ) ) {
		$audio_meta = CTA_Lmft_Amftrb_Sync::resolve_audio_meta( $resource );
	}
	$audio_runtime = ( $audio_meta && ! empty( $audio_meta['runtime'] ) ) ? (string) $audio_meta['runtime'] : '';
	$audio_track   = ( $audio_meta && ! empty( $audio_meta['track'] ) ) ? (int) $audio_meta['track'] : 0;

	$is_remediation = $can_access
		&& class_exists( 'CTA_Course_Materials' )
		&& CTA_Course_Materials::is_form_a_remediation_resource( $resource );
	$remediation_done = $is_remediation
		&& CTA_Course_Materials::user_has_completed_form_a_remediation( $user_id, (int) $resource->course_id );

	$preserved_type = '';
	$preserved_done = false;
	$show_preserve  = false;
	if ( $can_access && class_exists( 'CTA_Course_Materials' ) && class_exists( 'CTA_Exam_Access' ) ) {
		$course_for_preserve = ! empty( $resource->course_id ) && class_exists( 'CTA_Database' )
			? CTA_Database::get_course( (int) $resource->course_id )
			: null;
		if ( CTA_Exam_Access::uses_assessment_gates( $course_for_preserve ) ) {
			$preserved_type = CTA_Course_Materials::infer_preserved_attempt_type( $resource );
			if ( '' !== $preserved_type ) {
				$preserved_done = CTA_Course_Materials::user_has_preserved_attempt( $user_id, (int) $resource->course_id, $preserved_type )
					|| CTA_Course_Materials::user_has_completed_quiz_type( $user_id, (int) $resource->course_id, $preserved_type );
				$show_preserve = ! $preserved_done;
			}
		}
	}
	?>
	<li class="cta-materials-list__item course-module-list__item<?php echo $is_audio ? ' cta-materials-list__item--audio' : ''; ?>">
		<div class="course-module-list__row">
			<span class="course-module-list__number" aria-hidden="true">
				<?php if ( $is_audio ) : ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
				<?php else : ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
				<?php endif; ?>
			</span>
			<div class="course-module-list__info">
				<strong class="course-module-list__title"><?php echo esc_html( $resource->title ); ?></strong>
				<?php if ( $lock_msg ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html( $lock_msg ); ?></p>
				<?php elseif ( $show_preserve ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html__( 'Complete this assessment, then record your attempt to unlock the matching answer key and rationales.', 'cta-lms' ); ?></p>
				<?php elseif ( '' !== $preserved_type && $preserved_done ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html__( 'Attempt recorded — matching rationales are unlocked.', 'cta-lms' ); ?></p>
				<?php elseif ( $is_remediation && ! $remediation_done ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html__( 'Optional remediation after Form A. Download, complete, then mark complete.', 'cta-lms' ); ?></p>
				<?php elseif ( $is_remediation && $remediation_done ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html__( 'Remediation marked complete.', 'cta-lms' ); ?></p>
				<?php elseif ( $is_audio && $audio_runtime ) : ?>
					<p class="course-module-list__desc cta-audio-runtime">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: exact audio runtime from placement map */
								__( 'Runtime: %s', 'cta-lms' ),
								$audio_runtime
							)
						);
						?>
					</p>
				<?php elseif ( $is_audio && $type_label ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html__( 'Audio review — play below or download for offline use.', 'cta-lms' ); ?></p>
				<?php elseif ( $type_label ) : ?>
					<p class="course-module-list__desc"><?php echo esc_html( $type_label ); ?></p>
				<?php endif; ?>
				<?php if ( $serve_url && $is_audio && $can_access ) : ?>
					<audio class="cta-audio-player" controls preload="metadata" src="<?php echo esc_url( $serve_url ); ?>">
						<?php echo esc_html__( 'Your browser does not support the audio player.', 'cta-lms' ); ?>
					</audio>
					<?php if ( $cta_transcript_url ) : ?>
						<p class="cta-audio-transcript">
							<a
								href="<?php echo esc_url( $cta_transcript_url ); ?>"
								class="cta-audio-transcript__link"
								target="_blank"
								rel="noopener noreferrer"
							>
								<?php
								echo esc_html(
									$audio_track
										? sprintf(
											/* translators: 1: track number, 2: transcript document title */
											__( 'Track %1$d transcript — %2$s', 'cta-lms' ),
											$audio_track,
											$cta_transcript_label
										)
										: sprintf(
											/* translators: %s: transcript document title */
											__( 'Audio transcript — %s', 'cta-lms' ),
											$cta_transcript_label
										)
								);
								?>
							</a>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php if ( $serve_url ) : ?>
				<a
					href="<?php echo esc_url( $serve_url ); ?>"
					class="btn btn-outline btn--sm cta-materials-list__download"
					<?php echo $is_audio ? 'download' : ''; ?>
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php echo esc_html( $is_audio ? __( 'Download MP3', 'cta-lms' ) : __( 'Open / Download', 'cta-lms' ) ); ?>
				</a>
			<?php elseif ( $lock_msg ) : ?>
				<span class="course-module-list__lock" title="<?php echo esc_attr( $lock_msg ); ?>" aria-hidden="true">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
				</span>
			<?php endif; ?>
			<?php if ( $show_preserve ) : ?>
				<button
					type="button"
					class="btn btn-primary btn--sm cta-mark-preserved-attempt"
					data-course-id="<?php echo esc_attr( (string) (int) $resource->course_id ); ?>"
					data-resource-id="<?php echo esc_attr( (string) (int) $resource->id ); ?>"
					data-unlock-type="<?php echo esc_attr( $preserved_type ); ?>"
				>
					<?php echo esc_html__( 'Record that I completed this assessment', 'cta-lms' ); ?>
				</button>
			<?php elseif ( '' !== $preserved_type && $preserved_done ) : ?>
				<span class="badge badge--success"><?php echo esc_html__( 'Attempt recorded', 'cta-lms' ); ?></span>
			<?php endif; ?>
			<?php if ( $is_remediation && ! $remediation_done ) : ?>
				<button
					type="button"
					class="btn btn-primary btn--sm cta-mark-form-a-remediation"
					data-course-id="<?php echo esc_attr( (string) (int) $resource->course_id ); ?>"
				>
					<?php echo esc_html__( 'Mark remediation complete', 'cta-lms' ); ?>
				</button>
			<?php elseif ( $is_remediation && $remediation_done ) : ?>
				<span class="badge badge--success"><?php echo esc_html__( 'Complete', 'cta-lms' ); ?></span>
			<?php endif; ?>
		</div>
	</li>
	<?php
};
?>
<section class="cta-materials-section course-player__resources-section" aria-labelledby="cta-materials-title">
	<h2 class="dashboard-section__title" id="cta-materials-title"><?php echo esc_html( $heading ); ?></h2>

	<?php if ( ! $is_enrolled ) : ?>
		<div class="cta-quiz-locked-message">
			<p><?php echo esc_html__( 'Enroll in this course to unlock downloadable materials.', 'cta-lms' ); ?></p>
		</div>
	<?php elseif ( ! $has_resources ) : ?>
		<p class="cta-empty-state cta-empty-state--inline"><?php echo esc_html__( 'No materials have been added to this course yet.', 'cta-lms' ); ?></p>
	<?php else : ?>
		<?php if ( ! empty( $grouped['course'] ) ) : ?>
			<ul class="cta-materials-list course-module-list">
				<?php foreach ( $grouped['course'] as $resource ) : ?>
					<?php $cta_render_material_item( $resource ); ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php foreach ( $grouped['modules'] as $module_group ) : ?>
			<h3 class="cta-materials-list__module-title" style="margin:1rem 0 0.5rem;font-size:1rem;">
				<?php echo esc_html( $module_group['title'] ); ?>
			</h3>
			<ul class="cta-materials-list course-module-list">
				<?php foreach ( $module_group['resources'] as $resource ) : ?>
					<?php $cta_render_material_item( $resource ); ?>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
