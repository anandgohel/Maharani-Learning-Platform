<?php
/**
 * Gamification Engine — XP, Levels, Streaks, Badges
 * Trailhead-style learning gamification for the Maharani Academy.
 *
 * All values stored in user meta for portability. Level names and XP
 * thresholds are config-driven — change them without touching code.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Gamification {

    // ── Config (easily changeable) ──
    const XP_PER_LESSON    = 50;
    const XP_PER_QUIZ_PASS = 100;
    const XP_PER_QUIZ_FAIL = 20;
    const XP_COURSE_BONUS  = 500;
    const XP_STREAK_BONUS  = 25;  // bonus per day of streak

    /**
     * Level definitions — name, min XP threshold, badge SVG.
     * Edit these to rename levels or adjust thresholds.
     */
    public static function get_levels() {
        return array(
            1 => array( 'name' => 'Newcomer',           'min_xp' => 0,    'icon' => 'seedling' ),
            2 => array( 'name' => 'Cultural Learner',    'min_xp' => 200,  'icon' => 'book' ),
            3 => array( 'name' => 'Certified Planner',   'min_xp' => 500,  'icon' => 'star' ),
            4 => array( 'name' => 'Cultural Expert',     'min_xp' => 1000, 'icon' => 'gem' ),
            5 => array( 'name' => 'Maharani Master',     'min_xp' => 1800, 'icon' => 'crown' ),
        );
    }

    /**
     * Badge definitions.
     */
    public static function get_badge_definitions() {
        return array(
            'first_lesson'    => array( 'name' => 'First Step',       'desc' => 'Complete your first lesson',           'icon' => 'footprints' ),
            'fast_learner'    => array( 'name' => 'Fast Learner',     'desc' => 'Complete 5 lessons',                   'icon' => 'zap' ),
            'halfway'         => array( 'name' => 'Halfway Hero',     'desc' => 'Complete 50% of the course',           'icon' => 'flag' ),
            'quiz_ace'        => array( 'name' => 'Quiz Ace',         'desc' => 'Score 100% on any quiz',               'icon' => 'trophy' ),
            'streak_3'        => array( 'name' => 'On Fire',          'desc' => 'Maintain a 3-day streak',              'icon' => 'flame' ),
            'streak_7'        => array( 'name' => 'Week Warrior',     'desc' => 'Maintain a 7-day streak',              'icon' => 'shield' ),
            'course_complete' => array( 'name' => 'Certified',        'desc' => 'Complete the entire course',           'icon' => 'award' ),
            'feedback_giver'  => array( 'name' => 'Voice Heard',      'desc' => 'Submit lesson feedback',               'icon' => 'message' ),
        );
    }

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Award XP on lesson completion
        add_action( 'learndash_lesson_completed', array( __CLASS__, 'on_lesson_completed' ), 10, 1 );
        // Award XP on quiz completion
        add_action( 'learndash_quiz_completed', array( __CLASS__, 'on_quiz_completed' ), 10, 2 );
        // Award XP on course completion
        add_action( 'learndash_course_completed', array( __CLASS__, 'on_course_completed' ), 10, 1 );
        // Track streak on login
        add_action( 'wp_login', array( __CLASS__, 'update_streak' ), 10, 2 );
        // REST API for XP animation
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route( 'mwa/v1', '/gamification/profile', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_profile' ),
            'permission_callback' => function() { return is_user_logged_in(); },
        ) );
    }

    /**
     * Get full gamification profile for current user.
     */
    public static function get_profile( $request = null ) {
        $user_id = get_current_user_id();
        return new WP_REST_Response( self::get_user_data( $user_id ), 200 );
    }

    /**
     * Get gamification data for a user.
     */
    public static function get_user_data( $user_id ) {
        $xp           = (int) get_user_meta( $user_id, 'mw_xp_total', true );
        $streak       = (int) get_user_meta( $user_id, 'mw_streak_days', true );
        $badges       = (array) get_user_meta( $user_id, 'mw_badges_earned', true );
        $badges       = array_filter( $badges ); // Remove empty

        $level        = self::get_level_for_xp( $xp );
        $levels       = self::get_levels();
        $level_data   = $levels[ $level ];
        $next_level   = isset( $levels[ $level + 1 ] ) ? $levels[ $level + 1 ] : null;

        // XP progress within current level
        $xp_in_level  = $xp - $level_data['min_xp'];
        $xp_to_next   = $next_level ? ( $next_level['min_xp'] - $level_data['min_xp'] ) : 1;
        $xp_percent   = $next_level ? min( round( $xp_in_level / $xp_to_next * 100 ), 100 ) : 100;

        // Ring offset for SVG (364.4 circumference)
        $ring_offset  = round( 364.4 * ( 1 - $xp_percent / 100 ), 1 );

        return array(
            'xp'              => $xp,
            'level'           => $level,
            'level_name'      => $level_data['name'],
            'level_icon'      => $level_data['icon'],
            'xp_in_level'     => $xp_in_level,
            'xp_to_next'      => $next_level ? $next_level['min_xp'] : $xp,
            'xp_next_level'   => $next_level ? $next_level['min_xp'] - $level_data['min_xp'] : 0,
            'xp_percent'      => $xp_percent,
            'ring_offset'     => $ring_offset,
            'streak'          => $streak,
            'streak_text'     => self::get_streak_text( $streak ),
            'badges_earned'   => $badges,
            'badges_count'    => count( $badges ),
            'badges_total'    => count( self::get_badge_definitions() ),
            'badges_remaining'=> count( self::get_badge_definitions() ) - count( $badges ),
        );
    }

    /**
     * Award XP to a user.
     */
    public static function award_xp( $user_id, $amount, $reason = '' ) {
        $current_xp = (int) get_user_meta( $user_id, 'mw_xp_total', true );
        $old_level  = self::get_level_for_xp( $current_xp );

        $new_xp = $current_xp + $amount;
        update_user_meta( $user_id, 'mw_xp_total', $new_xp );

        $new_level = self::get_level_for_xp( $new_xp );

        // Level up notification
        if ( $new_level > $old_level ) {
            $levels = self::get_levels();
            $user = get_userdata( $user_id );
            self::slack_notify(
                "🎖️ *Level Up!* {$user->display_name} is now *Level {$new_level}: {$levels[$new_level]['name']}* ({$new_xp} XP)"
            );
        }

        // Log XP award
        if ( class_exists( 'MWA_DB' ) ) {
            MWA_DB::log_event( $user_id, 'xp_earned', 0, array(
                'amount' => $amount,
                'reason' => $reason,
                'total'  => $new_xp,
            ) );
        }

        return $new_xp;
    }

    /**
     * Award a badge to a user.
     */
    public static function award_badge( $user_id, $badge_key ) {
        $badges = (array) get_user_meta( $user_id, 'mw_badges_earned', true );
        $badges = array_filter( $badges );

        if ( in_array( $badge_key, $badges ) ) return false; // Already earned

        $badges[] = $badge_key;
        update_user_meta( $user_id, 'mw_badges_earned', $badges );

        $defs = self::get_badge_definitions();
        $user = get_userdata( $user_id );
        if ( isset( $defs[ $badge_key ] ) ) {
            self::slack_notify(
                "🏅 *Badge Unlocked!* {$user->display_name} earned *{$defs[$badge_key]['name']}* — _{$defs[$badge_key]['desc']}_"
            );
        }

        return true;
    }

    // ── Event handlers ──

    public static function on_lesson_completed( $data ) {
        $user = isset( $data['user'] ) ? $data['user'] : null;
        if ( ! $user ) return;

        self::award_xp( $user->ID, self::XP_PER_LESSON, 'lesson_completed' );

        // Badge: first lesson
        $badges = (array) get_user_meta( $user->ID, 'mw_badges_earned', true );
        if ( ! in_array( 'first_lesson', array_filter( $badges ) ) ) {
            self::award_badge( $user->ID, 'first_lesson' );
        }

        // Badge: fast learner (5 lessons)
        if ( class_exists( 'MWA_DB' ) ) {
            global $wpdb;
            $lesson_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mwa_events WHERE user_id = %d AND event_type = 'lesson_completed'",
                $user->ID
            ) );
            if ( $lesson_count >= 5 ) {
                self::award_badge( $user->ID, 'fast_learner' );
            }
            // Badge: halfway
            $course = isset( $data['course'] ) ? $data['course'] : null;
            if ( $course && function_exists( 'learndash_course_progress' ) ) {
                $progress = learndash_course_progress( array( 'user_id' => $user->ID, 'course_id' => $course->ID ) );
                if ( isset( $progress['percentage'] ) && $progress['percentage'] >= 50 ) {
                    self::award_badge( $user->ID, 'halfway' );
                }
            }
        }
    }

    public static function on_quiz_completed( $quiz_data, $user ) {
        $quiz_id = isset( $quiz_data['quiz'] ) ? $quiz_data['quiz']->ID : 0;
        $lock_key = 'mwa_quiz_xp_' . $user->ID . '_' . $quiz_id;
        if ( get_transient( $lock_key ) ) return; // Prevent duplicate fires
        set_transient( $lock_key, 1, 60 );

        $pass       = isset( $quiz_data['pass'] ) ? $quiz_data['pass'] : false;
        $percentage = isset( $quiz_data['percentage'] ) ? $quiz_data['percentage'] : 0;

        $xp = $pass ? self::XP_PER_QUIZ_PASS : self::XP_PER_QUIZ_FAIL;
        self::award_xp( $user->ID, $xp, 'quiz_completed' );

        // Badge: quiz ace
        if ( $percentage >= 100 ) {
            self::award_badge( $user->ID, 'quiz_ace' );
        }
    }

    public static function on_course_completed( $data ) {
        $user = isset( $data['user'] ) ? $data['user'] : null;
        if ( ! $user ) return;

        self::award_xp( $user->ID, self::XP_COURSE_BONUS, 'course_completed' );
        self::award_badge( $user->ID, 'course_complete' );
    }

    /**
     * Update streak on login.
     */
    public static function update_streak( $user_login, $user ) {
        $last_active = get_user_meta( $user->ID, 'mw_last_active_date', true );
        $today       = date( 'Y-m-d' );

        if ( $last_active === $today ) return; // Already counted today

        $streak = (int) get_user_meta( $user->ID, 'mw_streak_days', true );

        if ( $last_active === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
            // Consecutive day — extend streak
            $streak++;
            self::award_xp( $user->ID, self::XP_STREAK_BONUS, 'streak_bonus' );
        } elseif ( $last_active !== $today ) {
            // Streak broken — reset
            $streak = 1;
        }

        update_user_meta( $user->ID, 'mw_streak_days', $streak );
        update_user_meta( $user->ID, 'mw_last_active_date', $today );

        // Streak badges
        if ( $streak >= 3 ) self::award_badge( $user->ID, 'streak_3' );
        if ( $streak >= 7 ) self::award_badge( $user->ID, 'streak_7' );
    }

    // ── Helpers ──

    public static function get_level_for_xp( $xp ) {
        $levels = self::get_levels();
        $level = 1;
        foreach ( $levels as $num => $data ) {
            if ( $xp >= $data['min_xp'] ) $level = $num;
        }
        return $level;
    }

    public static function get_streak_text( $streak ) {
        if ( $streak <= 0 ) return '';
        if ( $streak === 1 ) return '1-day streak — nice start!';
        if ( $streak < 7 )   return "{$streak}-day streak — keep it going!";
        return "{$streak}-day streak — you're on fire! 🔥";
    }

    /**
     * Get greeting (timezone-agnostic).
     */
    public static function get_greeting( $user_id ) {
        $user = get_userdata( $user_id );
        $name = $user ? $user->display_name : 'there';
        $first = explode( ' ', $name )[0];

        $data   = self::get_user_data( $user_id );
        $streak = $data['streak'];
        $xp     = $data['xp'];

        if ( $xp > 500 )      return "Welcome back, {$first}";
        if ( $streak >= 3 )    return "Hey {$first}, great streak!";
        if ( $xp > 0 )        return "Welcome back, {$first}";
        return "Welcome, {$first}";
    }

    private static function slack_notify( $text ) {
        $settings = get_option( 'mwa_settings', array() );
        $webhook = isset( $settings['slack_webhook_url'] ) ? $settings['slack_webhook_url'] : '';
        if ( ! $webhook ) return;

        wp_remote_post( $webhook, array(
            'body'     => wp_json_encode( array( 'text' => $text, 'username' => 'MW LearnDash', 'icon_emoji' => ':video_game:' ) ),
            'headers'  => array( 'Content-Type' => 'application/json' ),
            'timeout'  => 5,
            'blocking' => false,
        ) );
    }
}
