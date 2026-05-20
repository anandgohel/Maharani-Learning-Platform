<?php
/**
 * Plugin Name: MW LearnDash Analytics
 * Plugin URI: https://maharaniweddings.com
 * Description: Analytics, usage tracking, and reporting for the Maharani Weddings LearnDash certification platform.
 * Version: 1.0.0
 * Author: Maharani Weddings Engineering
 * License: Proprietary
 * Text Domain: mw-learndash-analytics
 *
 * Tracks: logins, lesson views, time-on-lesson, quiz scores, course completions.
 * Provides: WP Admin dashboard with funnel, heatmap, drop-off, and CSV export.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MWA_VERSION', '1.0.0' );
define( 'MWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Includes ──
require_once MWA_PLUGIN_DIR . 'includes/class-mwa-db.php';
require_once MWA_PLUGIN_DIR . 'includes/class-mwa-tracker.php';
require_once MWA_PLUGIN_DIR . 'includes/class-mwa-api.php';

if ( is_admin() ) {
    require_once MWA_PLUGIN_DIR . 'admin/class-mwa-admin-dashboard.php';
}

// ── Activation / Deactivation ──
register_activation_hook( __FILE__, array( 'MWA_DB', 'create_tables' ) );
register_deactivation_hook( __FILE__, array( 'MWA_DB', 'on_deactivation' ) );

// ── Initialize ──
add_action( 'plugins_loaded', 'mwa_init' );

function mwa_init() {
    // Only track if LearnDash is active
    if ( ! defined( 'LEARNDASH_VERSION' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>MW LearnDash Analytics</strong> requires LearnDash LMS to be active.</p></div>';
        });
        return;
    }

    MWA_Tracker::init();
    MWA_API::init();

    if ( is_admin() ) {
        MWA_Admin_Dashboard::init();
    }
}

// ── Enqueue front-end heartbeat script (for time-on-lesson tracking) ──
add_action( 'wp_enqueue_scripts', 'mwa_enqueue_heartbeat' );

function mwa_enqueue_heartbeat() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Only on LearnDash lesson, topic, or quiz pages
    if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) ) ) {
        return;
    }

    wp_enqueue_script(
        'mwa-heartbeat',
        MWA_PLUGIN_URL . 'assets/js/heartbeat.js',
        array(),
        MWA_VERSION,
        true
    );

    wp_localize_script( 'mwa-heartbeat', 'mwaData', array(
        'ajaxUrl'  => rest_url( 'mwa/v1/heartbeat' ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'postId'   => get_the_ID(),
        'postType' => get_post_type(),
        'userId'   => get_current_user_id(),
        'interval' => 30, // seconds
    ) );
}
