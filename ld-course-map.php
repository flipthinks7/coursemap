<?php
/*
Plugin Name: LearnDash Course Map
Description: Displays courses with sections and lessons in a structured table.
Version: 1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('ld_course_map', 'ld_course_map_shortcode');

function ld_course_map_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'primary' => 'courses',
        ],
        $atts,
        'ld_course_map'
    );

    $primary = sanitize_key($atts['primary']);
    if (!in_array($primary, ['courses', 'lessons'], true)) {
        $primary = 'courses';
    }

    $table_data = ld_course_report_get_table_data();
    $container_id = 'ld-course-map-' . wp_unique_id();

    ob_start();
    ?>
    <div id="<?php echo esc_attr($container_id); ?>" class="ld-course-map">
        <div class="ld-course-map-controls" style="margin-bottom: 1em; display: flex; flex-wrap: wrap; gap: 1em; align-items: center;">
            <label>
                <?php esc_html_e('Primary column', 'ld-course-map'); ?>
                <select class="ld-course-map-primary" style="margin-left: 0.5em;">
                    <option value="courses" <?php selected('courses', $primary); ?>><?php esc_html_e('Courses', 'ld-course-map'); ?></option>
                    <option value="lessons" <?php selected('lessons', $primary); ?>><?php esc_html_e('Lessons', 'ld-course-map'); ?></option>
                </select>
            </label>

            <label>
                <input type="checkbox" class="ld-course-map-show-sections" checked>
                <?php esc_html_e('Show Sections', 'ld-course-map'); ?>
            </label>

            <label>
                <input type="checkbox" class="ld-course-map-show-related" checked>
                <span class="ld-course-map-related-label"><?php echo esc_html($table_data['toggle_labels']['courses']); ?></span>
            </label>
        </div>

        <table class="widefat fixed striped ld-course-map-table">
            <thead>
                <tr class="ld-course-map-header-row"></tr>
            </thead>
            <tbody class="ld-course-map-body"></tbody>
        </table>
    </div>

    <script>
    (function() {
        var container = document.getElementById(<?php echo wp_json_encode($container_id); ?>);
        if (!container) {
            return;
        }

        var data = <?php echo wp_json_encode($table_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var primarySelect = container.querySelector('.ld-course-map-primary');
        var showSectionsCheckbox = container.querySelector('.ld-course-map-show-sections');
        var showRelatedCheckbox = container.querySelector('.ld-course-map-show-related');
        var relatedLabel = container.querySelector('.ld-course-map-related-label');
        var headerRow = container.querySelector('.ld-course-map-header-row');
        var body = container.querySelector('.ld-course-map-body');

        function render() {
            var primary = primarySelect.value === 'lessons' ? 'lessons' : 'courses';
            var labels = data.labels[primary];
            var rows = data.rows[primary] || [];
            var showSections = !!showSectionsCheckbox.checked;
            var showRelated = !!showRelatedCheckbox.checked;

            relatedLabel.textContent = data.toggle_labels[primary];

            headerRow.innerHTML = '';
            body.innerHTML = '';

            var primaryTh = document.createElement('th');
            primaryTh.textContent = labels.primary;
            headerRow.appendChild(primaryTh);

            if (showSections) {
                var sectionsTh = document.createElement('th');
                sectionsTh.textContent = labels.sections;
                headerRow.appendChild(sectionsTh);
            }

            if (showRelated) {
                var relatedTh = document.createElement('th');
                relatedTh.textContent = labels.related;
                headerRow.appendChild(relatedTh);
            }

            rows.forEach(function(row) {
                var tr = document.createElement('tr');

                var primaryTd = document.createElement('td');
                primaryTd.textContent = row.primary;
                tr.appendChild(primaryTd);

                if (showSections) {
                    var sectionsTd = document.createElement('td');
                    sectionsTd.textContent = row.sections;
                    tr.appendChild(sectionsTd);
                }

                if (showRelated) {
                    var relatedTd = document.createElement('td');
                    relatedTd.textContent = row.related;
                    tr.appendChild(relatedTd);
                }

                body.appendChild(tr);
            });
        }

        primarySelect.addEventListener('change', render);
        showSectionsCheckbox.addEventListener('change', render);
        showRelatedCheckbox.addEventListener('change', render);

        render();
    })();
    </script>
    <?php

    return ob_get_clean();
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

function ld_course_report_no_section_label() {
    return __('No Section', 'ld-course-map');
}

function ld_course_report_get_course_sections($course_id) {
    $sections_raw = get_post_meta($course_id, 'course_sections', true);
    $sections = [];

    if (!empty($sections_raw)) {
        $decoded_sections = json_decode($sections_raw, true);
        if (is_array($decoded_sections)) {
            $sections = $decoded_sections;

            usort($sections, function ($a, $b) {
                $order_a = isset($a['order']) ? (int) $a['order'] : 0;
                $order_b = isset($b['order']) ? (int) $b['order'] : 0;

                return $order_a <=> $order_b;
            });
        }
    }

    return $sections;
}

function ld_course_report_build_lesson_section_map($course_id, $lessons, $sections) {
    $section_index = 0;
    $current_section = ld_course_report_no_section_label();
    $lesson_sections = [];

    foreach ($lessons as $lesson_position => $lesson) {
        $lesson_id = ld_course_report_get_lesson_id($lesson);
        if (!$lesson_id || 'sfwd-lessons' !== get_post_type($lesson_id)) {
            continue;
        }

        $position = $lesson_position + 1;
        while (isset($sections[$section_index]) && isset($sections[$section_index]['order']) && (int) $sections[$section_index]['order'] <= $position) {
            $section_title = isset($sections[$section_index]['title']) ? trim((string) $sections[$section_index]['title']) : '';
            $current_section = '' !== $section_title ? $section_title : ld_course_report_no_section_label();
            $section_index++;
        }

        $lesson_sections[$lesson_id] = $current_section;
    }

    return $lesson_sections;
}

function ld_course_report_get_table_data() {
    $courses = get_posts([
        'post_type' => 'sfwd-courses',
        'numberposts' => -1,
    ]);

    $course_rows = [];
    $lesson_course_map = [];
    $lesson_section_map = [];

    foreach ($courses as $course) {
        $course_id = (int) $course->ID;
        $lessons = learndash_get_lesson_list($course_id);
        $sections = ld_course_report_get_course_sections($course_id);
        $sections_by_lesson = ld_course_report_build_lesson_section_map($course_id, $lessons, $sections);

        $lesson_titles = [];
        foreach ($sections_by_lesson as $lesson_id => $section_title) {
            $lesson_titles[] = get_the_title($lesson_id);
            $lesson_course_map[$lesson_id][$course_id] = $course->post_title;
            $lesson_section_map[$lesson_id][$course_id] = $section_title;
        }

        $section_titles = array_values(array_filter(array_map(function ($section) {
            return isset($section['title']) ? trim((string) $section['title']) : '';
        }, $sections)));

        if (empty($section_titles)) {
            $section_titles = [ld_course_report_no_section_label()];
        }

        if (empty($lesson_titles)) {
            $lesson_titles = ['—'];
        }

        $course_rows[] = [
            'primary' => $course->post_title,
            'sections' => implode(', ', array_unique($section_titles)),
            'related' => implode(', ', $lesson_titles),
        ];
    }

    $lessons = get_posts([
        'post_type' => 'sfwd-lessons',
        'numberposts' => -1,
    ]);

    $lesson_rows = [];

    foreach ($lessons as $lesson) {
        $lesson_id = (int) $lesson->ID;

        if (empty($lesson_course_map[$lesson_id])) {
            $course_id = 0;
            if (function_exists('learndash_get_course_id')) {
                $course_id = (int) learndash_get_course_id($lesson_id);
            }
            if (!$course_id) {
                $course_id = (int) get_post_meta($lesson_id, 'course_id', true);
            }

            if ($course_id && 'sfwd-courses' === get_post_type($course_id)) {
                $lesson_course_map[$lesson_id][$course_id] = get_the_title($course_id);
                $lesson_section_map[$lesson_id][$course_id] = ld_course_report_no_section_label();
            }
        }

        $course_titles = !empty($lesson_course_map[$lesson_id])
            ? array_values(array_unique(array_filter($lesson_course_map[$lesson_id])))
            : ['—'];

        $section_titles = !empty($lesson_section_map[$lesson_id])
            ? array_values(array_unique(array_filter($lesson_section_map[$lesson_id])))
            : [ld_course_report_no_section_label()];

        $lesson_rows[] = [
            'primary' => $lesson->post_title,
            'sections' => implode(', ', $section_titles),
            'related' => implode(', ', $course_titles),
        ];
    }

    return [
        'labels' => [
            'courses' => [
                'primary' => __('Course', 'ld-course-map'),
                'sections' => __('Sections', 'ld-course-map'),
                'related' => __('Lessons', 'ld-course-map'),
            ],
            'lessons' => [
                'primary' => __('Lesson', 'ld-course-map'),
                'sections' => __('Sections', 'ld-course-map'),
                'related' => __('Courses', 'ld-course-map'),
            ],
        ],
        'toggle_labels' => [
            'courses' => __('Show Lessons', 'ld-course-map'),
            'lessons' => __('Show Courses', 'ld-course-map'),
        ],
        'rows' => [
            'courses' => $course_rows,
            'lessons' => $lesson_rows,
        ],
    ];
}
