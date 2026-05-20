<?php
/**
 * Maharani Academy — Lesson Page
 * Overrides: single-sfwd-lessons.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id   = get_current_user_id();
$gdata     = MWA_Gamification::get_user_data( $user_id );
$lesson_id = get_the_ID();
$lesson    = get_post( $lesson_id );
$course_id = 0;

// Get parent course
if ( function_exists( 'learndash_get_course_id' ) ) {
    $course_id = learndash_get_course_id( $lesson_id );
}
$course_title = $course_id ? get_the_title( $course_id ) : 'Course';

// Lesson completion state
$is_complete = false;
if ( function_exists( 'learndash_is_lesson_complete' ) ) {
    $is_complete = learndash_is_lesson_complete( $user_id, $lesson_id, $course_id );
}

// Navigation — get lesson list and find prev/next
$prev_lesson = null; $next_lesson = null; $lesson_num = 0; $total_lessons = 0;
$all_lessons = array();
if ( function_exists( 'learndash_get_lesson_list' ) && $course_id ) {
    $raw = learndash_get_lesson_list( $course_id );
    if ( is_array( $raw ) ) {
        $total_lessons = count( $raw );
        foreach ( $raw as $i => $l ) {
            $all_lessons[] = $l;
            if ( $l->ID == $lesson_id ) {
                $lesson_num = $i + 1;
                if ( $i > 0 ) $prev_lesson = $raw[ $i - 1 ];
                if ( $i < count( $raw ) - 1 ) $next_lesson = $raw[ $i + 1 ];
            }
        }
    }
}

// Course progress
$progress_pct = 0;
if ( function_exists( 'learndash_course_progress' ) && $course_id ) {
    $prog = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
    $progress_pct = isset( $prog['percentage'] ) ? $prog['percentage'] : 0;
}

// Lesson content
$content = apply_filters( 'the_content', $lesson->post_content );

// Mark complete URL (LearnDash uses a form)
$mark_complete_nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'sfwd_nonce' ) : '';

$dash_url   = home_url( '/my-dashboard/' );
$course_url = $course_id ? get_permalink( $course_id ) : $dash_url;

// Strip conflicting styles
add_action( 'wp_enqueue_scripts', function() {
    global $wp_styles;
    if ( $wp_styles ) {
        foreach ( $wp_styles->registered as $handle => $style ) {
            if ( strpos( $handle, 'astra' ) !== false || strpos( $handle, 'learndash' ) !== false ||
                 strpos( $handle, 'elementor' ) !== false || $handle === 'wp-block-library' ) {
                wp_dequeue_style( $handle ); wp_deregister_style( $handle );
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
  <title><?php echo esc_html( $lesson->post_title ); ?> — Maharani Weddings Academy</title>
  <link rel="icon" href="<?php echo esc_url( $theme_url . '/assets/images/favicon.svg' ); ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>">
  <?php wp_head(); ?>
  <style>
.lesson-layout{max-width:var(--page-max);margin:0 auto;padding:var(--space-8) var(--space-8) var(--space-16);display:grid;grid-template-columns:1fr 320px;gap:var(--space-8);align-items:start}
.lesson-content{min-width:0}
.lesson-nav-bar{display:flex;align-items:center;justify-content:space-between;padding:var(--space-4) var(--space-6);background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);margin-bottom:var(--space-6)}
.lesson-breadcrumb{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:600;color:var(--gray-500)}
.lesson-breadcrumb a{color:var(--gray-500);text-decoration:none}
.lesson-breadcrumb a:hover{color:var(--pink-600)}
.lesson-step{font-size:12px;font-weight:600;color:var(--gray-500)}
.lesson-body{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden}
.lesson-body__header{padding:var(--space-7) var(--space-8);border-bottom:1px solid var(--gray-100)}
.lesson-body__eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--pink-600);margin-bottom:var(--space-2);display:flex;align-items:center;gap:var(--space-2)}
.lesson-body__title{font-family:var(--font-display);font-size:28px;font-weight:600;color:var(--gray-900);line-height:1.15}
.lesson-body__content{padding:var(--space-7) var(--space-8)}
.lesson-body__content h2,.lesson-body__content h3{font-family:var(--font-display);margin:var(--space-6) 0 var(--space-3)}
.lesson-body__content p{font-size:15px;line-height:1.7;color:var(--gray-700);margin-bottom:var(--space-4)}
.lesson-body__content ul,.lesson-body__content ol{margin-bottom:var(--space-4);padding-left:var(--space-5)}
.lesson-body__content li{font-size:15px;line-height:1.7;color:var(--gray-700);margin-bottom:var(--space-2)}
.lesson-body__content img,.lesson-body__content video,.lesson-body__content iframe{max-width:100%;border-radius:var(--radius-lg);margin:var(--space-4) 0}
.lesson-footer{padding:var(--space-6) var(--space-8);border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--space-4)}
.lesson-nav-btn{display:inline-flex;align-items:center;gap:var(--space-2);font-size:13px;font-weight:600;color:var(--gray-600);text-decoration:none;padding:var(--space-2) var(--space-3);border-radius:var(--radius-md);transition:all var(--transition-fast)}
.lesson-nav-btn:hover{background:var(--gray-50);color:var(--pink-600)}
.lesson-sidebar{position:sticky;top:calc(var(--nav-height) + 24px)}
.sidebar-lessons{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden}
.sidebar-lessons__header{padding:var(--space-4) var(--space-5);border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between}
.sidebar-lessons__title{font-size:14px;font-weight:700;color:var(--gray-700)}
.sidebar-lessons__body{padding:var(--space-2);max-height:500px;overflow-y:auto}
.sl-row{display:flex;align-items:center;gap:var(--space-3);padding:var(--space-2) var(--space-3);border-radius:var(--radius-md);text-decoration:none;color:inherit;transition:background var(--transition-fast);font-size:13px}
.sl-row:hover{background:var(--gray-50);color:inherit}
.sl-row.active{background:var(--pink-50)}
.sl-row.active .sl-title{color:var(--pink-700);font-weight:600}
.sl-dot{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;font-weight:700}
.sl-dot--done{background:var(--color-success-bg);color:var(--color-success)}
.sl-dot--active{background:var(--pink-600);color:#fff}
.sl-dot--upcoming{background:var(--gray-100);color:var(--gray-500)}
.sl-title{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gray-700)}
.xp-reward{margin-top:var(--space-5);background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);padding:var(--space-5);text-align:center}
.xp-reward__num{font-family:var(--font-display);font-size:28px;font-weight:600;color:var(--pink-600)}
.xp-reward__label{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--gray-500);margin-top:2px}
.page-bg{background:var(--gray-50)}
.page-wrapper{padding-top:var(--nav-height)}
.btn-mark-complete{display:inline-flex;align-items:center;gap:var(--space-2);padding:10px 24px;border-radius:var(--radius-full);font-size:14px;font-weight:600;border:none;cursor:pointer;transition:all var(--transition-fast)}
.btn-mark-complete--pending{background:var(--pink-600);color:#fff}
.btn-mark-complete--pending:hover{background:var(--pink-700);transform:translateY(-1px);box-shadow:var(--shadow-md)}
.btn-mark-complete--done{background:var(--color-success-bg);color:var(--color-success);cursor:default}
@media(max-width:900px){.lesson-layout{grid-template-columns:1fr}.lesson-sidebar{position:static;order:-1}.lesson-body__header,.lesson-body__content,.lesson-footer{padding-left:var(--space-5);padding-right:var(--space-5)}.lesson-layout{padding:var(--space-5) var(--space-5) var(--space-10)}}
  </style>
</head>
<body class="page-bg">

<?php mw_render_nav( 'course' ); ?>

<main class="page-wrapper" id="main-content">
  <div class="lesson-layout">
    <div class="lesson-content">
      <!-- Breadcrumb bar -->
      <div class="lesson-nav-bar">
        <nav class="lesson-breadcrumb" aria-label="Breadcrumb">
          <a href="<?php echo esc_url( $dash_url ); ?>">Dashboard</a>
          <span aria-hidden="true">›</span>
          <a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( $course_title ); ?></a>
          <span aria-hidden="true">›</span>
          <span style="color:var(--gray-800);">Lesson <?php echo esc_html( $lesson_num ); ?></span>
        </nav>
        <span class="lesson-step"><?php echo esc_html( $lesson_num ); ?> / <?php echo esc_html( $total_lessons ); ?></span>
      </div>

      <!-- Lesson body -->
      <div class="lesson-body">
        <div class="lesson-body__header">
          <div class="lesson-body__eyebrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Lesson <?php echo esc_html( $lesson_num ); ?> of <?php echo esc_html( $total_lessons ); ?>
          </div>
          <h1 class="lesson-body__title"><?php echo esc_html( $lesson->post_title ); ?></h1>
        </div>
        <div class="lesson-body__content">
          <?php echo $content; ?>
        </div>
        <div class="lesson-footer">
          <div>
            <?php if ( $prev_lesson ) : ?>
            <a href="<?php echo esc_url( get_permalink( $prev_lesson->ID ) ); ?>" class="lesson-nav-btn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Previous
            </a>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:var(--space-3);">
            <?php if ( $is_complete ) : ?>
              <span class="btn-mark-complete btn-mark-complete--done">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Completed
              </span>
            <?php else : ?>
              <form method="post" action="<?php echo esc_url( get_permalink() ); ?>">
                <input type="hidden" name="sfwd_mark_complete" value="1">
                <input type="hidden" name="post" value="<?php echo esc_attr( $lesson_id ); ?>">
                <input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
                <?php wp_nonce_field( 'sfwd_mark_complete_' . $user_id . '_' . $lesson_id ); ?>
                <button type="submit" class="btn-mark-complete btn-mark-complete--pending">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                  Mark as complete
                </button>
              </form>
            <?php endif; ?>
            <?php if ( $next_lesson ) : ?>
            <a href="<?php echo esc_url( get_permalink( $next_lesson->ID ) ); ?>" class="lesson-nav-btn" style="color:var(--pink-600);">
              Next
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <aside class="lesson-sidebar">
      <div class="sidebar-lessons">
        <div class="sidebar-lessons__header">
          <h2 class="sidebar-lessons__title"><?php echo esc_html( $course_title ); ?></h2>
          <span style="font-size:11px;font-weight:600;color:var(--gray-500);"><?php echo esc_html( $progress_pct ); ?>%</span>
        </div>
        <div class="sidebar-lessons__body">
          <?php
          $found_active = false;
          foreach ( $all_lessons as $i => $sl ) :
            $sl_done   = function_exists( 'learndash_is_lesson_complete' ) ? learndash_is_lesson_complete( $user_id, $sl->ID, $course_id ) : false;
            $sl_active = ( $sl->ID == $lesson_id );
            $dot_class = $sl_done ? 'sl-dot--done' : ( $sl_active ? 'sl-dot--active' : 'sl-dot--upcoming' );
            $row_class = $sl_active ? 'sl-row active' : 'sl-row';
          ?>
          <a href="<?php echo esc_url( get_permalink( $sl->ID ) ); ?>" class="<?php echo esc_attr( $row_class ); ?>">
            <span class="sl-dot <?php echo esc_attr( $dot_class ); ?>">
              <?php if ( $sl_done ) : ?>
                <svg width="10" height="10" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="2,7 5,10 12,4"/></svg>
              <?php elseif ( $sl_active ) : ?>
                <svg width="8" height="8" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              <?php else : ?>
                <?php echo $i + 1; ?>
              <?php endif; ?>
            </span>
            <span class="sl-title"><?php echo esc_html( $sl->post_title ); ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="xp-reward">
        <div class="xp-reward__num">+<?php echo MWA_Gamification::XP_PER_LESSON; ?></div>
        <div class="xp-reward__label">XP per lesson</div>
      </div>
    </aside>
  </div>
</main>

<script src="<?php echo esc_url( $theme_url . '/assets/js/academy.js' ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
