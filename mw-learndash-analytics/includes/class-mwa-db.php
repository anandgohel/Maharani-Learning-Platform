<?php
/**
 * Database setup and helpers for MW LearnDash Analytics.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_DB {

    /**
     * Create custom analytics tables on plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $events_table   = $wpdb->prefix . 'mwa_events';
        $sessions_table = $wpdb->prefix . 'mwa_sessions';

        $sql = "
        CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            post_type VARCHAR(50) DEFAULT NULL,
            post_title VARCHAR(255) DEFAULT NULL,
            course_id BIGINT UNSIGNED DEFAULT NULL,
            meta_data TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_event_type (event_type),
            KEY idx_post_id (post_id),
            KEY idx_course_id (course_id),
            KEY idx_created_at (created_at)
        ) {$charset};

        CREATE TABLE {$sessions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL,
            course_id BIGINT UNSIGNED DEFAULT NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            heartbeats INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_post (user_id, post_id),
            KEY idx_course_id (course_id),
            KEY idx_started_at (started_at)
        ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'mwa_db_version', MWA_VERSION );
    }

    /**
     * On deactivation — we do NOT drop tables (data preservation).
     */
    public static function on_deactivation() {
        // Intentionally empty — preserve data on deactivation.
        // Tables are only dropped on uninstall (uninstall.php).
    }

    /**
     * Insert an analytics event.
     */
    public static function log_event( $user_id, $event_type, $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_events';

        $post_title = '';
        if ( ! empty( $data['post_id'] ) ) {
            $post_title = get_the_title( $data['post_id'] );
        }

        return $wpdb->insert( $table, array(
            'user_id'    => $user_id,
            'event_type' => $event_type,
            'post_id'    => isset( $data['post_id'] ) ? $data['post_id'] : null,
            'post_type'  => isset( $data['post_type'] ) ? $data['post_type'] : null,
            'post_title' => $post_title,
            'course_id'  => isset( $data['course_id'] ) ? $data['course_id'] : null,
            'meta_data'  => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 255 ) : '',
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
    }

    /**
     * Upsert a session heartbeat — create or update time tracking.
     */
    public static function heartbeat( $user_id, $post_id, $post_type, $course_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_sessions';
        $now   = current_time( 'mysql' );

        // Find active session (last heartbeat within 5 minutes)
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, heartbeats, duration_seconds, started_at FROM {$table}
             WHERE user_id = %d AND post_id = %d
             AND last_heartbeat_at >= DATE_SUB(%s, INTERVAL 5 MINUTE)
             ORDER BY last_heartbeat_at DESC LIMIT 1",
            $user_id, $post_id, $now
        ) );

        if ( $session ) {
            // Update existing session
            $elapsed = strtotime( $now ) - strtotime( $session->started_at );
            $wpdb->update( $table, array(
                'duration_seconds'   => min( $elapsed, 7200 ), // cap at 2 hours
                'heartbeats'         => $session->heartbeats + 1,
                'last_heartbeat_at'  => $now,
            ), array( 'id' => $session->id ), array( '%d', '%d', '%s' ), array( '%d' ) );
            return $session->id;
        } else {
            // New session
            $wpdb->insert( $table, array(
                'user_id'           => $user_id,
                'post_id'           => $post_id,
                'post_type'         => $post_type,
                'course_id'         => $course_id,
                'duration_seconds'  => 0,
                'heartbeats'        => 1,
                'started_at'        => $now,
                'last_heartbeat_at' => $now,
            ), array( '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ) );
            return $wpdb->insert_id;
        }
    }

    /**
     * Get client IP address.
     */
    private static function get_client_ip() {
        $headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = explode( ',', sanitize_text_field( $_SERVER[ $header ] ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }

    // ── Query Methods for Dashboard ──

    /**
     * Get event counts by type within a date range.
     */
    public static function get_event_counts( $event_type, $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_events';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $event_type, $days
        ) );
    }

    /**
     * Get unique active users within N days.
     */
    public static function get_active_users( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_events';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }

    /**
     * Get per-user progress summary for a course.
     */
    public static function get_user_progress( $course_id = null ) {
        global $wpdb;
        $events = $wpdb->prefix . 'mwa_events';
        $users  = $wpdb->users;

        $course_filter = '';
        $params = array();
        if ( $course_id ) {
            $course_filter = 'AND e.course_id = %d';
            $params[] = $course_id;
        }

        $sql = "
            SELECT
                u.ID as user_id,
                u.display_name,
                u.user_email,
                MAX(CASE WHEN e.event_type = 'login' THEN e.created_at END) as last_login,
                MAX(e.created_at) as last_activity,
                COUNT(DISTINCT CASE WHEN e.event_type = 'lesson_view' THEN e.post_id END) as lessons_viewed,
                MAX(CASE WHEN e.event_type = 'course_completed' THEN 1 ELSE 0 END) as completed
            FROM {$users} u
            LEFT JOIN {$events} e ON u.ID = e.user_id {$course_filter}
            WHERE u.ID IN (
                SELECT DISTINCT user_id FROM {$events}
            )
            GROUP BY u.ID, u.display_name, u.user_email
            ORDER BY last_activity DESC
        ";

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, ...$params );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get lesson engagement data (views + avg time).
     */
    public static function get_lesson_engagement( $course_id = null, $days = 90 ) {
        global $wpdb;
        $events   = $wpdb->prefix . 'mwa_events';
        $sessions = $wpdb->prefix . 'mwa_sessions';

        $course_filter = '';
        $params = array( $days );
        if ( $course_id ) {
            $course_filter = 'AND e.course_id = %d';
            $params[] = $course_id;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT
                e.post_id,
                e.post_title,
                COUNT(DISTINCT e.user_id) as unique_viewers,
                COUNT(*) as total_views,
                COALESCE(AVG(s.duration_seconds), 0) as avg_time_seconds,
                COALESCE(SUM(s.duration_seconds), 0) as total_time_seconds
            FROM {$events} e
            LEFT JOIN {$sessions} s ON e.post_id = s.post_id AND e.user_id = s.user_id
            WHERE e.event_type = 'lesson_view'
            AND e.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            {$course_filter}
            GROUP BY e.post_id, e.post_title
            ORDER BY total_views DESC",
            ...$params
        ) );
    }

    /**
     * Get daily event counts for charting.
     */
    public static function get_daily_events( $event_type = null, $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_events';

        $type_filter = '';
        $params = array( $days );
        if ( $event_type ) {
            $type_filter = 'AND event_type = %s';
            $params[] = $event_type;
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as event_date, COUNT(*) as event_count
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) {$type_filter}
             GROUP BY DATE(created_at)
             ORDER BY event_date",
            ...$params
        ) );
    }

    /**
     * Get all events for CSV export.
     */
    public static function get_events_for_export( $days = 90 ) {
        global $wpdb;
        $events = $wpdb->prefix . 'mwa_events';
        $users  = $wpdb->users;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, u.display_name, u.user_email, e.event_type, e.post_title,
                    e.post_type, e.ip_address, e.created_at, e.meta_data
             FROM {$events} e
             JOIN {$users} u ON e.user_id = u.ID
             WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY e.created_at DESC",
            $days
        ) );
    }
}
