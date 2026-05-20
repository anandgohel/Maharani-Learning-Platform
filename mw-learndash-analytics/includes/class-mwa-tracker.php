<?php
/**
 * Event tracker — hooks into WordPress and LearnDash lifecycle events.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Tracker {

    public static function init() {
        // ── Login tracking ──
        add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );

        // ── Lesson / Topic view tracking ──
        add_action( 'template_redirect', array( __CLASS__, 'on_page_view' ) );

        // ── LearnDash completion hooks ──
        add_action( 'learndash_course_completed', array( __CLASS__, 'on_course_completed' ), 10, 1 );
        add_action( 'learndash_lesson_completed', array( __CLASS__, 'on_lesson_completed' ), 10, 1 );
        add_action( 'learndash_topic_completed',  array( __CLASS__, 'on_topic_completed' ), 10, 1 );

        // ── Quiz tracking ──
        add_action( 'learndash_quiz_completed', array( __CLASS__, 'on_quiz_completed' ), 10, 2 );
    }

    /**
     * Track user login.
     */
    public static function on_login( $user_login, $user ) {
        MWA_DB::log_event( $user->ID, 'login', array(
            'meta' => array( 'user_login' => $user_login ),
        ) );
    }

    /**
     * Track lesson/topic/quiz page views.
     */
    public static function on_page_view() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-courses' ) ) ) {
            return;
        }

        $post_id   = get_the_ID();
        $post_type = get_post_type( $post_id );
        $user_id   = get_current_user_id();

        // Determine course ID from the post
        $course_id = self::get_course_id_for_post( $post_id, $post_type );

        $event_type = 'page_view';
        if ( $post_type === 'sfwd-lessons' )  $event_type = 'lesson_view';
        if ( $post_type === 'sfwd-topic' )    $event_type = 'topic_view';
        if ( $post_type === 'sfwd-quiz' )     $event_type = 'quiz_view';
        if ( $post_type === 'sfwd-courses' )  $event_type = 'course_view';

        // Deduplicate: skip if same user viewed same post in last 5 minutes
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_events';
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE user_id = %d AND post_id = %d AND event_type = %s
             AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $user_id, $post_id, $event_type
        ) );

        if ( $recent > 0 ) {
            return;
        }

        MWA_DB::log_event( $user_id, $event_type, array(
            'post_id'   => $post_id,
            'post_type' => $post_type,
            'course_id' => $course_id,
        ) );
    }

    /**
     * Track course completion.
     */
    public static function on_course_completed( $data ) {
        $user_id   = isset( $data['user'] )   ? $data['user']->ID   : ( isset( $data['user_id'] ) ? $data['user_id'] : 0 );
        $course_id = isset( $data['course'] ) ? $data['course']->ID : ( isset( $data['course_id'] ) ? $data['course_id'] : 0 );

        if ( ! $user_id ) return;

        MWA_DB::log_event( $user_id, 'course_completed', array(
            'post_id'   => $course_id,
            'post_type' => 'sfwd-courses',
            'course_id' => $course_id,
            'meta'      => array(
                'course_title' => get_the_title( $course_id ),
                'completed_at' => current_time( 'mysql' ),
            ),
        ) );
    }

    /**
     * Track lesson completion.
     */
    public static function on_lesson_completed( $data ) {
        $user_id   = isset( $data['user'] )   ? $data['user']->ID   : 0;
        $lesson_id = isset( $data['lesson'] ) ? $data['lesson']->ID : ( isset( $data['post']->ID ) ? $data['post']->ID : 0 );
        $course_id = isset( $data['course'] ) ? $data['course']->ID : 0;

        if ( ! $user_id ) return;

        MWA_DB::log_event( $user_id, 'lesson_completed', array(
            'post_id'   => $lesson_id,
            'post_type' => 'sfwd-lessons',
            'course_id' => $course_id,
        ) );
    }

    /**
     * Track topic completion.
     */
    public static function on_topic_completed( $data ) {
        $user_id  = isset( $data['user'] )  ? $data['user']->ID  : 0;
        $topic_id = isset( $data['topic'] ) ? $data['topic']->ID : ( isset( $data['post']->ID ) ? $data['post']->ID : 0 );
        $course_id = isset( $data['course'] ) ? $data['course']->ID : 0;

        if ( ! $user_id ) return;

        MWA_DB::log_event( $user_id, 'topic_completed', array(
            'post_id'   => $topic_id,
            'post_type' => 'sfwd-topic',
            'course_id' => $course_id,
        ) );
    }

    /**
     * Track quiz completion with score.
     */
    public static function on_quiz_completed( $quiz_data, $user ) {
        $user_id = $user->ID;
        $quiz_id = isset( $quiz_data['quiz'] ) ? $quiz_data['quiz']->ID : 0;
        $course_id = isset( $quiz_data['course'] ) ? $quiz_data['course']->ID : 0;

        $score      = isset( $quiz_data['score'] ) ? $quiz_data['score'] : 0;
        $points     = isset( $quiz_data['points'] ) ? $quiz_data['points'] : 0;
        $total      = isset( $quiz_data['total_points'] ) ? $quiz_data['total_points'] : 0;
        $percentage = isset( $quiz_data['percentage'] ) ? $quiz_data['percentage'] : 0;
        $pass       = isset( $quiz_data['pass'] ) ? $quiz_data['pass'] : false;

        MWA_DB::log_event( $user_id, 'quiz_completed', array(
            'post_id'   => $quiz_id,
            'post_type' => 'sfwd-quiz',
            'course_id' => $course_id,
            'meta'      => array(
                'score'      => $score,
                'points'     => $points,
                'total'      => $total,
                'percentage' => $percentage,
                'pass'       => $pass,
            ),
        ) );
    }

    /**
     * Get course ID for a lesson/topic/quiz post.
     */
    private static function get_course_id_for_post( $post_id, $post_type ) {
        if ( $post_type === 'sfwd-courses' ) {
            return $post_id;
        }

        // LearnDash stores course association in post meta
        if ( function_exists( 'learndash_get_course_id' ) ) {
            return learndash_get_course_id( $post_id );
        }

        // Fallback: check post meta directly
        $course_id = get_post_meta( $post_id, 'course_id', true );
        return $course_id ? (int) $course_id : null;
    }
}
