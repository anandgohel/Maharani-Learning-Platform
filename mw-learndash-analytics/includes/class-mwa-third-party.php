<?php
/**
 * Third-party analytics integration: Google Analytics 4, Microsoft Clarity.
 * Also injects custom GA4 events for granular LearnDash tracking.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MWA_Third_Party {

    public static function init() {
        add_action( 'wp_head', array( __CLASS__, 'inject_tracking_scripts' ), 1 );
        add_action( 'wp_footer', array( __CLASS__, 'inject_ga4_events' ), 99 );
    }

    /**
     * Inject GA4 and Clarity tags into <head>.
     */
    public static function inject_tracking_scripts() {
        $settings = get_option( 'mwa_settings', array() );
        $ga4_id   = isset( $settings['ga4_measurement_id'] ) ? sanitize_text_field( $settings['ga4_measurement_id'] ) : '';
        $clarity_id = isset( $settings['clarity_id'] ) ? sanitize_text_field( $settings['clarity_id'] ) : '';

        // ── Google Analytics 4 ──
        if ( $ga4_id ) {
            ?>
<!-- MW Analytics: Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4_id ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4_id ); ?>', {
    'custom_map': {
        'dimension1': 'user_role',
        'dimension2': 'course_name',
        'dimension3': 'lesson_name'
    }
});
<?php if ( is_user_logged_in() ) : ?>
gtag('set', 'user_properties', {
    'user_id': '<?php echo esc_js( get_current_user_id() ); ?>',
    'user_role': '<?php echo esc_js( self::get_user_role() ); ?>',
    'user_company': '<?php echo esc_js( self::get_user_company() ); ?>'
});
<?php endif; ?>
</script>
            <?php
        }

        // ── Microsoft Clarity ──
        if ( $clarity_id ) {
            ?>
<!-- MW Analytics: Microsoft Clarity -->
<script type="text/javascript">
(function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
})(window,document,"clarity","script","<?php echo esc_js( $clarity_id ); ?>");
<?php if ( is_user_logged_in() ) : ?>
clarity("identify", "<?php echo esc_js( get_current_user_id() ); ?>", null, null, "<?php echo esc_js( wp_get_current_user()->display_name ); ?>");
<?php endif; ?>
</script>
            <?php
        }
    }

    /**
     * Inject granular GA4 custom events for LearnDash actions.
     * These fire in addition to standard pageviews, giving deep funnel data.
     */
    public static function inject_ga4_events() {
        $settings = get_option( 'mwa_settings', array() );
        $ga4_id = isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
        if ( ! $ga4_id || ! is_user_logged_in() ) return;

        $post_type = get_post_type();
        $post_title = get_the_title();
        $course_title = '';

        // Get course title for context
        if ( function_exists( 'learndash_get_course_id' ) && is_singular() ) {
            $course_id = learndash_get_course_id( get_the_ID() );
            if ( $course_id ) {
                $course_title = get_the_title( $course_id );
            }
        }

        if ( ! is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-courses' ) ) ) {
            return;
        }

        $event_name = 'ld_page_view';
        if ( $post_type === 'sfwd-lessons' ) $event_name = 'ld_lesson_view';
        if ( $post_type === 'sfwd-topic' )   $event_name = 'ld_topic_view';
        if ( $post_type === 'sfwd-quiz' )    $event_name = 'ld_quiz_view';
        if ( $post_type === 'sfwd-courses' ) $event_name = 'ld_course_view';

        ?>
<!-- MW Analytics: GA4 LearnDash Events -->
<script>
if (typeof gtag !== 'undefined') {
    gtag('event', '<?php echo esc_js( $event_name ); ?>', {
        'event_category': 'learndash',
        'event_label': '<?php echo esc_js( $post_title ); ?>',
        'course_name': '<?php echo esc_js( $course_title ); ?>',
        'lesson_name': '<?php echo esc_js( $post_title ); ?>',
        'content_type': '<?php echo esc_js( $post_type ); ?>',
        'user_id': '<?php echo esc_js( get_current_user_id() ); ?>'
    });
}
</script>
        <?php
    }

    /**
     * Get current user's role.
     */
    private static function get_user_role() {
        $user = wp_get_current_user();
        return ! empty( $user->roles ) ? $user->roles[0] : 'none';
    }

    /**
     * Get company from user email domain.
     */
    private static function get_user_company() {
        $email = wp_get_current_user()->user_email;
        $domain = substr( strrchr( $email, '@' ), 1 );
        // Strip common free email domains
        $free = array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com' );
        return in_array( $domain, $free ) ? '' : $domain;
    }
}
