<?php
/**
 * Exam Prep Course Home — section navigation (Workbooks, future sections).
 *
 * @package CTA_LMS
 *
 * @var object $course      Course row.
 * @var string $active      Active section key: home|workbooks.
 * @var string $home_url    Course home URL.
 * @var string $player_base Player base URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workbooks_url = class_exists( 'CTA_Exam_Prep_Workbooks' )
	? CTA_Exam_Prep_Workbooks::get_workbooks_list_url( (int) $course->id, $player_base )
	: add_query_arg( array( 'course_id' => (int) $course->id, 'view' => 'workbooks' ), $player_base );

$sections = array(
	'home' => array(
		'label' => __( 'Course Home', 'cta-lms' ),
		'url'   => $home_url,
	),
	'workbooks' => array(
		'label' => __( 'Workbooks', 'cta-lms' ),
		'url'   => $workbooks_url,
	),
);
?>
<nav class="cta-ep-dashboard-nav" aria-label="<?php esc_attr_e( 'Course dashboard sections', 'cta-lms' ); ?>">
	<ul class="cta-ep-dashboard-nav__list">
		<?php foreach ( $sections as $key => $section ) : ?>
			<li class="cta-ep-dashboard-nav__item">
				<a
					class="cta-ep-dashboard-nav__link<?php echo $active === $key ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( (string) $section['url'] ); ?>"
					<?php echo $active === $key ? 'aria-current="page"' : ''; ?>
				>
					<?php echo esc_html( (string) $section['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
