<?php
/**
 * Maharani Academy — Child Theme Functions
 *
 * Sets up the Academy design system, gamification, and LearnDash template overrides.
 * Parent theme: Astra.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MW_ACADEMY_DIR', get_stylesheet_directory() );
define( 'MW_ACADEMY_URL', get_stylesheet_directory_uri() );
define( 'MW_ACADEMY_VERSION', '1.0.0' );

// ── Includes ──
require_once MW_ACADEMY_DIR . '/includes/class-mwa-gamification.php';

// ── Init ──
add_action( 'after_setup_theme', 'mw_academy_setup' );
function mw_academy_setup() {
    MWA_Gamification::init();
}

// ── Enqueue Assets ──
add_action( 'wp_enqueue_scripts', 'mw_academy_enqueue', 20 );
function mw_academy_enqueue() {
    // Dequeue Astra's default styles on Academy pages
    if ( mw_is_academy_page() ) {
        wp_dequeue_style( 'astra-theme-css' );
        wp_dequeue_style( 'astra-addon-css' );

        // Academy design system
        wp_enqueue_style(
            'mw-academy',
            MW_ACADEMY_URL . '/assets/css/academy.css',
            array(),
            MW_ACADEMY_VERSION
        );

        // Academy JS (drawer, password toggles)
        wp_enqueue_script(
            'mw-academy-js',
            MW_ACADEMY_URL . '/assets/js/academy.js',
            array(),
            MW_ACADEMY_VERSION,
            true
        );
    }
}

/**
 * Check if current page is an Academy page (LearnDash content).
 */
function mw_is_academy_page() {
    if ( is_singular( array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) ) ) {
        return true;
    }
    // Dashboard page — you can set this to a specific page ID or use a template
    if ( is_page_template( 'templates/dashboard.php' ) ) {
        return true;
    }
    return false;
}

// ── Template Overrides ──
// Override LearnDash course template
add_filter( 'learndash_template', 'mw_academy_override_templates', 10, 5 );
function mw_academy_override_templates( $filepath, $name, $args, $echo, $return_file_path ) {
    $theme_file = MW_ACADEMY_DIR . '/learndash/' . $name . '.php';
    if ( file_exists( $theme_file ) ) {
        return $theme_file;
    }
    return $filepath;
}

// ── Page Template Registration ──
add_filter( 'theme_page_templates', 'mw_academy_register_templates' );
function mw_academy_register_templates( $templates ) {
    $templates['templates/dashboard.php'] = 'Academy Dashboard';
    return $templates;
}

// ── Favicon ──
add_action( 'wp_head', 'mw_academy_favicon' );
function mw_academy_favicon() {
    if ( ! mw_is_academy_page() ) return;
    echo '<link rel="icon" href="' . esc_url( MW_ACADEMY_URL . '/assets/images/favicon.svg' ) . '" type="image/svg+xml">';
}

// ── Admin Bar Offset ──
// Push the fixed nav down when WP admin bar is visible
add_action( 'wp_head', 'mw_academy_admin_bar_fix' );
function mw_academy_admin_bar_fix() {
    if ( is_admin_bar_showing() && mw_is_academy_page() ) {
        echo '<style>.mw-nav { top: 32px; } body { padding-top: calc(var(--nav-height) + 32px); } @media (max-width: 782px) { .mw-nav { top: 46px; } body { padding-top: calc(var(--nav-height) + 46px); } }</style>';
    }
}

/**
 * Resolve a template variable for the current user.
 */
function mw_resolve_var( $var_name, $course_id = 63 ) {
    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $gdata   = MWA_Gamification::get_user_data( $user_id );

    switch ( $var_name ) {
        // User
        case 'USER_DISPLAY_NAME': return $user->display_name;
        case 'USER_INITIAL':      return strtoupper( mb_substr( $user->display_name, 0, 1 ) );

        // XP / Levels
        case 'XP_CURRENT':        return $gdata['xp'];
        case 'XP_NEXT_LEVEL':     return $gdata['xp_to_next'];
        case 'XP_PERCENT':        return $gdata['xp_percent'];
        case 'LEVEL_NUM':         return $gdata['level'];
        case 'LEVEL_NAME':        return $gdata['level_name'];

        // Streaks
        case 'STREAK_DAYS':       return $gdata['streak'];
        case 'STREAK_TEXT':       return $gdata['streak_text'];
        case 'IF_STREAK_HIDDEN':  return $gdata['streak'] > 0 ? '' : 'style="display:none"';

        // Badges
        case 'BADGES_EARNED_COUNT':    return $gdata['badges_count'];
        case 'BADGES_REMAINING_COUNT': return $gdata['badges_remaining'];

        // Course progress
        case 'COURSE_PROGRESS_PCT':
            if ( function_exists( 'learndash_course_progress' ) ) {
                $progress = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
                return isset( $progress['percentage'] ) ? $progress['percentage'] : 0;
            }
            return 0;

        case 'COURSE_RING_OFFSET':
            $pct = mw_resolve_var( 'COURSE_PROGRESS_PCT', $course_id );
            return round( 364.4 * ( 1 - $pct / 100 ), 1 );

        case 'LESSONS_DONE_IN_COURSE':
            if ( function_exists( 'learndash_course_progress' ) ) {
                $progress = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
                return isset( $progress['completed'] ) ? $progress['completed'] : 0;
            }
            return 0;

        case 'LESSONS_LEFT_IN_COURSE':
            if ( function_exists( 'learndash_course_progress' ) ) {
                $progress = learndash_course_progress( array( 'user_id' => $user_id, 'course_id' => $course_id ) );
                $total = isset( $progress['total'] ) ? $progress['total'] : 0;
                $done  = isset( $progress['completed'] ) ? $progress['completed'] : 0;
                return $total - $done;
            }
            return 0;

        // Greeting
        case 'GREETING_HEADLINE':
            return MWA_Gamification::get_greeting( $user_id );

        // URLs
        case 'HOME_URL':            return home_url( '/' );
        case 'LOGOUT_URL':          return wp_logout_url( home_url( '/' ) );
        case 'PROFILE_URL':         return get_edit_profile_url( $user_id );

        default: return '{{' . $var_name . '}}'; // Unresolved — leave marker for debugging
    }
}

