<?php
/*
Plugin Name: LearnDash Course Map
Description: Displays courses with lessons in a structured table.
Version: 1.3
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

        <style>
        .ld-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .ld-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .ld-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }

        .ld-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            top: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        .ld-toggle input:checked + .ld-slider {
            background-color: #2271b1;
        }

        .ld-toggle input:checked + .ld-slider:before {
            transform: translateX(20px);
        }

        .ld-toggle-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 1em;
        }
        </style>

        <div style="margin-bottom: 1em; display: flex; align-items: center; flex-wrap: wrap; gap: 1em;">
            <label>
                <?php esc_html_e('Primary column', 'ld-course-map'); ?>
                <select class="ld-course-map-primary" style="margin-left: 0.5em;">
                    <option value="courses" <?php selected('courses', $primary); ?>>Courses</option>
                    <option value="lessons" <?php selected('lessons', $primary); ?>>Lessons</option>
                </select>
            </label>

            <label class="ld-toggle-label">
                <span class="ld-course-map-related-label"></span>
                <span class="ld-toggle">
                    <input type="checkbox" class="ld-course-map-show-related" checked>
                    <span class="ld-slider"></span>
                </span>
            </label>

            <label class="ld-toggle-label">
                <span><?php esc_html_e('Show Categories', 'ld-course-map'); ?></span>
                <span class="ld-toggle">
                    <input type="checkbox" class="ld-course-map-show-categories" checked>
                    <span class="ld-slider"></span>
                </span>
            </label>
        </div>

        <table class="widefat fixed striped">
            <thead>
                <tr class="ld-course-map-header-row"></tr>
            </thead>
            <tbody class="ld-course-map-body"></tbody>
        </table>
    </div>

    <script>
    (function() {
        var container = document.getElementById(<?php echo wp_json_encode($container_id); ?>);
        if (!container) return;

        var data = <?php echo wp_json_encode($table_data); ?>;
        var primarySelect = container.querySelector('.ld-course-map-primary');
        var showRelatedCheckbox = container.querySelector('.ld-course-map-show-related');
        var showCategoriesCheckbox = container.querySelector('.ld-course-map-show-categories');
        var relatedLabel = container.querySelector('.ld-course-map-related-label');
        var headerRow = container.querySelector('.ld-course-map-header-row');
        var body = container.querySelector('.ld-course-map-body');

        function render() {
            var primary = primarySelect.value === 'lessons' ? 'lessons' : 'courses';
            var labels = data.labels[primary];
            var rows = data.rows[primary] || [];
            var showRelated = !!showRelatedCheckbox.checked;
            var showCategories = !!showCategoriesCheckbox.checked;

            relatedLabel.textContent = data.toggle_labels[primary];

            headerRow.innerHTML = '';
            body.innerHTML = '';

            var primaryTh = document.createElement('th');
            primaryTh.textContent = labels.primary;
            headerRow.appendChild(primaryTh);

            if (showRelated) {
                var relatedTh = document.createElement('th');
                relatedTh.textContent = labels.related;
                headerRow.appendChild(relatedTh);
            }

            if (showCategories) {
                var categoriesTh = document.createElement('th');
                categoriesTh.textContent = labels.categories;
                headerRow.appendChild(categoriesTh);
            }

            rows.forEach(function(row) {
                var tr = document.createElement('tr');

                var primaryTd = document.createElement('td');
                primaryTd.textContent = row.primary;
                tr.appendChild(primaryTd);

                if (showRelated) {
                    var relatedTd = document.createElement('td');
                    relatedTd.textContent = row.related;
                    tr.appendChild(relatedTd);
                }

                if (showCategories) {
                    var categoriesTd = document.createElement('td');
                    categoriesTd.textContent = row.categories;
                    tr.appendChild(categoriesTd);
                }

                body.appendChild(tr);
            });
        }

        primarySelect.addEventListener('change', render);
        showRelatedCheckbox.addEventListener('change', render);
        showCategoriesCheckbox.addEventListener('change', render);

        render();
    })();
    </script>
    <?php

    return ob_get_clean();
}

function ld_course_report_get_table_data() {
    $courses = get_posts([
        'post_type' => 'sfwd-courses',
        'numberposts' => -1,
    ]);

    $course_rows = [];
    $lesson_course_map = [];

    foreach ($courses as $course) {
        $course_id = (int) $course->ID;
        $lessons = learndash_get_lesson_list($course_id);

        $lesson_titles = [];

        foreach ($lessons as $lesson) {
            $lesson_id = is_object($lesson) ? $lesson->ID : $lesson;
            if ($lesson_id) {
                $lesson_titles[] = get_the_title($lesson_id);
                $lesson_course_map[$lesson_id][$course_id] = $course->post_title;
            }
        }

        $course_rows[] = [
            'primary' => $course->post_title,
            'related' => !empty($lesson_titles) ? implode(', ', $lesson_titles) : '—',
            'categories' => ld_course_report_get_categories_text($course_id, 'ld_course_category'),
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
            $course_id = function_exists('learndash_get_course_id')
                ? learndash_get_course_id($lesson_id)
                : get_post_meta($lesson_id, 'course_id', true);

            if ($course_id) {
                $lesson_course_map[$lesson_id][$course_id] = get_the_title($course_id);
            }
        }

        $course_titles = !empty($lesson_course_map[$lesson_id])
            ? array_values(array_unique(array_filter($lesson_course_map[$lesson_id])))
            : ['—'];

        $lesson_rows[] = [
            'primary' => $lesson->post_title,
            'related' => implode(', ', $course_titles),
            'categories' => ld_course_report_get_categories_text($lesson_id, 'ld_lesson_category'),
        ];
    }

    return [
        'labels' => [
            'courses' => [
                'primary' => 'Course',
                'related' => 'Lessons',
                'categories' => 'Categories',
            ],
            'lessons' => [
                'primary' => 'Lesson',
                'related' => 'Courses',
                'categories' => 'Categories',
            ],
        ],
        'toggle_labels' => [
            'courses' => 'Show Lessons',
            'lessons' => 'Show Courses',
        ],
        'rows' => [
            'courses' => $course_rows,
            'lessons' => $lesson_rows,
        ],
    ];
}

function ld_course_report_get_categories_text($post_id, $primary_taxonomy) {
    $terms = get_the_terms($post_id, $primary_taxonomy);

    if (empty($terms) || is_wp_error($terms)) {
        $terms = get_the_terms($post_id, 'category');
    }

    if (empty($terms) || is_wp_error($terms)) {
        return '—';
    }

    $category_names = array_values(array_unique(array_filter(wp_list_pluck($terms, 'name'))));

    return !empty($category_names) ? implode(', ', $category_names) : '—';
}
