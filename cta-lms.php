<?php
/**
 * CTA LMS bootstrap (loaded by Cta-plugin.php).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-bootstrap (two plugin folders) from fatalling the site.
if ( defined( 'CTA_LMS_BOOTSTRAPPED' ) ) {
	return;
}
define( 'CTA_LMS_BOOTSTRAPPED', true );

if ( ! defined( 'CTA_PLUGIN_FILE' ) ) {
	define( 'CTA_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CTA_VERSION' ) ) {
	define( 'CTA_VERSION', '1.0.277' );
}

if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', plugin_dir_path( CTA_PLUGIN_FILE ) );
}

if ( ! defined( 'CTA_PLUGIN_URL' ) ) {
	define( 'CTA_PLUGIN_URL', plugin_dir_url( CTA_PLUGIN_FILE ) );
}

if ( ! defined( 'CTA_PLUGIN_BASENAME' ) ) {
	define( 'CTA_PLUGIN_BASENAME', plugin_basename( CTA_PLUGIN_FILE ) );
}

if ( ! function_exists( 'cta_lms_require' ) ) {
	/**
	 * Load a plugin file if it exists.
	 *
	 * @param string $relative_path Path relative to plugin root.
	 * @return bool
	 */
	function cta_lms_require( $relative_path ) {
		$path = CTA_PLUGIN_DIR . ltrim( $relative_path, '/' );

		if ( ! file_exists( $path ) ) {
			if ( is_admin() ) {
				add_action(
					'admin_notices',
					static function () use ( $relative_path ) {
						echo '<div class="notice notice-error"><p>';
						printf(
							/* translators: %s: missing file path */
							esc_html__( 'CTA LMS is missing a required file: %s', 'cta-lms' ),
							esc_html( $relative_path )
						);
						echo '</p></div>';
					}
				);
			}
			return false;
		}

		require_once $path;
		return true;
	}
}

// Load Composer autoloader (Stripe SDK) when present.
if ( file_exists( CTA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once CTA_PLUGIN_DIR . 'vendor/autoload.php';
}

$cta_required_files = array(
	'includes/cta-timezone.php',
	'includes/cta-encoding.php',
	'includes/class-cta-activator.php',
	'includes/class-cta-deactivator.php',
	'includes/class-cta-roles.php',
	'includes/class-cta-associate-access.php',
	'includes/class-cta-exam-access.php',
	'includes/class-cta-ce-access.php',
	'includes/class-cta-ce-completion.php',
	'includes/class-cta-course-materials.php',
	'includes/class-cta-flashcards.php',
	'includes/class-cta-exam-prep-flashcard-center.php',
	'includes/class-cta-exam-prep-exam-center.php',
	'includes/class-cta-exam-prep-progress-readiness.php',
	'includes/class-cta-exam-prep-downloads.php',
	'includes/class-cta-exam-prep-audio-review.php',
	'includes/class-cta-exam-prep-lessons.php',
	'includes/class-cta-exam-prep-getting-started.php',
	'includes/class-cta-exam-prep-workbooks.php',
	'includes/class-cta-exam-prep-workbook-sections.php',
	'includes/class-cta-exam-prep-sidebar-nav.php',
	'includes/class-cta-evaluation-questions.php',
	'includes/class-cta-course-attestation.php',
	'includes/class-cta-telehealth-exam-sync.php',
	'includes/class-cta-lcsw-aswb-sync.php',
	'includes/class-cta-lcsw-aswb-form-quality.php',
	'includes/class-cta-lmft-clinical-sync.php',
	'includes/class-cta-lmft-clinical-legacy-forms-archive.php',
	'includes/class-cta-lmft-clinical-legacy-flashcard-archive.php',
	'includes/class-cta-lmft-clinical-form-gates.php',
	'includes/class-cta-lmft-clinical-form-a-sync.php',
	'includes/class-cta-lmft-clinical-comprehensive-scoring.php',
	'includes/class-cta-lmft-clinical-comprehensive-review.php',
	'includes/class-cta-lmft-clinical-form-a-answer-sync.php',
	'includes/class-cta-lmft-clinical-form-b-sync.php',
	'includes/class-cta-lmft-clinical-form-b-answer-sync.php',
	'includes/class-cta-lmft-amftrb-sync.php',
	'includes/class-cta-lmft-amftrb-legacy-flashcard-archive.php',
	'includes/class-cta-lpcc-ncmhce-sync.php',
	'includes/class-cta-lpcc-ncmhce-legacy-forms-archive.php',
	'includes/class-cta-lpcc-ncmhce-legacy-flashcard-archive.php',
	'includes/class-cta-lcsw-aswb-legacy-flashcard-archive.php',
	'includes/class-cta-lpcc-ncmhce-form-a-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-b-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-a-v2-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-a-v2-scoring.php',
	'includes/class-cta-lpcc-ncmhce-form-a-v2-answer-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-b-v2-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-b-v2-scoring.php',
	'includes/class-cta-lpcc-ncmhce-form-b-v2-answer-sync.php',
	'includes/class-cta-lpcc-ncmhce-form-v2-scoring-bridge.php',
	'includes/class-cta-lpcc-ncmhce-simulation.php',
	'includes/class-cta-lpcc-law-ethics-sync.php',
	'includes/class-cta-lcsw-law-ethics-sync.php',
	'includes/class-cta-lmft-law-ethics-copy.php',
	'includes/class-cta-lmft-law-ethics-sync.php',
	'includes/class-cta-law-ethics-module-sync.php',
	'includes/class-cta-law-ethics-evaluation-sync.php',
	'includes/class-cta-law-ethics-exam-sync.php',
	'includes/class-cta-suicide-risk-module-sync.php',
	'includes/class-cta-suicide-risk-toolkit-sync.php',
	'includes/class-cta-suicide-risk-exam-sync.php',
	'includes/class-cta-suicide-risk-evaluation-sync.php',
	'includes/class-cta-suicide-risk-certificate-sync.php',
	'includes/class-cta-database.php',
	'includes/class-cta-syllabus-sync.php',
	'includes/class-cta-course-catalog.php',
	'includes/class-cta-bundle-catalog.php',
	'includes/class-cta-supervision-plans.php',
	'includes/class-cta-emails.php',
	'includes/class-cta-pages.php',
	'includes/class-cta-academy-positioning.php',
	'includes/class-cta-course-routes.php',
	'includes/class-cta-loader.php',
	'includes/class-cta-stripe.php',
	'public/class-cta-shortcodes.php',
	'public/class-cta-auth.php',
	'public/class-cta-courses.php',
	'public/class-cta-memberships.php',
	'public/class-cta-supervision.php',
	'public/class-cta-student-dashboard.php',
	'public/class-cta-supervision-dashboard.php',
	'public/class-cta-certificates.php',
	'public/class-cta-quiz.php',
	'admin/class-cta-admin.php',
);

foreach ( $cta_required_files as $cta_file ) {
	if ( ! cta_lms_require( $cta_file ) ) {
		return;
	}
}

if ( ! function_exists( 'cta_get_stripe' ) ) {
	/**
	 * Get shared Stripe handler instance.
	 *
	 * @return CTA_Stripe
	 */
	function cta_get_stripe() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new CTA_Stripe();
		}

		return $instance;
	}
}

if ( ! function_exists( 'cta_lms_init' ) ) {
	/**
	 * Initialize plugin components.
	 */
	function cta_lms_init() {
		if ( ! class_exists( 'CTA_Loader' ) ) {
			return;
		}

		// Boot admin menus first and outside the public-component try block so a
		// Stripe/frontend failure never leaves every CTA admin page inaccessible.
		if ( is_admin() && class_exists( 'CTA_Admin' ) ) {
			new CTA_Admin();
		}

		try {
			$loader = new CTA_Loader();
			$loader->run();

			if ( class_exists( 'CTA_Course_Materials' ) ) {
				CTA_Course_Materials::ensure_package_tree_deny_rules();
			}

			if ( class_exists( 'CTA_Pages' ) ) {
				CTA_Pages::init();
			}

			if ( class_exists( 'CTA_Academy_Positioning' ) ) {
				CTA_Academy_Positioning::init();
			}

			if ( class_exists( 'CTA_Course_Routes' ) ) {
				CTA_Course_Routes::init();
			}

			add_filter(
				'cta_lms_reveal_quiz_explanations',
				static function ( $reveal, $quiz, $course ) {
					if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' )
						&& CTA_Lmft_Clinical_Form_A_Answer_Sync::should_suppress_learner_answer_reveal( $quiz, $course ) ) {
						return false;
					}
					if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' )
						&& CTA_Lmft_Clinical_Form_B_Answer_Sync::should_suppress_learner_answer_reveal( $quiz, $course ) ) {
						return false;
					}
					if ( $reveal || ! $quiz || ! $course ) {
						return $reveal;
					}
					if ( class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
						return CTA_Suicide_Risk_Exam_Sync::course_should_reveal_teaching_points( $course, $quiz );
					}
					return $reveal;
				},
				10,
				3
			);

			add_filter(
				'cta_lms_reveal_quiz_correct_answers',
				static function ( $reveal, $quiz, $course ) {
					if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' )
						&& CTA_Lmft_Clinical_Form_A_Answer_Sync::should_suppress_learner_answer_reveal( $quiz, $course ) ) {
						return false;
					}
					if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' )
						&& CTA_Lmft_Clinical_Form_B_Answer_Sync::should_suppress_learner_answer_reveal( $quiz, $course ) ) {
						return false;
					}
					return $reveal;
				},
				10,
				3
			);

			new CTA_Shortcodes();
			new CTA_Auth();
			new CTA_Courses();
			cta_get_stripe();
			new CTA_Memberships();
			new CTA_Supervision();
			new CTA_Student_Dashboard();
			new CTA_Supervision_Dashboard();
			new CTA_Quiz();
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA LMS init error: ' . $e->getMessage() );
			}
			if ( is_admin() ) {
				add_action(
					'admin_notices',
					static function () use ( $e ) {
						echo '<div class="notice notice-error"><p>';
						echo esc_html__( 'CTA LMS failed to initialize. Check the debug log or reinstall the plugin.', 'cta-lms' );
						echo '</p></div>';
					}
				);
			}
		}
	}
}

if ( function_exists( 'cta_lms_init' ) && ! has_action( 'plugins_loaded', 'cta_lms_init' ) ) {
	add_action( 'plugins_loaded', 'cta_lms_init' );
}

// Keep CTA + WP timezone healed to Pacific even when upgrade hooks already ran.
if ( function_exists( 'cta_lms_ensure_pacific_timezone' ) && ! has_action( 'plugins_loaded', 'cta_lms_ensure_pacific_timezone' ) ) {
	add_action( 'plugins_loaded', 'cta_lms_ensure_pacific_timezone', 4 );
}