/**
 * Render the Academy navigation bar.
 */
function mw_render_nav( $active = 'dashboard' ) {
    $user_id = get_current_user_id();
    $gdata   = MWA_Gamification::get_user_data( $user_id );
    $user    = wp_get_current_user();
    $initial = strtoupper( mb_substr( $user->display_name, 0, 1 ) );

    ?>
    <a href="#main-content" class="skip-link">Skip to content</a>
    <nav class="mw-nav" role="navigation" aria-label="Main">
        <button class="mw-nav__hamburger" aria-label="Open menu" aria-controls="drawer" aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mw-nav__logo">
            <span class="mw-nav__logo-script">Maharani</span>
            <span class="mw-nav__logo-sub">Academy</span>
        </a>
        <div class="mw-nav__divider"></div>
        <div class="mw-nav__links">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mw-nav__link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">My Dashboard</a>
            <a href="<?php echo esc_url( get_permalink( 63 ) ); ?>" class="mw-nav__link <?php echo $active === 'course' ? 'active' : ''; ?>">My Trail</a>
            <a href="#certifications" class="mw-nav__link <?php echo $active === 'certs' ? 'active' : ''; ?>">Certifications</a>
        </div>
        <div class="mw-nav__right">
            <div class="mw-xp-badge" title="<?php echo esc_attr( $gdata['xp'] ); ?> XP — Level <?php echo esc_attr( $gdata['level'] ); ?>: <?php echo esc_attr( $gdata['level_name'] ); ?>">
                <span class="mw-xp-badge__icon" aria-hidden="true">⚡</span>
                <span><?php echo esc_html( number_format( $gdata['xp'] ) ); ?> XP</span>
            </div>
            <?php if ( $gdata['streak'] > 0 ) : ?>
            <div class="mw-streak" title="<?php echo esc_attr( $gdata['streak_text'] ); ?>">
                <svg class="mw-streak__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2c.6 0 1 .4 1.2.9C14.8 7 18 9 18 13c0 3.3-2.7 6-6 6s-6-2.7-6-6c0-2 .7-3.7 1.8-5.2.3-.4.9-.3 1.1.1.2.4 0 .9-.3 1.2C7.6 10.4 7 11.6 7 13c0 2.8 2.2 5 5 5s5-2.2 5-5c0-3.3-2.7-5.2-4-8.5-.1-.3-.4-.5-.7-.5H12z"/></svg>
                <span><?php echo esc_html( $gdata['streak'] ); ?></span>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url( get_edit_profile_url( $user_id ) ); ?>" class="mw-avatar" title="<?php echo esc_attr( $user->display_name ); ?>">
                <?php echo esc_html( $initial ); ?>
            </a>
        </div>
    </nav>

    <!-- Mobile Drawer -->
    <div class="mw-drawer-backdrop" id="drawerBackdrop"></div>
    <aside class="mw-drawer" id="drawer" role="dialog" aria-label="Navigation menu" aria-hidden="true">
        <div class="mw-drawer__header">
            <span class="mw-nav__logo-script" style="font-size:22px;">Maharani</span>
            <button class="mw-drawer__close" aria-label="Close menu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="mw-drawer__user">
            <span class="mw-avatar" style="width:40px;height:40px;"><?php echo esc_html( $initial ); ?></span>
            <div class="mw-drawer__user-meta">
                <span class="mw-drawer__user-name"><?php echo esc_html( $user->display_name ); ?></span>
                <span class="mw-drawer__user-stat">Level <?php echo esc_html( $gdata['level'] ); ?>: <?php echo esc_html( $gdata['level_name'] ); ?> · <?php echo esc_html( number_format( $gdata['xp'] ) ); ?> XP</span>
            </div>
        </div>
        <div class="mw-drawer__section">
            <div class="mw-drawer__section-label">Learning</div>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mw-drawer__link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="<?php echo esc_url( get_permalink( 63 ) ); ?>" class="mw-drawer__link <?php echo $active === 'course' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                My Trail
            </a>
            <a href="#certifications" class="mw-drawer__link <?php echo $active === 'certs' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
                Certifications
            </a>
        </div>
        <div class="mw-drawer__section">
            <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="mw-drawer__link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign Out
            </a>
        </div>
    </aside>
    <?php
}
