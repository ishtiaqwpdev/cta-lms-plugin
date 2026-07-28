<?php
/**
 * Admin: CE course evaluation submissions + CAMFT template library.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Evaluation_Questions' ) ) {
	return;
}

$types = CTA_Evaluation_Questions::get_types();

// Single submission view.
if ( ! empty( $view_evaluation ) ) {
	$responses = array();
	if ( ! empty( $view_evaluation->responses ) ) {
		$decoded = json_decode( (string) $view_evaluation->responses, true );
		if ( is_array( $decoded ) ) {
			$responses = $decoded;
		}
	}
	?>
	<div class="wrap cta-admin-wrap">
		<h1><?php esc_html_e( 'Evaluation Submission', 'cta-lms' ); ?></h1>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-evaluation' ) ); ?>">&larr; <?php esc_html_e( 'Back to submissions', 'cta-lms' ); ?></a>
		</p>

		<div class="cta-admin-panel">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Course', 'cta-lms' ); ?></th>
					<td><?php echo esc_html( $view_evaluation->course_title ? $view_evaluation->course_title : (string) $view_evaluation->course_id ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Student', 'cta-lms' ); ?></th>
					<td>
						<?php echo esc_html( $view_evaluation->student_name ); ?>
						<?php if ( ! empty( $view_evaluation->student_email ) ) : ?>
							<br><a href="mailto:<?php echo esc_attr( $view_evaluation->student_email ); ?>"><?php echo esc_html( $view_evaluation->student_email ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Submitted', 'cta-lms' ); ?></th>
					<td><?php echo esc_html( (string) $view_evaluation->submitted_at ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
					<td><?php echo esc_html( (string) ( $view_evaluation->status ?? 'completed' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Summary ratings', 'cta-lms' ); ?></th>
					<td>
						<?php
						$recommend_label = (int) $view_evaluation->would_recommend
							? __( 'Yes', 'cta-lms' )
							: __( 'No', 'cta-lms' );
						printf(
							/* translators: 1: overall rating, 2: content quality, 3: instructor rating, 4: would recommend yes/no */
							esc_html__( 'Rating: %1$d | Content: %2$d | Instructor: %3$d | Recommend: %4$s', 'cta-lms' ),
							(int) $view_evaluation->rating,
							(int) $view_evaluation->content_quality,
							(int) $view_evaluation->instructor_rating,
							esc_html( $recommend_label )
						);
						?>
					</td>
				</tr>
				<?php if ( ! empty( $view_evaluation->comments ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Comments', 'cta-lms' ); ?></th>
					<td><?php echo esc_html( (string) $view_evaluation->comments ); ?></td>
				</tr>
				<?php endif; ?>
			</table>

			<h2><?php esc_html_e( 'Responses', 'cta-lms' ); ?></h2>
			<?php if ( empty( $responses ) ) : ?>
				<p><?php esc_html_e( 'No structured responses recorded.', 'cta-lms' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Question ID', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Answer', 'cta-lms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $responses as $qid => $answer ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $qid ); ?></code></td>
								<td>
									<?php
									if ( is_array( $answer ) ) {
										echo esc_html( implode( ', ', array_map( 'strval', $answer ) ) );
									} else {
										echo esc_html( (string) $answer );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Raw responses JSON', 'cta-lms' ); ?></h3>
			<textarea class="large-text code" rows="8" readonly><?php echo esc_textarea( (string) $view_evaluation->responses ); ?></textarea>
		</div>
	</div>
	<?php
	return;
}

$tab                = isset( $tab ) ? $tab : 'submissions';
$submissions        = isset( $submissions ) ? $submissions : array();
$courses            = isset( $courses ) ? $courses : array();
$template_questions = isset( $template_questions ) ? $template_questions : array();
$edit_question      = isset( $edit_question ) ? $edit_question : null;
$notice             = isset( $notice ) ? $notice : '';
$filter_course      = isset( $filter_course ) ? (int) $filter_course : 0;
$filter_search      = isset( $filter_search ) ? $filter_search : '';
$filter_from        = isset( $filter_from ) ? $filter_from : '';
$filter_to          = isset( $filter_to ) ? $filter_to : '';
$filter_status      = isset( $filter_status ) ? $filter_status : 'all';

$is_editing    = $edit_question && ! empty( $edit_question->id );
$edit_options_text = '';
if ( $is_editing && ! empty( $edit_question->options_json ) ) {
	$decoded = json_decode( (string) $edit_question->options_json, true );
	if ( is_array( $decoded ) ) {
		$lines = array();
		foreach ( $decoded as $key => $label ) {
			$lines[] = $key . '|' . $label;
		}
		$edit_options_text = implode( "\n", $lines );
	}
}

$base_url = admin_url( 'admin.php?page=cta-lms-evaluation' );
?>
<div class="wrap cta-admin-wrap">
	<h1><?php esc_html_e( 'Course Evaluation', 'cta-lms' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Review student evaluation submissions and manage the shared CAMFT question template library used when seeding new courses.', 'cta-lms' ); ?>
	</p>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Evaluation question saved.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'deleted' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Evaluation question deleted. Past student submissions were not changed.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'reordered' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Question order updated.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'save_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not save the evaluation question. Check required fields and options.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'submissions', $base_url ) ); ?>" class="nav-tab <?php echo 'submissions' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Submissions', 'cta-lms' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'templates', $base_url ) ); ?>" class="nav-tab <?php echo 'templates' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Question Templates (CAMFT library)', 'cta-lms' ); ?></a>
	</h2>

	<?php if ( 'submissions' === $tab ) : ?>
		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Filter Submissions', 'cta-lms' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="cta-lms-evaluation">
				<input type="hidden" name="tab" value="submissions">
				<table class="form-table">
					<tr>
						<th><label for="cta-eval-filter-course"><?php esc_html_e( 'Course', 'cta-lms' ); ?></label></th>
						<td>
							<select name="course_id" id="cta-eval-filter-course">
								<option value="0"><?php esc_html_e( 'All CE courses', 'cta-lms' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( (string) $course->id ); ?>" <?php selected( $filter_course, (int) $course->id ); ?>><?php echo esc_html( $course->title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-filter-search"><?php esc_html_e( 'Student search', 'cta-lms' ); ?></label></th>
						<td><input type="search" class="regular-text" name="s" id="cta-eval-filter-search" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Name or email', 'cta-lms' ); ?>"></td>
					</tr>
					<tr>
						<th><label for="cta-eval-filter-from"><?php esc_html_e( 'Date from', 'cta-lms' ); ?></label></th>
						<td><input type="date" name="date_from" id="cta-eval-filter-from" value="<?php echo esc_attr( $filter_from ); ?>"></td>
					</tr>
					<tr>
						<th><label for="cta-eval-filter-to"><?php esc_html_e( 'Date to', 'cta-lms' ); ?></label></th>
						<td><input type="date" name="date_to" id="cta-eval-filter-to" value="<?php echo esc_attr( $filter_to ); ?>"></td>
					</tr>
					<tr>
						<th><label for="cta-eval-filter-status"><?php esc_html_e( 'Status', 'cta-lms' ); ?></label></th>
						<td>
							<select name="status" id="cta-eval-filter-status">
								<option value="all" <?php selected( $filter_status, 'all' ); ?>><?php esc_html_e( 'All', 'cta-lms' ); ?></option>
								<option value="completed" <?php selected( $filter_status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'cta-lms' ); ?></option>
								<option value="draft" <?php selected( $filter_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'cta-lms' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'cta-lms' ); ?></button>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', 'submissions', $base_url ) ); ?>"><?php esc_html_e( 'Reset', 'cta-lms' ); ?></a>
				</p>
			</form>

			<?php
			$export_args = array(
				'action'     => 'cta_export_evaluations_csv',
				'course_id'  => $filter_course,
				's'          => $filter_search,
				'date_from'  => $filter_from,
				'date_to'    => $filter_to,
				'status'     => $filter_status,
			);
			$export_url = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'cta_export_evaluations_csv' );
			?>
			<p>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'cta-lms' ); ?></a>
			</p>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'Submissions', 'cta-lms' ); ?></h2>
			<?php if ( empty( $submissions ) ) : ?>
				<p><?php esc_html_e( 'No evaluation submissions match your filters.', 'cta-lms' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Course', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Student', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Date', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submissions as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->course_title ? $row->course_title : (string) $row->course_id ); ?></td>
								<td>
									<?php echo esc_html( $row->student_name ); ?>
									<?php if ( ! empty( $row->student_email ) ) : ?>
										<br><small><?php echo esc_html( $row->student_email ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) $row->submitted_at ); ?></td>
								<td><?php echo esc_html( (string) ( $row->status ?? 'completed' ) ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'view', (int) $row->id, $base_url ) ); ?>"><?php esc_html_e( 'View', 'cta-lms' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<div class="cta-admin-panel" id="cta-eval-templates-panel">
			<h2><?php echo $is_editing ? esc_html__( 'Edit Template Question', 'cta-lms' ) : esc_html__( 'Add Template Question', 'cta-lms' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These questions live in the shared CAMFT library (course_id = 0) and are copied into individual CE courses when you click “Add CAMFT / Standard Questions” on a course.', 'cta-lms' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cta-admin-form">
				<?php wp_nonce_field( 'cta_save_evaluation_question' ); ?>
				<input type="hidden" name="action" value="cta_save_evaluation_question">
				<input type="hidden" name="question_id" value="<?php echo esc_attr( $is_editing ? (string) $edit_question->id : '0' ); ?>">
				<input type="hidden" name="course_id" value="0">

				<table class="form-table">
					<tr>
						<th><label for="cta-eval-section"><?php esc_html_e( 'Section', 'cta-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="cta-eval-section" name="section_label" value="<?php echo esc_attr( $is_editing ? $edit_question->section_label : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Course Content', 'cta-lms' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-label"><?php esc_html_e( 'Question', 'cta-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="2" id="cta-eval-label" name="label" required><?php echo esc_textarea( $is_editing ? $edit_question->label : '' ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-type"><?php esc_html_e( 'Type', 'cta-lms' ); ?></label></th>
						<td>
							<select id="cta-eval-type" name="question_type">
								<?php foreach ( $types as $type_key => $type_label ) : ?>
									<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $is_editing ? $edit_question->question_type : 'rating', $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-options"><?php esc_html_e( 'Options', 'cta-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="5" id="cta-eval-options" name="options_text" placeholder="<?php esc_attr_e( "yes|Yes\nno|No\n\nor one label per line", 'cta-lms' ); ?>"><?php echo esc_textarea( $edit_options_text ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Required for radio and dropdown. Optional for rating (defaults to 1–5 Likert). Use value|Label per line, or one label per line. Leave blank for text fields.', 'cta-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Required', 'cta-lms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_required" value="1" <?php checked( ! $is_editing || (int) $edit_question->is_required === 1 ); ?>>
								<?php esc_html_e( 'Student must answer this question', 'cta-lms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-status"><?php esc_html_e( 'Status', 'cta-lms' ); ?></label></th>
						<td>
							<select id="cta-eval-status" name="status">
								<option value="active" <?php selected( $is_editing ? $edit_question->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'cta-lms' ); ?></option>
								<option value="draft" <?php selected( $is_editing ? $edit_question->status : '', 'draft' ); ?>><?php esc_html_e( 'Draft', 'cta-lms' ); ?></option>
								<option value="inactive" <?php selected( $is_editing ? $edit_question->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'cta-lms' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cta-eval-summary"><?php esc_html_e( 'Summary mapping (optional)', 'cta-lms' ); ?></label></th>
						<td>
							<select id="cta-eval-summary" name="summary_field">
								<option value=""><?php esc_html_e( 'None', 'cta-lms' ); ?></option>
								<?php
								$summary_choices = array(
									'rating'            => 'rating',
									'content_quality'   => 'content_quality',
									'instructor_rating' => 'instructor_rating',
									'would_recommend'   => 'would_recommend',
									'comments'          => 'comments',
								);
								$current_summary = $is_editing ? (string) $edit_question->summary_field : '';
								foreach ( $summary_choices as $skey => $slabel ) :
									?>
									<option value="<?php echo esc_attr( $skey ); ?>" <?php selected( $current_summary, $skey ); ?>><?php echo esc_html( $slabel ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Optional: map into legacy summary columns on the submission record.', 'cta-lms' ); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary"><?php echo $is_editing ? esc_html__( 'Update Question', 'cta-lms' ) : esc_html__( 'Add Question', 'cta-lms' ); ?></button>
					<?php if ( $is_editing ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', 'templates', $base_url ) ); ?>"><?php esc_html_e( 'Cancel', 'cta-lms' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<div class="cta-admin-panel">
			<h2><?php esc_html_e( 'CAMFT Template Questions', 'cta-lms' ); ?></h2>
			<?php if ( empty( $template_questions ) ) : ?>
				<p><?php esc_html_e( 'No template questions yet. Defaults are seeded automatically on first install.', 'cta-lms' ); ?></p>
			<?php else : ?>
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
					<tbody id="cta-eval-questions-list">
						<?php foreach ( $template_questions as $index => $q ) : ?>
							<tr data-question-id="<?php echo esc_attr( (string) $q->id ); ?>">
								<td><?php echo esc_html( (string) ( (int) $index + 1 ) ); ?></td>
								<td><?php echo esc_html( $q->section_label ); ?></td>
								<td><strong><?php echo esc_html( wp_trim_words( $q->label, 12 ) ); ?></strong></td>
								<td><?php echo esc_html( isset( $types[ $q->question_type ] ) ? $types[ $q->question_type ] : $q->question_type ); ?></td>
								<td><?php echo (int) $q->is_required ? esc_html__( 'Yes', 'cta-lms' ) : esc_html__( 'No', 'cta-lms' ); ?></td>
								<td><?php echo esc_html( $q->status ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'templates', 'edit' => (int) $q->id ), $base_url ) ); ?>"><?php esc_html_e( 'Edit', 'cta-lms' ); ?></a>
									<a class="button button-small button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_delete_evaluation_question&question_id=' . (int) $q->id ), 'cta_delete_evaluation_question' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this template question?', 'cta-lms' ) ); ?>');"><?php esc_html_e( 'Delete', 'cta-lms' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
					<?php wp_nonce_field( 'cta_reorder_evaluation_questions' ); ?>
					<input type="hidden" name="action" value="cta_reorder_evaluation_questions">
					<?php foreach ( $template_questions as $q ) : ?>
						<input type="hidden" name="order[]" value="<?php echo esc_attr( (string) $q->id ); ?>">
					<?php endforeach; ?>
					<label for="cta-eval-reorder"><?php esc_html_e( 'Reorder (comma-separated IDs, left = first)', 'cta-lms' ); ?></label><br>
					<input type="text" class="large-text" id="cta-eval-reorder" name="order_csv" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $template_questions, 'id' ) ) ); ?>">
					<p><button type="submit" class="button"><?php esc_html_e( 'Save Order', 'cta-lms' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
