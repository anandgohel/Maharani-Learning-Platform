<?php
/**
 * Template Name: Academy Dashboard
 * Maharani Academy — Learner Dashboard (Trailhead-style)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( home_url() ) ); exit; }

$user_id   = get_current_user_id();
$user      = wp_get_current_user();
$gdata     = MWA_Gamification::get_user_data( $user_id );
$initial   = strtoupper( mb_substr( $user->display_name, 0, 1 ) );
$course_id = 63; // Main course ID

// Course progress
$progress_pct = 0;
$lessons_done = 0;
$lessons_total = 0;
$current_lesson = null;

if ( function_exists( 'learndash_course_progress' ) ) {
    $prog = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
    $progress_pct  = isset( $prog['percentage'] ) ? $prog['percentage'] : 0;
    $lessons_done  = isset( $prog['completed'] ) ? $prog['completed'] : 0;
    $lessons_total = isset( $prog['total'] ) ? $prog['total'] : 0;
}

// Get lesson list for trail
$lessons = array();
if ( function_exists( 'learndash_get_lesson_list' ) ) {
    $lesson_list = learndash_get_lesson_list( $course_id );
    if ( is_array( $lesson_list ) ) {
        foreach ( $lesson_list as $i => $lesson ) {
            $is_complete = function_exists( 'learndash_is_lesson_complete' )
                ? learndash_is_lesson_complete( $user_id, $lesson->ID, $course_id )
                : false;
            $lessons[] = array(
                'id'       => $lesson->ID,
                'title'    => $lesson->post_title,
                'url'      => get_permalink( $lesson->ID ),
                'complete' => $is_complete,
                'num'      => $i + 1,
            );
            if ( ! $is_complete && ! $current_lesson ) {
                $current_lesson = end( $lessons );
            }
        }
    }
}

$has_progress = $lessons_done > 0;
$greeting     = MWA_Gamification::get_greeting( $user_id );
$course_title = get_the_title( $course_id );

// Badge rendering
$badge_defs   = MWA_Gamification::get_badge_definitions();
$earned       = $gdata['badges_earned'];

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your Maharani Weddings Academy learning dashboard.">
  <title>My Dashboard — Maharani Weddings Academy</title>
  <link rel="icon" href="<?php echo esc_url( MW_ACADEMY_URL . '/assets/images/favicon.svg' ); ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?php echo esc_url( MW_ACADEMY_URL . '/assets/css/academy.css' ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>">
  <?php wp_head(); ?>
</head>
<body class="page-bg">

<style>
/* Dashboard page-specific styles from the UI kit */
.dash-hero{background:var(--gray-900);position:relative;overflow:hidden;padding:var(--space-10) var(--space-16) var(--space-12);margin-bottom:var(--space-10)}
.dash-hero__bg{position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(200,0,107,.3) 0%,transparent 55%),radial-gradient(ellipse at 10% 80%,rgba(212,160,23,.15) 0%,transparent 45%);background-color:#12060D}
.dash-hero__pattern{position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(200,0,107,.05) 0px,rgba(200,0,107,.05) 1px,transparent 1px,transparent 24px),repeating-linear-gradient(-45deg,rgba(200,0,107,.05) 0px,rgba(200,0,107,.05) 1px,transparent 1px,transparent 24px);background-size:24px 24px}
.dash-hero__circle{position:absolute;right:-60px;top:-60px;width:320px;height:320px;border-radius:50%;border:1px solid rgba(200,0,107,.12);box-shadow:0 0 0 50px rgba(200,0,107,.04),0 0 0 100px rgba(200,0,107,.025)}
.dash-hero__content{position:relative;z-index:1;display:flex;align-items:center;gap:var(--space-6);max-width:var(--page-max);margin:0 auto;flex-wrap:wrap}
.dash-hero__avatar{width:64px;height:64px;border-radius:50%;background:var(--pink-600);color:#fff;font-size:24px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:3px solid rgba(255,255,255,.2)}
.dash-hero__greeting{flex:1;min-width:220px}
.dash-hero__eyebrow{font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--pink-300);margin-bottom:6px}
.dash-hero__name{font-family:var(--font-display);font-size:34px;font-weight:500;color:#fff;line-height:1.1;margin-bottom:8px;letter-spacing:-.01em}
.dash-hero__streak{display:flex;align-items:center;gap:var(--space-2);font-size:14px;color:rgba(255,255,255,.8)}
.dash-hero__streak svg{color:#FF80B8;flex-shrink:0}
.dash-hero__stats{display:flex;gap:var(--space-7);align-items:center}
.dash-hero__stat{display:flex;flex-direction:column;align-items:center;gap:4px}
.dash-hero__stat-num{font-family:var(--font-display);font-size:26px;font-weight:600;color:#fff;line-height:1}
.dash-hero__stat-label{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.55)}
.dash-hero__stat-divider{width:1px;height:40px;background:rgba(255,255,255,.14)}
.dash-hero__xp-bar{margin-top:16px;max-width:320px}
.dash-hero__xp-top{display:flex;justify-content:space-between;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);margin-bottom:6px;letter-spacing:.05em}
.dash-hero__xp-track{height:6px;background:rgba(255,255,255,.14);border-radius:3px;overflow:hidden}
.dash-hero__xp-fill{height:100%;background:linear-gradient(90deg,var(--pink-500),var(--gold-400));border-radius:3px;transition:width 1s cubic-bezier(.4,0,.2,1)}
.dash-grid{max-width:var(--page-max);margin:0 auto;padding:0 var(--space-8) var(--space-16);display:grid;grid-template-columns:1fr 320px;gap:var(--space-8)}
.dash-main{min-width:0}.dash-side{min-width:0}
.continue-card{background:#fff;border-radius:var(--radius-xl);border:1px solid var(--gray-200);overflow:hidden;display:grid;grid-template-columns:1fr auto;margin-bottom:var(--space-8);transition:box-shadow var(--transition-slow);text-decoration:none;color:inherit}
.continue-card:hover{box-shadow:var(--shadow-md);color:inherit}
.continue-card__body{padding:var(--space-7)}
.continue-card__eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--pink-600);margin-bottom:var(--space-2);display:flex;align-items:center;gap:var(--space-2)}
.continue-card__title{font-family:var(--font-display);font-size:24px;font-weight:600;color:var(--gray-900);margin-bottom:var(--space-2);line-height:1.15}
.continue-card__lesson{font-size:14px;color:var(--gray-600);margin-bottom:var(--space-5)}
.continue-card__progress-label{display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:var(--space-2)}
.continue-card__image{width:200px;background:linear-gradient(135deg,var(--pink-100),var(--blush-200));display:flex;align-items:center;justify-content:center;border-left:1px solid var(--gray-100);position:relative}
.section-header{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:var(--space-5)}
.section-header__title{font-family:var(--font-display);font-size:22px;font-weight:600;color:var(--gray-900);line-height:1.2}
.section-header__subtitle{font-size:13px;color:var(--gray-500);margin-top:2px}
.badge-section,.trail-card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl);overflow:hidden;margin-bottom:var(--space-5)}
.badge-section__header,.trail-card__header{padding:var(--space-5) var(--space-6);border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between}
.badge-section__title,.trail-card__title{font-family:var(--font-display);font-size:18px;font-weight:600;color:var(--gray-900)}
.badge-section__body,.trail-card__body{padding:var(--space-5) var(--space-6)}
.badge-row{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--space-3)}
.badge-thumb{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center}
.badge-thumb__icon{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;transition:transform var(--transition-slow)}
.badge-thumb__icon:hover{transform:scale(1.08) rotate(-3deg)}
.badge-thumb__icon--earned{background:linear-gradient(135deg,var(--gold-400),var(--gold-600));box-shadow:0 3px 12px rgba(212,160,23,.4)}
.badge-thumb__icon--earned-pink{background:linear-gradient(135deg,var(--pink-300),var(--pink-600));box-shadow:0 3px 12px rgba(200,0,107,.4)}
.badge-thumb__icon--locked{background:var(--gray-100);border:2px dashed var(--gray-300);color:var(--gray-500)}
.badge-thumb__label{font-size:10px;font-weight:600;color:var(--gray-700);line-height:1.2}
.badge-thumb--locked .badge-thumb__label{color:var(--gray-500)}
.trail-step{display:flex;gap:var(--space-3);align-items:flex-start;padding-bottom:var(--space-4);position:relative}
.trail-step:not(:last-child)::before{content:'';position:absolute;left:13px;top:28px;bottom:0;width:1.5px;background:var(--gray-200)}
.trail-step--done:not(:last-child)::before{background:var(--color-success)}
.trail-step--active:not(:last-child)::before{background:linear-gradient(to bottom,var(--pink-600),var(--gray-200))}
.trail-step__dot{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;z-index:1}
.trail-step__dot--done{background:var(--color-success);color:#fff}
.trail-step__dot--active{background:var(--pink-600);color:#fff;box-shadow:0 0 0 3px var(--pink-100)}
.trail-step__dot--upcoming{background:#fff;border:2px dashed var(--gray-300);color:var(--gray-500)}
.trail-step__content{flex:1;padding-top:3px}
.trail-step__name{font-size:13px;font-weight:600;color:var(--gray-800);margin-bottom:2px}
.trail-step--upcoming .trail-step__name{color:var(--gray-500)}
.trail-step--active .trail-step__name{color:var(--pink-700)}
.trail-step__meta{font-size:11px;color:var(--gray-500)}
.empty-state{text-align:center;padding:var(--space-12) var(--space-8);background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-xl)}
.empty-state__icon{width:64px;height:64px;border-radius:50%;background:var(--pink-50);color:var(--pink-600);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-5)}
.empty-state__title{font-family:var(--font-display);font-size:22px;font-weight:600;color:var(--gray-900);margin-bottom:var(--space-2)}
.empty-state__desc{font-size:14px;color:var(--gray-600);max-width:400px;margin:0 auto var(--space-6);line-height:1.6}
.pill{font-size:11px;font-weight:600;padding:3px 10px;border-radius:var(--radius-full);display:inline-flex;align-items:center}
.pill--pink{background:var(--pink-50);color:var(--pink-700);border:1px solid var(--pink-200)}
.pill--gray{background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200)}
.page-bg{background:var(--gray-50)}
.page-wrapper{padding-top:var(--nav-height)}
@media(max-width:1024px){.dash-grid{grid-template-columns:1fr}.dash-side{order:-1}}
@media(max-width:768px){.dash-hero{padding:var(--space-8) var(--space-5)}.dash-hero__stats{flex-wrap:wrap;gap:var(--space-4) var(--space-5)}.dash-hero__stat-divider{display:none}.dash-hero__name{font-size:28px}.dash-grid{padding:0 var(--space-5) var(--space-10)}.continue-card{grid-template-columns:1fr}.continue-card__image{display:none}}
</style>

