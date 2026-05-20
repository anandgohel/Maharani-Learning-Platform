<?php
/**
 * REST API endpoints for the analytics plugin.
 * - Heartbeat endpoint for time-on-lesson tracking
 * - CSV export endpoint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        // Heartbeat endpoint (called by front-end JS every 30s)
        register_rest_route( 'mwa/v1', '/heartbeat', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_heartbeat' ),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ) );

        // CSV export endpoint (admin only)
        register_rest_route( 'mwa/v1', '/export', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_export' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );

        // Dashboard data endpoint (admin only, for AJAX refresh)
        register_rest_route( 'mwa/v1', '/dashboard-data', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_dashboard_data' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    /**
     * Handle heartbeat pings from the front-end JS.
     */
    public static function handle_heartbeat( $request ) {
        $user_id   = get_current_user_id();
        $post_id   = (int) $request->get_param( 'postId' );
        $post_type = sanitize_text_field( $request->get_param( 'postType' ) );
        $course_id = (int) $request->get_param( 'courseId' );

        if ( ! $post_id || ! $user_id ) {
            return new WP_REST_Response( array( 'error' => 'Missing data' ), 400 );
        }

        $session_id = MWA_DB::heartbeat( $user_id, $post_id, $post_type, $course_id ?: null );

        return new WP_REST_Response( array(
            'success'    => true,
            'session_id' => $session_id,
        ), 200 );
    }

    /**
     * Export analytics data as CSV.
     */
    public static function handle_export( $request ) {
        $days = (int) $request->get_param( 'days' ) ?: 90;
        $type = sanitize_text_field( $request->get_param( 'type' ) ) ?: 'events';

        if ( $type === 'events' ) {
            $data = MWA_DB::get_events_for_export( $days );
            $headers = array( 'ID', 'User', 'Email', 'Event Type', 'Post Title', 'Post Type', 'IP', 'Date', 'Meta' );
        } else {
            // User progress export
            $data = MWA_DB::get_user_progress();
            $headers = array( 'User ID', 'Name', 'Email', 'Last Login', 'Last Activity', 'Lessons Viewed', 'Completed' );
        }

        // Build CSV
        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, $headers );

        foreach ( $data as $row ) {
            fputcsv( $output, (array) $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        $response = new WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv' );
        $response->header( 'Content-Disposition', 'attachment; filename="mwa-analytics-' . date('Y-m-d') . '.csv"' );

        return $response;
    }

    /**
     * Get dashboard data as JSON (for admin page AJAX refresh).
     */
    public static function handle_dashboard_data( $request ) {
        $days = (int) $request->get_param( 'days' ) ?: 30;

        return new WP_REST_Response( array(
            'activeUsers7d'       => MWA_DB::get_active_users( 7 ),
            'activeUsers30d'      => MWA_DB::get_active_users( 30 ),
            'totalLogins'         => MWA_DB::get_event_counts( 'login', $days ),
            'totalLessonViews'    => MWA_DB::get_event_counts( 'lesson_view', $days ),
            'totalCompletions'    => MWA_DB::get_event_counts( 'course_completed', $days ),
            'totalQuizzes'        => MWA_DB::get_event_counts( 'quiz_completed', $days ),
            'lessonEngagement'    => MWA_DB::get_lesson_engagement( null, $days ),
            'userProgress'        => MWA_DB::get_user_progress(),
            'dailyLogins'         => MWA_DB::get_daily_events( 'login', $days ),
            'dailyLessonViews'    => MWA_DB::get_daily_events( 'lesson_view', $days ),
        ), 200 );
    }
}
