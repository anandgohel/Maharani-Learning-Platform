<?php
/**
 * Maharani Academy — Quiz Page
 * Overrides: single-sfwd-quiz.php
 * Renders the LearnDash quiz within the Academy shell
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id  = get_current_user_id();
$gdata    = MWA_Gamification::get_user_data( $user_id );
$quiz_id  = get_the_ID();
$quiz     = get_post( $quiz_id );

// Get parent course and lesson
$course_id = 0;
$lesson_id = 0;
if ( function_exists( 'learndash_get_course_id' ) ) {
    $course_id = learndash_get_course_id( $quiz_id );
}
if ( function_exists( 'learndash_get_lesson_id' ) ) {
    $lesson_id = learndash_get_lesson_id( $quiz_id );
}
$course_title = $course_id ? get_the_title( $course_id ) : 'Course';
$lesson_title = $lesson_id ? get_the_title( $lesson_id ) : '';
$lesson_url   = $lesson_id ? get_permalink( $lesson_id ) : '';
$course_url   = $course_id ? get_permalink( $course_id ) : home_url( '/my-dashboard/' );
$dash_url     = home_url( '/my-dashboard/' );

// Render the LearnDash quiz content (includes the actual quiz form)
$quiz_content = '';
if ( function_exists( 'learndash_get_the_content' ) ) {
    // Use LearnDash's own content renderer for the quiz
    ob_start();
    the_content();
    $quiz_content = ob_get_clean();
} else {
    $quiz_content = apply_filters( 'the_content', $quiz->post_content );
}

// Strip conflicting styles (but keep LearnDash quiz styles)
add_action( 'wp_enqueue_scripts', function() {
    global $wp_styles;
    if ( $wp_styles ) {
        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( strpos( $handle, 'astra' ) !== false ||
                 strpos( $handle, 'elementor' ) !== false ||
                 $handle === 'wp-block-library' ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }
}, 999 );
$theme_url = str_replace( 'http://', 'https://', MW_ACADEMY_URL );
$css_url   = $theme_url . '/assets/css/academy.css';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html( $quiz->post_title ); ?> — Maharani Weddings Academy</title>
  <link rel="icon" href="<?php echo esc_url( $theme_url . '/assets/images/favicon.svg' ); ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>">
  <?php wp_head(); ?>
  <style>
.quiz-layout{max-width:900px;margin:0 auto;padding:var(--space-8) var(--space-8) var(--space-16)}
.quiz-nav-bar{display:flex;align-items:center;justify-content:space-between;padding:var(--space-4) var(--space-6);background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);margin-bottom:var(--space-6)}
.quiz-breadcrumb{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:600;color:var(--gray-500);flex-wrap:wrap}
.quiz-breadcrumb a{color:var(--gray-500);text-decoration:none}
.quiz-breadcrumb a:hover{color:var(--pink-600)}
.quiz-card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden}
.quiz-card__header{padding:var(--space-7) var(--space-8);border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:var(--space-4)}
.quiz-card__icon{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--gold-400),var(--gold-600));color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 3px 12px rgba(212,160,23,.4)}
.quiz-card__header-text{flex:1}
.quiz-card__eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gold-600);margin-bottom:var(--space-1)}
.quiz-card__title{font-family:var(--font-display);font-size:24px;font-weight:600;color:var(--gray-900);line-height:1.2}
.quiz-card__body{padding:var(--space-6) var(--space-8)}
.quiz-card__body .wpProQuiz_content{font-size:15px;line-height:1.7;color:var(--gray-700)}
.quiz-card__body .wpProQuiz_question{margin-bottom:var(--space-6);padding:var(--space-5);border:1px solid var(--gray-200);border-radius:var(--radius-lg);background:var(--gray-50)}
.quiz-card__body .wpProQuiz_questionList{list-style:none;padding:0;margin:var(--space-4) 0}
.quiz-card__body .wpProQuiz_questionListItem{padding:var(--space-3) var(--space-4);margin-bottom:var(--space-2);border:1px solid var(--gray-200);border-radius:var(--radius-md);background:#fff;cursor:pointer;transition:all var(--transition-fast)}
.quiz-card__body .wpProQuiz_questionListItem:hover{border-color:var(--pink-300);background:var(--pink-50)}
.quiz-card__body .wpProQuiz_questionListItem label{cursor:pointer;display:flex;align-items:center;gap:var(--space-3);font-size:14px}
.quiz-card__body input[type="submit"],.quiz-card__body .wpProQuiz_button,.quiz-card__body .wpProQuiz_button2{background:var(--pink-600)!important;color:#fff!important;border:none!important;padding:10px 24px!important;border-radius:var(--radius-full)!important;font-size:14px!important;font-weight:600!important;cursor:pointer;transition:all var(--transition-fast)!important}
.quiz-card__body input[type="submit"]:hover,.quiz-card__body .wpProQuiz_button:hover,.quiz-card__body .wpProQuiz_button2:hover{background:var(--pink-700)!important;transform:translateY(-1px)}
.quiz-card__footer{padding:var(--space-5) var(--space-8);border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between}
.quiz-xp-tag{display:inline-flex;align-items:center;gap:var(--space-2);font-size:13px;font-weight:600;color:var(--gold-600);background:var(--gold-50);padding:6px 14px;border-radius:var(--radius-full);border:1px solid var(--gold-200)}
.page-bg{background:var(--gray-50)}
.page-wrapper{padding-top:var(--nav-height)}
@media(max-width:768px){.quiz-layout{padding:var(--space-5)}.quiz-card__header,.quiz-card__body,.quiz-card__footer{padding-left:var(--space-5);padding-right:var(--space-5)}}
  </style>
</head>
<body class="page-bg">

<?php mw_render_nav( 'course' ); ?>

<main class="page-wrapper" id="main-content">
  <div class="quiz-layout">
    <!-- Breadcrumb -->
    <div class="quiz-nav-bar">
      <nav class="quiz-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( $dash_url ); ?>">Dashboard</a>
        <span aria-hidden="true">›</span>
        <a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( $course_title ); ?></a>
        <?php if ( $lesson_title ) : ?>
        <span aria-hidden="true">›</span>
        <a href="<?php echo esc_url( $lesson_url ); ?>"><?php echo esc_html( $lesson_title ); ?></a>
        <?php endif; ?>
        <span aria-hidden="true">›</span>
        <span style="color:var(--gray-800);">Quiz</span>
      </nav>
    </div>

    <!-- Quiz card -->
    <div class="quiz-card">
      <div class="quiz-card__header">
        <div class="quiz-card__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="quiz-card__header-text">
          <div class="quiz-card__eyebrow">Knowledge Check</div>
          <h1 class="quiz-card__title"><?php echo esc_html( $quiz->post_title ); ?></h1>
        </div>
      </div>
      <div class="quiz-card__body">
        <?php
        // Use WordPress loop to properly render LearnDash quiz
        if ( have_posts() ) {
            while ( have_posts() ) {
                the_post();
                the_content();
            }
        }
        ?>
      </div>
      <div class="quiz-card__footer">
        <?php if ( $lesson_url ) : ?>
        <a href="<?php echo esc_url( $lesson_url ); ?>" style="font-size:13px;font-weight:600;color:var(--gray-600);text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Back to lesson
        </a>
        <?php else : ?>
        <div></div>
        <?php endif; ?>
        <span class="quiz-xp-tag">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Pass to earn <?php echo MWA_Gamification::XP_PER_QUIZ_PASS; ?> XP
        </span>
      </div>
    </div>
  </div>
</main>

<script src="<?php echo esc_url( $theme_url . '/assets/js/academy.js' ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
