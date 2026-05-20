<?php
/**
 * Admin Survey Results Dashboard tab for MW LearnDash Analytics.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Admin_Surveys {

    public static function init() {
        // This is called from the main dashboard as a tab
    }

    /**
     * Render the Survey Results tab content.
     */
    public static function render( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mwa_survey_responses';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        if ( ! $table_exists ) {
            echo '<div class="mwa-section"><p class="mwa-empty">Survey table not yet created. Deactivate and reactivate the plugin to create it.</p></div>';
            return;
        }

        // ── Aggregate Stats ──
        $total_responses = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $lesson_responses = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE survey_type = 'lesson'" );
        $course_responses = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE survey_type = 'course'" );
        $avg_lesson_rating = (float) $wpdb->get_var( "SELECT AVG(rating) FROM {$table} WHERE survey_type = 'lesson'" );
        $avg_course_rating = (float) $wpdb->get_var( "SELECT AVG(rating) FROM {$table} WHERE survey_type = 'course'" );

        // NPS calculation
        $nps_rows = $wpdb->get_results( "SELECT nps_score FROM {$table} WHERE survey_type = 'course' AND nps_score IS NOT NULL" );
        $promoters = $detractors = 0;
        foreach ( $nps_rows as $n ) {
            if ( $n->nps_score >= 9 ) $promoters++;
            elseif ( $n->nps_score <= 6 ) $detractors++;
        }
        $nps_total = count( $nps_rows );
        $nps_score = $nps_total > 0 ? round( ( $promoters - $detractors ) / $nps_total * 100 ) : '—';

        // Average confidence
        $avg_confidence = $wpdb->get_var( "SELECT AVG(confidence_score) FROM {$table} WHERE survey_type = 'course' AND confidence_score IS NOT NULL" );

        // Export URL
        $export_url = rest_url( 'mwa/v1/survey/export' ) . '?_wpnonce=' . wp_create_nonce( 'wp_rest' );

        ?>
        <!-- Survey KPIs -->
        <div class="mwa-kpi-grid">
            <div class="mwa-kpi-card">
                <div class="mwa-kpi-value"><?php echo esc_html( $total_responses ); ?></div>
                <div class="mwa-kpi-label">Total Responses</div>
            </div>
            <div class="mwa-kpi-card">
                <div class="mwa-kpi-value"><?php echo esc_html( $lesson_responses ); ?></div>
                <div class="mwa-kpi-label">Lesson Surveys</div>
            </div>
            <div class="mwa-kpi-card">
                <div class="mwa-kpi-value"><?php echo esc_html( $course_responses ); ?></div>
                <div class="mwa-kpi-label">Course Surveys</div>
            </div>
            <div class="mwa-kpi-card mwa-kpi-accent">
                <div class="mwa-kpi-value"><?php echo $avg_lesson_rating ? round( $avg_lesson_rating, 1 ) : '—'; ?> ⭐</div>
                <div class="mwa-kpi-label">Avg Lesson Rating</div>
            </div>
            <div class="mwa-kpi-card mwa-kpi-accent">
                <div class="mwa-kpi-value"><?php echo $avg_course_rating ? round( $avg_course_rating, 1 ) : '—'; ?> ⭐</div>
                <div class="mwa-kpi-label">Avg Course Rating</div>
            </div>
            <div class="mwa-kpi-card mwa-kpi-success">
                <div class="mwa-kpi-value"><?php echo esc_html( $nps_score ); ?></div>
                <div class="mwa-kpi-label">NPS Score</div>
            </div>
        </div>

        <!-- Per-Lesson Ratings -->
        <div class="mwa-section">
            <h2>⭐ Lesson Ratings</h2>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" style="float:right;margin-top:-32px;">📥 Export Surveys CSV</a>
            <?php
            $lesson_data = $wpdb->get_results(
                "SELECT post_id, AVG(rating) as avg_rating, COUNT(*) as responses
                 FROM {$table} WHERE survey_type = 'lesson'
                 GROUP BY post_id ORDER BY avg_rating DESC"
            );
            if ( empty( $lesson_data ) ) : ?>
                <p class="mwa-empty">No lesson feedback yet. Ratings will appear as users complete lessons.</p>
            <?php else : ?>
            <table class="wp-list-table widefat striped mwa-table">
                <thead>
                    <tr>
                        <th>Lesson</th>
                        <th>Avg Rating</th>
                        <th>Responses</th>
                        <th>Stars</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $lesson_data as $l ) :
                        $avg = round( (float) $l->avg_rating, 1 );
                        $stars_html = str_repeat( '⭐', round( $avg ) ) . str_repeat( '☆', 5 - round( $avg ) );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( get_the_title( $l->post_id ) ); ?></strong></td>
                        <td><?php echo esc_html( $avg ); ?>/5</td>
                        <td><?php echo esc_html( $l->responses ); ?></td>
                        <td><?php echo $stars_html; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Text Feedback -->
        <div class="mwa-section">
            <h2>💬 Recent Feedback</h2>
            <?php
            $comments = $wpdb->get_results(
                "SELECT s.*, u.display_name, u.user_email
                 FROM {$table} s JOIN {$wpdb->users} u ON s.user_id = u.ID
                 WHERE s.comment IS NOT NULL AND s.comment != ''
                 ORDER BY s.created_at DESC LIMIT 20"
            );
            if ( empty( $comments ) ) : ?>
                <p class="mwa-empty">No text feedback yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat striped mwa-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $comments as $c ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $c->display_name ); ?></strong><br><small><?php echo esc_html( $c->user_email ); ?></small></td>
                        <td><?php echo $c->survey_type === 'lesson' ? '📖 Lesson' : '🎓 Course'; ?></td>
                        <td><?php echo str_repeat( '⭐', (int) $c->rating ); ?></td>
                        <td><?php echo esc_html( $c->comment ); ?></td>
                        <td><?php echo esc_html( date( 'M j, Y', strtotime( $c->created_at ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ( $avg_confidence ) : ?>
        <!-- Confidence Score -->
        <div class="mwa-section">
            <h2>💪 Confidence Score</h2>
            <p>Average confidence working with South Asian couples: <strong><?php echo round( (float) $avg_confidence, 1 ); ?>/5</strong></p>
        </div>
        <?php endif; ?>
        <?php
    }
}
