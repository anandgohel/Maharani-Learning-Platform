<?php
/**
 * Settings page for MW LearnDash Analytics.
 * Configure GA4, Clarity, Slack, and notification preferences.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Settings {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ), 31 );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function add_settings_page() {
        add_submenu_page(
            'learndash-lms',
            'MW Analytics Settings',
            'MW Settings',
            'manage_options',
            'mwa-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function register_settings() {
        register_setting( 'mwa_settings_group', 'mwa_settings', array(
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
        ) );

        // ── Google Analytics 4 ──
        add_settings_section( 'mwa_ga4', '📊 Google Analytics 4', function() {
            echo '<p>Enter your GA4 Measurement ID to enable Google Analytics tracking with custom LearnDash events.</p>';
        }, 'mwa-settings' );

        add_settings_field( 'ga4_measurement_id', 'Measurement ID', function() {
            $settings = get_option( 'mwa_settings', array() );
            $val = isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
            echo '<input type="text" name="mwa_settings[ga4_measurement_id]" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
            echo '<p class="description">Find this in Google Analytics → Admin → Data Streams → Your web stream.</p>';
        }, 'mwa-settings', 'mwa_ga4' );

        // ── Microsoft Clarity ──
        add_settings_section( 'mwa_clarity', '🔍 Microsoft Clarity', function() {
            echo '<p>Enter your Clarity Project ID for heatmaps, session recordings, and behavioral analytics.</p>';
        }, 'mwa-settings' );

        add_settings_field( 'clarity_id', 'Clarity Project ID', function() {
            $settings = get_option( 'mwa_settings', array() );
            $val = isset( $settings['clarity_id'] ) ? $settings['clarity_id'] : '';
            echo '<input type="text" name="mwa_settings[clarity_id]" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="abcdef1234" />';
            echo '<p class="description">Find this at <a href="https://clarity.microsoft.com" target="_blank">clarity.microsoft.com</a> → Settings → Overview.</p>';
        }, 'mwa-settings', 'mwa_clarity' );

        // ── Slack ──
        add_settings_section( 'mwa_slack', '💬 Slack Notifications', function() {
            echo '<p>Configure a Slack Incoming Webhook to receive real-time activity alerts.</p>';
            echo '<p><a href="https://api.slack.com/messaging/webhooks" target="_blank">How to create a Slack Webhook →</a></p>';
        }, 'mwa-settings' );

        add_settings_field( 'slack_webhook_url', 'Webhook URL', function() {
            $settings = get_option( 'mwa_settings', array() );
            $val = isset( $settings['slack_webhook_url'] ) ? $settings['slack_webhook_url'] : '';
            echo '<input type="url" name="mwa_settings[slack_webhook_url]" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="https://hooks.slack.com/services/T.../B.../..." />';
        }, 'mwa-settings', 'mwa_slack' );

        add_settings_field( 'slack_notify_login', 'Notify on Login', function() {
            $settings = get_option( 'mwa_settings', array() );
            $checked = ! empty( $settings['slack_notify_login'] ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="mwa_settings[slack_notify_login]" value="1" ' . $checked . ' /> Send a Slack message when a user logs in</label>';
        }, 'mwa-settings', 'mwa_slack' );

        add_settings_field( 'slack_notify_lesson', 'Notify on Lesson Completion', function() {
            $settings = get_option( 'mwa_settings', array() );
            $checked = ! empty( $settings['slack_notify_lesson'] ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="mwa_settings[slack_notify_lesson]" value="1" ' . $checked . ' /> Send a Slack message when a user completes a lesson (includes progress %)</label>';
        }, 'mwa-settings', 'mwa_slack' );

        add_settings_field( 'slack_notify_quiz', 'Notify on Quiz Completion', function() {
            $settings = get_option( 'mwa_settings', array() );
            $checked = ! empty( $settings['slack_notify_quiz'] ) ? 'checked' : '';
            echo '<label><input type="checkbox" name="mwa_settings[slack_notify_quiz]" value="1" ' . $checked . ' /> Send a Slack message when a user completes a quiz (includes score)</label>';
        }, 'mwa-settings', 'mwa_slack' );

        add_settings_field( 'slack_notify_course', 'Notify on Course Completion', function() {
            $settings = get_option( 'mwa_settings', array() );
            $checked = isset( $settings['slack_notify_course'] ) ? ( $settings['slack_notify_course'] ? 'checked' : '' ) : 'checked';
            echo '<label><input type="checkbox" name="mwa_settings[slack_notify_course]" value="1" ' . $checked . ' /> Send a Slack message when a user completes the full course (ALWAYS recommended)</label>';
        }, 'mwa-settings', 'mwa_slack' );
    }

    public static function sanitize_settings( $input ) {
        $clean = array();
        $clean['ga4_measurement_id'] = isset( $input['ga4_measurement_id'] ) ? sanitize_text_field( $input['ga4_measurement_id'] ) : '';
        $clean['clarity_id']         = isset( $input['clarity_id'] ) ? sanitize_text_field( $input['clarity_id'] ) : '';
        $clean['slack_webhook_url']  = isset( $input['slack_webhook_url'] ) ? esc_url_raw( $input['slack_webhook_url'] ) : '';
        $clean['slack_notify_login']   = ! empty( $input['slack_notify_login'] ) ? 1 : 0;
        $clean['slack_notify_lesson']  = ! empty( $input['slack_notify_lesson'] ) ? 1 : 0;
        $clean['slack_notify_quiz']    = ! empty( $input['slack_notify_quiz'] ) ? 1 : 0;
        $clean['slack_notify_course']  = ! empty( $input['slack_notify_course'] ) ? 1 : 0;
        return $clean;
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>⚙️ MW Analytics Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'mwa_settings_group' );
                do_settings_sections( 'mwa-settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>

            <hr />
            <h2>🔧 Quick Setup Guide</h2>
            <table class="widefat" style="max-width: 800px;">
                <tr>
                    <td><strong>Google Analytics 4</strong></td>
                    <td>
                        1. Go to <a href="https://analytics.google.com" target="_blank">analytics.google.com</a><br/>
                        2. Create a property for learn.maharaniweddings.com<br/>
                        3. Copy the Measurement ID (starts with <code>G-</code>)<br/>
                        4. Paste it above
                    </td>
                </tr>
                <tr>
                    <td><strong>Microsoft Clarity</strong></td>
                    <td>
                        1. Go to <a href="https://clarity.microsoft.com" target="_blank">clarity.microsoft.com</a><br/>
                        2. Create a new project for learn.maharaniweddings.com<br/>
                        3. Copy the Project ID from Settings<br/>
                        4. Paste it above
                    </td>
                </tr>
                <tr>
                    <td><strong>Slack</strong></td>
                    <td>
                        1. Go to <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a><br/>
                        2. Create an app → Add Incoming Webhooks<br/>
                        3. Choose the channel for notifications<br/>
                        4. Copy the Webhook URL and paste above
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
