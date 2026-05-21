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
/* ── Quiz Progress Bar ── */
.quiz-progress{padding:var(--space-4) var(--space-8);border-bottom:1px solid var(--gray-100);background:linear-gradient(135deg,#fdf8f0 0%,#fff9f0 100%)}
.quiz-progress__top{display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-2)}
.quiz-progress__label{font-size:14px;font-weight:600;color:var(--gray-700)}
.quiz-progress__label span{color:var(--pink-600)}
.quiz-progress__count{font-size:12px;font-weight:700;color:var(--gold-600);background:var(--gold-50);padding:2px 10px;border-radius:var(--radius-full);border:1px solid var(--gold-200)}
.quiz-progress__track{width:100%;height:8px;background:var(--gray-100);border-radius:var(--radius-full);overflow:hidden;position:relative}
.quiz-progress__fill{height:100%;background:linear-gradient(90deg,var(--pink-500),var(--gold-500));border-radius:var(--radius-full);transition:width .5s cubic-bezier(.4,0,.2,1);position:relative;min-width:4px}
.quiz-progress__fill::after{content:'';position:absolute;right:0;top:0;bottom:0;width:20px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4));border-radius:var(--radius-full);animation:progressShimmer 1.5s ease-in-out infinite}
@keyframes progressShimmer{0%,100%{opacity:0}50%{opacity:1}}
/* ── Celebration ── */
#mwa-celebration-canvas{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99998}
.mwa-celebrate-overlay{position:fixed;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;z-index:99999;pointer-events:none;opacity:0;transition:opacity .6s ease}
.mwa-celebrate-overlay.active{opacity:1}
.mwa-celebrate-card{background:linear-gradient(135deg,#fff9f0 0%,#fff5e6 50%,#fff0f5 100%);border:2px solid var(--gold-400);border-radius:24px;padding:40px 48px;text-align:center;max-width:480px;box-shadow:0 20px 60px rgba(212,160,23,.3),0 0 80px rgba(196,38,110,.15);pointer-events:auto;transform:scale(.8);transition:transform .5s cubic-bezier(.34,1.56,.64,1);position:relative;overflow:hidden}
.mwa-celebrate-overlay.active .mwa-celebrate-card{transform:scale(1)}
.mwa-celebrate-card::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:conic-gradient(from 0deg,transparent,rgba(212,160,23,.08),transparent,rgba(196,38,110,.06),transparent);animation:celebRotate 8s linear infinite}
@keyframes celebRotate{to{transform:rotate(360deg)}}
.mwa-celebrate-emoji{font-size:56px;margin-bottom:12px;animation:celebBounce 1s ease-in-out infinite alternate}
@keyframes celebBounce{0%{transform:translateY(0) scale(1)}100%{transform:translateY(-8px) scale(1.1)}}
.mwa-celebrate-title{font-family:var(--font-display);font-size:28px;font-weight:700;background:linear-gradient(135deg,var(--gold-600),var(--pink-600));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:8px;position:relative}
.mwa-celebrate-sub{font-size:15px;color:var(--gray-600);margin-bottom:20px;line-height:1.5}
.mwa-celebrate-xp{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--gold-400),var(--gold-600));color:#fff;padding:10px 24px;border-radius:var(--radius-full);font-size:16px;font-weight:700;box-shadow:0 4px 16px rgba(212,160,23,.4);animation:celebPulse 2s ease-in-out infinite}
@keyframes celebPulse{0%,100%{box-shadow:0 4px 16px rgba(212,160,23,.4)}50%{box-shadow:0 4px 24px rgba(212,160,23,.7)}}
.mwa-celebrate-dismiss{margin-top:16px;font-size:13px;color:var(--gray-400);cursor:pointer;border:none;background:none;padding:4px 8px}
.mwa-celebrate-dismiss:hover{color:var(--gray-600)}
.mwa-sparkle{position:absolute;pointer-events:none;animation:sparkle 1.5s ease-out forwards}
@keyframes sparkle{0%{opacity:1;transform:scale(0) rotate(0deg)}50%{opacity:1;transform:scale(1) rotate(180deg)}100%{opacity:0;transform:scale(.5) rotate(360deg)}}
.page-bg{background:var(--gray-50)}
.page-wrapper{padding-top:var(--nav-height)}
@media(max-width:768px){.quiz-layout{padding:var(--space-5)}.quiz-card__header,.quiz-card__body,.quiz-card__footer,.quiz-progress{padding-left:var(--space-5);padding-right:var(--space-5)}.mwa-celebrate-card{margin:16px;padding:28px 24px}.mwa-celebrate-title{font-size:22px}}
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
      <!-- Progress bar -->
      <div class="quiz-progress" id="mwa-quiz-progress" style="display:none;">
        <div class="quiz-progress__top">
          <div class="quiz-progress__label">Completed Question <span id="mwa-q-current">1</span> of <span id="mwa-q-total">?</span></div>
          <div class="quiz-progress__count" id="mwa-q-pct">0%</div>
        </div>
        <div class="quiz-progress__track">
          <div class="quiz-progress__fill" id="mwa-q-fill" style="width:0%;"></div>
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
<script>
(function(){
  'use strict';
  var progressBar = document.getElementById('mwa-quiz-progress');
  var currentEl   = document.getElementById('mwa-q-current');
  var totalEl     = document.getElementById('mwa-q-total');
  var pctEl       = document.getElementById('mwa-q-pct');
  var fillEl      = document.getElementById('mwa-q-fill');
  if (!progressBar) return;

  function updateProgress() {
    var questions = document.querySelectorAll('.wpProQuiz_listItem');
    var total = questions.length;
    if (total === 0) return;

    // Show the progress bar once we detect questions
    progressBar.style.display = '';
    totalEl.textContent = total;

    // Find which question is currently visible
    var current = 0;
    questions.forEach(function(q, i) {
      if (q.style.display !== 'none') current = i + 1;
    });

    // Count answered questions (any with a checked input)
    var answered = 0;
    questions.forEach(function(q) {
      var checked = q.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
      if (checked) answered++;
    });

    // Percentage = current question / total
    var pct = Math.round((current / total) * 100);

    currentEl.textContent = current;
    pctEl.textContent = pct + '%';
    fillEl.style.width = pct + '%';
  }

  // Initial check with delay (WPProQuiz initializes async)
  var initInterval = setInterval(function() {
    var questions = document.querySelectorAll('.wpProQuiz_listItem');
    if (questions.length > 0) {
      clearInterval(initInterval);
      updateProgress();
    }
  }, 200);

  // Watch for clicks on Next/Back/Check buttons
  document.addEventListener('click', function(e) {
    var btn = e.target;
    if (btn.classList.contains('wpProQuiz_button') ||
        btn.classList.contains('wpProQuiz_button2') ||
        btn.tagName === 'INPUT' && btn.type === 'submit' ||
        btn.name === 'next' || btn.name === 'back') {
      setTimeout(updateProgress, 100);
      setTimeout(updateProgress, 400);
    }
  }, true);

  // Also watch for answer selections
  document.addEventListener('change', function(e) {
    if (e.target.type === 'radio' || e.target.type === 'checkbox') {
      setTimeout(updateProgress, 50);
    }
  }, true);

  // MutationObserver as fallback for any DOM changes in the quiz area
  var quizBody = document.querySelector('.quiz-card__body');
  if (quizBody && typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function() {
      setTimeout(updateProgress, 100);
    });
    observer.observe(quizBody, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
  }
})();
</script>
<script>
/* ── Maharani Celebration Animation ── */
(function(){
  'use strict';
  var triggered = false;

  // Maharani color palette
  var COLORS = [
    '#D4A017', // Gold
    '#FFD700', // Bright gold
    '#C4266E', // Maharani pink
    '#FF6B9D', // Light pink
    '#FF8C00', // Marigold orange
    '#E65100', // Deep orange
    '#8B0000', // Deep red
    '#FFE082', // Soft gold
    '#F48FB1', // Rose
    '#FFAB40', // Amber
  ];

  var SHAPES = ['circle', 'square', 'star', 'petal'];

  function createCanvas() {
    var c = document.createElement('canvas');
    c.id = 'mwa-celebration-canvas';
    c.width = window.innerWidth;
    c.height = window.innerHeight;
    document.body.appendChild(c);
    return c;
  }

  function launchConfetti() {
    var canvas = createCanvas();
    var ctx = canvas.getContext('2d');
    var particles = [];
    var particleCount = 180;

    // Resize handler
    window.addEventListener('resize', function() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    });

    // Create particles
    for (var i = 0; i < particleCount; i++) {
      particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height - canvas.height,
        r: Math.random() * 6 + 3,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
        shape: SHAPES[Math.floor(Math.random() * SHAPES.length)],
        vx: (Math.random() - 0.5) * 4,
        vy: Math.random() * 3 + 2,
        rotation: Math.random() * 360,
        rotationSpeed: (Math.random() - 0.5) * 8,
        opacity: 1,
        wobble: Math.random() * 10,
        wobbleSpeed: Math.random() * 0.05 + 0.02,
        phase: Math.random() * Math.PI * 2
      });
    }

    var startTime = Date.now();
    var duration = 6000;

    function drawStar(ctx, x, y, r, rot) {
      ctx.save(); ctx.translate(x, y); ctx.rotate(rot);
      ctx.beginPath();
      for (var j = 0; j < 5; j++) {
        ctx.lineTo(Math.cos((j * 4 * Math.PI / 5) - Math.PI/2) * r,
                   Math.sin((j * 4 * Math.PI / 5) - Math.PI/2) * r);
      }
      ctx.closePath(); ctx.fill(); ctx.restore();
    }

    function drawPetal(ctx, x, y, r, rot) {
      ctx.save(); ctx.translate(x, y); ctx.rotate(rot);
      ctx.beginPath();
      ctx.ellipse(0, 0, r, r * 2.5, 0, 0, Math.PI * 2);
      ctx.fill(); ctx.restore();
    }

    function animate() {
      var elapsed = Date.now() - startTime;
      if (elapsed > duration) {
        canvas.remove();
        return;
      }

      ctx.clearRect(0, 0, canvas.width, canvas.height);
      var fadeOut = elapsed > duration - 1500 ? 1 - (elapsed - (duration - 1500)) / 1500 : 1;

      particles.forEach(function(p) {
        p.x += p.vx + Math.sin(p.phase) * p.wobble * 0.1;
        p.y += p.vy;
        p.rotation += p.rotationSpeed;
        p.phase += p.wobbleSpeed;
        p.opacity = fadeOut;

        if (p.y > canvas.height + 20) {
          p.y = -20;
          p.x = Math.random() * canvas.width;
        }

        ctx.globalAlpha = p.opacity * 0.9;
        ctx.fillStyle = p.color;
        var rad = p.rotation * Math.PI / 180;

        if (p.shape === 'circle') {
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fill();
        } else if (p.shape === 'square') {
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(rad);
          ctx.fillRect(-p.r, -p.r, p.r * 2, p.r * 2);
          ctx.restore();
        } else if (p.shape === 'star') {
          drawStar(ctx, p.x, p.y, p.r, rad);
        } else {
          drawPetal(ctx, p.x, p.y, p.r * 0.6, rad);
        }
      });

      ctx.globalAlpha = 1;
      requestAnimationFrame(animate);
    }
    animate();
  }

  function showCelebration(score, total) {
    // Create overlay
    var overlay = document.createElement('div');
    overlay.className = 'mwa-celebrate-overlay';
    overlay.innerHTML = [
      '<div class="mwa-celebrate-card">',
      '  <div class="mwa-celebrate-emoji">🪔✨🎉</div>',
      '  <div class="mwa-celebrate-title">Congratulations!</div>',
      '  <div class="mwa-celebrate-sub">You passed with flying colors!<br>',
      '    <strong>' + score + ' of ' + total + '</strong> questions answered correctly.</div>',
      '  <div class="mwa-celebrate-xp">⭐ +100 XP Earned!</div>',
      '  <button class="mwa-celebrate-dismiss" onclick="this.closest(\'.mwa-celebrate-overlay\').remove()">Click to continue</button>',
      '</div>'
    ].join('');
    document.body.appendChild(overlay);

    // Add sparkles to the card
    var card = overlay.querySelector('.mwa-celebrate-card');
    for (var i = 0; i < 12; i++) {
      (function(delay) {
        setTimeout(function() {
          var sparkle = document.createElement('div');
          sparkle.className = 'mwa-sparkle';
          sparkle.style.left = Math.random() * 100 + '%';
          sparkle.style.top = Math.random() * 100 + '%';
          sparkle.style.fontSize = (Math.random() * 16 + 10) + 'px';
          sparkle.textContent = ['✦','✧','❋','✺','✵'][Math.floor(Math.random() * 5)];
          sparkle.style.color = COLORS[Math.floor(Math.random() * COLORS.length)];
          card.appendChild(sparkle);
          setTimeout(function() { sparkle.remove(); }, 1500);
        }, delay);
      })(i * 200);
    }

    // Animate in
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        overlay.classList.add('active');
      });
    });

    // Launch confetti
    launchConfetti();

    // Second burst after 1.5s
    setTimeout(launchConfetti, 1500);

    // Auto-dismiss after 8s
    setTimeout(function() {
      if (overlay.parentNode) {
        overlay.style.transition = 'opacity 1s ease';
        overlay.style.opacity = '0';
        setTimeout(function() { overlay.remove(); }, 1000);
      }
    }, 8000);
  }

  function checkForPass() {
    if (triggered) return;

    // Look for the WPProQuiz results box
    var resultsBox = document.querySelector('.wpProQuiz_results');
    if (!resultsBox) return;
    if (resultsBox.style.display === 'none' || resultsBox.offsetParent === null) return;

    // Check if there's a Continue button (only shown on pass)
    var continueBtn = resultsBox.querySelector('.wpProQuiz_button2[name="continue"], input[name="continue"]');
    if (!continueBtn) {
      // Alternative: check the results text for pass indicator
      var resultText = resultsBox.textContent || '';
      // If it says "You have reached X of Y" parse it
      var match = resultText.match(/(\d+)\s+of\s+(\d+)\s+point/);
      if (match) {
        var score = parseInt(match[1]);
        var total = parseInt(match[2]);
        var pct = (score / total) * 100;
        if (pct < 70) return; // Didn't pass (default threshold)
      } else {
        return;
      }
    }

    // Parse score from results text
    var txt = resultsBox.textContent || '';
    var scoreMatch = txt.match(/(\d+)\s+of\s+(\d+)\s+Questions?\s+answered/);
    var sc = scoreMatch ? parseInt(scoreMatch[1]) : '?';
    var tot = scoreMatch ? parseInt(scoreMatch[2]) : '?';

    triggered = true;
    setTimeout(function() { showCelebration(sc, tot); }, 500);
  }

  // Watch for results appearing
  var quizBody = document.querySelector('.quiz-card__body');
  if (quizBody && typeof MutationObserver !== 'undefined') {
    var obs = new MutationObserver(function() {
      setTimeout(checkForPass, 300);
    });
    obs.observe(quizBody, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
  }

  // Also check on page load (in case results are already shown)
  setTimeout(checkForPass, 1000);
  setTimeout(checkForPass, 2000);
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
