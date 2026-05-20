<?php
/**
 * WP Admin Dashboard for MW LearnDash Analytics.
 * Adds a menu page under LearnDash with KPIs, funnel, lesson heatmap, and user table.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Admin_Dashboard {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 30 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'learndash-lms',           // parent slug (LearnDash menu)
            'MW Analytics',            // page title
            'MW Analytics',            // menu title
            'manage_options',          // capability
            'mwa-analytics',           // menu slug
            array( __CLASS__, 'render_dashboard' )
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'learndash-lms_page_mwa-analytics' ) {
            return;
        }

        // Chart.js from CDN
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js', array(), '4.4.4', true );

        // Inline admin styles
        wp_add_inline_style( 'wp-admin', self::get_admin_css() );
    }

    public static function render_dashboard() {
        $days = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30;
        if ( $days < 1 || $days > 365 ) $days = 30;

        // ── KPI Data ──
        $active_7d    = MWA_DB::get_active_users( 7 );
        $active_30d   = MWA_DB::get_active_users( 30 );
        $logins       = MWA_DB::get_event_counts( 'login', $days );
        $lesson_views = MWA_DB::get_event_counts( 'lesson_view', $days );
        $completions  = MWA_DB::get_event_counts( 'course_completed', $days );
        $quizzes      = MWA_DB::get_event_counts( 'quiz_completed', $days );

        // ── User Progress ──
        $users = MWA_DB::get_user_progress();
        $total_users = count( $users );
        $users_started = count( array_filter( $users, function( $u ) { return $u->lessons_viewed > 0; } ) );
        $users_completed = count( array_filter( $users, function( $u ) { return $u->completed; } ) );

        // ── Lesson Engagement ──
        $lessons = MWA_DB::get_lesson_engagement( null, $days );

        // ── Daily Login Chart Data ──
        $daily_logins = MWA_DB::get_daily_events( 'login', $days );
        $daily_views  = MWA_DB::get_daily_events( 'lesson_view', $days );

        // Export URL
        $export_url = rest_url( 'mwa/v1/export' ) . '?days=' . $days . '&_wpnonce=' . wp_create_nonce( 'wp_rest' );

        ?>
        <div class="wrap mwa-dashboard">
            <h1>📊 MW LearnDash Analytics</h1>

            <!-- Period Selector -->
            <div class="mwa-period-selector">
                <form method="get">
                    <input type="hidden" name="page" value="mwa-analytics" />
                    <label>Period:
                        <select name="days" onchange="this.form.submit()">
                            <option value="7" <?php selected( $days, 7 ); ?>>Last 7 days</option>
                            <option value="30" <?php selected( $days, 30 ); ?>>Last 30 days</option>
                            <option value="90" <?php selected( $days, 90 ); ?>>Last 90 days</option>
                            <option value="365" <?php selected( $days, 365 ); ?>>Last year</option>
                        </select>
                    </label>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" style="margin-left:12px;">📥 Export CSV</a>
                    <a href="<?php echo esc_url( $export_url . '&type=progress' ); ?>" class="button button-secondary">📥 Export User Progress</a>
                </form>
            </div>

            <!-- KPI Cards -->
            <div class="mwa-kpi-grid">
                <div class="mwa-kpi-card">
                    <div class="mwa-kpi-value"><?php echo esc_html( $active_7d ); ?></div>
                    <div class="mwa-kpi-label">Active Users (7d)</div>
                </div>
                <div class="mwa-kpi-card">
                    <div class="mwa-kpi-value"><?php echo esc_html( $active_30d ); ?></div>
                    <div class="mwa-kpi-label">Active Users (30d)</div>
                </div>
                <div class="mwa-kpi-card mwa-kpi-accent">
                    <div class="mwa-kpi-value"><?php echo esc_html( $logins ); ?></div>
                    <div class="mwa-kpi-label">Total Logins</div>
                </div>
                <div class="mwa-kpi-card">
                    <div class="mwa-kpi-value"><?php echo esc_html( $lesson_views ); ?></div>
                    <div class="mwa-kpi-label">Lesson Views</div>
                </div>
                <div class="mwa-kpi-card mwa-kpi-success">
                    <div class="mwa-kpi-value"><?php echo esc_html( $completions ); ?></div>
                    <div class="mwa-kpi-label">Course Completions</div>
                </div>
                <div class="mwa-kpi-card">
                    <div class="mwa-kpi-value"><?php echo esc_html( $quizzes ); ?></div>
                    <div class="mwa-kpi-label">Quizzes Taken</div>
                </div>
            </div>

            <!-- Course Funnel -->
            <div class="mwa-section">
                <h2>📈 Course Funnel</h2>
                <div class="mwa-funnel">
                    <div class="mwa-funnel-step">
                        <div class="mwa-funnel-bar" style="width: 100%;">
                            <span>Enrolled: <?php echo esc_html( $total_users ); ?></span>
                        </div>
                    </div>
                    <div class="mwa-funnel-step">
                        <div class="mwa-funnel-bar mwa-funnel-mid" style="width: <?php echo $total_users ? round( $users_started / $total_users * 100 ) : 0; ?>%;">
                            <span>Started: <?php echo esc_html( $users_started ); ?> (<?php echo $total_users ? round( $users_started / $total_users * 100 ) : 0; ?>%)</span>
                        </div>
                    </div>
                    <div class="mwa-funnel-step">
                        <div class="mwa-funnel-bar mwa-funnel-end" style="width: <?php echo $total_users ? round( $users_completed / $total_users * 100 ) : 0; ?>%;">
                            <span>Completed: <?php echo esc_html( $users_completed ); ?> (<?php echo $total_users ? round( $users_completed / $total_users * 100 ) : 0; ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Chart -->
            <div class="mwa-section">
                <h2>📉 Daily Activity</h2>
                <div class="mwa-chart-container">
                    <canvas id="mwa-activity-chart" height="300"></canvas>
                </div>
            </div>

            <!-- Lesson Heatmap -->
            <div class="mwa-section">
                <h2>🔥 Lesson Engagement</h2>
                <?php if ( empty( $lessons ) ) : ?>
                    <p class="mwa-empty">No lesson data yet. Data will appear as users view lessons.</p>
                <?php else : ?>
                <table class="wp-list-table widefat striped mwa-table">
                    <thead>
                        <tr>
                            <th>Lesson</th>
                            <th>Unique Viewers</th>
                            <th>Total Views</th>
                            <th>Avg Time (min)</th>
                            <th>Total Time (hr)</th>
                            <th>Engagement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $max_views = max( array_map( function( $l ) { return $l->total_views; }, $lessons ) );
                        foreach ( $lessons as $lesson ) :
                            $pct = $max_views > 0 ? round( $lesson->total_views / $max_views * 100 ) : 0;
                            $avg_min = round( $lesson->avg_time_seconds / 60, 1 );
                            $total_hr = round( $lesson->total_time_seconds / 3600, 1 );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $lesson->post_title ); ?></strong></td>
                            <td><?php echo esc_html( $lesson->unique_viewers ); ?></td>
                            <td><?php echo esc_html( $lesson->total_views ); ?></td>
                            <td><?php echo esc_html( $avg_min ); ?></td>
                            <td><?php echo esc_html( $total_hr ); ?></td>
                            <td>
                                <div class="mwa-heatbar" style="width: <?php echo $pct; ?>%;" title="<?php echo $pct; ?>% of max"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- User Progress Table -->
            <div class="mwa-section">
                <h2>👥 User Progress</h2>
                <?php if ( empty( $users ) ) : ?>
                    <p class="mwa-empty">No user activity data yet.</p>
                <?php else : ?>
                <table class="wp-list-table widefat striped mwa-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Last Login</th>
                            <th>Last Activity</th>
                            <th>Lessons Viewed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $users as $user ) :
                            $status = '❌ Not Started';
                            $status_class = 'mwa-status-none';
                            if ( $user->completed ) {
                                $status = '✅ Completed';
                                $status_class = 'mwa-status-done';
                            } elseif ( $user->lessons_viewed > 0 ) {
                                $status = '🔄 In Progress';
                                $status_class = 'mwa-status-progress';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
                            <td><?php echo esc_html( $user->user_email ); ?></td>
                            <td><?php echo $user->last_login ? esc_html( date( 'M j, Y g:ia', strtotime( $user->last_login ) ) ) : '<span class="mwa-muted">Never</span>'; ?></td>
                            <td><?php echo $user->last_activity ? esc_html( date( 'M j, Y g:ia', strtotime( $user->last_activity ) ) ) : '<span class="mwa-muted">Never</span>'; ?></td>
                            <td><?php echo esc_html( $user->lessons_viewed ); ?></td>
                            <td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo $status; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('mwa-activity-chart');
            if (!ctx) return;

            var loginData = <?php echo wp_json_encode( $daily_logins ); ?>;
            var viewData  = <?php echo wp_json_encode( $daily_views ); ?>;

            // Merge dates
            var allDates = {};
            (loginData || []).forEach(function(d) { allDates[d.event_date] = allDates[d.event_date] || {}; allDates[d.event_date].logins = parseInt(d.event_count); });
            (viewData || []).forEach(function(d) { allDates[d.event_date] = allDates[d.event_date] || {}; allDates[d.event_date].views = parseInt(d.event_count); });

            var labels = Object.keys(allDates).sort();
            var logins = labels.map(function(d) { return (allDates[d] && allDates[d].logins) || 0; });
            var views  = labels.map(function(d) { return (allDates[d] && allDates[d].views) || 0; });

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Logins',
                            data: logins,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Lesson Views',
                            data: views,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245,158,11,0.1)',
                            tension: 0.3,
                            fill: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { position: 'top' } }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Admin CSS.
     */
    private static function get_admin_css() {
        return '
            .mwa-dashboard { max-width: 1400px; }
            .mwa-period-selector { margin: 16px 0; display: flex; align-items: center; }
            .mwa-period-selector select { margin-left: 8px; padding: 4px 12px; }

            .mwa-kpi-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
                margin: 20px 0;
            }
            .mwa-kpi-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                padding: 24px;
                text-align: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
                transition: transform 0.15s;
            }
            .mwa-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .mwa-kpi-value { font-size: 36px; font-weight: 700; color: #1e293b; }
            .mwa-kpi-label { font-size: 13px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
            .mwa-kpi-accent .mwa-kpi-value { color: #6366f1; }
            .mwa-kpi-success .mwa-kpi-value { color: #22c55e; }

            .mwa-section {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                padding: 24px;
                margin: 20px 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .mwa-section h2 { margin-top: 0; font-size: 18px; color: #1e293b; }

            .mwa-chart-container { position: relative; height: 300px; }

            .mwa-funnel-step { margin: 8px 0; }
            .mwa-funnel-bar {
                background: #6366f1;
                color: #fff;
                padding: 12px 16px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 14px;
                min-width: 120px;
                transition: width 0.5s ease;
            }
            .mwa-funnel-mid { background: #f59e0b; }
            .mwa-funnel-end { background: #22c55e; }

            .mwa-table { border-collapse: collapse; }
            .mwa-table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
            .mwa-table td { vertical-align: middle; }

            .mwa-heatbar {
                height: 12px;
                background: linear-gradient(90deg, #fbbf24, #ef4444);
                border-radius: 6px;
                min-width: 4px;
                transition: width 0.3s;
            }

            .mwa-status-done { color: #22c55e; font-weight: 600; }
            .mwa-status-progress { color: #f59e0b; font-weight: 600; }
            .mwa-status-none { color: #94a3b8; }
            .mwa-muted { color: #94a3b8; font-style: italic; }
            .mwa-empty { color: #94a3b8; font-style: italic; padding: 20px 0; }
        ';
    }
}