<?php mw_render_nav( 'dashboard' ); ?>

<main class="page-wrapper" id="main-content">
  <!-- HERO -->
  <div class="dash-hero">
    <div class="dash-hero__bg"></div>
    <div class="dash-hero__pattern"></div>
    <div class="dash-hero__circle"></div>
    <div class="dash-hero__content">
      <div class="dash-hero__avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></div>
      <div class="dash-hero__greeting">
        <p class="dash-hero__eyebrow">Welcome back</p>
        <h1 class="dash-hero__name"><?php echo esc_html( $greeting ); ?></h1>
        <?php if ( $gdata['streak'] > 0 ) : ?>
        <div class="dash-hero__streak">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14a8 8 0 0 0 16 0c0-4.18-2.13-7.93-6.5-13.33z"/></svg>
          <span><?php echo esc_html( $gdata['streak_text'] ); ?></span>
        </div>
        <?php endif; ?>
        <div class="dash-hero__xp-bar">
          <div class="dash-hero__xp-top">
            <span>LEVEL <?php echo esc_html( $gdata['level'] ); ?> · <?php echo esc_html( $gdata['level_name'] ); ?></span>
            <span><?php echo esc_html( number_format( $gdata['xp'] ) ); ?> / <?php echo esc_html( number_format( $gdata['xp_to_next'] ) ); ?> XP</span>
          </div>
          <div class="dash-hero__xp-track" role="progressbar" aria-valuenow="<?php echo esc_attr( $gdata['xp_percent'] ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Level progress">
            <div class="dash-hero__xp-fill" style="width:<?php echo esc_attr( $gdata['xp_percent'] ); ?>%;"></div>
          </div>
        </div>
      </div>
      <div class="dash-hero__stats" aria-label="Your stats">
        <div class="dash-hero__stat">
          <span class="dash-hero__stat-num"><?php echo esc_html( $gdata['badges_count'] ); ?></span>
          <span class="dash-hero__stat-label">Badges earned</span>
        </div>
        <div class="dash-hero__stat-divider" aria-hidden="true"></div>
        <div class="dash-hero__stat">
          <span class="dash-hero__stat-num"><?php echo esc_html( $progress_pct ); ?>%</span>
          <span class="dash-hero__stat-label">Course complete</span>
        </div>
        <div class="dash-hero__stat-divider" aria-hidden="true"></div>
        <div class="dash-hero__stat">
          <span class="dash-hero__stat-num"><?php echo esc_html( $lessons_done ); ?></span>
          <span class="dash-hero__stat-label">Lessons done</span>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="dash-grid">
    <div class="dash-main">
      <?php if ( $has_progress && $current_lesson ) : ?>
      <!-- CONTINUE LEARNING -->
      <div class="section-header">
        <div><h2 class="section-header__title">Continue learning</h2><p class="section-header__subtitle">Pick up right where you left off</p></div>
      </div>
      <a href="<?php echo esc_url( $current_lesson['url'] ); ?>" class="continue-card">
        <div class="continue-card__body">
          <div class="continue-card__eyebrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Continue — <?php echo esc_html( $course_title ); ?>
          </div>
          <h3 class="continue-card__title"><?php echo esc_html( $current_lesson['title'] ); ?></h3>
          <p class="continue-card__lesson">Lesson <?php echo esc_html( $current_lesson['num'] ); ?> of <?php echo esc_html( $lessons_total ); ?></p>
          <div class="continue-card__progress-label">
            <span>Your progress</span>
            <span style="color:var(--pink-600);"><?php echo esc_html( $progress_pct ); ?>% complete</span>
          </div>
          <div class="progress-bar" style="margin-bottom:20px;" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%;"></div>
          </div>
          <span class="btn btn-primary">Resume lesson <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
        </div>
        <div class="continue-card__image" aria-hidden="true"></div>
      </a>
      <?php else : ?>
      <!-- EMPTY STATE -->
      <div class="section-header">
        <div><h2 class="section-header__title">Welcome to the Academy</h2><p class="section-header__subtitle">Your learning journey starts here</p></div>
      </div>
      <div class="empty-state">
        <div class="empty-state__icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <h3 class="empty-state__title">Ready to begin?</h3>
        <p class="empty-state__desc">Start with <strong><?php echo esc_html( $course_title ); ?></strong> — your first lesson takes about a minute and earns you your first badge.</p>
        <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="btn btn-primary btn--lg">Start your first course <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
      </div>
      <?php endif; ?>

      <!-- COURSES -->
      <div class="section-header" style="margin-top:var(--space-8);">
        <div><h2 class="section-header__title">My courses</h2><p class="section-header__subtitle">Your enrolled learning trails</p></div>
      </div>
      <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="continue-card" style="margin-bottom:0;">
        <div class="continue-card__body">
          <h3 class="continue-card__title"><?php echo esc_html( $course_title ); ?></h3>
          <p class="continue-card__lesson"><?php echo esc_html( $lessons_total ); ?> lessons · Certificate</p>
          <div class="continue-card__progress-label">
            <span><?php echo esc_html( $progress_pct ); ?>% complete</span>
            <span><?php echo esc_html( $lessons_done ); ?>/<?php echo esc_html( $lessons_total ); ?> lessons</span>
          </div>
          <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress_pct ); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar__fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%;"></div>
          </div>
        </div>
      </a>
    </div>

    <!-- SIDEBAR -->
    <aside class="dash-side" aria-label="Achievements and trail">
      <!-- BADGES -->
      <div class="badge-section">
        <div class="badge-section__header">
          <h2 class="badge-section__title">My badges</h2>
        </div>
        <div class="badge-section__body">
          <div class="badge-row">
            <?php
            $i = 0;
            foreach ( $badge_defs as $key => $badge ) :
              $is_earned = in_array( $key, $earned );
              $color = ( $i % 2 === 0 ) ? 'earned' : 'earned-pink';
              $i++;
            ?>
            <div class="badge-thumb<?php echo ! $is_earned ? ' badge-thumb--locked' : ''; ?>">
              <div class="badge-thumb__icon badge-thumb__icon--<?php echo $is_earned ? esc_attr( $color ) : 'locked'; ?>" title="<?php echo esc_attr( $badge['name'] ); ?>">
                <?php if ( $is_earned ) : ?>
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="m8.21 13.89-1.21 7.11L12 18l5 3-1.21-7.11"/></svg>
                <?php else : ?>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <?php endif; ?>
              </div>
              <span class="badge-thumb__label"><?php echo $is_earned ? esc_html( $badge['name'] ) : 'Locked'; ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--gray-100);text-align:center;">
            <span style="font-size:12px;color:var(--gray-500);font-weight:500;"><?php echo esc_html( $gdata['badges_count'] ); ?> earned · <?php echo esc_html( $gdata['badges_remaining'] ); ?> more to unlock</span>
          </div>
        </div>
      </div>

      <!-- LEARNING TRAIL -->
      <div class="trail-card">
        <div class="trail-card__header">
          <h2 class="trail-card__title">Learning trail</h2>
          <span class="pill pill--pink"><?php echo $progress_pct >= 100 ? 'Complete' : 'In progress'; ?></span>
        </div>
        <div class="trail-card__body">
          <?php
          $found_active = false;
          $show_max = min( count( $lessons ), 8 );
          for ( $j = 0; $j < $show_max; $j++ ) :
            $l = $lessons[ $j ];
            if ( $l['complete'] ) {
                $step_class = 'trail-step--done';
                $dot_class  = 'trail-step__dot--done';
                $dot_inner  = '<svg width="12" height="12" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="2.5,7 5.5,10 11.5,4"/></svg>';
                $meta       = 'Completed · +' . MWA_Gamification::XP_PER_LESSON . ' XP';
            } elseif ( ! $found_active ) {
                $step_class = 'trail-step--active';
                $dot_class  = 'trail-step__dot--active';
                $dot_inner  = '▸';
                $meta       = '<span style="color:var(--pink-500);">In progress</span>';
                $found_active = true;
            } else {
                $step_class = 'trail-step--upcoming';
                $dot_class  = 'trail-step__dot--upcoming';
                $dot_inner  = $l['num'];
                $meta       = 'Upcoming';
            }
          ?>
          <div class="trail-step <?php echo esc_attr( $step_class ); ?>">
            <div class="trail-step__dot <?php echo esc_attr( $dot_class ); ?>" aria-hidden="true"><?php echo $dot_inner; ?></div>
            <div class="trail-step__content">
              <div class="trail-step__name"><?php echo esc_html( $l['title'] ); ?></div>
              <div class="trail-step__meta"><?php echo $meta; ?></div>
            </div>
          </div>
          <?php endfor; ?>
          <?php if ( count( $lessons ) > $show_max ) : ?>
          <div style="text-align:center;padding-top:var(--space-2);">
            <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" style="font-size:12px;font-weight:600;color:var(--pink-600);">View all <?php echo count( $lessons ); ?> lessons →</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </div>
</main>

<script src="<?php echo esc_url( MW_ACADEMY_URL . '/assets/js/academy.js' ); ?>?v=<?php echo MW_ACADEMY_VERSION; ?>"></script>
<?php wp_footer(); ?>
</body>
</html>
