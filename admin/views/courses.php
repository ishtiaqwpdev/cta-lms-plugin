<?php
/**
 * Admin courses list view.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice       = sanitize_text_field( wp_unslash( $_GET['cta_notice'] ?? '' ) );
$product_type = isset( $product_type ) ? $product_type : 'ce';
$is_exam      = ( 'exam_prep' === $product_type );
$access_counts = isset( $access_counts ) ? $access_counts : array();
?>
<div class="wrap cta-admin-wrap">
	<div class="cta-admin-header-row">
		<h1><?php echo $is_exam ? esc_html__( 'Exam Preparation Programs', 'cta-lms' ) : esc_html__( 'CE Courses', 'cta-lms' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-course-edit&product_type=' . ( $is_exam ? 'exam_prep' : 'ce' ) ) ); ?>">
			<?php echo $is_exam ? esc_html__( 'Add Exam Prep Program', 'cta-lms' ) : esc_html__( 'Add New Course', 'cta-lms' ); ?>
		</a>
		<?php if ( ! $is_exam ) : ?>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_sync_syllabus' ), 'cta_sync_syllabus' ) ); ?>">
				<?php esc_html_e( 'Restore Prices + Sync Syllabus', 'cta-lms' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( ! $is_exam ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'CAMFT CEPA:', 'cta-lms' ); ?></strong>
				<?php esc_html_e( 'CE courses must remain Draft/Unpublished until CTA receives CAMFT CEPA provider approval. Publishing a CE course requires an explicit confirmation.', 'cta-lms' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( in_array( $notice, array( 'course_deleted', 'status_updated' ), true ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Course updated.', 'cta-lms' ); ?></p></div>
	<?php elseif ( 'syllabus_synced' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				$created = absint( wp_unslash( $_GET['created'] ?? 0 ) );
				$updated = absint( wp_unslash( $_GET['updated'] ?? 0 ) );
				$mods_c  = absint( wp_unslash( $_GET['modules_created'] ?? 0 ) );
				$mods_u  = absint( wp_unslash( $_GET['modules_updated'] ?? 0 ) );
				$exam_u  = absint( wp_unslash( $_GET['exam_updated'] ?? 0 ) );
				$miss_m  = absint( wp_unslash( $_GET['missing_modules'] ?? 0 ) );
				$miss_q  = absint( wp_unslash( $_GET['missing_quiz'] ?? 0 ) );
				printf(
					/* translators: 1: courses created, 2: courses updated, 3: modules created, 4: modules updated, 5: exam prep updated */
					esc_html__( 'Catalog restore complete. CE created: %1$d. CE updated: %2$d. Modules created: %3$d. Modules updated: %4$d. Exam Prep updated: %5$d. Prices/categories restored from the client catalog. Enrollments and certificates were preserved.', 'cta-lms' ),
					$created,
					$updated,
					$mods_c,
					$mods_u,
					$exam_u
				);
				if ( $miss_m > 0 || $miss_q > 0 ) {
					echo ' ';
					printf(
						/* translators: 1: courses missing modules, 2: courses missing quiz */
						esc_html__( 'Needs content: %1$d course(s) missing modules, %2$d course(s) missing quiz.', 'cta-lms' ),
						$miss_m,
						$miss_q
					);
				}
				?>
			</p>
		</div>
	<?php elseif ( 'syllabus_sync_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Syllabus sync could not run. Confirm CTA syllabus files are installed.', 'cta-lms' ); ?></p></div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper cta-product-type-tabs">
		<a class="nav-tab <?php echo 'ce' === $product_type ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-courses&product_type=ce' ) ); ?>"><?php esc_html_e( 'CE Courses', 'cta-lms' ); ?></a>
		<a class="nav-tab <?php echo 'exam_prep' === $product_type ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-courses&product_type=exam_prep' ) ); ?>"><?php esc_html_e( 'Exam Preparation', 'cta-lms' ); ?></a>
		<a class="nav-tab <?php echo 'all' === $product_type ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-courses&product_type=all' ) ); ?>"><?php esc_html_e( 'All', 'cta-lms' ); ?></a>
	</h2>

	<form method="get" class="cta-admin-filters">
		<input type="hidden" name="page" value="cta-lms-courses">
		<input type="hidden" name="product_type" value="<?php echo esc_attr( $product_type ); ?>">
		<select name="status">
			<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'cta-lms' ); ?></option>
			<option value="published" <?php selected( $status_filter, 'published' ); ?>><?php esc_html_e( 'Published', 'cta-lms' ); ?></option>
			<option value="draft" <?php selected( $status_filter, 'draft' ); ?>><?php esc_html_e( 'Draft', 'cta-lms' ); ?></option>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by title', 'cta-lms' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cta-lms' ); ?></button>
	</form>

	<table class="widefat striped cta-admin-table">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Title', 'cta-lms' ); ?></th>
				<?php if ( 'exam_prep' !== $product_type ) : ?>
					<th><?php esc_html_e( 'CE Hours', 'cta-lms' ); ?></th>
				<?php else : ?>
					<th><?php esc_html_e( 'Access (months)', 'cta-lms' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Price', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Category', 'cta-lms' ); ?></th>
				<?php if ( 'all' === $product_type ) : ?>
					<th><?php esc_html_e( 'Type', 'cta-lms' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Status', 'cta-lms' ); ?></th>
				<th><?php echo $is_exam ? esc_html__( 'Purchases', 'cta-lms' ) : esc_html__( 'Enrollments', 'cta-lms' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cta-lms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $courses ) ) : ?>
				<tr><td colspan="9"><?php echo $is_exam ? esc_html__( 'No exam preparation programs found.', 'cta-lms' ) : esc_html__( 'No courses found.', 'cta-lms' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $courses as $course ) : ?>
					<?php
					$row_is_exam = CTA_Exam_Access::is_exam_prep( $course );
					$count       = $row_is_exam
						? (int) ( $access_counts[ (int) $course->id ] ?? $enrollment_counts[ (int) $course->id ] ?? 0 )
						: (int) ( $enrollment_counts[ (int) $course->id ] ?? 0 );
					?>
					<tr>
						<td><?php echo esc_html( (string) $course->id ); ?></td>
						<td><strong><?php echo esc_html( $course->title ); ?></strong></td>
						<?php if ( 'exam_prep' !== $product_type && ! $row_is_exam ) : ?>
							<td><?php echo esc_html( rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' ) ); ?></td>
						<?php else : ?>
							<td><?php echo esc_html( (string) (int) ( $course->access_period_months ?? 6 ) ); ?></td>
						<?php endif; ?>
						<td>$<?php echo esc_html( number_format( (float) $course->price, 2 ) ); ?></td>
						<td><?php echo esc_html( $course->category ? $course->category : '—' ); ?></td>
						<?php if ( 'all' === $product_type ) : ?>
							<td><?php echo $row_is_exam ? esc_html__( 'Exam Prep', 'cta-lms' ) : esc_html__( 'CE', 'cta-lms' ); ?></td>
						<?php endif; ?>
						<td><span class="cta-status-badge cta-status-badge--<?php echo esc_attr( $course->status ); ?>"><?php echo esc_html( ucfirst( $course->status ) ); ?></span></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
						<td class="cta-table-actions">
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=cta-lms-course-edit&course_id=' . (int) $course->id ) ); ?>"><?php esc_html_e( 'Edit', 'cta-lms' ); ?></a>
							<?php
							$toggle_url   = wp_nonce_url( admin_url( 'admin-post.php?action=cta_toggle_course&course_id=' . (int) $course->id ), 'cta_toggle_course' );
							$is_published = ( 'published' === $course->status );
							?>
							<a
								class="button button-small<?php echo ( ! $row_is_exam && ! $is_published ) ? ' cta-ce-publish-btn' : ''; ?>"
								href="<?php echo esc_url( $toggle_url ); ?>"
								<?php if ( ! $row_is_exam && ! $is_published ) : ?>
									data-cta-ce-publish="1"
								<?php endif; ?>
							><?php echo $is_published ? esc_html__( 'Unpublish', 'cta-lms' ) : esc_html__( 'Publish', 'cta-lms' ); ?></a>
							<a class="button button-small button-link-delete cta-delete-course" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cta_delete_course&course_id=' . (int) $course->id ), 'cta_delete_course' ) ); ?>"><?php esc_html_e( 'Delete', 'cta-lms' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<script>
(function () {
	document.querySelectorAll('a[data-cta-ce-publish="1"]').forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			var ok = window.confirm(
				'CAMFT CEPA compliance warning:\n\n' +
				'This CE course will become publicly visible and purchasable.\n' +
				'Do NOT publish until CTA has CAMFT CEPA provider approval.\n\n' +
				'Publish this CE course anyway?'
			);
			if (!ok) {
				return;
			}
			var url = link.href;
			url += (url.indexOf('?') >= 0 ? '&' : '?') + 'cta_confirm_ce_publish=1';
			window.location.href = url;
		});
	});
})();
</script>
