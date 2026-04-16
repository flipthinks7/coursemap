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
add_action('admin_post_ld_course_map_export', 'ld_course_map_handle_export');

function ld_course_map_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'primary' => 'courses',
        ],
        $atts,
        'ld_course_map'
    );

    $primary = sanitize_key($atts['primary']);
    if (!in_array($primary, ['courses', 'lessons', 'categories'], true)) {
        $primary = 'courses';
    }

    $table_data = ld_course_report_get_table_data();
    $can_export = current_user_can('manage_options');
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
                    <option value="categories" <?php selected('categories', $primary); ?>>Categories</option>
                </select>
            </label>

            <label class="ld-toggle-label ld-course-map-related-toggle">
                <span class="ld-course-map-related-label"></span>
                <span class="ld-toggle">
                    <input type="checkbox" class="ld-course-map-show-related" checked>
                    <span class="ld-slider"></span>
                </span>
            </label>

            <label class="ld-toggle-label ld-course-map-lessons-toggle" style="display: none;">
                <span class="ld-course-map-lessons-label"><?php esc_html_e('Show Lessons', 'ld-course-map'); ?></span>
                <span class="ld-toggle">
                    <input type="checkbox" class="ld-course-map-show-lessons" checked>
                    <span class="ld-slider"></span>
                </span>
            </label>

            <label class="ld-toggle-label ld-course-map-categories-toggle">
                <span><?php esc_html_e('Show Categories', 'ld-course-map'); ?></span>
                <span class="ld-toggle">
                    <input type="checkbox" class="ld-course-map-show-categories" checked>
                    <span class="ld-slider"></span>
                </span>
            </label>

            <?php if ($can_export) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ld-course-map-export-form" style="display: flex; align-items: center; gap: 0.5em; margin-left: auto;">
                    <input type="hidden" name="action" value="ld_course_map_export">
                    <?php wp_nonce_field('ld_course_map_export', 'ld_course_map_export_nonce'); ?>
                    <input type="hidden" name="primary" class="ld-course-map-export-primary" value="<?php echo esc_attr($primary); ?>">
                    <input type="hidden" name="show_related" class="ld-course-map-export-show-related" value="1">
                    <input type="hidden" name="show_categories" class="ld-course-map-export-show-categories" value="1">
                    <input type="hidden" name="show_lessons" class="ld-course-map-export-show-lessons" value="1">
                    <label>
                        <span class="screen-reader-text"><?php esc_html_e('Export format', 'ld-course-map'); ?></span>
                        <select name="format" class="ld-course-map-export-format">
                            <option value="csv"><?php esc_html_e('CSV', 'ld-course-map'); ?></option>
                            <option value="xls"><?php esc_html_e('Excel', 'ld-course-map'); ?></option>
                        </select>
                    </label>
                    <button type="submit" class="button"><?php esc_html_e('Export', 'ld-course-map'); ?></button>
                </form>
            <?php endif; ?>
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
        var showLessonsCheckbox = container.querySelector('.ld-course-map-show-lessons');
        var relatedLabel = container.querySelector('.ld-course-map-related-label');
        var lessonsLabel = container.querySelector('.ld-course-map-lessons-label');
        var lessonsToggle = container.querySelector('.ld-course-map-lessons-toggle');
        var categoriesToggle = container.querySelector('.ld-course-map-categories-toggle');
        var headerRow = container.querySelector('.ld-course-map-header-row');
        var body = container.querySelector('.ld-course-map-body');
        var exportForm = container.querySelector('.ld-course-map-export-form');
        var exportPrimaryInput = container.querySelector('.ld-course-map-export-primary');
        var exportShowRelatedInput = container.querySelector('.ld-course-map-export-show-related');
        var exportShowCategoriesInput = container.querySelector('.ld-course-map-export-show-categories');
        var exportShowLessonsInput = container.querySelector('.ld-course-map-export-show-lessons');

        function syncExportState() {
            if (!exportForm) return;

            var primary = primarySelect.value;
            if (primary !== 'lessons' && primary !== 'categories') {
                primary = 'courses';
            }

            var isCategoriesPrimary = primary === 'categories';

            exportPrimaryInput.value = primary;
            exportShowRelatedInput.value = showRelatedCheckbox.checked ? '1' : '0';
            exportShowCategoriesInput.value = (!isCategoriesPrimary && showCategoriesCheckbox.checked) ? '1' : '0';
            exportShowLessonsInput.value = (isCategoriesPrimary && showLessonsCheckbox.checked) ? '1' : '0';
        }

        function render() {
            var primary = primarySelect.value;
            if (primary !== 'lessons' && primary !== 'categories') {
                primary = 'courses';
            }

            var isCategoriesPrimary = primary === 'categories';
            var labels = data.labels[primary];
            var rows = data.rows[primary] || [];
            var showRelated = !!showRelatedCheckbox.checked;
            var showCategories = !isCategoriesPrimary && !!showCategoriesCheckbox.checked;
            var showLessons = isCategoriesPrimary && !!showLessonsCheckbox.checked;

            relatedLabel.textContent = data.toggle_labels[primary].related;
            lessonsLabel.textContent = data.toggle_labels[primary].lessons || 'Show Lessons';
            lessonsToggle.style.display = isCategoriesPrimary ? 'flex' : 'none';
            categoriesToggle.style.display = isCategoriesPrimary ? 'none' : 'flex';

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

            if (isCategoriesPrimary) {
                if (showLessons) {
                    var lessonsTh = document.createElement('th');
                    lessonsTh.textContent = labels.lessons;
                    headerRow.appendChild(lessonsTh);
                }
            } else if (showCategories) {
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

                if (isCategoriesPrimary) {
                    if (showLessons) {
                        var lessonsTd = document.createElement('td');
                        lessonsTd.textContent = row.lessons;
                        tr.appendChild(lessonsTd);
                    }
                } else if (showCategories) {
                    var categoriesTd = document.createElement('td');
                    categoriesTd.textContent = row.categories;
                    tr.appendChild(categoriesTd);
                }

                body.appendChild(tr);
            });

            syncExportState();
        }

        primarySelect.addEventListener('change', render);
        showRelatedCheckbox.addEventListener('change', render);
        showCategoriesCheckbox.addEventListener('change', render);
        showLessonsCheckbox.addEventListener('change', render);
        if (exportForm) {
            exportForm.addEventListener('submit', syncExportState);
        }

        render();
    })();
    </script>
    <?php

    return ob_get_clean();
}

