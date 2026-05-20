<?php
/**
 * Maharani Academy — Course Overview Page
 * Overrides: single-sfwd-courses.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id   = get_current_user_id();
$user      = wp_get_current_user();
$gdata     = MWA_Gamification::get_user_data( $user_id );
$initial   = strtoupper( mb_substr( $user->display_name, 0, 1 ) );
$course_id = get_the_ID();
$course    = get_post( $course_id );

// Progress
$progress_pct = 0; $lessons_done = 0; $lessons_total = 0;
if ( function_exists( 'learndash_course_progress' ) ) {
    $prog = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
    $progress_pct  = isset( $prog['percentage'] ) ? $prog['percentage'] : 0;
    $lessons_done  = isset( $prog['completed'] ) ? $prog['completed'] : 0;
    $lessons_total = isset( $prog['total'] ) ? $prog['total'] : 0;
}
$lessons_left = $lessons_total - $lessons_done;
$ring_offset  = round( 364.4 * ( 1 - $progress_pct / 100 ), 1 );

// Lessons
$lesson_list = array();
$current_lesson = null;
if ( function_exists( 'learndash_get_lesson_list' ) ) {
    $raw = learndash_get_lesson_list( $course_id );
    if ( is_array( $raw ) ) {
        foreach ( $raw as $i => $l ) {
            $done = function_exists( 'learndash_is_lesson_complete' )
                ? learndash_is_lesson_complete( $user_id, $l->ID, $course_id ) : false;
            $lesson_list[] = array(
                'id' => $l->ID, 'title' => $l->post_title,
                'url' => get_permalink( $l->ID ), 'complete' => $done, 'num' => $i + 1,
            );
            if ( ! $done && ! $current_lesson ) $current_lesson = end( $lesson_list );
        }
    }
}

$cta_url   = $current_lesson ? $current_lesson['url'] : ( ! empty( $lesson_list ) ? $lesson_list[0]['url'] : '#' );
$cta_label = $lessons_done > 0 ? 'Continue learning' : 'Start learning';
$dash_url  = home_url( '/my-dashboard/' );

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
  <title><?php echo esc_html( $course->post_title ); ?> — Maharani Weddings Academy</title>
  <link rel="icon" href="<?php echo esc_url( $theme_url . '/assets/images/favicon.svg' ); ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>">
  <?php wp_head(); ?>
  <style>
.course-hero{background:var(--gray-900);position:relative;overflow:hidden}
.course-hero__bg{position:absolute;inset:0;background:radial-gradient(ellipse at 70% 60%,rgba(200,0,107,.28) 0%,transparent 50%),radial-gradient(ellipse at 20% 30%,rgba(212,160,23,.12) 0%,transparent 45%);background-color:#12060D}
.course-hero__pattern{position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(200,0,107,.05) 0px,rgba(200,0,107,.05) 1px,transparent 1px,transparent 22px),repeating-linear-gradient(-45deg,rgba(200,0,107,.05) 0px,rgba(200,0,107,.05) 1px,transparent 1px,transparent 22px);background-size:22px 22px}
.course-hero__inner{position:relative;z-index:1;max-width:var(--page-max);margin:0 auto;padding:60px var(--space-10) 48px;display:grid;grid-template-columns:1fr auto;gap:var(--space-10);align-items:center}
.course-hero__breadcrumb{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:var(--space-4)}
.course-hero__breadcrumb a{color:rgba(255,255,255,.55);text-decoration:none}
.course-hero__breadcrumb a:hover{color:rgba(255,255,255,.85)}
.course-hero__eyebrow{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--pink-300);margin-bottom:var(--space-3);display:flex;align-items:center;gap:var(--space-2)}
.course-hero__title{font-family:var(--font-display);font-size:44px;font-weight:600;color:#fff;line-height:1.08;letter-spacing:-.02em;margin-bottom:var(--space-4)}
.course-hero__desc{font-size:15px;color:rgba(255,255,255,.75);line-height:1.65;max-width:520px;margin-bottom:var(--space-7)}
.course-hero__meta{display:flex;align-items:center;gap:var(--space-6);margin-bottom:var(--space-7);flex-wrap:wrap}
.course-hero__meta-item{display:flex;align-items:center;gap:var(--space-2);font-size:13px;font-weight:500;color:rgba(255,255,255,.7)}
.course-hero__actions{display:flex;align-items:center;gap:var(--space-3);flex-wrap:wrap}
.btn-on-dark{color:rgba(255,255,255,.85);border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.06)}
.btn-on-dark:hover{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.35)}
.course-hero__ring{display:flex;flex-direction:column;align-items:center;gap:var(--space-4)}
.ring-container{position:relative;width:140px;height:140px}
.ring-container svg{transform:rotate(-90deg)}
.ring-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ring-label__num{font-family:var(--font-display);font-size:32px;font-weight:600;color:#fff;line-height:1}
.ring-label__unit{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.6);margin-top:4px}
.ring-stats{display:flex;gap:var(--space-4);text-align:center}
.ring-stat{display:flex;flex-direction:column;gap:3px}
.ring-stat__num{font-family:var(--font-display);font-size:20px;font-weight:600;color:#fff}
.ring-stat__label{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.55)}
.course-body{max-width:var(--page-max);margin:0 auto;padding:var(--space-10) var(--space-10) var(--space-16);display:grid;grid-template-columns:1fr 300px;gap:var(--space-8);align-items:start}
.lesson-card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden}
.lesson-card__header{padding:var(--space-5) var(--space-6);border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between}
.lesson-card__title{font-family:var(--font-display);font-size:18px;font-weight:600;color:var(--gray-900)}
.lesson-card__progress-text{font-size:12px;font-weight:600;color:var(--gray-600)}
.lesson-card__body{padding:var(--space-3)}
.lesson-row{display:flex;align-items:center;gap:var(--space-4);padding:var(--space-3) var(--space-4);border-radius:var(--radius-md);transition:background var(--transition-fast);text-decoration:none;color:inherit}
.lesson-row:hover{background:var(--gray-50);color:inherit}
.lesson-row.active{background:var(--pink-50)}
.lesson-row.active .lesson-row__title{color:var(--pink-700)}
.lesson-row__num{width:28px;height:28px;border-radius:50%;background:var(--gray-100);color:var(--gray-600);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.lesson-row__num--done{background:var(--color-success-bg);color:var(--color-success)}
.lesson-row__content{flex:1;min-width:0}
.lesson-row__title{font-size:14px;font-weight:500;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lesson-row__meta{font-size:11px;color:var(--gray-500);margin-top:2px}
.lesson-row__right{display:flex;align-items:center;gap:var(--space-2);flex-shrink:0}
.pill{font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);display:inline-flex;align-items:center}
.pill--pink{background:var(--pink-50);color:var(--pink-700);border:1px solid var(--pink-200)}
.pill--green{background:var(--color-success-bg);color:var(--color-success);border:1px solid #BBE5C9}
.sidebar-widget{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden;margin-bottom:var(--space-5)}
.sidebar-widget__header{padding:var(--space-4) var(--space-5);border-bottom:1px solid var(--gray-100)}
.sidebar-widget__title{font-size:14px;font-weight:700;color:var(--gray-700)}
.sidebar-widget__body{padding:var(--space-4) var(--space-5)}
.cert-preview{background:linear-gradient(135deg,var(--pink-50),var(--blush-200));border-radius:var(--radius-lg);padding:var(--space-5);text-align:center;border:1.5px dashed var(--pink-200);margin-bottom:var(--space-4)}
.cert-preview__icon{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--gold-400),var(--gold-600));color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-2);box-shadow:0 3px 12px rgba(212,160,23,.4)}
.cert-preview__title{font-family:var(--font-display);font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:var(--space-1)}
.cert-preview__sub{font-size:11px;color:var(--gray-600)}
.reward-row{display:flex;align-items:center;gap:12px;padding:var(--space-3) 0}
.reward-row+.reward-row{border-top:1px solid var(--gray-100)}
.reward-row__icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff}
.course-sidebar{position:sticky;top:calc(var(--nav-height) + 24px)}
.page-bg{background:var(--gray-50)}
.page-wrapper{padding-top:var(--nav-height)}
@media(max-width:900px){.course-hero__inner{grid-template-columns:1fr;padding:36px var(--space-5) 32px}.course-hero__ring{display:none}.course-body{grid-template-columns:1fr;padding:var(--space-6) var(--space-5) var(--space-10)}.course-sidebar{position:static}.course-hero__title{font-size:30px}}
  </style>
</head>
<body class="page-bg">

<?php mw_render_nav( 'course' ); ?>

<main class="page-wrapper" id="main-content">
  <div class="course-hero">
    <div class="course-hero__bg"></div>
    <div class="course-hero__pattern"></div>
    <div class="course-hero__inner">
      <div>
        <nav class="course-hero__breadcrumb" aria-label="Breadcrumb">
          <a href="<?php echo esc_url( $dash_url ); ?>">My Dashboard</a>
          <span aria-hidden="true">›</span>
          <span style="color:rgba(255,255,255,.85);"><?php echo esc_html( $course->post_title ); ?></span>
        </nav>
        <div class="course-hero__eyebrow">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="m8.21 13.89-1.21 7.11L12 18l5 3-1.21-7.11"/></svg>
          Learning Trail
        </div>
        <h1 class="course-hero__title"><?php echo esc_html( $course->post_title ); ?></h1>
        <?php if ( $course->post_excerpt ) : ?>
        <p class="course-hero__desc"><?php echo esc_html( $course->post_excerpt ); ?></p>
        <?php endif; ?>
        <div class="course-hero__meta">
          <div class="course-hero__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?php echo esc_html( $lessons_total ); ?> lessons
          </div>
          <div class="course-hero__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Earns <?php echo MWA_Gamification::XP_PER_COURSE; ?> XP + Badge
          </div>
        </div>
        <div class="course-hero__actions">
          <a href="<?php echo esc_url( $cta_url ); ?>" class="btn btn-primary btn--lg">
            <?php echo esc_html( $cta_label ); ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
          <a href="<?php echo esc_url( $dash_url ); ?>" class="btn btn-on-dark btn--lg">← Back to dashboard</a>
        </div>
      </div>
      <div class="course-hero__ring">
        <div class="ring-container" role="img" aria-label="<?php echo esc_attr( $progress_pct ); ?> percent complete">
          <svg width="140" height="140" viewBox="0 0 140 140" aria-hidden="true">
            <circle cx="70" cy="70" r="58" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="10"/>
            <circle cx="70" cy="70" r="58" fill="none" stroke="url(#ring-grad)" stroke-width="10" stroke-dasharray="364.4" stroke-dashoffset="<?php echo esc_attr( $ring_offset ); ?>" stroke-linecap="round"/>
            <defs><linearGradient id="ring-grad" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#C8006B"/><stop offset="100%" stop-color="#F5C330"/></linearGradient></defs>
          </svg>
          <div class="ring-label">
            <span class="ring-label__num"><?php echo esc_html( $progress_pct ); ?>%</span>
            <span class="ring-label__unit">complete</span>
          </div>
        </div>
        <div class="ring-stats">
          <div class="ring-stat"><span class="ring-stat__num"><?php echo esc_html( $lessons_done ); ?></span><span class="ring-stat__label">Done</span></div>
          <div class="ring-stat" style="border-left:1px solid rgba(255,255,255,.14);padding-left:16px;"><span class="ring-stat__num"><?php echo esc_html( $lessons_left ); ?></span><span class="ring-stat__label">Left</span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="course-body" id="course-lessons">
    <div>
      <!-- Progress bar -->
      <div style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);padding:var(--space-5) var(--space-6);margin-bottom:var(--space-6);display:flex;align-items:center;gap:var(--space-5);">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:8px;">
            <span>Course progress</span>
            <span style="color:var(--pink-600);"><?php echo esc_html( $lessons_done ); ?> of <?php echo esc_html( $lessons_total ); ?> lessons complete</span>
          </div>
          <div class="progress-bar" style="height:8px;" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%;"></div>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div style="font-family:var(--font-display);font-size:28px;font-weight:600;color:var(--pink-600);line-height:1;"><?php echo esc_html( $progress_pct ); ?>%</div>
          <div style="font-size:11px;color:var(--gray-600);font-weight:600;letter-spacing:.05em;text-transform:uppercase;">Complete</div>
        </div>
      </div>

      <!-- Lesson list -->
      <section class="lesson-card" aria-labelledby="lessons-heading">
        <div class="lesson-card__header">
          <h2 class="lesson-card__title" id="lessons-heading">Course lessons</h2>
          <span class="lesson-card__progress-text"><?php echo esc_html( $lessons_done ); ?> completed · <?php echo esc_html( $lessons_left ); ?> remaining</span>
        </div>
        <div class="lesson-card__body">
          <?php
          $found_active = false;
          foreach ( $lesson_list as $l ) :
            $is_done   = $l['complete'];
            $is_active = ! $is_done && ! $found_active;
            if ( $is_active ) $found_active = true;
            $row_class = $is_active ? 'lesson-row active' : 'lesson-row';
          ?>
          <a href="<?php echo esc_url( $l['url'] ); ?>" class="<?php echo esc_attr( $row_class ); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
            <?php if ( $is_done ) : ?>
            <div class="lesson-row__num lesson-row__num--done" aria-hidden="true">
              <svg width="12" height="12" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="2,7 5,10 12,4"/></svg>
            </div>
            <?php elseif ( $is_active ) : ?>
            <div class="lesson-row__num" style="background:var(--pink-600);color:#fff;" aria-hidden="true">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="white" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
            <?php else : ?>
            <div class="lesson-row__num"><?php echo esc_html( $l['num'] ); ?></div>
            <?php endif; ?>
            <div class="lesson-row__content">
              <div class="lesson-row__title"><?php echo esc_html( $l['title'] ); ?></div>
              <div class="lesson-row__meta">
                <?php if ( $is_active ) : ?>
                <span style="color:var(--pink-500);">Up next</span>
                <?php elseif ( $is_done ) : ?>
                Completed · +<?php echo MWA_Gamification::XP_PER_LESSON; ?> XP
                <?php else : ?>
                Lesson <?php echo esc_html( $l['num'] ); ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="lesson-row__right">
              <?php if ( $is_done ) : ?>
              <span class="pill pill--green">Done</span>
              <?php elseif ( $is_active ) : ?>
              <span class="pill pill--pink">Active</span>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <!-- Sidebar -->
    <aside class="course-sidebar" aria-label="Course rewards">
      <div class="sidebar-widget">
        <div class="sidebar-widget__header"><h2 class="sidebar-widget__title">Your certificate</h2></div>
        <div class="sidebar-widget__body">
          <div class="cert-preview">
            <div class="cert-preview__icon" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="8" r="6"/><path d="m8.21 13.89-1.21 7.11L12 18l5 3-1.21-7.11"/></svg>
            </div>
            <div class="cert-preview__title">Course Completion</div>
            <div class="cert-preview__sub">Issued by Certifier.io</div>
          </div>
          <p style="font-size:13px;color:var(--gray-600);line-height:1.5;margin-bottom:var(--space-4);">
            Complete all <?php echo esc_html( $lessons_total ); ?> lessons to earn your official certification.
          </p>
          <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:6px;">
            <span>Progress</span><span style="color:var(--pink-600);"><?php echo esc_html( $progress_pct ); ?>%</span>
          </div>
          <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%;"></div>
          </div>
        </div>
      </div>
      <div class="sidebar-widget">
        <div class="sidebar-widget__header"><h2 class="sidebar-widget__title">Complete this course to earn</h2></div>
        <div class="sidebar-widget__body" style="padding-top:4px;">
          <div class="reward-row">
            <div class="reward-row__icon" style="background:linear-gradient(135deg,#F5C330,#D4A017);" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="8" r="6"/><path d="m8.21 13.89-1.21 7.11L12 18l5 3-1.21-7.11"/></svg>
            </div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-800);">Course Completion Badge</div>
              <div style="font-size:11px;color:var(--gray-600);">Displayed on your profile</div>
            </div>
          </div>
          <div class="reward-row">
            <div class="reward-row__icon" style="background:var(--pink-50);color:var(--pink-600);border:2px solid var(--pink-200);" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-800);">+<?php echo MWA_Gamification::XP_PER_COURSE; ?> XP</div>
              <div style="font-size:11px;color:var(--gray-600);">Advances your Academy level</div>
            </div>
          </div>
          <div class="reward-row">
            <div class="reward-row__icon" style="background:#EBF5FF;color:#2563EB;border:2px solid #BDD3F3;" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-800);">Official certificate</div>
              <div style="font-size:11px;color:var(--gray-600);">Issued by Certifier.io · LinkedIn ready</div>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</main>

<script src="<?php echo esc_url( $theme_url . '/assets/js/academy.js' ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
