<?php
// Check lesson video and quiz data
global $wpdb;
$lessons = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type='sfwd-lessons' AND post_status='publish' LIMIT 8");
foreach ($lessons as $l) {
    $meta = get_post_meta($l->ID, '_sfwd-lessons', true);
    $video = isset($meta['sfwd-lessons_lesson_video_url']) ? $meta['sfwd-lessons_lesson_video_url'] : 'NONE';
    $has_content = strlen(get_post_field('post_content', $l->ID)) > 10 ? 'YES' : 'NO';
    
    // Check for quizzes
    $quizzes = array();
    if (function_exists('learndash_get_lesson_quiz_list')) {
        $quiz_list = learndash_get_lesson_quiz_list($l->ID);
        if (is_array($quiz_list)) {
            foreach ($quiz_list as $q) {
                $quizzes[] = $q['post']->post_title;
            }
        }
    }
    $quiz_str = empty($quizzes) ? 'NONE' : implode(', ', $quizzes);
    
    echo $l->ID . ' | ' . $l->post_title . "\n";
    echo "  video: " . $video . "\n";
    echo "  content: " . $has_content . "\n";
    echo "  quizzes: " . $quiz_str . "\n\n";
}
