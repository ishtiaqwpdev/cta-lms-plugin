<?php
/**
 * Admin course edit view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = (bool) $course;
$notice  = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );

$current_product_type = $course->product_type ?? ( $default_product_type ?? 'ce' );
if ( ! in_array( $current_product_type, array( 'ce', 'exam_prep' ), true ) ) {
	$current_product_type = 'ce';
}
$is_exam_prep = ( 'exam_prep' === $current_product_type );
$resources    = isset( $resources ) ? $resources : array();
$exam_learners = isset( $exam_learners ) ? $exam_learners : array();
$syllabus_meta = isset( $syllabus_meta ) && is_array( $syllabus_meta ) ? $syllabus_meta : array();
$educational_goals = isset( $educational_goals ) && is_array( $educational_goals ) ? $educational_goals : array( '' );
$completion_requirements = isset( $completion_requirements ) && is_array( $completion_requirements ) ? $completion_requirements : array( '' );
$syllabus_references = isset( $syllabus_references ) ? (string) $syllabus_references : '';
$eval_questions      = isset( $eval_questions ) ? $eval_questions : array();
$quizzes             = isset( $quizzes ) ? $quizzes : array();
$eval_types          = class_exists( 'CTA_Evaluation_Questions' ) ? CTA_Evaluation_Questions::get_types() : array();

$course_video_type  = 'vimeo';
$course_video_value = '';
$course_video_url   = '';

if ( $course ) {
	$course_video_url = (string) ( $course->video_url ?? '' );

	if ( '' !== $course_video_url ) {
		if ( false !== strpos( $course_video_url, 'youtube.com' ) || false !== strpos( $course_video_url, 'youtu.be' ) ) {
			$course_video_type  = 'youtube';
			$course_video_value = $course_video_url;
		} elseif ( false !== strpos( $course_video_url, 'vimeo.com' ) ) {
			$course_video_type = 'vimeo';
			if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $course_video_url, $matches ) ) {
				$course_video_value = $matches[1];
			}
		} elseif ( false !== strpos( $course_video_url, '/wp-content/' ) ) {
			$course_video_type  = 'wordpress';
			$course_video_value = $course_video_url;
		} else {
			$course_video_type  = 'url';
			$course_video_value = $course_video_url;
		}
	} elseif ( ! empty( $course->vimeo_id ) ) {
		$course_video_type  = 'vimeo';
		$course_video_value = preg_replace( '/\D/', '', (string) $course->vimeo_id );
	}
}
?>
<div class="wrap cta-admin-wrap">
	<h1>
		<?php
		if ( $is_edit ) {
			echo $is_exam_prep ? esc_html__( 'Edit Exam Preparation Program', 'cta-lms' ) : esc_html__( 'Edit Course', 'cta-lms' );
		} else {
			echo $is_exam_prep ? esc_html__( 'Add Exam Preparation Program', 'cta-lms' ) : esc_html__( 'Add New Course', 'cta-lms' );
		}
		?>
	</h1>

	<?php if ( 'course_saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved successfully.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'course_save_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not be saved. Check that only one CTA LMS plugin is installed, then deactivate and reactivate the plugin.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'resource_saved' === $notice || 'resource_deleted' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Downloadable resource updated.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'resource_invalid_type' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Only PDF, DOC, and DOCX files are allowed for course materials.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'resource_too_large' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'File exceeds the 20MB size limit. Please upload a smaller PDF, DOC, or DOCX file.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'resource_save_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not save the course material. Please try again with a valid PDF, DOC, or DOCX under 20MB.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'exam_extended' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Exam access extended.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'exam_extend_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not extend exam access.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
		<?php wp_nonce_field( 'cta_save_course' ); ?>
		<input type="hidden" name="action" value="cta_save_course">
		<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>">

		<div class="cta-admin-panel">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Product Type', 'cta-lms' ); ?></th>
					<td>
						<label><input type="radio" name="product_type" value="ce" <?php checked( $current_product_type, 'ce' ); ?>> <?php esc_html_e( 'CE Course', 'cta-lms' ); ?></label>
						<label style="margin-left:12px;"><input type="radio" name="product_type" value="exam_prep" <?php checked( $current_product_type, 'exam_prep' ); ?>> <?php esc_html_e( 'Exam Preparation Program', 'cta-lms' ); ?></label>
						<p class="description"><?php esc_html_e( 'Exam Preparation programs do not award CE hours or certificates.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-title"><?php echo $is_exam_prep ? esc_html__( 'Program Name', 'cta-lms' ) : esc_html__( 'Course Title', 'cta-lms' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="cta-course-title" name="title" value="<?php echo esc_attr( $course->title ?? '' ); ?>" required>
						<?php
						$admin_public_title = '';
						if ( ! empty( $course ) && function_exists( 'cta_lms_get_course_display_title' ) ) {
							$admin_public_title = cta_lms_get_course_display_title( $course );
						}
						if ( $is_exam_prep && $admin_public_title && $admin_public_title !== (string) ( $course->title ?? '' ) ) :
							?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: shorter public display name */
									esc_html__( 'Public display name: %s', 'cta-lms' ),
									esc_html( $admin_public_title )
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-slug"><?php esc_html_e( 'Slug', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta-course-slug" name="slug" value="<?php echo esc_attr( $course->slug ?? '' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta-course-category"><?php esc_html_e( 'Category', 'cta-lms' ); ?></label></th>
					<td>
						<select id="cta-course-category" name="category">
							<option value=""><?php esc_html_e( 'Select category', 'cta-lms' ); ?></option>
							<?php foreach ( $categories as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $course->category ?? ( $is_exam_prep ? 'Exam Preparation' : '' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr class="cta-field-ce-hours" <?php echo $is_exam_prep ? 'style="display:none;"' : ''; ?>>
					<th><label for="cta-course-ce-hours"><?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></label></th>
					<td><input type="number" step="0.5" min="0" id="cta-course-ce-hours" name="ce_hours" value="<?php echo esc_attr( $is_exam_prep ? '0' : ( $course->ce_hours ?? '0' ) ); ?>" <?php disabled( $is_exam_prep ); ?>></td>
				</tr>
				<tr class="cta-field-access-months" <?php echo $is_exam_prep ? '' : 'style="display:none;"'; ?>>
					<th><label for="cta-access-period"><?php esc_html_e( 'Access Period (months)', 'cta-lms' ); ?></label></th>
					<td>
						<input type="number" min="1" max="36" id="cta-access-period" name="access_period_months" value="<?php echo esc_attr( (string) (int) ( $course->access_period_months ?? 6 ) ); ?>">
						<p class="description"><?php esc_html_e( 'Default is 6 months from purchase. Admins can manually extend access per learner.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-price"><?php esc_html_e( 'Price', 'cta-lms' ); ?></label></th>
					<td><input type="number" step="0.01" min="0" id="cta-course-price" name="price" value="<?php echo esc_attr( $course->price ?? '0' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="cta-course-description"><?php esc_html_e( 'Description', 'cta-lms' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$course->description ?? '',
							'cta-course-description',
							array(
								'textarea_name' => 'description',
								'textarea_rows' => 10,
								'media_buttons' => false,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Learning Objectives', 'cta-lms' ); ?></th>
					<td>
						<div id="cta-objectives-repeater" class="cta-objectives-repeater">
							<?php foreach ( $objectives as $objective ) : ?>
								<div class="cta-objective-row">
									<input type="text" class="regular-text" name="learning_objectives[]" value="<?php echo esc_attr( $objective ); ?>">
									<button type="button" class="button cta-remove-objective"><?php esc_html_e( 'Remove', 'cta-lms' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="cta-add-objective"><?php esc_html_e( 'Add Objective', 'cta-lms' ); ?></button>
					</td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-course-code"><?php esc_html_e( 'Course ID / Code', 'cta-lms' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="cta-course-code" name="course_code" value="<?php echo esc_attr( $syllabus_meta['course_code'] ?? '' ); ?>" placeholder="CTA-CE-001">
						<p class="description"><?php esc_html_e( 'Official course identifier (e.g. CTA-CE-001). Shown in Course Information.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-course-level"><?php esc_html_e( 'Course Level', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta-course-level" name="course_level" value="<?php echo esc_attr( $syllabus_meta['course_level'] ?? '' ); ?>"></td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-target-audience"><?php esc_html_e( 'Target Audience', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="large-text" id="cta-target-audience" name="target_audience" value="<?php echo esc_attr( $syllabus_meta['target_audience'] ?? '' ); ?>"></td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-instructional-method"><?php esc_html_e( 'Instructional Method', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="large-text" id="cta-instructional-method" name="instructional_method" value="<?php echo esc_attr( $syllabus_meta['instructional_method'] ?? '' ); ?>"></td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-presenter"><?php esc_html_e( 'Presenter / Author', 'cta-lms' ); ?></label></th>
					<td><input type="text" class="regular-text" id="cta-presenter" name="presenter" value="<?php echo esc_attr( $syllabus_meta['presenter'] ?? '' ); ?>"></td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><?php esc_html_e( 'Educational Goals', 'cta-lms' ); ?></th>
					<td>
						<div id="cta-goals-repeater" class="cta-objectives-repeater">
							<?php foreach ( $educational_goals as $goal ) : ?>
								<div class="cta-objective-row">
									<input type="text" class="large-text" name="educational_goals[]" value="<?php echo esc_attr( $goal ); ?>">
									<button type="button" class="button cta-remove-objective"><?php esc_html_e( 'Remove', 'cta-lms' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="cta-add-goal"><?php esc_html_e( 'Add Goal', 'cta-lms' ); ?></button>
					</td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><?php esc_html_e( 'Completion Requirements', 'cta-lms' ); ?></th>
					<td>
						<div id="cta-completion-repeater" class="cta-objectives-repeater">
							<?php foreach ( $completion_requirements as $req ) : ?>
								<div class="cta-objective-row">
									<input type="text" class="large-text" name="completion_requirements[]" value="<?php echo esc_attr( $req ); ?>">
									<button type="button" class="button cta-remove-objective"><?php esc_html_e( 'Remove', 'cta-lms' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="cta-add-completion"><?php esc_html_e( 'Add Requirement', 'cta-lms' ); ?></button>
					</td>
				</tr>
				<tr class="cta-field-syllabus">
					<th><label for="cta-syllabus-references"><?php esc_html_e( 'References', 'cta-lms' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="8" id="cta-syllabus-references" name="syllabus_references"><?php echo esc_textarea( $syllabus_references ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One reference per line.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr class="cta-field-attestation" <?php echo $is_exam_prep ? 'style="display:none;"' : ''; ?>>
					<th><?php esc_html_e( 'Attestation', 'cta-lms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="attestation_required" value="1" <?php checked( ! $is_exam_prep && ( ! empty( $syllabus_meta['attestation_required'] ) || ! isset( $syllabus_meta['attestation_required'] ) ) ); ?> <?php disabled( $is_exam_prep ); ?>>
							<?php esc_html_e( 'Require course-completion attestation (asynchronous distance learning)', 'cta-lms' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-thumbnail"><?php esc_html_e( 'Thumbnail URL', 'cta-lms' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="cta-course-thumbnail" name="thumbnail_url" value="<?php echo esc_url( $course->thumbnail_url ?? '' ); ?>">
						<?php if ( ! empty( $course->thumbnail_url ) ) : ?>
							<p><img src="<?php echo esc_url( $course->thumbnail_url ); ?>" alt="" class="cta-thumb-preview"></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="cta-course-video-type"><?php esc_html_e( 'Preview Video', 'cta-lms' ); ?></label></th>
					<td>
						<p>
							<label for="cta-course-video-type"><strong><?php esc_html_e( 'Video Source', 'cta-lms' ); ?></strong></label><br>
							<select id="cta-course-video-type" name="course_video_type">
								<option value="vimeo" <?php selected( $course_video_type, 'vimeo' ); ?>><?php esc_html_e( 'Vimeo', 'cta-lms' ); ?></option>
								<option value="youtube" <?php selected( $course_video_type, 'youtube' ); ?>><?php esc_html_e( 'YouTube URL', 'cta-lms' ); ?></option>
								<option value="wordpress" <?php selected( $course_video_type, 'wordpress' ); ?>><?php esc_html_e( 'WordPress Media Library', 'cta-lms' ); ?></option>
								<option value="url" <?php selected( $course_video_type, 'url' ); ?>><?php esc_html_e( 'Direct Video URL (MP4)', 'cta-lms' ); ?></option>
							</select>
						</p>
						<p class="cta-course-video-row">
							<input type="text" class="regular-text" id="cta-course-video-value" name="course_video_value" value="<?php echo esc_attr( $course_video_value ); ?>" placeholder="<?php esc_attr_e( 'Vimeo ID or video URL', 'cta-lms' ); ?>">
							<input type="hidden" id="cta-course-video-url" name="course_video_url" value="<?php echo esc_url( $course_video_url ); ?>">
							<button type="button" class="button" id="cta-course-video-select" style="display:none;"><?php esc_html_e( 'Select Video', 'cta-lms' ); ?></button>
						</p>
						<p class="description cta-course-video-help" data-help="vimeo"><?php esc_html_e( 'Enter the Vimeo video ID (numbers only). Used as fallback when a module has no video.', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="youtube" style="display:none;"><?php esc_html_e( 'Example: https://www.youtube.com/watch?v=VIDEO_ID', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="wordpress" style="display:none;"><?php esc_html_e( 'Click Select Video to choose an uploaded MP4 from your Media Library.', 'cta-lms' ); ?></p>
						<p class="description cta-course-video-help" data-help="url" style="display:none;"><?php esc_html_e( 'Paste a direct link to an MP4 or other supported video file.', 'cta-lms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
					<td>
						<label><input type="radio" name="status" value="published" <?php checked( $course->status ?? 'draft', 'published' ); ?>> <?php esc_html_e( 'Published', 'cta-lms' ); ?></label>
						<label><input type="radio" name="status" value="draft" <?php checked( $course->status ?? 'draft', 'draft' ); ?>> <?php esc_html_e( 'Draft', 'cta-lms' ); ?></label>
						<?php if ( ! $is_exam_prep ) : ?>
							<input type="hidden" name="cta_confirm_ce_publish" id="cta-confirm-ce-publish" value="">
							<p class="description" style="margin-top:8px;">
								<?php esc_html_e( 'CAMFT CEPA: CE courses must stay Draft until provider approval. Publishing requires an explicit confirmation prompt.', 'cta-lms' ); ?>
							</p>
						<?php else : ?>
							<input type="hidden" name="cta_confirm_exam_prep_publish" id="cta-confirm-exam-prep-publish" value="">
							<p class="description" style="margin-top:8px;">
								<?php esc_html_e( 'Exam Prep release gate: programs must stay Draft until final learner testing is verified AND written CTA approval is received. Publishing requires an explicit confirmation prompt.', 'cta-lms' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_exam_prep ? esc_html__( 'Save Program', 'cta-lms' ) : esc_html__( 'Save Course', 'cta-lms' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-courses&product_type=' . $current_product_type ) ); ?>"><?php esc_html_e( 'Back to List', 'cta-lms' ); ?></a>
			</p>
		</div>
	</form>

	<script>
	(function () {
		function syncProductType() {
			var exam = document.querySelector('input[name="product_type"][value="exam_prep"]');
			var isExam = exam && exam.checked;
			var ceRow = document.querySelector('.cta-field-ce-hours');
			var accessRow = document.querySelector('.cta-field-access-months');
			var attestRow = document.querySelector('.cta-field-attestation');
			var ceInput = document.getElementById('cta-course-ce-hours');
			var attestInput = document.querySelector('input[name="attestation_required"]');
			if (ceRow) { ceRow.style.display = isExam ? 'none' : ''; }
			if (accessRow) { accessRow.style.display = isExam ? '' : 'none'; }
			if (attestRow) { attestRow.style.display = isExam ? 'none' : ''; }
			if (ceInput) {
				ceInput.disabled = !!isExam;
				if (isExam) { ceInput.value = '0'; }
			}
			if (attestInput) {
				attestInput.disabled = !!isExam;
				if (isExam) { attestInput.checked = false; }
			}
		}
		document.querySelectorAll('input[name="product_type"]').forEach(function (el) {
			el.addEventListener('change', syncProductType);
		});
		syncProductType();

		var form = document.querySelector('form[action*="cta_save_course"], form.cta-course-edit-form, form');
		var confirmField = document.getElementById('cta-confirm-ce-publish');
		var examConfirmField = document.getElementById('cta-confirm-exam-prep-publish');
		if (form && (confirmField || examConfirmField)) {
			form.addEventListener('submit', function (e) {
				var exam = document.querySelector('input[name="product_type"][value="exam_prep"]');
				var isExam = exam && exam.checked;
				var published = document.querySelector('input[name="status"][value="published"]');
				if (confirmField) {
					confirmField.value = '';
				}
				if (examConfirmField) {
					examConfirmField.value = '';
				}
				if (!published || !published.checked) {
					return;
				}
				if (isExam) {
					if (!examConfirmField) {
						return;
					}
					var okExam = window.confirm(
						'Exam Prep release gate:\n\n' +
						'This Exam Preparation program will become publicly visible and purchasable.\n' +
						'Do NOT publish until:\n' +
						'1) Final learner testing has been completed and verified, AND\n' +
						'2) Written approval from CTA has been received.\n\n' +
						'Publish this Exam Prep program anyway?'
					);
					if (!okExam) {
						e.preventDefault();
						return;
					}
					examConfirmField.value = '1';
					return;
				}
				if (!confirmField) {
					return;
				}
				var ok = window.confirm(
					'CAMFT CEPA compliance warning:\n\n' +
					'This CE course will become publicly visible and purchasable.\n' +
					'Do NOT publish until CTA has CAMFT CEPA provider approval.\n\n' +
					'Publish this CE course anyway?'
				);
				if (!ok) {
					e.preventDefault();
					return;
				}
				confirmField.value = '1';
			});
		}
	})();
	</script>

	<?php if ( $course_id ) : ?>
		<div class="cta-admin-panel" id="cta-modules-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>">
			<h2><?php esc_html_e( 'Course Modules', 'cta-lms' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Order', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Title', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Video', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
					</tr>
				</thead>
				<tbody id="cta-modules-list">
					<?php foreach ( $modules as $module ) : ?>
						<?php echo $admin->render_module_row_html( $module ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Add Module', 'cta-lms' ); ?></h3>
			<div class="cta-module-form">
				<input type="hidden" id="cta-module-id" value="">
				<p><input type="text" id="cta-module-title" class="regular-text" placeholder="<?php esc_attr_e( 'Module title', 'cta-lms' ); ?>"></p>
				<p><textarea id="cta-module-description" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Description', 'cta-lms' ); ?>"></textarea></p>
				<p>
					<label for="cta-module-video-type"><strong><?php esc_html_e( 'Video Source', 'cta-lms' ); ?></strong></label><br>
					<select id="cta-module-video-type">
						<option value="vimeo"><?php esc_html_e( 'Vimeo', 'cta-lms' ); ?></option>
						<option value="youtube"><?php esc_html_e( 'YouTube URL', 'cta-lms' ); ?></option>
						<option value="wordpress"><?php esc_html_e( 'WordPress Media Library', 'cta-lms' ); ?></option>
						<option value="url"><?php esc_html_e( 'Direct Video URL (MP4)', 'cta-lms' ); ?></option>
					</select>
				</p>
				<p class="cta-module-video-row">
					<input type="text" id="cta-module-video" class="regular-text" placeholder="<?php esc_attr_e( 'Vimeo ID or video URL', 'cta-lms' ); ?>">
					<button type="button" class="button" id="cta-module-video-select" style="display:none;"><?php esc_html_e( 'Select Video', 'cta-lms' ); ?></button>
				</p>
				<p class="description cta-module-video-help" data-help="vimeo"><?php esc_html_e( 'Enter the Vimeo video ID (numbers only) or full Vimeo URL.', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="youtube"><?php esc_html_e( 'Example: https://www.youtube.com/watch?v=VIDEO_ID', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="wordpress" style="display:none;"><?php esc_html_e( 'Click Select Video to choose an uploaded MP4 from your Media Library.', 'cta-lms' ); ?></p>
				<p class="description cta-module-video-help" data-help="url" style="display:none;"><?php esc_html_e( 'Paste a direct link to an MP4 or other supported video file.', 'cta-lms' ); ?></p>
				<p>
					<input type="number" id="cta-module-duration" min="0" placeholder="<?php esc_attr_e( 'Duration (mins)', 'cta-lms' ); ?>">
					<label><input type="checkbox" id="cta-module-locked" checked> <?php esc_html_e( 'Locked until previous complete', 'cta-lms' ); ?></label>
				</p>
				<button type="button" class="button button-primary" id="cta-save-module"><?php esc_html_e( 'Add Module', 'cta-lms' ); ?></button>
			</div>
		</div>

		<?php if ( ! $is_exam_prep ) : ?>
		<div class="cta-admin-panel" id="cta-course-evaluation-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>">
			<h2><?php esc_html_e( 'Course Evaluation', 'cta-lms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Each course has its own independent evaluation form. Students complete it after passing the quiz and before receiving their certificate.', 'cta-lms' ); ?></p>

			<p>
				<button type="button" class="button" id="cta-sync-eval-objectives"><?php esc_html_e( 'Sync Learning Objective Questions', 'cta-lms' ); ?></button>
				<button type="button" class="button" id="cta-copy-eval-camft"><?php esc_html_e( 'Add CAMFT / Standard Questions', 'cta-lms' ); ?></button>
				<span id="cta-course-eval-action-status" class="cta-inline-result"></span>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:40px;"><?php esc_html_e( '#', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Section', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Question', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Type', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Required', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
					</tr>
				</thead>
				<tbody id="cta-course-eval-questions-list">
					<?php
					if ( empty( $eval_questions ) ) {
						echo '<tr class="cta-eval-empty-row"><td colspan="7">' . esc_html__( 'No evaluation questions yet. Sync learning objectives or add CAMFT questions to get started.', 'cta-lms' ) . '</td></tr>';
					} else {
						foreach ( $eval_questions as $index => $q ) {
							echo $admin->render_course_eval_question_row_html( $q, $eval_types, $index, $course_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
					?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Add / Edit Question', 'cta-lms' ); ?></h3>
			<div class="cta-course-eval-form">
				<input type="hidden" id="cta-course-eval-question-id" value="0">
				<input type="hidden" id="cta-course-eval-course-id" value="<?php echo esc_attr( (string) $course_id ); ?>">
				<table class="form-table">
					<tr>
						<th><label for="cta-course-eval-section"><?php esc_html_e( 'Section', 'cta-lms' ); ?></label></th>
						<td><input type="text" class="regular-text" id="cta-course-eval-section" placeholder="<?php esc_attr_e( 'e.g. Course Content', 'cta-lms' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="cta-course-eval-label"><?php esc_html_e( 'Question', 'cta-lms' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="cta-course-eval-label" required></textarea></td>
					</tr>
					<tr>
						<th><label for="cta-course-eval-type"><?php esc_html_e( 'Type', 'cta-lms' ); ?></label></th>
						<td>
							<select id="cta-course-eval-type">
								<?php foreach ( $eval_types as $type_key => $type_label ) : ?>
									<option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cta-course-eval-options"><?php esc_html_e( 'Options', 'cta-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="5" id="cta-course-eval-options" placeholder="<?php esc_attr_e( "yes|Yes\nno|No\n\nor one label per line", 'cta-lms' ); ?>"></textarea>
							<p class="description"><?php esc_html_e( 'Required for radio, checkbox, and dropdown. Optional for rating (defaults to 1–5 Likert). Leave blank for text fields.', 'cta-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Required', 'cta-lms' ); ?></th>
						<td>
							<label><input type="checkbox" id="cta-course-eval-required" value="1" checked> <?php esc_html_e( 'Student must answer this question', 'cta-lms' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="cta-course-eval-status"><?php esc_html_e( 'Status', 'cta-lms' ); ?></label></th>
						<td>
							<select id="cta-course-eval-status">
								<option value="active"><?php esc_html_e( 'Active', 'cta-lms' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'cta-lms' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'cta-lms' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" class="button button-primary" id="cta-course-eval-save"><?php esc_html_e( 'Save Question', 'cta-lms' ); ?></button>
					<button type="button" class="button" id="cta-course-eval-cancel" style="display:none;"><?php esc_html_e( 'Cancel Edit', 'cta-lms' ); ?></button>
					<span id="cta-course-eval-save-status" class="cta-inline-result"></span>
				</p>
			</div>
		</div>
		<?php endif; ?>

		<div class="cta-admin-panel" id="cta-quiz-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>" data-is-exam-prep="<?php echo $is_exam_prep ? '1' : '0'; ?>" data-quiz-id="<?php echo esc_attr( (string) ( $quiz->id ?? 0 ) ); ?>">
			<h2><?php echo $is_exam_prep ? esc_html__( 'Assessments (Practice / Form A / Form B)', 'cta-lms' ) : esc_html__( 'Course Quiz', 'cta-lms' ); ?></h2>

			<?php if ( $is_exam_prep ) : ?>
				<p class="description"><?php esc_html_e( 'Exam Preparation programs support multiple assessments. Create Practice Assessment, Form A, and Form B (or custom). Each assessment has its own questions and learner progress.', 'cta-lms' ); ?></p>
				<div id="cta-exam-assessment-toolbar" class="cta-exam-assessment-toolbar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0;">
					<label for="cta-active-quiz-select"><strong><?php esc_html_e( 'Editing:', 'cta-lms' ); ?></strong></label>
					<select id="cta-active-quiz-select" style="min-width:260px;">
						<?php
						$quiz_rows = ! empty( $quizzes ) ? $quizzes : array();
						if ( empty( $quiz_rows ) && $quiz ) {
							$quiz_rows = array( $quiz );
						}
						foreach ( $quiz_rows as $qrow ) :
							?>
							<option value="<?php echo esc_attr( (string) $qrow->id ); ?>" <?php selected( (int) ( $quiz->id ?? 0 ), (int) $qrow->id ); ?>>
								<?php echo esc_html( $qrow->title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="cta-add-assessment-practice"><?php esc_html_e( '+ Practice Assessment', 'cta-lms' ); ?></button>
					<button type="button" class="button" id="cta-add-assessment-form-a"><?php esc_html_e( '+ Form A', 'cta-lms' ); ?></button>
					<button type="button" class="button" id="cta-add-assessment-form-b"><?php esc_html_e( '+ Form B', 'cta-lms' ); ?></button>
					<button type="button" class="button" id="cta-add-assessment-custom"><?php esc_html_e( '+ Custom', 'cta-lms' ); ?></button>
				</div>
				<p>
					<label for="cta-quiz-type"><strong><?php esc_html_e( 'Assessment type', 'cta-lms' ); ?></strong></label><br>
					<select id="cta-quiz-type">
						<option value="practice" <?php selected( (string) ( $quiz->quiz_type ?? 'practice' ), 'practice' ); ?>><?php esc_html_e( 'Practice Assessment', 'cta-lms' ); ?></option>
						<option value="form_a" <?php selected( (string) ( $quiz->quiz_type ?? '' ), 'form_a' ); ?>><?php esc_html_e( 'Form A — Comprehensive Simulation', 'cta-lms' ); ?></option>
						<option value="form_b" <?php selected( (string) ( $quiz->quiz_type ?? '' ), 'form_b' ); ?>><?php esc_html_e( 'Form B — Comprehensive Simulation', 'cta-lms' ); ?></option>
						<option value="custom" <?php selected( (string) ( $quiz->quiz_type ?? '' ), 'custom' ); ?>><?php esc_html_e( 'Custom', 'cta-lms' ); ?></option>
					</select>
				</p>
			<?php endif; ?>

			<div id="cta-quiz-status-line" class="cta-quiz-status-line">
				<?php if ( $quiz ) : ?>
					<p><?php echo $is_exam_prep ? esc_html__( 'Assessment selected:', 'cta-lms' ) : esc_html__( 'Quiz exists for this course.', 'cta-lms' ); ?> <strong><?php echo esc_html( $quiz->title ); ?></strong></p>
				<?php else : ?>
					<p><?php echo $is_exam_prep ? esc_html__( 'No assessments created yet. Use the buttons above to add Practice / Form A / Form B.', 'cta-lms' ) : esc_html__( 'No quiz created yet.', 'cta-lms' ); ?></p>
				<?php endif; ?>
			</div>

			<div id="cta-quiz-saved-list" class="cta-quiz-saved-list">
				<?php if ( ! empty( $quiz_questions ) ) : ?>
					<h3><?php esc_html_e( 'Saved Questions', 'cta-lms' ); ?> (<?php echo esc_html( (string) count( $quiz_questions ) ); ?>)</h3>
					<ol class="cta-quiz-saved-list__items">
						<?php foreach ( $quiz_questions as $index => $question ) : ?>
							<li>
								<strong><?php echo esc_html( sprintf( __( 'Q%d', 'cta-lms' ), $index + 1 ) ); ?>:</strong>
								<?php echo esc_html( wp_trim_words( $question->question_text, 12 ) ); ?>
								<span class="cta-quiz-saved-list__answer">(<?php echo esc_html( strtoupper( $question->correct_option ) ); ?>)</span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>

			<p>
				<label for="cta-quiz-title"><strong><?php echo $is_exam_prep ? esc_html__( 'Assessment Title', 'cta-lms' ) : esc_html__( 'Quiz Title', 'cta-lms' ); ?></strong></label><br>
				<input type="text" id="cta-quiz-title" class="regular-text" placeholder="<?php esc_attr_e( 'Quiz title', 'cta-lms' ); ?>" value="<?php echo esc_attr( $quiz->title ?? '' ); ?>">
			</p>

			<div id="cta-quiz-builder" class="cta-quiz-builder">
				<div id="cta-quiz-questions" class="cta-quiz-questions"></div>
				<p>
					<button type="button" class="button" id="cta-add-quiz-question"><?php esc_html_e( '+ Add Question', 'cta-lms' ); ?></button>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary" id="cta-save-quiz"><?php echo $quiz ? esc_html__( 'Save Assessment', 'cta-lms' ) : esc_html__( 'Create Quiz', 'cta-lms' ); ?></button>
				<span id="cta-quiz-save-status" class="cta-inline-result"></span>
			</p>
			<p class="description">
				<?php
				echo $is_exam_prep
					? esc_html__( 'Add practice / simulation questions. Use the explanation field as the answer rationale shown after submit. Learners can complete each assessment independently. No CE certificate is issued for exam prep programs.', 'cta-lms' )
					: esc_html__( 'Add multiple-choice questions below. Students must score 70% or higher to pass. Quizzes have no time limit and unlimited retake attempts until passed.', 'cta-lms' );
				?>
			</p>
		</div>

		<div class="cta-admin-panel" id="cta-resources-panel" data-course-id="<?php echo esc_attr( (string) $course_id ); ?>">
			<h2><?php echo $is_exam_prep ? esc_html__( 'Downloadable Workbooks & Practice Tests', 'cta-lms' ) : esc_html__( 'Course Materials', 'cta-lms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Attach PDFs, handouts, worksheets, checklists, templates, or reference guides to the whole course or to a specific module. Allowed types: PDF, DOC, DOCX. Max size: 20MB per file. Files are stored in a protected folder and only enrolled learners can download them.', 'cta-lms' ); ?></p>

			<?php if ( ! empty( $resources ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:36px;"></th>
							<th><?php esc_html_e( 'Title', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Attached to', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Type', 'cta-lms' ); ?></th>
							<?php if ( $is_exam_prep ) : ?>
								<th><?php esc_html_e( 'Practice Test', 'cta-lms' ); ?></th>
							<?php endif; ?>
							<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
						</tr>
					</thead>
					<tbody id="cta-resources-list">
						<?php
						$module_labels = array();
						foreach ( $modules as $module ) {
							$module_labels[ (int) $module->id ] = $module->title;
						}
						foreach ( $resources as $resource ) :
							$mid = isset( $resource->module_id ) ? (int) $resource->module_id : 0;
							?>
							<tr data-resource-id="<?php echo esc_attr( (string) $resource->id ); ?>"
								data-title="<?php echo esc_attr( $resource->title ); ?>"
								data-module-id="<?php echo esc_attr( (string) $mid ); ?>"
								data-file-type="<?php echo esc_attr( (string) $resource->file_type ); ?>"
								data-practice="<?php echo ! empty( $resource->is_practice_test ) ? '1' : '0'; ?>"
								data-attachment-id="<?php echo esc_attr( (string) (int) ( $resource->attachment_id ?? 0 ) ); ?>">
								<td class="cta-drag-handle" style="cursor:move;" title="<?php esc_attr_e( 'Drag to reorder', 'cta-lms' ); ?>">&#8942;&#8942;</td>
								<td><strong><?php echo esc_html( $resource->title ); ?></strong></td>
								<td>
									<?php
									echo $mid && isset( $module_labels[ $mid ] )
										? esc_html( $module_labels[ $mid ] )
										: esc_html__( 'Entire course', 'cta-lms' );
									?>
								</td>
								<td><?php echo esc_html( strtoupper( (string) $resource->file_type ) ); ?></td>
								<?php if ( $is_exam_prep ) : ?>
									<td><?php echo ! empty( $resource->is_practice_test ) ? esc_html__( 'Yes', 'cta-lms' ) : esc_html__( 'No', 'cta-lms' ); ?></td>
								<?php endif; ?>
								<td>
									<button type="button" class="button button-small cta-edit-resource"><?php esc_html_e( 'Edit / Replace', 'cta-lms' ); ?></button>
									<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_delete_resource&resource_id=' . (int) $resource->id . '&course_id=' . (int) $course_id ), 'cta_delete_resource' ) ); ?>"><?php esc_html_e( 'Delete', 'cta-lms' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No course materials yet.', 'cta-lms' ); ?></p>
			<?php endif; ?>

			<h3 id="cta-resource-form-heading"><?php esc_html_e( 'Add Material', 'cta-lms' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form" id="cta-resource-form">
				<?php wp_nonce_field( 'cta_save_resource' ); ?>
				<input type="hidden" name="action" value="cta_save_resource">
				<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>">
				<input type="hidden" name="resource_id" id="cta-resource-id" value="">
				<input type="hidden" name="resource_attachment_id" id="cta-resource-attachment-id" value="">
				<input type="hidden" name="resource_file_url" id="cta-resource-file-url" value="">
				<p>
					<label for="cta-resource-title"><strong><?php esc_html_e( 'Title', 'cta-lms' ); ?></strong></label><br>
					<input type="text" class="regular-text" id="cta-resource-title" name="resource_title" placeholder="<?php esc_attr_e( 'e.g. Session Handout, Worksheet, Checklist', 'cta-lms' ); ?>" required>
				</p>
				<p>
					<label for="cta-resource-module"><strong><?php esc_html_e( 'Attach to', 'cta-lms' ); ?></strong></label><br>
					<select id="cta-resource-module" name="resource_module_id">
						<option value="0"><?php esc_html_e( 'Entire course', 'cta-lms' ); ?></option>
						<?php foreach ( $modules as $module ) : ?>
							<option value="<?php echo esc_attr( (string) $module->id ); ?>"><?php echo esc_html( $module->title ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label><strong><?php esc_html_e( 'File', 'cta-lms' ); ?></strong></label><br>
					<button type="button" class="button" id="cta-resource-select-file"><?php esc_html_e( 'Select / Upload File', 'cta-lms' ); ?></button>
					<span id="cta-resource-file-label" class="description" style="margin-left:8px;"></span>
					<br><span class="description"><?php esc_html_e( 'PDF, DOC, or DOCX only. Maximum 20MB.', 'cta-lms' ); ?></span>
				</p>
				<p>
					<label for="cta-resource-file-type"><strong><?php esc_html_e( 'File type', 'cta-lms' ); ?></strong></label><br>
					<input type="text" class="small-text" id="cta-resource-file-type" name="resource_file_type" placeholder="<?php esc_attr_e( 'pdf', 'cta-lms' ); ?>">
					<?php if ( $is_exam_prep ) : ?>
						<label style="margin-left:12px;"><input type="checkbox" name="is_practice_test" id="cta-resource-practice" value="1"> <?php esc_html_e( 'Practice test / approved testing material', 'cta-lms' ); ?></label>
					<?php endif; ?>
				</p>
				<p>
					<button type="submit" class="button button-primary" id="cta-resource-submit"><?php esc_html_e( 'Add Material', 'cta-lms' ); ?></button>
					<button type="button" class="button" id="cta-resource-cancel-edit" style="display:none;"><?php esc_html_e( 'Cancel', 'cta-lms' ); ?></button>
				</p>
			</form>
		</div>

		<?php if ( $is_exam_prep ) : ?>
			<div class="cta-admin-panel" id="cta-exam-access-panel">
				<h2><?php esc_html_e( 'Learner Access — Extend Manually', 'cta-lms' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Extend a learner\'s access without a request/approval workflow. Progress and purchase history are preserved.', 'cta-lms' ); ?></p>

				<?php if ( empty( $exam_learners ) ) : ?>
					<p><?php esc_html_e( 'No learners have purchased this program yet.', 'cta-lms' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Learner', 'cta-lms' ); ?></th>
								<th><?php esc_html_e( 'Purchased', 'cta-lms' ); ?></th>
								<th><?php esc_html_e( 'Expires', 'cta-lms' ); ?></th>
								<th><?php esc_html_e( 'Extend', 'cta-lms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $exam_learners as $learner ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $learner->display_name ? $learner->display_name : __( 'User', 'cta-lms' ) . ' #' . (int) $learner->user_id ); ?></strong><br>
										<span class="description"><?php echo esc_html( (string) $learner->user_email ); ?></span>
									</td>
									<td><?php echo esc_html( cta_lms_format_local_date( $learner->purchased_at, 'M j, Y' ) ); ?></td>
									<td><?php echo esc_html( cta_lms_format_local_date( $learner->expires_at, 'M j, Y g:i A' ) ); ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
											<?php wp_nonce_field( 'cta_extend_exam_access' ); ?>
											<input type="hidden" name="action" value="cta_extend_exam_access">
											<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>">
											<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $learner->user_id ); ?>">
											<input type="number" name="extra_months" min="1" max="24" value="1" class="small-text" title="<?php esc_attr_e( 'Months to add', 'cta-lms' ); ?>">
											<input type="text" name="extension_notes" class="regular-text" placeholder="<?php esc_attr_e( 'Notes (optional)', 'cta-lms' ); ?>">
											<button type="submit" class="button"><?php esc_html_e( 'Extend', 'cta-lms' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Save the course first to add modules, quizzes, and downloadable resources.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>
</div>
