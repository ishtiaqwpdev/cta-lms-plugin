<?php
/**
 * Exam Prep Course Home — section navigation (horizontal tabs).
 *
 * @package CTA_LMS
 *
 * @var object $course      Course row.
 * @var string $active      Active section key.
 * @var string $home_url    Course home URL.
 * @var string $player_base Player base URL.
 * @var array  $sidebar_nav Optional sidebar nav tree (sections synced from here).
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

if ( ! empty( $sidebar_nav['sections'] ) && is_array( $sidebar_nav['sections'] ) ) {
	$sections = array();
	foreach ( (array) $sidebar_nav['sections'] as $nav_section ) {
		$key = (string) ( $nav_section['key'] ?? '' );
		if ( '' === $key ) {
			continue;
		}
		$sections[ $key ] = array(
			'label' => (string) ( $nav_section['label'] ?? '' ),
			'url'   => (string) ( $nav_section['url'] ?? '' ),
		);
	}
}

$resolved_active = $active;
if ( ! empty( $sidebar_nav['active_section'] ) ) {
	$resolved_active = (string) $sidebar_nav['active_section'];
}
?>
<nav class="cta-ep-dashboard-nav" aria-label="<?php esc_attr_e( 'Course dashboard sections', 'cta-lms' ); ?>">
	<ul class="cta-ep-dashboard-nav__list">
		<?php foreach ( $sections as $key => $section ) : ?>
			<li class="cta-ep-dashboard-nav__item">
				<a
					class="cta-ep-dashboard-nav__link<?php echo $resolved_active === $key ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( (string) $section['url'] ); ?>"
					<?php echo $resolved_active === $key ? 'aria-current="page"' : ''; ?>
				>
					<?php echo esc_html( (string) $section['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
