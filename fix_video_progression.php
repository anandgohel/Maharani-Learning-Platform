<?php
// Check and fix video progression settings
global $wpdb;
$lessons = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type='sfwd-lessons' AND post_status='publish'");
foreach ($lessons as $l) {
    $meta = get_post_meta($l->ID, '_sfwd-lessons', true);
    if (!is_array($meta)) continue;
    
    $vp = isset($meta['sfwd-lessons_lesson_video_enabled']) ? $meta['sfwd-lessons_lesson_video_enabled'] : 'not_set';
    $va = isset($meta['sfwd-lessons_lesson_video_auto_complete']) ? $meta['sfwd-lessons_lesson_video_auto_complete'] : 'not_set';
    $vh = isset($meta['sfwd-lessons_lesson_video_hide_complete_button']) ? $meta['sfwd-lessons_lesson_video_hide_complete_button'] : 'not_set';
    $vsc = isset($meta['sfwd-lessons_lesson_video_shown']) ? $meta['sfwd-lessons_lesson_video_shown'] : 'not_set';
    
    echo $l->ID . " | " . $l->post_title . "\n";
    echo "  video_enabled: " . $vp . "\n";
    echo "  auto_complete: " . $va . "\n";  
    echo "  hide_complete_btn: " . $vh . "\n";
    echo "  video_shown: " . $vsc . "\n";
    
    // Disable video progression — allow quiz access without watching
    if (isset($meta['sfwd-lessons_lesson_video_enabled']) && $meta['sfwd-lessons_lesson_video_enabled'] === 'on') {
        $meta['sfwd-lessons_lesson_video_enabled'] = 'on';
        $meta['sfwd-lessons_lesson_video_hide_complete_button'] = '';  // Don't hide complete button
        $meta['sfwd-lessons_lesson_video_auto_complete'] = '';  // Don't require video completion
        $meta['sfwd-lessons_lesson_video_shown'] = 'BEFORE';  // Show video but don't gate
        update_post_meta($l->ID, '_sfwd-lessons', $meta);
        echo "  -> FIXED: removed video progression gate\n";
    }
    echo "\n";
}
echo "Done. All lessons updated.\n";
