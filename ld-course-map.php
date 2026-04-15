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
    $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'courses';
    if (!in_array($view, ['courses', 'lessons'], true)) {
        $view = 'courses';
    }

    $courses_url = admin_url('admin.php?page=ld-course-report&view=courses');
    $lessons_url = admin_url('admin.php?page=ld-course-report&view=lessons');

    ?>
    <div class="wrap">
        <h1>LearnDash Course Structure Report</h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url($courses_url); ?>" class="nav-tab <?php echo ('courses' === $view) ? 'nav-tab-active' : ''; ?>">Courses</a>
            <a href="<?php echo esc_url($lessons_url); ?>" class="nav-tab <?php echo ('lessons' === $view) ? 'nav-tab-active' : ''; ?>">Lessons</a>
        </h2>

        <?php if ('lessons' === $view) : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Lesson</th>
                        <th>Associated Course(s)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php ld_course_report_render_lessons_rows(); ?>
                </tbody>
            </table>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Section</th>
                        <th>Lessons</th>
                    </tr>
                </thead>
                <tbody>
                    <?php ld_course_report_render_courses_rows(); ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

function ld_course_report_get_lesson_id($lesson) {
    if (is_numeric($lesson)) {
        return (int) $lesson;
    }

    if ($lesson instanceof WP_Post) {
        return (int) $lesson->ID;
    }

    if (is_array($lesson)) {
        if (isset($lesson['post']) && $lesson['post'] instanceof WP_Post) {
            return (int) $lesson['post']->ID;
        }

        if (isset($lesson['ID'])) {
            return (int) $lesson['ID'];
        }
    }

    return 0;
}

function ld_course_report_render_courses_rows() {
    $courses = get_posts([
        'post_type' => 'sfwd-courses',
        'numberposts' => -1
    ]);

    foreach ($courses as $course) {
        $lessons = learndash_get_lesson_list($course->ID);
        $sections_raw = get_post_meta($course->ID, 'course_sections', true);
        $sections = !empty($sections_raw) ? json_decode($sections_raw, true) : [];
        $sections = is_array($sections) ? $sections : [];

        usort($sections, function ($a, $b) {
            $order_a = isset($a['order']) ? (int) $a['order'] : 0;
            $order_b = isset($b['order']) ? (int) $b['order'] : 0;
            return $order_a <=> $order_b;
        });

        $section_index = 0;
        $current_section = 'No Section';
        $rows = [];

        foreach ($lessons as $lesson_position => $lesson) {
            $lesson_id = ld_course_report_get_lesson_id($lesson);
            if (!$lesson_id || 'sfwd-lessons' !== get_post_type($lesson_id)) {
                continue;
            }

            $position = $lesson_position + 1;
            while (isset($sections[$section_index]) && isset($sections[$section_index]['order']) && (int) $sections[$section_index]['order'] <= $position) {
                $section_title = isset($sections[$section_index]['title']) ? trim((string) $sections[$section_index]['title']) : '';
                $current_section = '' !== $section_title ? $section_title : 'No Section';
                $section_index++;
            }

            $rows[$current_section][] = get_the_title($lesson_id);
        }

        if (empty($rows)) {
            $rows['No Section'] = ['—'];
        }

        foreach ($rows as $section_title => $section_lessons) {
            echo '<tr>';
            echo '<td>' . esc_html($course->post_title) . '</td>';
            echo '<td>' . esc_html($section_title) . '</td>';
            echo '<td>' . esc_html(implode(', ', $section_lessons)) . '</td>';
            echo '</tr>';
        }
    }
}

function ld_course_report_render_lessons_rows() {
    $lessons = get_posts([
        'post_type' => 'sfwd-lessons',
        'numberposts' => -1
    ]);

    foreach ($lessons as $lesson) {
        $course_ids = [];

        if (function_exists('learndash_get_courses_for_step')) {
            $step_course_ids = learndash_get_courses_for_step($lesson->ID, true);
            if (is_array($step_course_ids)) {
                $course_ids = array_merge($course_ids, $step_course_ids);
            }
        }

        if (function_exists('learndash_get_course_id')) {
            $course_ids[] = learndash_get_course_id($lesson->ID);
        }

        $course_ids[] = get_post_meta($lesson->ID, 'course_id', true);
        $course_ids[] = get_post_meta($lesson->ID, 'ld_course_id', true);

        $course_ids = array_unique(array_filter(array_map('intval', $course_ids)));
        $course_titles = [];

        foreach ($course_ids as $course_id) {
            if ('sfwd-courses' === get_post_type($course_id)) {
                $course_titles[] = get_the_title($course_id);
            }
        }

        echo '<tr>';
        echo '<td>' . esc_html($lesson->post_title) . '</td>';
        echo '<td>' . esc_html(!empty($course_titles) ? implode(', ', $course_titles) : '—') . '</td>';
        echo '</tr>';
    }
}
