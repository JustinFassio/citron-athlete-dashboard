<?php
function divi_child_enqueue_styles_and_scripts() {
    // Enqueue parent (Divi) style
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');

    // Enqueue variables.css
    wp_enqueue_style(
        'variables-style',
        get_stylesheet_directory_uri() . '/variables.css',
        array(),
        wp_get_theme()->get('Version')
    );

    // Enqueue child style with dependency on parent style and variables
    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style', 'variables-style'),
        wp_get_theme()->get('Version')
    );

    // Enqueue custom CSS file
    wp_enqueue_style(
        'custom-styles', 
        get_stylesheet_directory_uri() . '/custom-styles.css', 
        array('parent-style'), 
        wp_get_theme()->get('Version')
    );

    // Enqueue jQuery and its dependencies
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_script('jquery-effects-core');

    // Enqueue Chart.js
    wp_enqueue_script(
        'chartjs', 
        'https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js', 
        array(), 
        '4.3.0', 
        true
    );

    // Enqueue Chart.js Adapter
    wp_enqueue_script(
        'chartjs-adapter-date-fns',
        'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
        array('chartjs'),
        '3.0.0',
        true
    );

    // Enqueue custom JS file with dynamic version
    $custom_js_file = get_stylesheet_directory() . '/js/custom-scripts.js';
    $custom_js_version = file_exists($custom_js_file) ? filemtime($custom_js_file) : '1.0';
    wp_enqueue_script(
        'custom-scripts', 
        get_stylesheet_directory_uri() . '/js/custom-scripts.js', 
        array('jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-effects-core', 'chartjs', 'chartjs-adapter-date-fns'), 
        $custom_js_version, 
        true
    );

    // Get exercise tests data
    $exercise_tests = get_exercise_tests();

    // Localize script for AJAX URL, nonce, and exercise tests
    wp_localize_script('custom-scripts', 'athleteDashboard', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('athlete_dashboard_nonce'),
        'exerciseTests' => $exercise_tests
    ));
}
add_action('wp_enqueue_scripts', 'divi_child_enqueue_styles_and_scripts', 20);

// Check Divi Shortcodes
function check_divi_shortcodes() {
    if (current_user_can('manage_options')) {
        echo '<div style="display:none;">Divi Shortcodes Processed: ' . (shortcode_exists('et_pb_section') ? 'Yes' : 'No') . '</div>';
    }
}
add_action('wp_footer', 'check_divi_shortcodes');

// Grant subscriber upload capability
function grant_upload_capability_to_subscribers() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->add_cap('upload_files');
    }
}
add_action('init', 'grant_upload_capability_to_subscribers');

//Handle profile update
function handle_profile_update() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $user_id = get_current_user_id();
    $user_data = array(
        'ID' => $user_id,
        'display_name' => sanitize_text_field($_POST['display_name']),
        'user_email' => sanitize_email($_POST['email']),
        'description' => sanitize_textarea_field($_POST['bio'])
    );

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    } else {
        wp_send_json_success(['message' => 'Profile updated successfully']);
    }
}
add_action('wp_ajax_update_profile', 'handle_profile_update');

// Create custom post types
function create_custom_post_types() {
    $post_types = array(
        'workout' => 'Workouts',
        'progress' => 'Progress',
        'overview' => 'Overview',
        'fitness_plan' => 'Fitness Plan',
        'nutrition' => 'Nutrition',
        'upcoming_workouts' => 'Upcoming Workouts'
    );

    foreach ($post_types as $slug => $name) {
        $labels = array(
            'name' => _x($name, 'post type general name', 'athlete-dashboard'),
            'singular_name' => _x(rtrim($name, 's'), 'post type singular name', 'athlete-dashboard'),
            'menu_name' => _x($name, 'admin menu', 'athlete-dashboard'),
            'name_admin_bar' => _x(rtrim($name, 's'), 'add new on admin bar', 'athlete-dashboard'),
            'add_new' => _x('Add New', $slug, 'athlete-dashboard'),
            'add_new_item' => __('Add New ' . rtrim($name, 's'), 'athlete-dashboard'),
            'new_item' => __('New ' . rtrim($name, 's'), 'athlete-dashboard'),
            'edit_item' => __('Edit ' . rtrim($name, 's'), 'athlete-dashboard'),
            'view_item' => __('View ' . rtrim($name, 's'), 'athlete-dashboard'),
            'all_items' => __('All ' . $name, 'athlete-dashboard'),
            'search_items' => __('Search ' . $name, 'athlete-dashboard'),
            'parent_item_colon' => __('Parent ' . rtrim($name, 's') . ':', 'athlete-dashboard'),
            'not_found' => __('No ' . strtolower($name) . ' found.', 'athlete-dashboard'),
            'not_found_in_trash' => __('No ' . strtolower($name) . ' found in Trash.', 'athlete-dashboard')
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => $slug),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
            'show_in_rest' => true,
        );

        register_post_type($slug, $args);
    }
}
add_action('init', 'create_custom_post_types');