function ld_course_map_handle_export() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to export this report.', 'ld-course-map'));
    }

    if (!isset($_POST['ld_course_map_export_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ld_course_map_export_nonce'])), 'ld_course_map_export')) {
        wp_die(esc_html__('Invalid export request.', 'ld-course-map'));
    }

    $format = isset($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : 'csv';
    if (!in_array($format, ['csv', 'xls'], true)) {
        $format = 'csv';
    }

    $primary = isset($_POST['primary']) ? sanitize_key(wp_unslash($_POST['primary'])) : 'courses';
    if (!in_array($primary, ['courses', 'lessons', 'categories'], true)) {
        $primary = 'courses';
    }

    $show_related = isset($_POST['show_related']) && '1' === sanitize_text_field(wp_unslash($_POST['show_related']));
    $show_categories = isset($_POST['show_categories']) && '1' === sanitize_text_field(wp_unslash($_POST['show_categories']));
    $show_lessons = isset($_POST['show_lessons']) && '1' === sanitize_text_field(wp_unslash($_POST['show_lessons']));

    $table_data = ld_course_report_get_table_data();
    $labels = isset($table_data['labels'][$primary]) && is_array($table_data['labels'][$primary]) ? $table_data['labels'][$primary] : [];
    $rows = isset($table_data['rows'][$primary]) && is_array($table_data['rows'][$primary]) ? $table_data['rows'][$primary] : [];

    $column_keys = ['primary'];
    $column_labels = [isset($labels['primary']) ? (string) $labels['primary'] : 'Primary'];

    if ($show_related && isset($labels['related'])) {
        $column_keys[] = 'related';
        $column_labels[] = (string) $labels['related'];
    }

    if ('categories' === $primary) {
        if ($show_lessons && isset($labels['lessons'])) {
            $column_keys[] = 'lessons';
            $column_labels[] = (string) $labels['lessons'];
        }
    } elseif ($show_categories && isset($labels['categories'])) {
        $column_keys[] = 'categories';
        $column_labels[] = (string) $labels['categories'];
    }

    $filename_primary = sanitize_key($primary);
    $filename = sanitize_file_name('ld-course-map-' . $filename_primary . '-' . gmdate('Y-m-d'));
    $xls_filename = $filename . '.xls';
    $csv_filename = $filename . '.csv';

    nocache_headers();

    if ('xls' === $format) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($xls_filename));

        echo '<table border="1"><thead><tr>';
        foreach ($column_labels as $column_label) {
            echo '<th>' . esc_html($column_label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($column_keys as $column_key) {
                $value = isset($row[$column_key]) ? (string) $row[$column_key] : '';
                echo '<td>' . esc_html($value) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        exit;
    }

    $output = fopen('php://output', 'w');
    if (false === $output) {
        wp_die(esc_html__('Unable to create export file.', 'ld-course-map'));
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($csv_filename));

    fputcsv($output, $column_labels);

    foreach ($rows as $row) {
        $csv_row = [];
        foreach ($column_keys as $column_key) {
            $csv_row[] = isset($row[$column_key]) ? (string) $row[$column_key] : '';
        }
        fputcsv($output, $csv_row);
    }

    fclose($output);
    exit;
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
            'categories' => ld_course_map_get_categories_text($course_id, 'ld_course_category'),
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
            'categories' => ld_course_map_get_categories_text($lesson_id, 'ld_lesson_category'),
        ];
    }

    $category_terms = get_terms([
        'taxonomy' => 'ld_course_category',
        'hide_empty' => false,
    ]);

    if (empty($category_terms) || is_wp_error($category_terms)) {
        $category_terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);
    }

    if (is_wp_error($category_terms) || empty($category_terms)) {
        $category_terms = [];
    }

    $category_rows = [];

    foreach ($category_terms as $category_term) {
        $course_taxonomy = $category_term->taxonomy === 'category' ? 'category' : 'ld_course_category';
        $courses_in_category = get_posts([
            'post_type' => 'sfwd-courses',
            'numberposts' => -1,
            'tax_query' => [
                [
                    'taxonomy' => $course_taxonomy,
                    'field' => 'term_id',
                    'terms' => (int) $category_term->term_id,
                ],
            ],
        ]);

        $course_titles = array_values(array_unique(array_filter(wp_list_pluck($courses_in_category, 'post_title'))));
        if (empty($course_titles)) {
            $course_titles = ['—'];
        }

        $lessons_in_category = get_posts([
            'post_type' => 'sfwd-lessons',
            'numberposts' => -1,
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'ld_lesson_category',
                    'field' => 'slug',
                    'terms' => (string) $category_term->slug,
                ],
                [
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => (string) $category_term->slug,
                ],
            ],
        ]);

        $lesson_titles = array_values(array_unique(array_filter(wp_list_pluck($lessons_in_category, 'post_title'))));
        if (empty($lesson_titles)) {
            $lesson_titles = ['—'];
        }

        $category_rows[] = [
            'primary' => $category_term->name,
            'related' => implode(', ', $course_titles),
            'lessons' => implode(', ', $lesson_titles),
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
            'categories' => [
                'primary' => 'Category',
                'related' => 'Courses',
                'lessons' => 'Lessons',
            ],
        ],
        'toggle_labels' => [
            'courses' => [
                'related' => 'Show Lessons',
            ],
            'lessons' => [
                'related' => 'Show Courses',
            ],
            'categories' => [
                'related' => 'Show Courses',
                'lessons' => 'Show Lessons',
            ],
        ],
        'rows' => [
            'courses' => $course_rows,
            'lessons' => $lesson_rows,
            'categories' => $category_rows,
        ],
    ];
}

function ld_course_map_get_categories_text($post_id, $primary_taxonomy) {
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
