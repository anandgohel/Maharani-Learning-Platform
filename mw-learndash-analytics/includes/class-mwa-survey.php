<?php
/**
 * Survey system for MW LearnDash Analytics.
 * Handles post-lesson micro-surveys and post-course comprehensive surveys.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Survey {

    public static function init() {
        // Inject micro-survey at bottom of lesson content
        add_filter( 'the_content', array( __CLASS__, 'inject_lesson_survey' ), 99 );

        // Register REST API routes for survey submission
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

        // Enqueue survey assets on lesson/course pages
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Show course survey after completion
        add_action( 'learndash_course_completed', array( __CLASS__, 'on_course_completed' ), 10, 1 );

        // Shortcode for manual placement
        add_shortcode( 'mwa_course_survey', array( __CLASS__, 'render_course_survey_shortcode' ) );
    }

    /**
     * Create the survey responses table.
     */
    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'mwa_survey_responses';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            survey_type VARCHAR(20) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED DEFAULT NULL,
            rating TINYINT UNSIGNED DEFAULT NULL,
            nps_score TINYINT UNSIGNED DEFAULT NULL,
            confidence_score TINYINT UNSIGNED DEFAULT NULL,
            most_valuable_lesson BIGINT UNSIGNED DEFAULT NULL,
            least_valuable_lesson BIGINT UNSIGNED DEFAULT NULL,
            comment TEXT DEFAULT NULL,
            additional_feedback TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_survey (user_id, survey_type, post_id),
            KEY idx_survey_type (survey_type),
            KEY idx_course_id (course_id),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route( 'mwa/v1', '/survey/submit', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_submit' ),
            'permission_callback' => function() { return is_user_logged_in(); },
        ) );

        register_rest_route( 'mwa/v1', '/survey/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_status' ),
            'permission_callback' => function() { return is_user_logged_in(); },
        ) );

        register_rest_route( 'mwa/v1', '/survey/results', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_results' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        register_rest_route( 'mwa/v1', '/survey/export', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle_export' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );
    }

    /**
     * Enqueue survey JS + CSS on lesson/course pages.
     */
    public static function enqueue_assets() {
        if ( ! is_user_logged_in() ) return;
        if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-courses' ) ) ) return;

        wp_enqueue_style( 'mwa-survey', MWA_PLUGIN_URL . 'assets/css/survey.css', array(), MWA_VERSION );
        wp_enqueue_script( 'mwa-survey', MWA_PLUGIN_URL . 'assets/js/survey.js', array(), MWA_VERSION, true );

        $course_id = 0;
        if ( function_exists( 'learndash_get_course_id' ) ) {
            $course_id = learndash_get_course_id( get_the_ID() );
        }

        // Get all lessons for the course survey dropdown
        $lessons = array();
        if ( $course_id && function_exists( 'learndash_get_lesson_list' ) ) {
            $lesson_list = learndash_get_lesson_list( $course_id );
            if ( is_array( $lesson_list ) ) {
                foreach ( $lesson_list as $lesson ) {
                    $lessons[] = array( 'id' => $lesson->ID, 'title' => $lesson->post_title );
                }
            }
        }

        wp_localize_script( 'mwa-survey', 'mwaSurvey', array(
            'restUrl'  => rest_url( 'mwa/v1/survey/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'postId'   => get_the_ID(),
            'postType' => get_post_type(),
            'courseId'  => $course_id,
            'userId'   => get_current_user_id(),
            'lessons'  => $lessons,
        ) );
    }

    /**
     * Inject micro-survey at the bottom of lesson content.
     */
    public static function inject_lesson_survey( $content ) {
        if ( ! is_singular( 'sfwd-lessons' ) || ! is_user_logged_in() ) {
            return $content;
        }

        // Check if user already submitted for this lesson
        $existing = self::get_user_response( get_current_user_id(), 'lesson', get_the_ID() );

        $post_id = get_the_ID();
        $existing_rating = $existing ? (int) $existing->rating : 0;
        $existing_comment = $existing ? esc_attr( $existing->comment ) : '';
        $submitted_class = $existing ? ' mwa-survey-submitted' : '';

        $survey_html = '
        <div class="mwa-micro-survey' . $submitted_class . '" id="mwa-lesson-survey" data-post-id="' . $post_id . '">
            <div class="mwa-survey-inner">
                <h4 class="mwa-survey-title">How useful was this lesson?</h4>
                <div class="mwa-stars" data-rating="' . $existing_rating . '">
                    <span class="mwa-star" data-value="1">★</span>
                    <span class="mwa-star" data-value="2">★</span>
                    <span class="mwa-star" data-value="3">★</span>
                    <span class="mwa-star" data-value="4">★</span>
                    <span class="mwa-star" data-value="5">★</span>
                    <span class="mwa-rating-text"></span>
                </div>
                <div class="mwa-comment-wrap">
                    <textarea class="mwa-comment" placeholder="Any suggestions? (optional)" maxlength="500">' . $existing_comment . '</textarea>
                </div>
                <button class="mwa-submit-btn" type="button">' . ( $existing ? 'Update Feedback' : 'Submit Feedback' ) . '</button>
                <div class="mwa-survey-thanks" style="display:none;">✓ Thanks for your feedback!</div>
            </div>
        </div>';

        return $content . $survey_html;
    }

    /**
     * Handle survey submission.
     */
    public static function handle_submit( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_survey_responses';

        $user_id     = get_current_user_id();
        $survey_type = sanitize_text_field( $request->get_param( 'surveyType' ) );
        $post_id     = (int) $request->get_param( 'postId' );
        $course_id   = (int) $request->get_param( 'courseId' );

        if ( ! in_array( $survey_type, array( 'lesson', 'course' ) ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid survey type' ), 400 );
        }

        $data = array(
            'user_id'     => $user_id,
            'survey_type' => $survey_type,
            'post_id'     => $post_id,
            'course_id'   => $course_id,
            'rating'      => (int) $request->get_param( 'rating' ),
            'comment'     => sanitize_textarea_field( $request->get_param( 'comment' ) ),
            'created_at'  => current_time( 'mysql' ),
        );

        // Course-specific fields
        if ( $survey_type === 'course' ) {
            $data['nps_score']              = (int) $request->get_param( 'npsScore' );
            $data['confidence_score']       = (int) $request->get_param( 'confidenceScore' );
            $data['most_valuable_lesson']   = (int) $request->get_param( 'mostValuableLesson' );
            $data['least_valuable_lesson']  = (int) $request->get_param( 'leastValuableLesson' );
            $data['additional_feedback']    = sanitize_textarea_field( $request->get_param( 'additionalFeedback' ) );
        }

        // Upsert — update if exists, insert if not
        $existing = self::get_user_response( $user_id, $survey_type, $post_id );
        if ( $existing ) {
            $wpdb->update( $table, $data, array( 'id' => $existing->id ) );
        } else {
            $wpdb->insert( $table, $data );
        }

        // Send Slack notification
        self::slack_notify( $user_id, $survey_type, $data );

        return new WP_REST_Response( array( 'success' => true, 'updated' => (bool) $existing ), 200 );
    }

    /**
     * Check if user already submitted for this post.
     */
    public static function handle_status( $request ) {
        $user_id     = get_current_user_id();
        $post_id     = (int) $request->get_param( 'postId' );
        $survey_type = sanitize_text_field( $request->get_param( 'surveyType' ) ) ?: 'lesson';

        $response = self::get_user_response( $user_id, $survey_type, $post_id );

        return new WP_REST_Response( array(
            'submitted' => (bool) $response,
            'data'      => $response,
        ), 200 );
    }

    /**
     * Get aggregate survey results (admin).
     */
    public static function handle_results( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_survey_responses';
        $days = (int) $request->get_param( 'days' ) ?: 90;

        // Per-lesson ratings
        $lesson_ratings = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id, AVG(rating) as avg_rating, COUNT(*) as responses
             FROM {$table}
             WHERE survey_type = 'lesson' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY post_id ORDER BY avg_rating DESC",
            $days
        ) );
        foreach ( $lesson_ratings as &$r ) {
            $r->post_title = get_the_title( $r->post_id );
            $r->avg_rating = round( (float) $r->avg_rating, 1 );
        }

        // Course survey aggregates
        $course_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, AVG(nps_score) as avg_nps,
                    AVG(confidence_score) as avg_confidence, COUNT(*) as responses
             FROM {$table}
             WHERE survey_type = 'course' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        // NPS calculation
        $nps_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT nps_score FROM {$table}
             WHERE survey_type = 'course' AND nps_score IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        $promoters = $detractors = $total_nps = 0;
        foreach ( $nps_data as $n ) {
            $total_nps++;
            if ( $n->nps_score >= 9 ) $promoters++;
            elseif ( $n->nps_score <= 6 ) $detractors++;
        }
        $nps_score = $total_nps > 0 ? round( ( $promoters - $detractors ) / $total_nps * 100 ) : null;

        // Most/least valuable lessons
        $most_valuable = $wpdb->get_results( $wpdb->prepare(
            "SELECT most_valuable_lesson as post_id, COUNT(*) as votes
             FROM {$table}
             WHERE survey_type = 'course' AND most_valuable_lesson IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY most_valuable_lesson ORDER BY votes DESC LIMIT 10",
            $days
        ) );
        foreach ( $most_valuable as &$m ) {
            $m->post_title = get_the_title( $m->post_id );
        }

        // Recent text responses
        $recent_comments = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, u.display_name, u.user_email
             FROM {$table} s JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE (s.comment IS NOT NULL AND s.comment != '')
             AND s.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY s.created_at DESC LIMIT 50",
            $days
        ) );

        return new WP_REST_Response( array(
            'lessonRatings'   => $lesson_ratings,
            'courseStats'     => $course_stats,
            'npsScore'        => $nps_score,
            'mostValuable'    => $most_valuable,
            'recentComments'  => $recent_comments,
            'totalResponses'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
        ), 200 );
    }

    /**
     * CSV export of all survey responses.
     */
    public static function handle_export( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_survey_responses';

        $rows = $wpdb->get_results(
            "SELECT s.*, u.display_name, u.user_email
             FROM {$table} s JOIN {$wpdb->users} u ON s.user_id = u.ID
             ORDER BY s.created_at DESC"
        );

        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, array( 'ID', 'User', 'Email', 'Type', 'Post', 'Rating', 'NPS', 'Confidence', 'Comment', 'Date' ) );
        foreach ( $rows as $r ) {
            fputcsv( $output, array(
                $r->id, $r->display_name, $r->user_email, $r->survey_type,
                get_the_title( $r->post_id ), $r->rating, $r->nps_score,
                $r->confidence_score, $r->comment, $r->created_at,
            ) );
        }
        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        $response = new WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv' );
        $response->header( 'Content-Disposition', 'attachment; filename="mwa-surveys-' . date('Y-m-d') . '.csv"' );
        return $response;
    }

    /**
     * Mark course as having a pending survey when completed.
     */
    public static function on_course_completed( $data ) {
        $user_id = isset( $data['user'] ) ? $data['user']->ID : 0;
        $course_id = isset( $data['course'] ) ? $data['course']->ID : 0;
        if ( $user_id && $course_id ) {
            update_user_meta( $user_id, 'mwa_pending_course_survey_' . $course_id, 1 );
        }
    }

    /**
     * Shortcode: [mwa_course_survey]
     */
    public static function render_course_survey_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) return '';
        $atts = shortcode_atts( array( 'course_id' => 63 ), $atts );
        return '<div id="mwa-course-survey-container" data-course-id="' . esc_attr( $atts['course_id'] ) . '"></div>';
    }

    // ── Helpers ──

    private static function get_user_response( $user_id, $survey_type, $post_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mwa_survey_responses
             WHERE user_id = %d AND survey_type = %s AND post_id = %d",
            $user_id, $survey_type, $post_id
        ) );
    }

    private static function slack_notify( $user_id, $survey_type, $data ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $stars = str_repeat( '⭐', (int) $data['rating'] );

        if ( $survey_type === 'lesson' ) {
            $lesson_title = get_the_title( $data['post_id'] );
            $comment = ! empty( $data['comment'] ) ? "\n💬 _\"{$data['comment']}\"_" : '';
            $text = "{$stars} *Lesson Feedback:* {$user->display_name} rated *{$lesson_title}* {$data['rating']}/5{$comment}";
        } else {
            $nps = isset( $data['nps_score'] ) ? $data['nps_score'] : '?';
            $confidence = isset( $data['confidence_score'] ) ? $data['confidence_score'] : '?';
            $most = $data['most_valuable_lesson'] ? get_the_title( $data['most_valuable_lesson'] ) : 'N/A';
            $comment = ! empty( $data['comment'] ) ? "\n💬 _\"{$data['comment']}\"_" : '';
            $extra = ! empty( $data['additional_feedback'] ) ? "\n📝 _\"{$data['additional_feedback']}\"_" : '';
            $text = "📋 *Course Survey from {$user->display_name}:*\n{$stars} Overall: {$data['rating']}/5\n📊 NPS: {$nps}/10 | Confidence: {$confidence}/5\n🏆 Most Valuable: _{$most}_{$comment}{$extra}";
        }

        // Use the MWA_Slack::send method if available
        if ( class_exists( 'MWA_Slack' ) && method_exists( 'MWA_Slack', 'send_public' ) ) {
            MWA_Slack::send_public( array( 'text' => $text, 'icon_emoji' => ':star:' ) );
        } else {
            // Direct send
            $settings = get_option( 'mwa_settings', array() );
            $webhook = isset( $settings['slack_webhook_url'] ) ? $settings['slack_webhook_url'] : '';
            if ( $webhook ) {
                wp_remote_post( $webhook, array(
                    'body'     => wp_json_encode( array( 'text' => $text, 'username' => 'MW LearnDash', 'icon_emoji' => ':star:' ) ),
                    'headers'  => array( 'Content-Type' => 'application/json' ),
                    'timeout'  => 5,
                    'blocking' => false,
                ) );
            }
        }
    }
}