// Render Athlete Dashboard
function athlete_workout_generator_render_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to view your dashboard.</p>';
    }
    $current_user = wp_get_current_user();

    ob_start();
    ?>
    <div class="athlete-dashboard">
        <h2>Welcome, <?php echo esc_html($current_user->display_name); ?>!</h2>

        <div class="dashboard-section">
            <h3>Overview</h3>
            <?php echo do_shortcode('[user_overview]'); ?>
        </div>
        
        <div class="dashboard-section">
            <h3>Your Workouts</h3>
            <?php echo do_shortcode('[user_workouts]'); ?>
        </div>
        
        <div class="dashboard-section">
            <h3>Progress Tracker</h3>
            <?php echo do_shortcode('[user_progress]'); ?>
        </div>

        <div class="dashboard-section">
            <h3>Fitness Plan</h3>
            <?php echo do_shortcode('[user_fitness_plan]'); ?>
        </div>

        <div class="dashboard-section">
            <h3>Nutrition</h3>
            <?php echo do_shortcode('[user_nutrition]'); ?>
        </div>
        
        <div class="dashboard-section">
            <h3>Upcoming Workouts</h3>
            <?php echo do_shortcode('[user_upcoming_workouts]'); ?>
        </div>
        
        <div class="dashboard-section">
            <h3>Account Details</h3>
            <p>Username: <?php echo esc_html($current_user->user_login); ?></p>
            <p>Name: <?php echo esc_html($current_user->display_name); ?></p>
            <p>Email: <?php echo esc_html($current_user->user_email); ?></p>
            <p>Bio: <?php echo esc_html($current_user->description); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('athlete_dashboard', 'athlete_workout_generator_render_dashboard');

// Function to get chart data
function get_chart_data() {
    $user_id = get_current_user_id();
    $progress_data = get_user_weight_progress($user_id); // Assuming this function exists
    $data = array();
    foreach ($progress_data as $entry) {
        $data[] = array(
            'x' => date('Y-m-d', strtotime($entry['date'])),
            'y' => floatval($entry['weight'])
        );
    }
    return array(
        'datasets' => array(
            array(
                'label' => 'Body Weight Progress',
                'data' => $data,
                'fill' => false,
                'borderColor' => 'rgb(75, 192, 192)',
                'tension' => 0.1
            )
        )
    );
}

// AJAX handler for getting user progress
function get_most_recent_weight_ajax() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $most_recent_weight = get_most_recent_weight($user_id);
    
    if ($most_recent_weight === 'No data') {
        wp_send_json_error('No weight data found');
    } else {
        wp_send_json_success($most_recent_weight);
    }
}
add_action('wp_ajax_get_most_recent_weight', 'get_most_recent_weight_ajax');


// AJAX handler for getting user progress
function get_user_progress() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $progress = get_user_meta($user_id, 'weight_progress', true);
    
    if (!is_array($progress)) {
        wp_send_json_error('No progress data found');
    }
    
    // Sort progress data by date
    usort($progress, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $data = array();
    foreach ($progress as $entry) {
        $data[] = array(
            'x' => date('Y-m-d', strtotime($entry['date'])),
            'y' => floatval($entry['weight'])
        );
    }
    
    $chart_data = array(
        'datasets' => array(
            array(
                'label' => 'Body Weight Progress',
                'data' => $data,
                'fill' => false,
                'borderColor' => 'rgb(75, 192, 192)',
                'tension' => 0.1
            )
        )
    );
    
    wp_send_json_success($chart_data);
}
add_action('wp_ajax_get_user_progress', 'get_user_progress');

