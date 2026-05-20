<?php
/**
 * Slack notifications for LearnDash activity.
 * Sends real-time alerts to a Slack channel for key events.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Slack {

    public static function init() {
        // Hook into key events for notifications
        add_action( 'wp_login', array( __CLASS__, 'on_login' ), 20, 2 );
        add_action( 'learndash_course_completed', array( __CLASS__, 'on_course_completed' ), 20, 1 );
        add_action( 'learndash_lesson_completed', array( __CLASS__, 'on_lesson_completed' ), 20, 1 );
        add_action( 'learndash_quiz_completed', array( __CLASS__, 'on_quiz_completed' ), 20, 2 );
    }

    /**
     * Notify on user login.
     */
    public static function on_login( $user_login, $user ) {
        $settings = get_option( 'mwa_settings', array() );
        if ( empty( $settings['slack_notify_login'] ) ) return;

        self::send( array(
            'text' => "🔑 *Login:* {$user->display_name} (`{$user->user_email}`) just logged into LearnDash",
            'icon_emoji' => ':key:',
        ) );
    }

    /**
     * Notify on course completion.
     */
    public static function on_course_completed( $data ) {
        $user = isset( $data['user'] ) ? $data['user'] : null;
        $course = isset( $data['course'] ) ? $data['course'] : null;

        if ( ! $user ) return;

        $course_title = $course ? $course->post_title : 'Unknown Course';

        self::send( array(
            'text' => "🎓 *Course Completed!* {$user->display_name} (`{$user->user_email}`) has completed *{$course_title}*! 🎉",
            'icon_emoji' => ':mortar_board:',
        ) );
    }

    /**
     * Notify on lesson completion.
     */
    public static function on_lesson_completed( $data ) {
        $settings = get_option( 'mwa_settings', array() );
        if ( empty( $settings['slack_notify_lesson'] ) ) return;

        $user = isset( $data['user'] ) ? $data['user'] : null;
        $lesson = isset( $data['lesson'] ) ? $data['lesson'] : ( isset( $data['post'] ) ? $data['post'] : null );
        $course = isset( $data['course'] ) ? $data['course'] : null;

        if ( ! $user || ! $lesson ) return;

        $lesson_title = $lesson->post_title;
        $course_title = $course ? " ({$course->post_title})" : '';

        // Count how many lessons this user has completed
        global $wpdb;
        $completed_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mwa_events WHERE user_id = %d AND event_type = 'lesson_completed'",
            $user->ID
        ) );

        // Get total lessons in the course
        $total_lessons = 22; // Known from audit
        if ( $course && function_exists( 'learndash_get_lesson_list' ) ) {
            $lessons_list = learndash_get_lesson_list( $course->ID );
            if ( is_array( $lessons_list ) ) {
                $total_lessons = count( $lessons_list );
            }
        }

        $progress = round( ( $completed_count / max( $total_lessons, 1 ) ) * 100 );

        self::send( array(
            'text' => "📖 *Lesson Completed:* {$user->display_name} finished *{$lesson_title}*{$course_title}\n📊 Progress: {$completed_count}/{$total_lessons} lessons ({$progress}%)",
            'icon_emoji' => ':open_book:',
        ) );
    }

    /**
     * Notify on quiz completion.
     */
    public static function on_quiz_completed( $quiz_data, $user ) {
        $settings = get_option( 'mwa_settings', array() );
        if ( empty( $settings['slack_notify_quiz'] ) ) return;

        $percentage = isset( $quiz_data['percentage'] ) ? $quiz_data['percentage'] : 0;
        $pass = isset( $quiz_data['pass'] ) ? $quiz_data['pass'] : false;
        $quiz_title = isset( $quiz_data['quiz'] ) ? $quiz_data['quiz']->post_title : 'Quiz';

        $emoji = $pass ? '✅' : '❌';
        $result = $pass ? 'PASSED' : 'FAILED';

        self::send( array(
            'text' => "📝 *Quiz {$result}:* {$user->display_name} scored *{$percentage}%* on *{$quiz_title}* {$emoji}",
            'icon_emoji' => ':pencil:',
        ) );
    }

    /**
     * Send a message to Slack via webhook.
     */
    private static function send( $payload ) {
        $settings = get_option( 'mwa_settings', array() );
        $webhook_url = isset( $settings['slack_webhook_url'] ) ? $settings['slack_webhook_url'] : '';

        if ( empty( $webhook_url ) ) return;

        $payload['username'] = 'MW LearnDash';

        wp_remote_post( $webhook_url, array(
            'body'        => wp_json_encode( $payload ),
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'timeout'     => 5,
            'blocking'    => false, // Non-blocking — don't slow down the user experience
            'sslverify'   => true,
        ) );
    }
}
