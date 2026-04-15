<?php
/*
Plugin Name: LearnDash Course Map
Description: Displays courses with sections and lessons in a structured table.
Version: 1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// Add menu page
add_action('admin_menu', function () {
    add_menu_page(
        'LD Course Report',
        'LD Reports',
        'manage_options',
        'ld-course-report',
        'ld_course_report_page',
        'dashicons-welcome-learn-more',
        6
    );
});

function ld_course_report_page() {
    ?>
    <div class="wrap">
        <h1>LearnDash Course Structure Report</h1>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Lessons</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $courses = get_posts([
                    'post_type' => 'sfwd-courses',
                    'numberposts' => -1
                ]);

                foreach ($courses as $course) {

                    $builder_data = learndash_get_course_builder_data($course->ID);

                    if (empty($builder_data)) continue;

                    foreach ($builder_data as $section) {

                        // Section title
                        $section_title = isset($section['title']) ? $section['title'] : 'No Section';

                        $lessons = [];

                        if (!empty($section['steps'])) {
                            foreach ($section['steps'] as $step_id) {
                                if (get_post_type($step_id) === 'sfwd-lessons') {
                                    $lessons[] = get_the_title($step_id);
                                }
                            }
                        }

                        echo '<tr>';
                        echo '<td>' . esc_html($course->post_title) . '</td>';
                        echo '<td>' . esc_html($section_title) . '</td>';
                        echo '<td>' . esc_html(implode(', ', $lessons)) . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}