/**
 * Handle progress submission via AJAX
 */
function handle_progress_submission() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Get and sanitize user inputs
    $user_id = get_current_user_id();
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $unit = isset($_POST['weight_unit']) ? sanitize_text_field($_POST['weight_unit']) : 'kg';
    $date = isset($_POST['date']) && !empty($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('mysql');

    if ($weight <= 0) {
        wp_send_json_error('Invalid weight value');
    }

    // Convert weight to kg if it's in lbs
    if ($unit === 'lbs') {
        $weight = $weight / 2.20462; // Convert lbs to kg
        $unit = 'kg';
    }

    // Store or update the progress
    $updated = store_user_progress($user_id, $weight, $unit, $date);

    // Update the body_weight user meta
    update_user_meta($user_id, 'body_weight', $weight . ' ' . $unit);

    // Send response
    if ($updated) {
        wp_send_json_success('Progress updated successfully');
    } else {
        wp_send_json_success('Progress added successfully');
    }
}
add_action('wp_ajax_handle_progress_submission', 'handle_progress_submission');

// Hook the function to the AJAX action
add_action('wp_ajax_handle_progress_submission', 'handle_progress_submission');

// Function to store user progress
function store_user_progress($user_id, $weight, $unit, $date = null) {
    if (!$date) {
        $date = current_time('mysql');
    }
    
    $progress = get_user_meta($user_id, 'weight_progress', true);
    if (!is_array($progress)) {
        $progress = array();
    }
    
    // Convert date to Y-m-d format for consistent comparison
    $entry_date = date('Y-m-d', strtotime($date));
    
    // Check if an entry for this date already exists
    $existing_entry_index = array_search($entry_date, array_column($progress, 'date'));
    
    if ($existing_entry_index !== false) {
        // Update existing entry
        $progress[$existing_entry_index] = array(
            'date' => $entry_date,
            'weight' => floatval($weight),
            'unit' => $unit
        );
    } else {
        // Add new entry
        $progress[] = array(
            'date' => $entry_date,
            'weight' => floatval($weight),
            'unit' => $unit
        );
    }
    
    // Sort progress array by date (newest first)
    usort($progress, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    update_user_meta($user_id, 'weight_progress', $progress);
    
    return $existing_entry_index !== false;
}

// Handle profile picture upload
function handle_profile_picture_upload() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $user_id = get_current_user_id();

    if (!isset($_FILES['profile_picture'])) {
        wp_send_json_error(['message' => 'No file uploaded']);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('profile_picture', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
    }

    // Update user meta with the new attachment ID
    update_user_meta($user_id, 'wp_user_avatar', $attachment_id);

    // Get the URL of the uploaded image
    $image_url = wp_get_attachment_url($attachment_id);

    wp_send_json_success(['url' => $image_url]);
}
add_action('wp_ajax_update_profile_picture', 'handle_profile_picture_upload');

	// Get custom avatar
function get_custom_avatar($avatar, $id_or_email, $size, $default, $alt) {
    $user = false;

    if (is_numeric($id_or_email)) {
        $id = (int) $id_or_email;
        $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $id = (int) $id_or_email->user_id;
            $user = get_user_by('id', $id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
        $avatar_id = get_user_meta($user->ID, 'wp_user_avatar', true);
        if ($avatar_id) {
            $avatar_url = wp_get_attachment_image_src($avatar_id, 'thumbnail');
            if ($avatar_url) {
                $avatar = "<img alt='{$alt}' src='{$avatar_url[0]}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
            }
        }
    }

    return $avatar;
}
add_filter('get_avatar', 'get_custom_avatar', 10, 5);

// Shortcode to display user's email
function display_user_email() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        return esc_html($current_user->user_email);
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_email', 'display_user_email');

// Shortcode to display user's username
function display_user_name() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        return esc_html($current_user->user_login);
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_name', 'display_user_name');

// Shortcode to display user's workouts
function display_user_workouts() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $args = array(
            'post_type' => 'workout',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_subscriber',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            )
        );
        $workouts = new WP_Query($args);

        if ($workouts->have_posts()) {
            $output = '<div class="user-workouts">';
            while ($workouts->have_posts()) {
                $workouts->the_post();
                $workout_json = get_post_meta(get_the_ID(), 'workout_json', true);
                if ($workout_json) {
                    $workout_data = json_decode($workout_json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $output .= '<div class="workout">';
                        $output .= '<h3>' . esc_html($workout_data['title']) . '</h3>';
                        $output .= '<p><strong>Description:</strong> ' . esc_html($workout_data['description']) . '</p>';
                        foreach ($workout_data['sections'] as $section) {
                            $output .= '<h4>' . esc_html($section['name']) . '</h4>';
                            foreach ($section['exercises'] as $exercise) {
                                $output .= '<p><strong>' . esc_html($exercise['name']) . ':</strong> ' . esc_html($exercise['description']) . ' (Duration: ' . esc_html($exercise['duration']) . ' minutes)</p>';
                                if (isset($exercise['rest'])) {
                                    $output .= '<p><strong>Rest:</strong> ' . esc_html($exercise['rest']) . ' seconds</p>';
                                }
                            }
                        }
                        $output .= '</div>';
                    } else {
                        $output .= '<p>Invalid workout data format.</p>';
                    }
                } else {
                    $output .= '<p>No workout data found.</p>';
                }
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return 'No workouts found.';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_workouts', 'display_user_workouts');

// Shortcode to display user's progress
function display_user_progress() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $args = array(
            'post_type' => 'progress',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_subscriber',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            )
        );
        $progress = new WP_Query($args);

        if ($progress->have_posts()) {
            $output = '<div class="user-progress">';
            while ($progress->have_posts()) {
                $progress->the_post();
                $progress_json = get_post_meta(get_the_ID(), 'progress_json', true);
                if ($progress_json) {
                    $progress_data = json_decode($progress_json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $output .= '<div class="progress">';
                        $output .= '<h3>Progress for ' . esc_html($progress_data['client_name']) . '</h3>';
                        $output .= '<p><strong>Workout Name:</strong> ' . esc_html($progress_data['workout_name']) . '</p>';
                        $output .= '<h4>Progress Tracking</h4>';
                        
                        foreach ($progress_data['progress_tracking'] as $exercise => $data) {
                            $output .= '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $exercise))) . ':</strong></p>';
                            $output .= '<ul>';
                            foreach ($data as $key => $value) {
                                $output .= '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</li>';
                            }
                            $output .= '</ul>';
                        }

                        $output .= '</div>';
                    } else {
                        $output .= '<p>Invalid progress data format.</p>';
                    }
                } else {
                    $output .= '<p>No progress data found.</p>';
                }
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return 'No progress data found.';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_progress', 'display_user_progress');

// Shortcode to display user's overview
function display_user_overview() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $args = array(
            'post_type' => 'overview',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_subscriber',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            )
        );
        $overview = new WP_Query($args);

        if ($overview->have_posts()) {
            $output = '<div class="user-overview">';
            while ($overview->have_posts()) {
                $overview->the_post();
                $output .= '<div class="overview">';
                $output .= '<h3>' . get_the_title() . '</h3>';
                $output .= '<div>' . get_the_content() . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return 'No overview data found.';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_overview', 'display_user_overview');

// Shortcode to display user's fitness plan
function display_user_fitness_plan() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $args = array(
            'post_type' => 'fitness_plan',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_subscriber',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            )
        );
        $fitness_plan = new WP_Query($args);

        if ($fitness_plan->have_posts()) {
            $output = '<div class="user-fitness-plan">';
            while ($fitness_plan->have_posts()) {
                $fitness_plan->the_post();
                $output .= '<div class="fitness-plan">';
                $output .= '<h3>' . get_the_title() . '</h3>';
                $output .= '<div>' . get_the_content() . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return 'No fitness plan data found.';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_fitness_plan', 'display_user_fitness_plan');

// Shortcode to display user's nutrition
function display_user_nutrition() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $args = array(
            'post_type' => 'nutrition',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'assigned_subscriber',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            )
        );
        $nutrition = new WP_Query($args);

        if ($nutrition->have_posts()) {
            $output = '<div class="user-nutrition">';
            while ($nutrition->have_posts()) {
                $nutrition->the_post();
                $output .= '<div class="nutrition">';
                $output .= '<h3>' . get_the_title() . '</h3>';
                $output .= '<div>' . get_the_content() . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return 'No nutrition data found.';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_nutrition', 'display_user_nutrition');

// Shortcode to display user's upcoming workouts
function display_user_upcoming_workouts() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        
        $args = array(
            'post_type' => 'upcoming_workouts',
            'posts_per_page' => -1,
            'meta_key' => 'assigned_subscriber',
            'meta_value' => $current_user->ID
        );

        $upcoming_workouts = new WP_Query($args);

        if ($upcoming_workouts->have_posts()) {
            $output = '<div class="user-upcoming-workouts">';
            while ($upcoming_workouts->have_posts()) {
                $upcoming_workouts->the_post();
                $output .= '<div class="upcoming-workout">';
                $output .= '<h3>' . get_the_title() . '</h3>';
                $output .= '<div>' . get_the_content() . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
            wp_reset_postdata();
            return $output;
        } else {
            return '<p>Debug: No upcoming workouts data found. Query: ' . print_r($args, true) . '</p>';
        }
    } else {
        return 'Not logged in.';
    }
}
add_shortcode('user_upcoming_workouts', 'display_user_upcoming_workouts');

// Redirect admin to custom user profile
function custom_user_profile_view_link($actions, $user) {
    if (current_user_can('edit_users')) {
        $url = add_query_arg('user_id', $user->ID, site_url('/athlete-dashboard/'));
        $actions['view'] = "<a href='{$url}'>View Posts</a>";
    }
    return $actions;
}
add_filter('user_row_actions', 'custom_user_profile_view_link', 10, 2);

// Redirect logged-in users away from the login and registration pages
function redirect_logged_in_users() {
    if (is_user_logged_in() && (is_page_template('custom-login.php') || is_page_template('custom-register.php'))) {
        wp_redirect(home_url('/athlete-dashboard'));
        exit;
    }
}
add_action('template_redirect', 'redirect_logged_in_users');

// Redirect users after login
function custom_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('subscriber', $user->roles)) {
            return home_url('/athlete-dashboard');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

// Custom authentication for login
function custom_authenticate($user, $username, $password) {
    if (empty($username) || empty($password)) {
        return $user;
    }

    if (is_email($username)) {
        $user = get_user_by('email', $username);
    } else {
        $user = get_user_by('login', $username);
    }

    if (!$user) {
        return null;
    }

    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        return null;
    }

    return $user;
}
add_filter('authenticate', 'custom_authenticate', 30, 3);

// Custom user registration
function custom_registration_function() {
    if (isset($_POST['submit']) && isset($_POST['register_nonce']) && wp_verify_nonce($_POST['register_nonce'], 'custom_register_nonce')) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        $errors = new WP_Error();

        if (empty($username)) {
            $errors->add('empty_username', __('Please enter a username.'));
        }
        if (empty($email)) {
            $errors->add('empty_email', __('Please enter an email address.'));
        }
        if (empty($password)) {
            $errors->add('empty_password', __('Please enter a password.'));
        }

        if (username_exists($username)) {
            $errors->add('username_exists', __('This username already exists.'));
        }
        if (email_exists($email)) {
            $errors->add('email_exists', __('This email address is already registered.'));
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (!is_wp_error($user_id)) {
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name
            ));
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            wp_redirect(home_url('/athlete-dashboard'));
            exit;
        }
    }
}
add_action('init', 'custom_registration_function');

// Add custom fields to user profile
function add_custom_user_profile_fields($user) {
    ?>
    <h3><?php _e("Exercise Test Results", "athlete-dashboard"); ?></h3>

    <table class="form-table">
        <?php
        $exercise_tests = get_exercise_tests();
        $exercise_tests['body_weight'] = array('label' => 'Body Weight Progress', 'unit' => 'kg/lbs');

        foreach ($exercise_tests as $field_name => $test) :
            if ($field_name === 'body_weight') {
                $field_value = get_most_recent_weight($user->ID);
            } else {
                $field_value = get_user_meta($user->ID, $field_name, true);
            }
        ?>
            <tr>
                <th><label for="<?php echo $field_name; ?>"><?php _e($test['label'] . ' (' . $test['unit'] . ')', "athlete-dashboard"); ?></label></th>
                <td>
                    <?php if ($field_name === 'body_weight'): ?>
                        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" value="<?php echo esc_attr($field_value); ?>" class="regular-text" readonly /><br />
                        <span class="description"><?php _e("This field is automatically updated with your most recent weight entry.", "athlete-dashboard"); ?></span>
                    <?php else: ?>
                        <input type="text" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" value="<?php echo esc_attr($field_value); ?>" class="regular-text" /><br />
                        <span class="description"><?php _e("Enter your most recent result for this test.", "athlete-dashboard"); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
}
add_action('show_user_profile', 'add_custom_user_profile_fields');
add_action('edit_user_profile', 'add_custom_user_profile_fields');

function get_most_recent_weight($user_id) {
    $weight_progress = get_user_meta($user_id, 'weight_progress', true);
    if (is_array($weight_progress) && !empty($weight_progress)) {
        $latest_entry = reset($weight_progress); // Get the first (most recent) entry
        return $latest_entry['weight'] . ' ' . $latest_entry['unit'];
    }
    return 'No data';
}

// Save custom fields
function save_custom_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $exercise_tests = array_keys(get_exercise_tests());

    foreach ($exercise_tests as $field_name) {
        if (isset($_POST[$field_name])) {
            update_user_meta($user_id, $field_name, sanitize_text_field($_POST[$field_name]));
        }
    }
}
add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');

// Enqueue custom scripts and styles
function enqueue_custom_scripts_and_styles() {
    // Enqueue custom JavaScript
    wp_enqueue_script('custom-scripts', get_stylesheet_directory_uri() . '/js/custom-scripts.js', array('jquery'), '1.0', true);
    wp_localize_script('custom-scripts', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    
    // Enqueue custom CSS
    wp_enqueue_style('custom-styles', get_stylesheet_directory_uri() . '/custom-styles.css', array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts_and_styles');

// AJAX handler for updating user progress
function update_user_progress() {
    if (!isset($_POST['progress_nonce']) || !wp_verify_nonce($_POST['progress_nonce'], 'update_progress_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $user_id = get_current_user_id();
    $progress_data = array(
        'weight' => floatval($_POST['weight']),
        'date' => current_time('mysql')
    );

    $existing_progress = get_user_meta($user_id, 'weight_progress', true);
    if (!is_array($existing_progress)) {
        $existing_progress = array();
    }
    $existing_progress[] = $progress_data;

    update_user_meta($user_id, 'weight_progress', $existing_progress);
    update_user_meta($user_id, 'weight', $progress_data['weight']);

    wp_send_json_success('Progress updated successfully');
}
add_action('wp_ajax_update_user_progress', 'update_user_progress');

// Function to get user's weight progress
function get_user_weight_progress($user_id) {
    $progress = get_user_meta($user_id, 'weight_progress', true);
    if (!is_array($progress)) {
        return array();
    }
    return $progress;
}

// Shortcode to display user's weight progress chart
function display_weight_progress_chart() {
    if (!is_user_logged_in()) {
        return 'Please log in to view your progress.';
    }

    $user_id = get_current_user_id();
    $progress_data = get_user_weight_progress($user_id);

    if (empty($progress_data)) {
        return 'No weight progress data available.';
    }

    // Prepare data for Chart.js
    $labels = array();
    $weights = array();
    foreach ($progress_data as $entry) {
        $labels[] = date('M d', strtotime($entry['date']));
        $weights[] = $entry['weight'];
    }

    $chart_data = array(
        'labels' => $labels,
        'datasets' => array(
            array(
                'label' => 'Weight Progress',
                'data' => $weights,
                'fill' => false,
                'borderColor' => 'rgb(75, 192, 192)',
                'tension' => 0.1
            )
        )
    );

    $chart_options = array(
        'responsive' => true,
        'scales' => array(
            'y' => array(
                'beginAtZero' => false
            )
        )
    );

    // Enqueue Chart.js
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.1', true);

    // Generate unique ID for the canvas
    $canvas_id = 'weight-progress-chart-' . uniqid();

    // Generate the chart
    $output = '<canvas id="' . $canvas_id . '"></canvas>';
    $output .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("' . $canvas_id . '").getContext("2d");
            new Chart(ctx, {
                type: "line",
                data: ' . json_encode($chart_data) . ',
                options: ' . json_encode($chart_options) . '
            });
        });
    </script>';

    return $output;
}
add_shortcode('weight_progress_chart', 'display_weight_progress_chart');

// Function to get exercise tests data
function get_exercise_tests() {
    return array(
        '5k_run' => array('label' => '5k Run', 'unit' => 'minutes', 'decimal_places' => 2),
        '20k_cycling' => array('label' => '20k Cycling', 'unit' => 'minutes', 'decimal_places' => 2),
        '10k_rucking' => array('label' => '10k Rucking', 'unit' => 'minutes', 'decimal_places' => 2),
        '400m_swim' => array('label' => '400m Swim', 'unit' => 'seconds', 'decimal_places' => 1),
        'slrdl' => array('label' => 'Single-Leg Romanian Deadlift', 'unit' => 'reps', 'decimal_places' => 0),
        'pistol_squat' => array('label' => 'Single-Leg Squat', 'unit' => 'reps', 'decimal_places' => 0),
        'pushups' => array('label' => 'Push-Ups', 'unit' => 'reps', 'decimal_places' => 0),
        'pullups' => array('label' => 'Pull-Ups', 'unit' => 'reps', 'decimal_places' => 0),
        'vertical_jump' => array('label' => 'Vertical Jump', 'unit' => 'inches', 'decimal_places' => 1),
        'sit_reach' => array('label' => 'Sit-and-Reach', 'unit' => 'inches', 'decimal_places' => 1),
        'balance_test' => array('label' => 'Single-Leg Balance', 'unit' => 'seconds', 'decimal_places' => 1),
        'farmers_walk' => array('label' => 'Loaded Carry', 'unit' => 'meters', 'decimal_places' => 1),
        'burpee_test' => array('label' => 'Burpee Test', 'unit' => 'reps', 'decimal_places' => 0),
        'deadhang' => array('label' => 'Deadhang', 'unit' => 'seconds', 'decimal_places' => 1)
    );
}

function handle_exercise_progress_submission() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $user_id = get_current_user_id();
    $exercise_key = sanitize_text_field($_POST['exercise_key']);
    $value = floatval($_POST['value']);
    $date = sanitize_text_field($_POST['date']);
    
    $progress = get_user_meta($user_id, "{$exercise_key}_progress", true);
    if (!is_array($progress)) $progress = array();
    
    // Add new data point
    $progress[] = array(
        'date' => $date,
        'value' => $value
    );
    
    // Sort progress by date
    usort($progress, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    update_user_meta($user_id, "{$exercise_key}_progress", $progress);
    
    // Update the most recent value in user profile
    update_user_meta($user_id, $exercise_key, $value);
    
    wp_send_json_success('Progress updated successfully');
}
add_action('wp_ajax_handle_exercise_progress_submission', 'handle_exercise_progress_submission');

function get_exercise_progress() {
    check_ajax_referer('athlete_dashboard_nonce', 'nonce');
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $user_id = get_current_user_id();
    $exercise_key = sanitize_text_field($_POST['exercise_key']);
    
    $progress = get_user_meta($user_id, "{$exercise_key}_progress", true);
    
    if (!is_array($progress)) {
        $progress = array();
    }
    wp_send_json_success($progress);
}
add_action('wp_ajax_get_exercise_progress', 'get_exercise_progress');

?>