if ( ! function_exists( 'cta_maybe_upgrade_db' ) ) {
	/**
	 * Run database upgrades when plugin version changes.
	 */
	function cta_maybe_upgrade_db() {
		$installed = get_option( 'cta_lms_version', '0' );

		if ( version_compare( (string) $installed, CTA_VERSION, '>=' ) ) {
			return;
		}

		// Prevent overlapping upgrade work in the same request.
		if ( get_transient( 'cta_lms_upgrading' ) ) {
			return;
		}
		set_transient( 'cta_lms_upgrading', 1, 120 );

		try {
			if ( class_exists( 'CTA_Database' ) ) {
				CTA_Database::create_tables();
			}

			// Quizzes are untimed with unlimited retakes by product policy.
			if ( version_compare( $installed, '1.0.39', '<' ) && class_exists( 'CTA_Database' ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'cta_quizzes';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( "UPDATE {$table} SET time_limit_mins = 0, max_attempts = 0" );
			}

			// Re-normalize any leftover legacy quiz caps (e.g. max_attempts=3) on upgrade to 1.0.50+.
			if ( version_compare( $installed, '1.0.50', '<' ) && class_exists( 'CTA_Database' ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'cta_quizzes';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( "UPDATE {$table} SET time_limit_mins = 0, max_attempts = 0, passing_score = 70" );
			}

			// Align supervision plan names/prices (Group $260, All-Access $350).
			// Re-run through 1.0.53 so sites already past 1.0.40 still heal mismatched meta.
			if ( version_compare( $installed, '1.0.53', '<' ) && class_exists( 'CTA_Supervision_Plans' ) ) {
				CTA_Supervision_Plans::sync_all_access_bundle();
				CTA_Supervision_Plans::migrate_legacy_names();
			}

			// Demote Approved associates who have no purchase and no agency-assigned plan.
			if ( version_compare( $installed, '1.0.41', '<' ) && class_exists( 'CTA_Associate_Access' ) ) {
				$query = new WP_User_Query(
					array(
						'role'       => 'cta_associate',
						'number'     => 500,
						'meta_key'   => 'cta_approval_status',
						'meta_value' => CTA_Associate_Access::STATUS_APPROVED,
						'fields'     => 'ID',
					)
				);

				foreach ( (array) $query->get_results() as $user_id ) {
					$user_id = absint( $user_id );
					if ( ! $user_id || CTA_Associate_Access::has_qualifying_plan( $user_id ) ) {
						continue;
					}

					update_user_meta( $user_id, 'cta_approval_status', CTA_Associate_Access::STATUS_PENDING );
					$supervision = (string) get_user_meta( $user_id, 'cta_supervision_status', true );
					if ( 'active' === $supervision ) {
						update_user_meta( $user_id, 'cta_supervision_status', CTA_Associate_Access::STATUS_PENDING );
					}
				}
			}

			// Ensure display timezone option exists (default Pacific Time).
			if ( version_compare( $installed, '1.0.42', '<' ) ) {
				add_option( 'cta_timezone', 'America/Los_Angeles' );
			}

			// Re-assert Pacific default when option is empty (never change intentional custom zones).
			if ( version_compare( $installed, '1.0.56', '<' ) ) {
				$tz = (string) get_option( 'cta_timezone', '' );
				if ( '' === $tz ) {
					update_option( 'cta_timezone', 'America/Los_Angeles' );
				}
			}

			// Public marketing pages + nav cleanup (CE catalog, supervision booking, hide quiz).
			if ( version_compare( $installed, '1.0.45', '<' ) && class_exists( 'CTA_Pages' ) ) {
				CTA_Pages::sync_public_pages();
				delete_option( 'cta_pages_synced_' . CTA_VERSION );
			}

			// Exam Preparation Programs: schema columns, access/resources tables, seed programs.
			if ( version_compare( $installed, '1.0.47', '<' ) && class_exists( 'CTA_Database' ) ) {
				CTA_Database::maybe_add_exam_prep_columns();
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
			}

			// Course materials: module attachment + protected file path columns.
			if ( version_compare( $installed, '1.0.48', '<' ) && class_exists( 'CTA_Database' ) ) {
				CTA_Database::maybe_add_resource_columns();
				if ( class_exists( 'CTA_Course_Materials' ) ) {
					CTA_Course_Materials::get_protected_root();
				}
			}

			// Admin-configurable CE evaluation question bank.
			if ( version_compare( $installed, '1.0.51', '<' ) && class_exists( 'CTA_Evaluation_Questions' ) ) {
				CTA_Evaluation_Questions::install();
			}

			// Force UTF-8 + repair any mojibake already stored in options/tables/meta.
			// Re-run through 1.0.59: 1.0.57 used wrong evaluation columns and could fatal
			// on hosts that throw mysqli exceptions for unknown columns.
			if ( version_compare( $installed, '1.0.59', '<' ) ) {
				if ( function_exists( 'cta_lms_ensure_utf8_environment' ) ) {
					cta_lms_ensure_utf8_environment();
				}
				if ( function_exists( 'cta_lms_repair_stored_utf8_content' ) ) {
					cta_lms_repair_stored_utf8_content();
				}
			}

			// Repair header/footer page links (FAQ, Policies, Supervision booking, About, Contact).
			if ( version_compare( $installed, '1.0.70', '<' ) && class_exists( 'CTA_Pages' ) ) {
				CTA_Pages::sync_public_pages();
				delete_option( 'cta_pages_synced_' . CTA_VERSION );
			}

			// Put public CTA pages (incl. Login) back into the Elementor/theme header menu.
			if ( version_compare( $installed, '1.0.72', '<' ) && class_exists( 'CTA_Pages' ) ) {
				CTA_Pages::sync_public_pages();
				CTA_Pages::sync_primary_nav_menu();
				delete_option( 'cta_pages_synced_' . CTA_VERSION );
			}

			// Add Alcoholism CE category + link existing Alcoholism course to it.
			if ( version_compare( $installed, '1.0.74', '<' ) ) {
				cta_lms_migrate_alcoholism_category();
			}

			// Sync client syllabus content into existing CE courses/modules (backup + upsert).
			if ( version_compare( $installed, '1.0.75', '<' ) && class_exists( 'CTA_Syllabus_Sync' ) ) {
				CTA_Database::maybe_add_syllabus_columns();
				CTA_Syllabus_Sync::sync_all( true );
			}

			// Per-course evaluation questions + submission columns.
			if ( version_compare( $installed, '1.0.76', '<' ) ) {
				if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
					CTA_Evaluation_Questions::install();
				}
				if ( class_exists( 'CTA_Course_Attestation' ) ) {
					CTA_Course_Attestation::install();
				}
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_evaluation_submission_columns();
				}
			}

			// Safe syllabus seed/upsert for all 7 CE courses (create missing, update existing).
			if ( version_compare( $installed, '1.0.77', '<' ) && class_exists( 'CTA_Syllabus_Sync' ) ) {
				CTA_Database::maybe_add_syllabus_columns();
				delete_option( 'cta_syllabus_synced_1_0_75' );
				CTA_Syllabus_Sync::sync_all( true );
			}

			// Restore exact client CE/Exam Prep prices + categories; never wipe tables.
			if ( version_compare( $installed, '1.0.78', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				CTA_Course_Catalog::restore_all();
			}

			// Official revised syllabi: Law & Ethics modules/LOs, full CAMFT eval set, attestation fix deploy.
			if ( version_compare( $installed, '1.0.79', '<' ) ) {
				if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
					CTA_Evaluation_Questions::install();
				}
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					delete_option( 'cta_syllabus_synced_1_0_75' );
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_all();
				}
			}

			// Ensure Law & Ethics Module 1 (Regulatory Frameworks) is created when missing.
			if ( version_compare( $installed, '1.0.84', '<' ) && class_exists( 'CTA_Syllabus_Sync' ) ) {
				CTA_Database::maybe_add_syllabus_columns();
				delete_option( 'cta_syllabus_synced_1_0_75' );
				CTA_Syllabus_Sync::sync_all( true );
			}

			// Push complete standard CAMFT evaluation questions onto every CE course
			// (fills gaps on courses that already had a partial older question set).
			if ( version_compare( $installed, '1.0.85', '<' ) && class_exists( 'CTA_Evaluation_Questions' ) ) {
				CTA_Evaluation_Questions::sync_camft_to_all_ce_courses();
			}

			// Force-sync every CE + Exam Prep price to the approved catalog (before/after logged).
			if ( version_compare( $installed, '1.0.86', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::sync_approved_prices();
			}

			// Re-sync CE prices to the Jul 2026 approved catalog ($79 / $45 / $149 / $169, etc.).
			if ( version_compare( $installed, '1.0.95', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::sync_approved_prices();
			}

			// Re-run price sync if 1.0.95 code never landed on the server (Hostinger deploy lag).
			if ( version_compare( $installed, '1.0.96', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::sync_approved_prices();
				update_option( 'cta_ce_price_catalog_fp', cta_ce_price_catalog_fingerprint(), false );
			}

			// Fix Alcoholism + Suicide Risk Assessment category mismatches from catalog.
			if ( version_compare( $installed, '1.0.100', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::restore_ce_pricing();
				update_option( 'cta_ce_price_catalog_fp', cta_ce_price_catalog_fingerprint(), false );
			}

			// Enable multiple Exam Prep assessments (Practice / Form A / Form B).
			if ( version_compare( $installed, '1.0.102', '<' ) && class_exists( 'CTA_Database' ) ) {
				CTA_Database::maybe_add_multi_quiz_support();
			}

			// Refresh CE evaluation to Participant + Sections A–E (Agree/Disagree + N/A).
			if ( version_compare( $installed, '1.0.104', '<' ) ) {
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
					CTA_Evaluation_Questions::sync_camft_to_all_ce_courses();
				}
			}

			// Mandatory completion attestation: signature_date column + updated statement.
			if ( version_compare( $installed, '1.0.105', '<' ) && class_exists( 'CTA_Course_Attestation' ) ) {
				CTA_Course_Attestation::install();
			}

			// Telehealth website/catalog copy + SEO metadata (does not change access period or thumbnail).
			if ( version_compare( $installed, '1.0.106', '<' ) && class_exists( 'CTA_Syllabus_Sync' ) ) {
				CTA_Database::maybe_add_syllabus_columns();
				CTA_Syllabus_Sync::sync_all( true );
			}

			// Attach bundled Telehealth Clinical Resource Toolkit for enrolled learners.
			if ( version_compare( $installed, '1.0.107', '<' ) && class_exists( 'CTA_Course_Materials' ) ) {
				CTA_Course_Materials::ensure_bundled_resources();
			}

			// Telehealth (CTA-CE-002) official 25-question final exam + evaluation refresh.
			if ( version_compare( $installed, '1.0.108', '<' ) && class_exists( 'CTA_Telehealth_Exam_Sync' ) ) {
				CTA_Telehealth_Exam_Sync::sync( true );
			}

			// Telehealth (CTA-CE-002) module Vimeo videos (Legal Foundations / Intake / Security).
			if ( version_compare( $installed, '1.0.110', '<' ) && class_exists( 'CTA_Telehealth_Exam_Sync' ) ) {
				CTA_Telehealth_Exam_Sync::sync_module_videos( true );
			}

			// Telehealth (CTA-CE-002) approved branded thumbnail (Telehealth.png).
			if ( version_compare( $installed, '1.0.111', '<' ) && class_exists( 'CTA_Telehealth_Exam_Sync' ) ) {
				CTA_Telehealth_Exam_Sync::sync_thumbnail( true );
			}

			// CE access rules: purchase permanent vs membership-gated + certificate permanence.
			if ( version_compare( $installed, '1.0.112', '<' ) && class_exists( 'CTA_CE_Access' ) ) {
				CTA_CE_Access::maybe_install_schema();
			}

			// CAMFT CEPA compliance: keep all CE courses unpublished until explicit authorization.
			if ( version_compare( $installed, '1.0.113', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
			}

			// Re-apply Telehealth module videos (per-module IDs; avoid course-preview fallback).
			if ( version_compare( $installed, '1.0.114', '<' ) && class_exists( 'CTA_Telehealth_Exam_Sync' ) ) {
				CTA_Telehealth_Exam_Sync::sync_module_videos( true );
			}

			// CTA-CE-001 Law & Ethics: course code, instructional format, Final Syllabus v2.1 bundle.
			// Remap modules by legacy title BEFORE syllabus sync so new titles do not create duplicates.
			// Keeps CE courses in Draft (pending CAMFT CEPA) — never publishes.
			if ( version_compare( $installed, '1.0.120', '<' ) ) {
				if ( class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
					CTA_Law_Ethics_Module_Sync::sync_modules( true );
				}
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Course_Materials' ) ) {
					CTA_Course_Materials::ensure_bundled_resources();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// CTA-CE-001 Law & Ethics: 6 modules + Capstone with official Vimeo videos (order remap).
			if ( version_compare( $installed, '1.0.121', '<' ) ) {
				if ( class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
					CTA_Law_Ethics_Module_Sync::sync_modules( true );
				}
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// CTA-CE-001 Law & Ethics: CAMFT 9-section evaluation + in-form attestation.
			if ( version_compare( $installed, '1.0.122', '<' ) ) {
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Law_Ethics_Evaluation_Sync' ) ) {
					CTA_Law_Ethics_Evaluation_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// CTA-CE-001 Law & Ethics: approved course image on catalog / detail / dashboard.
			if ( version_compare( $installed, '1.0.124', '<' ) && class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
				CTA_Law_Ethics_Module_Sync::sync_thumbnail( true );
			}

			// CTA-CE-001 Law & Ethics: Final Syllabus v2.1 module titles + runtimes (~360 min).
			// Remap by legacy/short titles BEFORE syllabus sync so new titles do not create duplicates.
			if ( version_compare( $installed, '1.0.125', '<' ) ) {
				if ( class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
					CTA_Law_Ethics_Module_Sync::sync_modules( true );
				}
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// CTA-CE-001: Practice Protection Toolkit v1.0 as course-level downloadable resource.
			if ( version_compare( $installed, '1.0.126', '<' ) && class_exists( 'CTA_Course_Materials' ) ) {
				CTA_Course_Materials::ensure_bundled_resources();
			}

			// CTA LCSW ASWB Clinical Exam Prep: program, 12 workbooks, Form A/B + rationale gating.
			if ( version_compare( $installed, '1.0.127', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::sync( true );
				}
			}

			// CTA LMFT California Clinical Exam Prep: content sync; commercial terms stay draft/pending.
			if ( version_compare( $installed, '1.0.128', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
					CTA_Lmft_Clinical_Sync::sync( true );
				}
			}

			// CTA LPCC NCMHCE Exam Prep: full student package, checkpoints, Form A/B + gated rationales.
			if ( version_compare( $installed, '1.0.129', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// CTA LPCC NCMHCE: enforce exact public listing (title, $249, 6 months, format, no CE).
			if ( version_compare( $installed, '1.0.130', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::ensure_program();
				}
			}

			// CTA LPCC NCMHCE: strip audio/video marketing references from public listing copy.
			if ( version_compare( $installed, '1.0.131', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::ensure_program();
				}
			}

			// CTA LPCC NCMHCE: gate all practice-bank / checkpoint / Form A–B rationales per-student after submit.
			if ( version_compare( $installed, '1.0.132', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// CTA LPCC NCMHCE: historical remediation meta/sync (Form B no longer gated on remediation).
			if ( version_compare( $installed, '1.0.133', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// Harden: deny HTTP access to _packages (90_Admin_Restricted and related trees).
			if ( version_compare( $installed, '1.0.134', '<' ) && class_exists( 'CTA_Course_Materials' ) ) {
				CTA_Course_Materials::ensure_package_tree_deny_rules();
			}

			// CTA LPCC NCMHCE: keep Draft until full student testing checklist is verified (unpublish if live).
			if ( version_compare( $installed, '1.0.135', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::ensure_program();
				}
			}

			// Bulletproof quiz Start: drop ALL unique attempt indexes; allow unlimited retakes.
			if ( version_compare( $installed, '1.0.138', '<' ) && class_exists( 'CTA_Database' ) ) {
				delete_option( 'cta_quiz_attempt_schema_v138' );
				CTA_Database::ensure_tables();
				CTA_Database::maybe_ensure_quiz_attempt_schema();
			}

			// Exam Prep: lock Form A/B printable exams behind the same progress gates as online assessments.
			if ( version_compare( $installed, '1.0.139', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
					CTA_Lmft_Clinical_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// Rename Law & Ethics Exam Prep: formal + public titles, confirm $199 / 6 months.
			if ( version_compare( $installed, '1.0.140', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
			}

			// Add LCSW/LPCC Law & Ethics + LMFT AMFTRB Exam Prep shells (draft / unpublished).
			if ( version_compare( $installed, '1.0.141', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
			}

			// Keep ALL Exam Prep unpublished until learner testing + written CTA approval.
			if ( version_compare( $installed, '1.0.142', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// Exam Prep: public display titles for clinical packages; keep LCSW sync draft (hard gate).
			if ( version_compare( $installed, '1.0.143', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::ensure_program();
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
					CTA_Lmft_Clinical_Sync::ensure_program();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::ensure_program();
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					// Re-assert draft after sync ensure_program (never leave Exam Prep published).
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// CTA LMFT AMFTRB National + LPCC audio / Access Correction re-sync (draft / HOLD).
			if ( version_compare( $installed, '1.0.145', '<' ) ) {
				if ( class_exists( 'CTA_Database' ) ) {
					CTA_Database::maybe_add_resource_unlock_column();
				}
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// LMFT AMFTRB: authoritative audio placement map titles/runtimes + transcript title re-sync.
			if ( version_compare( $installed, '1.0.146', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
			}

			// LMFT AMFTRB: preserved-attempt gates for protected rationales (no open-when-missing-quiz bypass).
			if ( version_compare( $installed, '1.0.147', '<' ) ) {
				if ( class_exists( 'CTA_Course_Materials' ) ) {
					CTA_Course_Materials::ensure_package_tree_deny_rules();
				}
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
			}

			// AMFTRB (and all Exam Prep): re-assert checkout HOLD until written client release approval.
			if ( version_compare( $installed, '1.0.148', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// AMFTRB: sync approved offer controls (classification + exact combined audio runtime).
			if ( version_compare( $installed, '1.0.149', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
			}

			// LPCC: re-sync 8 audio tracks (exact titles/runtimes, open from enrollment).
			if ( version_compare( $installed, '1.0.150', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// LPCC: authoritative combined audio runtime (48 minutes 49 seconds).
			if ( version_compare( $installed, '1.0.151', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// LPCC: approve public description audio advertising after Prompt 11 test PASS.
			if ( version_compare( $installed, '1.0.153', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// Access Correction Notice: clear Exam Prep material unlock gates programwide.
			if ( version_compare( $installed, '1.0.154', '<' ) ) {
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::clear_all_exam_prep_material_unlock_gates();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
					CTA_Lmft_Clinical_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
					CTA_Lmft_Amftrb_Sync::sync( true );
				}
			}

			// LPCC: force re-attach 8 audio MP3s if missing on server after deploy.
			if ( version_compare( $installed, '1.0.155', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Sync::sync( true );
				}
			}

			// Exam Prep catalog shortcode + dedicated Exam Preparation page (nav left for manual add).
			if ( version_compare( $installed, '1.0.156', '<' ) && class_exists( 'CTA_Pages' ) ) {
				CTA_Pages::sync_public_pages();
			}

			// Master Pricing Catalog v3.5 — sync membership/bundle/pathway names + prices.
			if ( version_compare( $installed, '1.0.158', '<' ) && class_exists( 'CTA_Bundle_Catalog' ) ) {
				CTA_Bundle_Catalog::sync_all();
			}

			// Force re-apply v3.5 (fix partial sync / leftover Clinical Focus + Crisis $215 cards).
			if ( version_compare( $installed, '1.0.159', '<' ) && class_exists( 'CTA_Bundle_Catalog' ) ) {
				delete_option( 'cta_bundle_catalog_v35_fp' );
				CTA_Bundle_Catalog::maybe_sync( true );
			}

			// CE Course 8 Clinical Supervision: 15 CE / $169 (was 6 / $129). Re-sync full CE catalog.
			if ( version_compare( $installed, '1.0.160', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				delete_option( 'cta_ce_price_catalog_fp' );
				CTA_Course_Catalog::restore_ce_pricing();
				if ( function_exists( 'cta_ce_price_catalog_fingerprint' ) ) {
					update_option( 'cta_ce_price_catalog_fp', cta_ce_price_catalog_fingerprint(), false );
				}
			}

			// Force CE catalog restore again (live still showing Supervision 6/$129 until page-render heal).
			if ( version_compare( $installed, '1.0.161', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				delete_option( 'cta_ce_price_catalog_fp' );
				CTA_Course_Catalog::maybe_restore_ce_pricing( true );
			}

			// CE certificate provider line: CAMFT-Approved Continuing Education Provider | CEPA Provider #003369.
			if ( version_compare( $installed, '1.0.166', '<' ) ) {
				$camft = (string) get_option( 'cta_camft_provider_number', '' );
				$cepa  = (string) get_option( 'cta_cepa_provider_number', '' );
				if ( '' === trim( $camft ) || false !== stripos( $camft, 'CAMFT CEPA' ) ) {
					update_option( 'cta_camft_provider_number', '#003369', false );
				}
				if ( '' === trim( $cepa ) || false !== stripos( $cepa, 'CAMFT CEPA' ) ) {
					update_option( 'cta_cepa_provider_number', '#003369', false );
				}
			}

			// Certificates / dashboards: force Pacific Time (never PKT / Asia/Karachi).
			if ( version_compare( $installed, '1.0.168', '<' ) ) {
				if ( function_exists( 'cta_lms_ensure_pacific_timezone' ) ) {
					cta_lms_ensure_pacific_timezone();
				} else {
					update_option( 'cta_timezone', 'America/Los_Angeles', false );
				}
				if ( class_exists( 'CTA_Certificates' ) && method_exists( 'CTA_Certificates', 'refresh_all_certificates' ) ) {
					CTA_Certificates::refresh_all_certificates();
				}
			}

			// Re-heal Pacific + rewrite certificate HTML with hard-locked LA Issued stamps.
			// (1.0.168 may have been marked installed before live files were updated.)
			if ( version_compare( $installed, '1.0.169', '<' ) ) {
				if ( function_exists( 'cta_lms_ensure_pacific_timezone' ) ) {
					cta_lms_ensure_pacific_timezone();
				} else {
					update_option( 'cta_timezone', 'America/Los_Angeles', false );
					update_option( 'timezone_string', 'America/Los_Angeles', false );
				}
				if ( class_exists( 'CTA_Certificates' ) && method_exists( 'CTA_Certificates', 'refresh_all_certificates' ) ) {
					CTA_Certificates::refresh_all_certificates();
				}
			}

			// CTA-EP-003 LPCC California Law & Ethics Exam Prep — load full content; keep Draft.
			if ( version_compare( $installed, '1.0.172', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Law_Ethics_Sync' ) ) {
					CTA_Lpcc_Law_Ethics_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// Force re-sync CTA-EP-003 (1.0.172 may have stamped version before Hostinger finished deploy / sync timed out).
			if ( version_compare( $installed, '1.0.173', '<' ) ) {
				delete_option( 'cta_lpcc_law_ethics_seeded_1_0_172' );
				delete_option( 'cta_lpcc_law_ethics_seeded_1_0_173' );
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( class_exists( 'CTA_Lpcc_Law_Ethics_Sync' ) ) {
					if ( function_exists( 'set_time_limit' ) ) {
						@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
					CTA_Lpcc_Law_Ethics_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
			}

			// URGENT: CTA-EP-002 LCSW Law & Ethics was found Published with purchases while Stage 5E incomplete.
			// Force Draft + launch hold. Do NOT touch memberships/orders/purchases.
			if ( version_compare( $installed, '1.0.174', '<' ) ) {
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
				if ( function_exists( 'cta_force_draft_lcsw_law_ethics_ep' ) ) {
					cta_force_draft_lcsw_law_ethics_ep();
				}
			}

			// CTA-EP-002 LCSW California Law & Ethics — load Stage 5D content; keep Draft.
			if ( version_compare( $installed, '1.0.175', '<' ) ) {
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				if ( class_exists( 'CTA_Exam_Access' ) ) {
					CTA_Exam_Access::seed_default_programs();
				}
				if ( function_exists( 'cta_force_draft_lcsw_law_ethics_ep' ) ) {
					cta_force_draft_lcsw_law_ethics_ep();
				}
				if ( class_exists( 'CTA_Lcsw_Law_Ethics_Sync' ) ) {
					CTA_Lcsw_Law_Ethics_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch();
				}
				if ( function_exists( 'cta_force_draft_lcsw_law_ethics_ep' ) ) {
					cta_force_draft_lcsw_law_ethics_ep();
				}
			}

			// Unify Form A/B learner titles with downloadable Comprehensive Simulation naming.
			if ( version_compare( $installed, '1.0.181', '<' ) && function_exists( 'cta_lms_unify_form_ab_simulation_titles' ) ) {
				cta_lms_unify_form_ab_simulation_titles();
			}

			// CTA-CE-001 Law & Ethics: official 25-question final exam (staging only; CEPA hold).
			if ( version_compare( $installed, '1.0.185', '<' ) ) {
				if ( class_exists( 'CTA_Law_Ethics_Exam_Sync' ) ) {
					CTA_Law_Ethics_Exam_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// Re-run Law & Ethics exam seed if 1.0.185 upgrade ran before the course/quiz existed.
			if ( version_compare( $installed, '1.0.186', '<' ) && class_exists( 'CTA_Law_Ethics_Exam_Sync' ) ) {
				CTA_Law_Ethics_Exam_Sync::ensure();
			}

			// Publish all Exam Preparation programs (written CTA launch approval).
			if ( version_compare( $installed, '1.0.188', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::publish_all_exam_prep_programs();
			}

			// Re-assert publish on any Exam Prep rows still draft after deploy.
			if ( version_compare( $installed, '1.0.189', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::ensure_all_exam_prep_published();
			}

			// Simplify Exam Prep workflow: status-only gates, heal published meta.
			if ( version_compare( $installed, '1.0.190', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				CTA_Course_Catalog::heal_published_exam_prep_meta();
				CTA_Course_Catalog::restore_exam_prep_pricing();
			}

			// CAMFT CEPA approval: CE-only provider #122418 + official stamp.
			if ( version_compare( $installed, '1.0.204', '<' ) ) {
				update_option( 'cta_camft_provider_number', '#122418', false );
				update_option( 'cta_cepa_provider_number', '#122418', false );
				add_option( 'cta_certificate_provider_address', '' );
				if ( class_exists( 'CTA_Certificates' ) ) {
					CTA_Certificates::refresh_all_certificates();
				}
			}

			// LMFT California Law & Ethics Exam Prep dashboard scaffold (placeholder shell).
			if ( version_compare( $installed, '1.0.205', '<' ) && class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
				CTA_Lmft_Law_Ethics_Sync::sync( true );
			}

			// LCSW ASWB Clinical: correct product identity (title, meta, artwork) from legacy California Clinical label.
			if ( version_compare( $installed, '1.0.206', '<' ) ) {
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::heal_product_identity( true );
				}
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::restore_exam_prep_pricing();
				}
			}

			// Exam Prep: restore per-assessment gates on protected answer keys / rationales only.
			if ( version_compare( $installed, '1.0.207', '<' ) && class_exists( 'CTA_Course_Materials' ) ) {
				CTA_Course_Materials::restore_exam_prep_protected_rationale_gates();
			}

			// CE certificates: approved Riverside business mailing address on provider block.
			if ( version_compare( $installed, '1.0.208', '<' ) ) {
				if ( '' === trim( (string) get_option( 'cta_certificate_provider_address', '' ) ) ) {
					update_option(
						'cta_certificate_provider_address',
						"6296 Magnolia Ave #1077\nRiverside, CA 92506",
						false
					);
				}
				if ( class_exists( 'CTA_Certificates' ) && method_exists( 'CTA_Certificates', 'refresh_all_certificates' ) ) {
					CTA_Certificates::refresh_all_certificates();
				}
			}

			// Advanced Suicide Risk Assessment (CTA-CE-003): syllabus/catalog metadata refresh.
			if ( version_compare( $installed, '1.0.209', '<' ) && class_exists( 'CTA_Syllabus_Sync' ) ) {
				CTA_Syllabus_Sync::sync_all( true );
				if ( class_exists( 'CTA_Course_Catalog' ) ) {
					CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
				}
			}

			// CTA-CE-003: six instructional Vimeo modules (sequential unlock).
			if ( version_compare( $installed, '1.0.210', '<' ) && class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
				CTA_Suicide_Risk_Module_Sync::sync_modules( true );
			}

			// CTA-CE-003: enrollment-gated learner resource toolkit (HTML).
			if ( version_compare( $installed, '1.0.211', '<' ) && class_exists( 'CTA_Suicide_Risk_Toolkit_Sync' ) ) {
				CTA_Suicide_Risk_Toolkit_Sync::sync( true );
			}

			// CTA-CE-003: learner final exam (25 questions, no answer keys until Chunk 5).
			if ( version_compare( $installed, '1.0.212', '<' ) && class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
				CTA_Suicide_Risk_Exam_Sync::sync( true );
			}

			// CTA-CE-003: secured final exam answer keys + teaching points (DB merge only).
			if ( version_compare( $installed, '1.0.213', '<' ) && class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
				CTA_Suicide_Risk_Exam_Sync::sync_answer_keys( true );
			}

			// CTA-CE-003: approved course evaluation + inline completion attestation.
			if ( version_compare( $installed, '1.0.214', '<' ) && class_exists( 'CTA_Suicide_Risk_Evaluation_Sync' ) ) {
				CTA_Suicide_Risk_Evaluation_Sync::sync( true );
			}

			// CTA-CE-003: certificate metadata, completion statement, admin placeholder thumbnail.
			if ( version_compare( $installed, '1.0.215', '<' ) && class_exists( 'CTA_Suicide_Risk_Certificate_Sync' ) ) {
				CTA_Suicide_Risk_Certificate_Sync::sync( true );
			}

			// CE publish control: content sync must not mass-unpublish; heal admin-published rows.
			if ( version_compare( $installed, '1.0.216', '<' ) && class_exists( 'CTA_Course_Catalog' ) ) {
				global $wpdb;
				$ce_table = $wpdb->prefix . 'cta_courses';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$published_ce = $wpdb->get_results(
					"SELECT id, syllabus_meta FROM {$ce_table}
					WHERE status = 'published'
					AND (product_type = 'ce' OR product_type = '' OR product_type IS NULL)"
				);
				foreach ( (array) $published_ce as $row ) {
					if ( CTA_Course_Catalog::is_admin_ce_publish_confirmed( $row ) ) {
						continue;
					}
					$meta = array();
					if ( ! empty( $row->syllabus_meta ) ) {
						$decoded = json_decode( (string) $row->syllabus_meta, true );
						$meta    = is_array( $decoded ) ? $decoded : array();
					}
					$meta = CTA_Course_Catalog::apply_admin_ce_publish_meta( $meta, true );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$ce_table,
						array( 'syllabus_meta' => wp_json_encode( $meta ) ),
						array( 'id' => (int) $row->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}

			// CTA-CE-003: repair missing module rows / Vimeo URLs after partial sync.
			if ( version_compare( $installed, '1.0.217', '<' ) && class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
				CTA_Suicide_Risk_Module_Sync::ensure();
			}

			// CTA-CE-003: repair missing final exam + answer keys after partial deploy.
			if ( version_compare( $installed, '1.0.218', '<' ) && class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {
				CTA_Suicide_Risk_Exam_Sync::ensure();
			}

			// CTA-CE-003: repair toolkit, evaluation, and certificate metadata after partial deploy.
			if ( version_compare( $installed, '1.0.219', '<' ) ) {
				if ( class_exists( 'CTA_Suicide_Risk_Toolkit_Sync' ) ) {
					CTA_Suicide_Risk_Toolkit_Sync::ensure();
				}
				if ( class_exists( 'CTA_Suicide_Risk_Evaluation_Sync' ) ) {
					CTA_Suicide_Risk_Evaluation_Sync::ensure();
				}
				if ( class_exists( 'CTA_Suicide_Risk_Certificate_Sync' ) ) {
					CTA_Suicide_Risk_Certificate_Sync::ensure();
				}
			}

			// LMFT California Clinical: archive legacy Form A/B (course_id=10) for replacement build.
			if ( version_compare( $installed, '1.0.220', '<' ) && class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
				CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_legacy_forms( CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID, true );
			}

			// LMFT California Clinical: import rebuilt Comprehensive Simulation Form A (PROMPT 01 items 1–25).
			if ( version_compare( $installed, '1.0.221', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
					CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_legacy_forms( CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID, true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
					CTA_Lmft_Clinical_Form_A_Sync::sync( true );
				}
			}

			// LMFT California Clinical: Form A items 26–50 (PROMPT 02).
			if ( version_compare( $installed, '1.0.222', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
			}

			// LMFT California Clinical: Form A items 51–75 (PROMPT 03).
			if ( version_compare( $installed, '1.0.223', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
			}

			// LMFT California Clinical: Form A items 76–100 (PROMPT 04).
			if ( version_compare( $installed, '1.0.224', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
			}

			// LMFT California Clinical: Form A items 101–125 (PROMPT 05).
			if ( version_compare( $installed, '1.0.225', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
			}

			// LMFT California Clinical: Form A items 126–150 (PROMPT 06) — full bank active.
			if ( version_compare( $installed, '1.0.226', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
			}

			// LMFT California Clinical: Form B items 1–25 (PROMPT 07).
			if ( version_compare( $installed, '1.0.227', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
					CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_legacy_forms( CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID, true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
					CTA_Lmft_Clinical_Form_B_Sync::sync( true );
				}
			}

			// LMFT California Clinical: Form B items 26–50 (PROMPT 08).
			if ( version_compare( $installed, '1.0.228', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
			}

			// LMFT California Clinical: Form B items 51–75 (PROMPT 09).
			if ( version_compare( $installed, '1.0.229', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
			}

			// LMFT California Clinical: Form B items 76–100 (PROMPT 10).
			if ( version_compare( $installed, '1.0.230', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
			}

			// LMFT California Clinical: Form B items 101–125 (PROMPT 11).
			if ( version_compare( $installed, '1.0.231', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
			}

			// LMFT California Clinical: Form B items 126–150 (PROMPT 12) — activates Form B.
			if ( version_compare( $installed, '1.0.232', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
			}

			// LMFT California Clinical: Form A admin answer keys 1–25 (PROMPT 13).
			if ( version_compare( $installed, '1.0.233', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form A admin answer keys 26–50 (PROMPT 14).
			if ( version_compare( $installed, '1.0.234', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form A admin answer keys 51–75 (PROMPT 15).
			if ( version_compare( $installed, '1.0.235', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form A admin answer keys 76–100 (PROMPT 16).
			if ( version_compare( $installed, '1.0.236', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form A admin answer keys 101–125 (PROMPT 17).
			if ( version_compare( $installed, '1.0.237', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form A admin answer keys 126–150 (PROMPT 18) — completes Form A keys.
			if ( version_compare( $installed, '1.0.238', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 1–25 (PROMPT 19).
			if ( version_compare( $installed, '1.0.239', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 26–50 (PROMPT 20).
			if ( version_compare( $installed, '1.0.240', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 51–75 (PROMPT 21).
			if ( version_compare( $installed, '1.0.241', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 76–100 (PROMPT 22).
			if ( version_compare( $installed, '1.0.242', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 101–125 (PROMPT 23).
			if ( version_compare( $installed, '1.0.243', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: Form B admin answer keys 126–150 (PROMPT 24) — completes Form B keys.
			if ( version_compare( $installed, '1.0.244', '<' ) && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}

			// LMFT California Clinical: enable 240-minute timers on Form A and Form B.
			if ( version_compare( $installed, '1.0.245', '<' ) && class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
				CTA_Lmft_Clinical_Sync::sync_comprehensive_simulation_time_limits();
			}

			// LMFT California Clinical: archive legacy 132-card flashcards.json deck (PROMPT 00).
			if ( version_compare( $installed, '1.0.248', '<' ) && class_exists( 'CTA_Lmft_Clinical_Legacy_Flashcard_Archive' ) ) {
				CTA_Lmft_Clinical_Legacy_Flashcard_Archive::archive_legacy_flashcards(
					CTA_Lmft_Clinical_Legacy_Flashcard_Archive::TARGET_COURSE_ID,
					true
				);
			}

			// LCSW ASWB Clinical: slug landing page + legacy URL redirects.
			if ( version_compare( $installed, '1.0.249', '<' ) ) {
				if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
					CTA_Lcsw_Aswb_Sync::heal_product_identity( true );
				}
				if ( class_exists( 'CTA_Course_Routes' ) ) {
					CTA_Course_Routes::sync_landing_pages( true );
				}
			}

			// LMFT California Law & Ethics: standalone license module + 25-question assessment.
			if ( version_compare( $installed, '1.0.250', '<' ) && class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
				CTA_Lmft_Law_Ethics_Sync::sync( true );
			}

			// LMFT California Law & Ethics: six Study Center toolkits (CTA-EP-001).
			if ( version_compare( $installed, '1.0.251', '<' ) && class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
				CTA_Lmft_Law_Ethics_Sync::sync_toolkits( true );
			}

			// LMFT California Law & Ethics: Point 6 Website/LMS Copy Package v1.1 (staging only; not public release).
			if ( version_compare( $installed, '1.0.252', '<' ) && class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
				CTA_Lmft_Law_Ethics_Sync::apply_website_lms_copy( true );
			}

			// LMFT California Law & Ethics: real workbook HTML + printable assessments/rationales (handoff package).
			if ( version_compare( $installed, '1.0.253', '<' ) && class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
				$lmft_le = CTA_Lmft_Law_Ethics_Sync::find_course();
				if ( $lmft_le && ! empty( $lmft_le->id ) ) {
					CTA_Lmft_Law_Ethics_Sync::sync_assessments( (int) $lmft_le->id );
				}
				CTA_Lmft_Law_Ethics_Sync::sync_materials( true );
				CTA_Lmft_Law_Ethics_Sync::sync_toolkits( true );
			}

			// LMFT California Clinical: REPLACE July/legacy Form A/B with August 14 Final forms.
			if ( version_compare( $installed, '1.0.256', '<' ) ) {
				if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
					// Archive only quizzes whose Q1 is not the Final fingerprint (e.g. July partner-violence),
					// plus July printable resources. Do not wipe an already-correct Final bank.
					CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_non_final_active_forms(
						CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID,
						true
					);
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
					CTA_Lmft_Clinical_Form_A_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
					CTA_Lmft_Clinical_Form_B_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
					CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
					CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
				}
				if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
					$lmft = CTA_Lmft_Clinical_Sync::find_course();
					if ( $lmft && ! empty( $lmft->id ) ) {
						CTA_Lmft_Clinical_Sync::sync_materials( (int) $lmft->id );
					}
				}
			}

			// LCSW ASWB Clinical: publish online 17-question Practice Banks for workbooks 1–12.
			if ( version_compare( $installed, '1.0.258', '<' ) && class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
				CTA_Lcsw_Aswb_Sync::sync( true );
			}

			// Practice Bank status: clear ghost completions; keep workbook module completion intact.
			if ( version_compare( $installed, '1.0.259', '<' ) && class_exists( 'CTA_Exam_Prep_Workbooks' ) ) {
				CTA_Exam_Prep_Workbooks::reset_ghost_practice_bank_completions();
			}

			// Timed quiz attempts: no upgrade data migration — expired open attempts are
			// finalized on next quiz open / start (shared CTA_Quiz timer fix in 1.0.260).

			// LPCC NCMHCE Form A v2.0 staging load + secured answer merge (does not replace live Form A).
			if ( version_compare( $installed, '1.0.263', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_A_V2_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::sync_answer_keys( true );
				}
			}

			// LPCC NCMHCE Form B v2.0 staging load + secured answer merge (does not replace live Form B).
			if ( version_compare( $installed, '1.0.264', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_B_V2_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync::sync_answer_keys( true );
				}
			}

			// LPCC NCMHCE v2.0 cutover: archive legacy Form A/B and publish live v2 forms together.
			if ( version_compare( $installed, '1.0.265', '<' ) && class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive' ) ) {
				CTA_Lpcc_Ncmhce_Legacy_Forms_Archive::perform_v2_cutover( 0, true );
			}

			// LPCC NCMHCE: archive legacy 132-card flashcards.json deck; 180-card Study Center is live.
			if ( version_compare( $installed, '1.0.266', '<' ) && class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive' ) ) {
				CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive::archive_legacy_flashcards( 0, true );
			}

			// CTA-CE-003: approved Suicide Risk course image (replace admin placeholder + stale alt text).
			if ( version_compare( $installed, '1.0.267', '<' ) ) {
				if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
					CTA_Database::maybe_add_syllabus_columns();
					CTA_Syllabus_Sync::sync_all( true );
				}
				if ( class_exists( 'CTA_Suicide_Risk_Certificate_Sync' ) ) {
					CTA_Suicide_Risk_Certificate_Sync::sync_thumbnail( true );
				}
			}

			// CE courses: heal access_period_months=0 (breaks admin Save HTML5 validation when field is hidden).
			if ( version_compare( $installed, '1.0.268', '<' ) ) {
				global $wpdb;
				$courses_table = $wpdb->prefix . 'cta_courses';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					"UPDATE {$courses_table} SET access_period_months = 6 WHERE product_type = 'ce' AND access_period_months < 1"
				);
			}

			// Sitewide academy positioning: footer/top-bar/Elementor narrow CE+supervision copy.
			if ( version_compare( $installed, '1.0.269', '<' ) && class_exists( 'CTA_Academy_Positioning' ) ) {
				CTA_Academy_Positioning::sync_sitewide_copy( true );
			}

			// LMFT California Clinical: restore missing/inactive August 14 Final Form A for Practice Exams.
			if ( version_compare( $installed, '1.0.270', '<' ) && class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
				CTA_Lmft_Clinical_Legacy_Forms_Archive::ensure_learner_final_forms(
					CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID,
					false
				);
			}

			// LCSW ASWB Clinical: archive legacy 132-card deck once 180-card Study Center is live.
			if ( version_compare( $installed, '1.0.271', '<' ) && class_exists( 'CTA_Lcsw_Aswb_Legacy_Flashcard_Archive' ) ) {
				CTA_Lcsw_Aswb_Legacy_Flashcard_Archive::archive_legacy_flashcards( 0, false );
			}

			// LCSW ASWB Clinical: heal missing/inactive Form A/B DB rows (shell/timer config preserved).
			if ( version_compare( $installed, '1.0.272', '<' ) && class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
				CTA_Lcsw_Aswb_Sync::ensure_learner_forms( 0, false );
			}

			// LPCC NCMHCE: re-ensure 180-card Study Center cutover if legacy JSON was redeployed.
			if (
				version_compare( $installed, '1.0.273', '<' )
				&& class_exists( 'CTA_Exam_Prep_Flashcard_Center' )
				&& class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive' )
				&& CTA_Exam_Prep_Flashcard_Center::study_center_deck_is_live( 'lpcc-ncmhce' )
			) {
				CTA_Lpcc_Ncmhce_Legacy_Flashcard_Archive::archive_legacy_flashcards( 0, false );
			}

			// LMFT AMFTRB: archive broken 120-card legacy deck (workbook tags as domains).
			if ( version_compare( $installed, '1.0.274', '<' ) && class_exists( 'CTA_Lmft_Amftrb_Legacy_Flashcard_Archive' ) ) {
				CTA_Lmft_Amftrb_Legacy_Flashcard_Archive::archive_legacy_flashcards( 0, false );
			}

			// LMFT AMFTRB: seed online Form A/B (180q / 240min) from approved quiz seeds.
			if ( version_compare( $installed, '1.0.275', '<' ) && class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
				CTA_Lmft_Amftrb_Sync::ensure_learner_forms( 0, false );
			}

			// LMFT AMFTRB: seed 12 workbook online practice banks (17q each).
			if ( version_compare( $installed, '1.0.276', '<' ) && class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {
				CTA_Lmft_Amftrb_Sync::ensure_learner_forms( 0, true );
			}

			// LPCC NCMHCE: 225-minute timers + progressive case simulation player wiring.
			if ( version_compare( $installed, '1.0.277', '<' ) ) {
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Simulation' ) ) {
					CTA_Lpcc_Ncmhce_Simulation::sync_simulation_time_limits();
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_A_Sync::sync( true );
				}
				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {
					CTA_Lpcc_Ncmhce_Form_B_Sync::sync( true );
				}
			}

			// Decouple supervision application pending from general account / CE access.
			if ( version_compare( $installed, '1.0.90', '<' ) && class_exists( 'CTA_Associate_Access' ) ) {
				$query = new WP_User_Query(
					array(
						'role'   => 'cta_associate',
						'number' => 1000,
						'fields' => 'ID',
					)
				);

				foreach ( (array) $query->get_results() as $user_id ) {
					CTA_Associate_Access::heal_decoupled_statuses( absint( $user_id ) );
				}
			}
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CTA LMS upgrade error: ' . $e->getMessage() );
			}
		}

		// Always stamp the version so a failed one-shot migration cannot boot-loop the site.
		update_option( 'cta_lms_version', CTA_VERSION );
		delete_transient( 'cta_lms_upgrading' );
	}
}

if ( ! function_exists( 'cta_lms_unify_form_ab_simulation_titles' ) ) {
	/**
	 * Align online Form A/B quiz titles with downloadable Comprehensive Simulation labels.
	 *
	 * Package/README/DOCX wording uses "Comprehensive Simulation"; older LMS quiz
	 * titles used "Full-Length Simulation (N Questions)".
	 *
	 * @return int Number of quiz rows updated.
	 */
	function cta_lms_unify_form_ab_simulation_titles() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}

		$courses_table = $wpdb->prefix . 'cta_courses';
		$quizzes_table = $wpdb->prefix . 'cta_quizzes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$courses       = $wpdb->get_results( "SELECT id, slug FROM {$courses_table}" );
		if ( empty( $courses ) ) {
			return 0;
		}

		$title_map = array(
			'lmft-california-clinical-exam-preparation' => array(
				'form_a' => 'Form A — 150-Question Comprehensive Simulation',
				'form_b' => 'Form B — 150-Question Comprehensive Simulation',
			),
			'lcsw-aswb-clinical-exam-preparation'       => array(
				'form_a' => 'Form A — 122-Question Comprehensive Simulation',
				'form_b' => 'Form B — 122-Question Comprehensive Simulation',
			),
			'lcsw-california-clinical-exam-preparation' => array(
				'form_a' => 'Form A — 122-Question Comprehensive Simulation',
				'form_b' => 'Form B — 122-Question Comprehensive Simulation',
			),
			'lpcc-ncmhce-exam-preparation'              => array(
				'form_a' => 'Form A — 143-Question Comprehensive Simulation (Candidate Exam)',
				'form_b' => 'Form B — 143-Question Comprehensive Simulation (Candidate Exam)',
			),
			'lpcc-california-clinical-exam-preparation' => array(
				'form_a' => 'Form A — 143-Question Comprehensive Simulation (Candidate Exam)',
				'form_b' => 'Form B — 143-Question Comprehensive Simulation (Candidate Exam)',
			),
		);

		$updated = 0;
		foreach ( $courses as $course ) {
			$slug = sanitize_title( (string) ( $course->slug ?? '' ) );
			if ( ! isset( $title_map[ $slug ] ) ) {
				continue;
			}
			$course_id = absint( $course->id );
			foreach ( $title_map[ $slug ] as $quiz_type => $title ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->update(
					$quizzes_table,
					array( 'title' => $title ),
					array(
						'course_id' => $course_id,
						'quiz_type' => $quiz_type,
					),
					array( '%s' ),
					array( '%d', '%s' )
				);
				if ( false !== $result ) {
					$updated += (int) $result;
				}
			}
		}

		return $updated;
	}
}

if ( ! function_exists( 'cta_ce_price_catalog_fingerprint' ) ) {
	/**
	 * Fingerprint of approved CE catalog prices (detects catalog price edits).
	 *
	 * @return string
	 */
	function cta_ce_price_catalog_fingerprint() {
		if ( ! class_exists( 'CTA_Course_Catalog' ) ) {
			return '';
		}

		$prices = array();
		foreach ( CTA_Course_Catalog::get_ce_catalog() as $entry ) {
			$prices[] = array(
				'title'    => (string) ( $entry['title'] ?? '' ),
				'price'    => round( (float) ( $entry['price'] ?? 0 ), 2 ),
				'ce_hours' => round( (float) ( $entry['ce_hours'] ?? 0 ), 2 ),
				'category' => (string) ( $entry['category'] ?? '' ),
			);
		}

		return md5( wp_json_encode( $prices ) );
	}
}

if ( ! function_exists( 'cta_maybe_sync_ce_prices_from_catalog' ) ) {
	/**
	 * Self-heal CE prices/categories whenever the approved catalog fingerprint changes.
	 *
	 * Needed when GitHub is updated but Hostinger deploy / version stamp lag
	 * left the live `cta_courses` commercial fields on the previous catalog.
	 */
	function cta_maybe_sync_ce_prices_from_catalog() {
		if ( ! class_exists( 'CTA_Course_Catalog' ) ) {
			return;
		}

		$fingerprint = cta_ce_price_catalog_fingerprint();
		if ( '' === $fingerprint ) {
			return;
		}

		if ( get_option( 'cta_ce_price_catalog_fp', '' ) === $fingerprint ) {
			return;
		}

		if ( get_transient( 'cta_ce_price_sync_lock' ) ) {
			return;
		}
		set_transient( 'cta_ce_price_sync_lock', 1, 60 );

		CTA_Course_Catalog::restore_ce_pricing();
		update_option( 'cta_ce_price_catalog_fp', $fingerprint, false );
		delete_transient( 'cta_ce_price_sync_lock' );
	}
}

if ( function_exists( 'cta_maybe_upgrade_db' ) && ! has_action( 'plugins_loaded', 'cta_maybe_upgrade_db' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_upgrade_db', 5 );
}

if ( ! function_exists( 'cta_maybe_heal_lpcc_law_ethics' ) ) {
	/**
	 * Re-run CTA-EP-003 content sync when the Draft shell is still empty after deploy.
	 */
	function cta_maybe_heal_lpcc_law_ethics() {
		if ( class_exists( 'CTA_Lpcc_Law_Ethics_Sync' ) ) {
			CTA_Lpcc_Law_Ethics_Sync::maybe_heal_incomplete_content();
		}
	}
}

if ( function_exists( 'cta_maybe_heal_lpcc_law_ethics' ) && ! has_action( 'plugins_loaded', 'cta_maybe_heal_lpcc_law_ethics' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_heal_lpcc_law_ethics', 7 );
}

if ( ! function_exists( 'cta_force_draft_lcsw_law_ethics_ep' ) ) {
	/**
	 * Force CTA-EP-002 LCSW California Law & Ethics Exam Prep to Draft + launch hold.
	 *
	 * Does not modify memberships, orders, or purchase history.
	 *
	 * @return int Course ID updated, or 0.
	 */
	function cta_force_draft_lcsw_law_ethics_ep() {
		// Retired: admin controls Exam Prep publish/draft manually.
		return 0;
	}
}

if ( ! function_exists( 'cta_maybe_heal_lcsw_law_ethics' ) ) {
	/**
	 * Re-run CTA-EP-002 content sync when the Draft shell is still empty after deploy.
	 */
	function cta_maybe_heal_lcsw_law_ethics() {
		if ( class_exists( 'CTA_Lcsw_Law_Ethics_Sync' ) ) {
			CTA_Lcsw_Law_Ethics_Sync::maybe_heal_incomplete_content();
		}
	}
}

if ( function_exists( 'cta_maybe_heal_lcsw_law_ethics' ) && ! has_action( 'plugins_loaded', 'cta_maybe_heal_lcsw_law_ethics' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_heal_lcsw_law_ethics', 9 );
}

if ( ! function_exists( 'cta_maybe_heal_lmft_law_ethics' ) ) {
	/**
	 * Seed LMFT California Law & Ethics dashboard scaffold when modules are still empty.
	 */
	function cta_maybe_heal_lmft_law_ethics() {
		if ( class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {
			CTA_Lmft_Law_Ethics_Sync::maybe_heal_incomplete_content();
		}
	}
}

if ( function_exists( 'cta_maybe_heal_lmft_law_ethics' ) && ! has_action( 'plugins_loaded', 'cta_maybe_heal_lmft_law_ethics' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_heal_lmft_law_ethics', 8 );
}

if ( ! function_exists( 'cta_maybe_heal_lcsw_aswb_identity' ) ) {
	/**
	 * Heal stale LCSW California Clinical labels on existing course rows.
	 */
	function cta_maybe_heal_lcsw_aswb_identity() {
		if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
			CTA_Lcsw_Aswb_Sync::maybe_heal_stale_product_identity();
		}
	}
}

if ( function_exists( 'cta_maybe_heal_lcsw_aswb_identity' ) && ! has_action( 'plugins_loaded', 'cta_maybe_heal_lcsw_aswb_identity' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_heal_lcsw_aswb_identity', 10 );
}

if ( function_exists( 'cta_maybe_sync_ce_prices_from_catalog' ) && ! has_action( 'plugins_loaded', 'cta_maybe_sync_ce_prices_from_catalog' ) ) {
	add_action( 'plugins_loaded', 'cta_maybe_sync_ce_prices_from_catalog', 6 );
}

if ( ! function_exists( 'cta_lms_migrate_alcoholism_category' ) ) {
	/**
	 * Ensure the Alcoholism CE category exists and link matching courses to it.
	 *
	 * Idempotent: skips courses already on the exact category label; will not
	 * create a duplicate category string in the admin list (handled separately).
	 */
	function cta_lms_migrate_alcoholism_category() {
		global $wpdb;

		$category = class_exists( 'CTA_Admin' )
			? CTA_Admin::get_alcoholism_category_name()
			: 'Alcoholism & Other Chemical Substance Dependency';

		$table = $wpdb->prefix . 'cta_courses';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$table} WHERE category = %s",
				$category
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, category FROM {$table}
				WHERE (product_type = %s OR product_type = '' OR product_type IS NULL)
				AND (
					title LIKE %s
					OR title LIKE %s
					OR category LIKE %s
				)",
				'ce',
				'%Alcoholism & Other Chemical Substance Dependency%',
				'%Alcoholism%Chemical%Dependency%',
				'%Alcoholism%Chemical%Dependency%'
			)
		);

		if ( empty( $candidates ) && ! $exists ) {
			// Category stays available via CTA_Admin::get_course_categories() + catalog merge.
			return;
		}

		foreach ( (array) $candidates as $row ) {
			$current = isset( $row->category ) ? trim( (string) $row->category ) : '';
			if ( 0 === strcasecmp( $current, $category ) ) {
				continue;
			}

			// Only remount when category is empty, Law & Ethics, or an Alcoholism near-match.
			$should_update = ( '' === $current )
				|| ( 0 === strcasecmp( $current, 'Law & Ethics' ) )
				|| (
					false !== stripos( $current, 'alcoholism' )
					&& false !== stripos( $current, 'chemical' )
				);

			$title = isset( $row->title ) ? (string) $row->title : '';
			$title_matches = ( false !== stripos( $title, 'Alcoholism & Other Chemical Substance Dependency' ) )
				|| (
					false !== stripos( $title, 'alcoholism' )
					&& false !== stripos( $title, 'chemical' )
					&& false !== stripos( $title, 'dependency' )
				);

			if ( ! $should_update || ! $title_matches ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'category' => $category ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}
}

if ( function_exists( 'cta_lms_register_encoding_hooks' ) ) {
	cta_lms_register_encoding_hooks();
}

if ( class_exists( 'CTA_Emails' ) ) {
	add_action( 'init', array( 'CTA_Emails', 'register_cron' ) );
	add_action( 'cta_send_session_reminders', array( 'CTA_Emails', 'send_daily_reminders' ) );
}

if ( ! function_exists( 'cta_lms_find_media_url_by_filename' ) ) {
	/**
	 * Find a Media Library attachment URL by attached filename (basename match).
	 *
	 * @param string $filename File basename, e.g. CTA_Horizontal_Logo.png.
	 * @return string Attachment URL or empty string.
	 */
	function cta_lms_find_media_url_by_filename( $filename ) {
		global $wpdb;

		$filename = sanitize_file_name( (string) $filename );
		if ( '' === $filename ) {
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND meta_value LIKE %s
				ORDER BY post_id DESC
				LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );
		return $url ? (string) $url : '';
	}
}

if ( ! function_exists( 'cta_lms_resolve_linked_page_id' ) ) {
	/**
	 * Resolve a CTA linked page ID from option + slug fallbacks.
	 *
	 * @param string $option_name Option key.
	 * @return int
	 */
	function cta_lms_resolve_linked_page_id( $option_name ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( $page_id && get_post_status( $page_id ) ) {
			// Prefer the dedicated supervision-booking page over stray copies (e.g. "test").
			if ( 'cta_supervision_page_id' === $option_name ) {
				$preferred = get_page_by_path( 'supervision-booking' );
				if (
					$preferred instanceof WP_Post
					&& 'publish' === $preferred->post_status
					&& (int) $preferred->ID !== $page_id
				) {
					$page_id = (int) $preferred->ID;
					update_option( $option_name, $page_id );
				}

				$post = get_post( $page_id );
				$content = $post ? (string) $post->post_content : '';
				if ( ! $post || false === strpos( $content, '[cta_supervision_booking' ) ) {
					$page_id = 0;
				}
			}

			if ( $page_id ) {
				return $page_id;
			}
		}

		$slug_map = array(
			'cta_courses_page_id'      => array( 'ce-courses', 'courses' ),
			'cta_exam_prep_page_id'    => array( 'exam-preparation', 'exam-prep', 'exam-prep-catalog' ),
			'cta_supervision_page_id'  => array( 'supervision-booking', 'clinical-supervision', 'supervision' ),
			'cta_memberships_page_id'  => array( 'memberships-page', 'memberships' ),
			'cta_faq_page_id'          => array( 'faq', 'faqs' ),
			'cta_policies_page_id'     => array( 'policies', 'privacy-policy-2', 'privacy-policy', 'terms-of-use' ),
			'cta_about_page_id'        => array( 'about', 'about-us' ),
			'cta_contact_page_id'      => array( 'contact', 'contact-us' ),
			'cta_login_page_id'        => array( 'login', 'sign-in' ),
		);

		$slugs = isset( $slug_map[ $option_name ] ) ? $slug_map[ $option_name ] : array();

		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				update_option( $option_name, (int) $page->ID );
				return (int) $page->ID;
			}
		}

		// Shortcode-based recovery for known marketing pages.
		$shortcode_map = array(
			'cta_courses_page_id'     => 'cta_course_catalog',
			'cta_exam_prep_page_id'   => 'cta_exam_prep_catalog',
			'cta_supervision_page_id' => 'cta_supervision_booking',
			'cta_memberships_page_id' => 'cta_membership_pricing',
			'cta_login_page_id'       => 'cta_login_form',
		);

		if ( isset( $shortcode_map[ $option_name ] ) && function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
			$found = absint( cta_lms_find_page_id_by_shortcode( $shortcode_map[ $option_name ] ) );
			if ( $found ) {
				update_option( $option_name, $found );
				return $found;
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'cta_lms_get_linked_page_url' ) ) {
	/**
	 * Permalink for a CTA linked page option (with slug/shortcode fallbacks).
	 *
	 * @param string $option_name Option key.
	 * @return string
	 */
	function cta_lms_get_linked_page_url( $option_name ) {
		$page_id = cta_lms_resolve_linked_page_id( $option_name );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? (string) $url : '';
	}
}

if ( ! function_exists( 'cta_lms_get_logo_url' ) ) {
	/**
	 * Resolve the first available CTA logo URL for certificates, headers, and emails.
	 *
	 * Prefers a Media Library attachment for CTA_Horizontal_Logo.png when present.
	 *
	 * @param string $preference Preferred variant: default|white|placeholder|auth.
	 * @return string
	 */
	function cta_lms_get_logo_url( $preference = 'default' ) {
		$horizontal_filename = 'CTA_Horizontal_Logo.png';
		$horizontal_logo     = 'https://cta.techosuppglobal.com/wp-content/uploads/2026/06/CTA_Horizontal_Logo.png';

		$custom = (string) get_option( 'cta_logo_url', '' );
		if ( '' !== $custom ) {
			/**
			 * Filter the resolved CTA logo URL.
			 *
			 * @param string $url        Logo URL.
			 * @param string $preference Requested variant.
			 */
			return (string) apply_filters( 'cta_lms_logo_url', esc_url_raw( $custom ), $preference );
		}

		// Explicit attachment ID from settings (if ever stored).
		$attachment_id = absint( get_option( 'cta_logo_attachment_id', 0 ) );
		if ( $attachment_id ) {
			$attached = wp_get_attachment_url( $attachment_id );
			if ( $attached ) {
				return (string) apply_filters( 'cta_lms_logo_url', $attached, $preference );
			}
		}

		if ( 'white' === $preference ) {
			$white_path = CTA_PLUGIN_DIR . 'assets/img/logo-white.png';
			if ( file_exists( $white_path ) ) {
				return (string) apply_filters( 'cta_lms_logo_url', CTA_PLUGIN_URL . 'assets/img/logo-white.png', $preference );
			}
		}

		// Prefer Media Library copy of the horizontal logo over a hardcoded remote URL.
		$media_url = cta_lms_find_media_url_by_filename( $horizontal_filename );
		if ( $media_url ) {
			return (string) apply_filters( 'cta_lms_logo_url', $media_url, $preference );
		}

		// Resolve known upload URL to an attachment ID when possible.
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$known_id = absint( attachment_url_to_postid( $horizontal_logo ) );
			if ( $known_id ) {
				$known_attached = wp_get_attachment_url( $known_id );
				if ( $known_attached ) {
					return (string) apply_filters( 'cta_lms_logo_url', $known_attached, $preference );
				}
			}
		}

		$local_horizontal = CTA_PLUGIN_DIR . 'assets/img/logo-horizontal.png';
		if ( file_exists( $local_horizontal ) ) {
			return (string) apply_filters( 'cta_lms_logo_url', CTA_PLUGIN_URL . 'assets/img/logo-horizontal.png', $preference );
		}

		return (string) apply_filters( 'cta_lms_logo_url', $horizontal_logo, $preference );
	}
}

if ( ! function_exists( 'cta_lms_format_money' ) ) {
	/**
	 * Format a course/checkout amount with a consistent two-decimal currency label.
	 *
	 * @param float|int|string $amount Amount.
	 * @return string e.g. "$89.99"
	 */
	function cta_lms_format_money( $amount ) {
		return '$' . number_format( (float) $amount, 2 );
	}
}

if ( ! function_exists( 'cta_lms_get_course_display_title' ) ) {
	/**
	 * Learner-/public-facing course title.
	 *
	 * Prefers syllabus_meta.public_title when set (shorter marketing name),
	 * otherwise falls back to the formal course.title used in admin.
	 *
	 * @param object|null $course Course row.
	 * @return string
	 */
	function cta_lms_get_course_display_title( $course ) {
		if ( ! $course ) {
			return '';
		}

		$meta = array();
		if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
			$meta = CTA_Syllabus_Sync::get_meta( $course );
		} elseif ( ! empty( $course->syllabus_meta ) ) {
			$decoded = json_decode( (string) $course->syllabus_meta, true );
			$meta    = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! empty( $meta['public_title'] ) ) {
			$public = sanitize_text_field( (string) $meta['public_title'] );
			if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) && CTA_Lcsw_Aswb_Sync::is_stale_display_title( $public ) ) {
				return CTA_Lcsw_Aswb_Sync::PUBLIC_TITLE;
			}

			return $public;
		}

		$formal = isset( $course->title ) ? (string) $course->title : '';
		if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) && CTA_Lcsw_Aswb_Sync::is_stale_display_title( $formal ) ) {
			return CTA_Lcsw_Aswb_Sync::PUBLIC_TITLE;
		}

		return $formal;
	}
}

if ( ! function_exists( 'cta_lms_get_user_legal_name' ) ) {
	/**
	 * Resolve a user's legal / display full name for certificates.
	 *
	 * Prefers first+last name meta, then display_name when it is not just the
	 * username/login handle (avoids certificates showing account usernames).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	function cta_lms_get_user_legal_name( $user_id ) {
		$user_id = absint( $user_id );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return '';
		}

		$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );

		if ( '' !== $first && '' !== $last ) {
			return trim( $first . ' ' . $last );
		}

		$display = trim( (string) $user->display_name );
		$login   = trim( (string) $user->user_login );

		if ( '' !== $display && 0 !== strcasecmp( $display, $login ) ) {
			return $display;
		}

		if ( '' !== $first ) {
			return $first;
		}
		if ( '' !== $last ) {
			return $last;
		}

		if ( '' !== $display ) {
			return $display;
		}

		$nicename = trim( (string) $user->user_nicename );
		if ( '' !== $nicename && 0 !== strcasecmp( $nicename, $login ) ) {
			return $nicename;
		}

		return $login;
	}
}

if ( ! function_exists( 'cta_lms_sync_user_name_parts' ) ) {
	/**
	 * Keep WP first/last name meta aligned with a full legal name string.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $full_name Full name from Account Settings / registration.
	 */
	function cta_lms_sync_user_name_parts( $user_id, $full_name ) {
		$user_id   = absint( $user_id );
		$full_name = trim( sanitize_text_field( (string) $full_name ) );

		if ( ! $user_id || '' === $full_name ) {
			return;
		}

		$parts = preg_split( '/\s+/', $full_name, 2 );
		$first = isset( $parts[0] ) ? $parts[0] : '';
		$last  = isset( $parts[1] ) ? $parts[1] : '';

		update_user_meta( $user_id, 'first_name', $first );
		update_user_meta( $user_id, 'last_name', $last );
	}
}

if ( ! function_exists( 'cta_lms_get_user_license_number' ) ) {
	/**
	 * Resolve a user's license / registration number from user meta.
	 *
	 * Primary key: cta_license_number (Account Settings + admin Users).
	 * Falls back to legacy keys if present.
	 *
	 * @param int $user_id User ID.
	 * @return string Sanitized license number (may be empty).
	 */
	function cta_lms_get_user_license_number( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}

		$keys = array( 'cta_license_number', 'license_number', 'cta_registration_number' );

		foreach ( $keys as $key ) {
			$value = cta_lms_sanitize_license_number( (string) get_user_meta( $user_id, $key, true ) );
			if ( '' !== $value ) {
				// Normalize onto the canonical key when found via a legacy key.
				if ( 'cta_license_number' !== $key ) {
					update_user_meta( $user_id, 'cta_license_number', $value );
				}
				return $value;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'cta_lms_get_license_types' ) ) {
	/**
	 * Allowed license / registration type codes.
	 *
	 * @return string[]
	 */
	function cta_lms_get_license_types() {
		return array( 'LMFT', 'LCSW', 'LPCC', 'LEP', 'AMFT', 'ASW', 'APCC' );
	}
}

if ( ! function_exists( 'cta_lms_sanitize_license_number' ) ) {
	/**
	 * Sanitize a license / registration number (formats vary by type).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function cta_lms_sanitize_license_number( $value ) {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return is_string( $value ) ? trim( $value ) : '';
	}
}

if ( ! function_exists( 'cta_lms_is_valid_license_number' ) ) {
	/**
	 * Basic license-number validation (not a strict format — types vary).
	 *
	 * Empty is allowed (admin may clear; certificates show N/A).
	 * Non-empty values must include at least one letter or digit and stay within length.
	 *
	 * @param string $value Sanitized license number.
	 * @return bool
	 */
	function cta_lms_is_valid_license_number( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return true;
		}

		if ( strlen( $value ) > 64 ) {
			return false;
		}

		// Reject entries that are only punctuation / symbols.
		return (bool) preg_match( '/[A-Za-z0-9]/', $value );
	}
}

if ( ! function_exists( 'cta_lms_user_has_license_number' ) ) {
	/**
	 * Whether a user has a non-empty license number on file.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	function cta_lms_user_has_license_number( $user_id ) {
		return '' !== cta_lms_get_user_license_number( $user_id );
	}
}

if ( ! function_exists( 'cta_lms_get_icon' ) ) {
	/**
	 * Return an inline SVG icon for CTA templates.
	 *
	 * @param string $name Icon name: check, check-circle, lock, circle, eye, eye-off.
	 * @param int    $size Icon size in pixels.
	 * @param string $class Optional CSS class.
	 * @return string
	 */
	function cta_lms_get_icon( $name, $size = 16, $class = 'cta-icon' ) {
		$size       = max( 12, absint( $size ) );
		$class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

		switch ( $name ) {
			case 'check':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
			case 'check-circle':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="8 12 11 15 16 9"></polyline></svg>';
			case 'lock':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
			case 'circle':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"' . $class_attr . ' aria-hidden="true"><circle cx="12" cy="12" r="9"></circle></svg>';
			case 'arrow-right':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';
			case 'eye':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
			case 'eye-off':
				return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $class_attr . ' aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'cta_lms_render_password_field' ) ) {
	/**
	 * Render a password input with show/hide toggle.
	 *
	 * @param array $args Field arguments: id, name, label, autocomplete, required.
	 * @return string
	 */
	function cta_lms_render_password_field( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'           => '',
				'name'         => '',
				'label'        => '',
				'autocomplete' => 'current-password',
				'required'     => true,
			)
		);

		if ( empty( $args['id'] ) || empty( $args['name'] ) || empty( $args['label'] ) ) {
			return '';
		}

		$required_attr = ! empty( $args['required'] ) ? ' required' : '';

		ob_start();
		?>
		<div class="form-group">
			<label class="form-label" for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<div class="form-password" data-password-field>
				<input
					type="password"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					name="<?php echo esc_attr( $args['name'] ); ?>"
					class="form-input form-password__input"
					autocomplete="<?php echo esc_attr( $args['autocomplete'] ); ?>"
					<?php echo $required_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				>
				<button
					type="button"
					class="form-password__toggle"
					aria-label="<?php echo esc_attr__( 'Show password', 'cta-lms' ); ?>"
					aria-pressed="false"
				>
					<span class="form-password__icon--show"><?php echo cta_lms_get_icon( 'eye', 20, 'form-password__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="form-password__icon--hide"><?php echo cta_lms_get_icon( 'eye-off', 20, 'form-password__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'cta_lms_find_page_id_by_shortcode' ) ) {
	/**
	 * Find a published page that contains a CTA shortcode.
	 *
	 * @param string $shortcode Shortcode tag without brackets.
	 * @return int Page ID or 0.
	 */
	function cta_lms_find_page_id_by_shortcode( $shortcode ) {
		static $cache = array();

		$shortcode = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $shortcode ) );

		if ( '' === $shortcode ) {
			return 0;
		}

		if ( isset( $cache[ $shortcode ] ) ) {
			return $cache[ $shortcode ];
		}

		global $wpdb;

		$like = '%[' . $wpdb->esc_like( $shortcode ) . '%';

		// Fast path: one SQL match on post_content (avoids loading every page into PHP).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'page'
				AND post_status = 'publish'
				AND post_content LIKE %s
				ORDER BY ID ASC
				LIMIT 1",
				$like
			)
		);

		if ( $page_id ) {
			$cache[ $shortcode ] = $page_id;
			return $page_id;
		}

		// Fallback: Elementor stores shortcodes in post meta, not post_content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = 'page'
				AND p.post_status = 'publish'
				AND pm.meta_key = '_elementor_data'
				AND pm.meta_value LIKE %s
				ORDER BY p.ID ASC
				LIMIT 1",
				$like
			)
		);

		$cache[ $shortcode ] = $page_id > 0 ? $page_id : 0;
		return $cache[ $shortcode ];
	}
}

if ( ! function_exists( 'cta_lms_get_single_course_url' ) ) {
	/**
	 * Build the single course detail URL for a course ID.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	function cta_lms_get_single_course_url( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return '';
		}

		if ( class_exists( 'CTA_Course_Routes' ) ) {
			$canonical = CTA_Course_Routes::get_canonical_url( $course_id );
			if ( $canonical ) {
				return $canonical;
			}
		}

		$page_id = absint( get_option( 'cta_single_course_page_id', 0 ) );

		if ( ! $page_id ) {
			$page_id = cta_lms_find_page_id_by_shortcode( 'cta_single_course' );
		}

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg( 'course_id', $course_id, $permalink );
	}
}

if ( ! function_exists( 'cta_lms_admin_notices' ) ) {
	/**
	 * Warn when duplicate CTA LMS plugin folders are installed / activation failed.
	 */
	function cta_lms_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$activation_error = get_option( 'cta_lms_activation_error', '' );
		if ( $activation_error ) {
			$data = json_decode( (string) $activation_error, true );
			$msg  = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : (string) $activation_error;

			// Ignore stale "bootstrap missing" fatals once this file has loaded.
			$is_missing_bootstrap = false !== stripos( $msg, 'Bootstrap file cta-lms.php is missing' );
			if ( $is_missing_bootstrap ) {
				if ( function_exists( 'cta_lms_clear_fatal' ) ) {
					cta_lms_clear_fatal();
				} else {
					delete_option( 'cta_lms_activation_error' );
				}
			} else {
				echo '<div class="notice notice-error"><p><strong>';
				esc_html_e( 'CTA LMS activation warning:', 'cta-lms' );
				echo '</strong> ';
				echo esc_html( $msg );
				echo '</p></div>';
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$matches     = array();
		$active_file = defined( 'CTA_PLUGIN_BASENAME' ) ? CTA_PLUGIN_BASENAME : '';
		$cta_plugins = get_plugins();

		foreach ( $cta_plugins as $plugin_file => $plugin_data ) {
			$text_domain = $plugin_data['TextDomain'] ?? '';
			$name        = $plugin_data['Name'] ?? '';

			if ( 'cta-lms' === $text_domain || false !== stripos( $name, 'CTA LMS' ) || false !== stripos( $name, 'CTA Academy' ) ) {
				$matches[ $plugin_file ] = $plugin_data;
			}
		}

		if ( count( $matches ) <= 1 ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'CTA LMS: Multiple plugin copies detected.', 'cta-lms' );
		echo '</strong></p><p>';
		esc_html_e( 'This breaks activation and course saving. Keep only one CTA Academy LMS and delete every other copy.', 'cta-lms' );
		echo '</p><ul style="list-style:disc;padding-left:20px;">';

		foreach ( $matches as $plugin_file => $plugin_data ) {
			$is_active = ( $plugin_file === $active_file );
			printf(
				'<li><code>%1$s</code> — %2$s %3$s</li>',
				esc_html( $plugin_file ),
				esc_html( $plugin_data['Version'] ?? '?' ),
				$is_active ? esc_html__( '(active — keep this one)', 'cta-lms' ) : esc_html__( '(delete this copy)', 'cta-lms' )
			);
		}

		echo '</ul><p>';
		esc_html_e( 'Steps: Keep only CTA Academy LMS active, then delete every other CTA LMS copy from Plugins.', 'cta-lms' );
		echo '</p></div>';
	}
}

if ( function_exists( 'cta_lms_admin_notices' ) && ! has_action( 'admin_notices', 'cta_lms_admin_notices' ) ) {
	add_action( 'admin_notices', 'cta_lms_admin_notices' );
}
