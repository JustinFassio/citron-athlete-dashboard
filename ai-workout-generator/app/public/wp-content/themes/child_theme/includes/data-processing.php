Certainly! I'll provide the updated version of the includes/data-processing.php file with the logging functionality implemented. Here's the entire file with the new changes:

```php
<?php
/**
 * Enhanced Athlete Body Composition Tracking System - Data Processing
 *
 * @package AthleteDashboard
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Log body composition related data and messages
 *
 * @param string $message The message to log
 * @param mixed $data Optional data to log
 */
function log_body_composition_data($message, $data = null) {
    $log_file = WP_CONTENT_DIR . '/body_composition_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    if ($data !== null) {
        $log_message .= ': ' . print_r($data, true);
    }
    $log_message .= "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Define the schema for body composition progress entries
 *
 * @return array The schema definition
 */
function get_body_composition_progress_schema() {
    return array(
        'date' => array('type' => 'date', 'required' => true),
        'weight' => array('type' => 'float', 'required' => true, 'minimum' => 20, 'maximum' => 500),
        'body_fat_percentage' => array('type' => 'float', 'required' => false, 'minimum' => 1, 'maximum' => 60),
        'muscle_mass' => array('type' => 'float', 'required' => false, 'minimum' => 10, 'maximum' => 300),
        'bmi' => array('type' => 'float', 'required' => false, 'minimum' => 10, 'maximum' => 50),
        'notes' => array('type' => 'string', 'required' => false, 'maximum_length' => 500)
    );
}

/**
 * Sanitize and validate a body composition progress entry
 *
 * @param array $entry The progress entry to validate
 * @return array Sanitized entry and any errors
 */
function sanitize_and_validate_body_composition_entry($entry) {
    $schema = get_body_composition_progress_schema();
    $sanitized_entry = array();
    $errors = array();

    foreach ($schema as $field => $rules) {
        if (!isset($entry[$field]) && $rules['required']) {
            $errors[] = sprintf(__("Field '%s' is required.", 'athlete-dashboard'), $field);
            continue;
        }

        $value = isset($entry[$field]) ? $entry[$field] : null;

        switch ($rules['type']) {
            case 'date':
                $sanitized_entry[$field] = sanitize_text_field($value);
                if (!wp_date('Y-m-d H:i:s', strtotime($sanitized_entry[$field]))) {
                    $errors[] = sprintf(__("Invalid date format for '%s'.", 'athlete-dashboard'), $field);
                }
                break;
            case 'float':
                $sanitized_entry[$field] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($sanitized_entry[$field] === false) {
                    $errors[] = sprintf(__("Invalid float value for '%s'.", 'athlete-dashboard'), $field);
                } elseif (isset($rules['minimum']) && $sanitized_entry[$field] < $rules['minimum']) {
                    $errors[] = sprintf(__("'%s' must be at least %s.", 'athlete-dashboard'), $field, $rules['minimum']);
                } elseif (isset($rules['maximum']) && $sanitized_entry[$field] > $rules['maximum']) {
                    $errors[] = sprintf(__("'%s' must not exceed %s.", 'athlete-dashboard'), $field, $rules['maximum']);
                }
                break;
            case 'string':
                $sanitized_entry[$field] = sanitize_textarea_field($value);
                if (isset($rules['maximum_length']) && strlen($sanitized_entry[$field]) > $rules['maximum_length']) {
                    $errors[] = sprintf(__("'%s' must not exceed %d characters.", 'athlete-dashboard'), $field, $rules['maximum_length']);
                }
                break;
        }
    }

    return array(
        'sanitized_entry' => $sanitized_entry,
        'errors' => $errors
    );
}

/**
 * Store a body composition progress entry for a user
 *
 * @param integer $user_id The ID of the user
 * @param array $entry The progress entry to store
 * @return array Result of the operation
 */
function store_user_body_composition_progress($user_id, $entry) {
    log_body_composition_data("Attempting to store body composition data for user", $user_id);
    log_body_composition_data("Input data", $entry);

    $result = sanitize_and_validate_body_composition_entry($entry);
    if (!empty($result['errors'])) {
        log_body_composition_data("Validation errors", $result['errors']);
        return array('success' => false, 'errors' => $result['errors']);
    }

    $progress = get_user_meta($user_id, 'body_composition_progress', true);
    if (!is_array($progress)) {
        $progress = array();
    }

    $entry_date = $result['sanitized_entry']['date'];
    $existing_entry_index = array_search($entry_date, array_column($progress, 'date'));

    if ($existing_entry_index !== false) {
        $progress[$existing_entry_index] = $result['sanitized_entry'];
    } else {
        $progress[] = $result['sanitized_entry'];
    }

    usort($progress, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    update_user_meta($user_id, 'body_composition_progress', $progress);

    log_body_composition_data("Body composition data stored successfully", $progress);
    return array('success' => true, 'message' => __('Progress updated successfully', 'athlete-dashboard'));
}

/**
 * Retrieve body composition progress entries for a user
 *
 * @param integer $user_id The ID of the user
 * @param string $start_date Optional start date for filtering
 * @param string $end_date Optional end date for filtering
 * @param string $metric Optional specific metric to retrieve
 * @param string $sort_order Optional sort order ('date_ascending' or 'date_descending')
 * @return array The progress entries
 */
function get_user_body_composition_progress($user_id, $start_date = null, $end_date = null, $metric = 'all', $sort_order = 'date_ascending') {
    log_body_composition_data("Retrieving body composition data for user", $user_id);
    log_body_composition_data("Query parameters", array(
        'start_date' => $start_date,
        'end_date' => $end_date,
        'metric' => $metric,
        'sort_order' => $sort_order
    ));

    $progress = get_user_meta($user_id, 'body_composition_progress', true);
    if (!is_array($progress)) {
        return array();
    }

    if ($start_date || $end_date) {
        $progress = array_filter($progress, function($entry) use ($start_date, $end_date) {
            $entry_date = strtotime($entry['date']);
            return (!$start_date || $entry_date >= strtotime($start_date)) &&
                   (!$end_date || $entry_date <= strtotime($end_date));
        });
    }

    if ($metric !== 'all') {
        $progress = array_map(function($entry) use ($metric) {
            return array(
                'date' => $entry['date'],
                $metric => isset($entry[$metric]) ? $entry[$metric] : null
            );
        }, $progress);
    }

    usort($progress, function($a, $b) use ($sort_order) {
        $date_comparison = strtotime($a['date']) - strtotime($b['date']);
        return $sort_order === 'date_ascending' ? $date_comparison : -$date_comparison;
    });

    log_body_composition_data("Retrieved body composition data", $progress);
    return $progress;
}

/**
 * Delete a specific body composition progress entry for a user
 *
 * @param integer $user_id The ID of the user
 * @param string $entry_date The date of the entry to delete
 * @return array Result of the operation
 */
function delete_user_body_composition_progress_entry($user_id, $entry_date) {
    $progress = get_user_meta($user_id, 'body_composition_progress', true);
    
    if (!is_array($progress)) {
        return array('success' => false, 'message' => __('No progress data found', 'athlete-dashboard'));
    }

    $updated_progress = array_filter($progress, function($entry) use ($entry_date) {
        return $entry['date'] !== $entry_date;
    });

    if (count($updated_progress) === count($progress)) {
        return array('success' => false, 'message' => __('Progress entry not found', 'athlete-dashboard'));
    }

    $updated = update_user_meta($user_id, 'body_composition_progress', $updated_progress);

    if ($updated) {
        return array('success' => true, 'message' => __('Progress entry deleted successfully', 'athlete-dashboard'));
    } else {
        return array('success' => false, 'message' => __('Failed to delete progress entry', 'athlete-dashboard'));
    }
}

/**
 * AJAX handler for retrieving body composition progress data
 */
function handle_get_body_composition_progress_ajax() {
    log_body_composition_data("Received AJAX request for body composition data", $_POST);

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error(__('Invalid nonce', 'athlete-dashboard'));
    }

    $user_id = get_current_user_id();
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $metric = isset($_POST['metric']) ? sanitize_text_field($_POST['metric']) : 'all';
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'date_ascending';

    $progress = get_user_body_composition_progress($user_id, $start_date, $end_date, $metric, $sort_order);

    if (empty($progress)) {
        wp_send_json_error(__('No progress data found', 'athlete-dashboard'));
    }

    $chart_data = array(
        'labels' => array(),
        'datasets' => array(
            array('label' => __('Weight (kg)', 'athlete-dashboard'), 'data' => array()),
            array('label' => __('Body Fat (%)', 'athlete-dashboard'), 'data' => array()),
            array('label' => __('Muscle Mass (kg)', 'athlete-dashboard'), 'data' => array()),
            array('label' => __('BMI', 'athlete-dashboard'), 'data' => array())
        )
    );

    foreach ($progress as $entry) {
        $chart_data['labels'][] = $entry['date'];
        $chart_data['datasets'][0]['data'][] = isset($entry['weight']) ? $entry['weight'] : null;
        $chart_data['datasets'][1]['data'][] = isset($entry['body_fat_percentage']) ? $entry['body_fat_percentage'] : null;
        $chart_data['datasets'][2]['data'][] = isset($entry['muscle_mass']) ? $entry['muscle_mass'] : null;
        $chart_data['datasets'][3]['data'][] = isset($entry['bmi']) ? $entry['bmi'] : null;
    }

    wp_send_json_success($chart_data);
}
add_action('wp_ajax_get_body_composition_progress', 'handle_get_body_composition_progress_ajax');

/**
 * AJAX handler for storing body composition progress data
 */
function handle_store_body_composition_progress_ajax() {
    log_body_composition_data("Received AJAX request to store body composition data", $_POST);

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error(__('Invalid nonce', 'athlete-dashboard'));
    }

    $user_id = get_current_user_id();
    $entry = array(
        'date' => sanitize_text_field($_POST['date']),
        'weight' => floatval($_POST['weight']),
        'body_fat_percentage' => isset($_POST['body_fat_percentage']) ? floatval($_POST['body_fat_percentage']) : null,
        'muscle_mass' => isset($_POST['muscle_mass']) ? floatval($_POST['muscle_mass']) : null,
        'bmi' => isset($_POST['bmi']) ? floatval($_POST['bmi']) : null,
        'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null
    );

    $result = store_user_body_composition_progress($user_id, $entry);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_store_body_composition_progress', 'handle_store_body_composition_progress_ajax');

/**
 * Get the most recent weight for a user
 *
 * @param int $user_id The ID of the user
 * @return string|null The most recent weight or null if not found
 */
function get_most_recent_weight($user_id) {
    $progress = get_user_meta($user_id, 'body_composition_progress', true);
    if (is_array($progress) && !empty($progress)) {
        $latest_entry = reset($progress); // Get the first (most recent) entry
        return isset($latest_entry['weight']) ? $latest_entry['weight'] : null;
    }
    return null;
}

/**
 * AJAX handler for getting the most recent weight
 */
function handle_get_most_recent_weight_ajax() {
    log_body_composition_data("Received AJAX request for most recent weight", $_POST);

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $user_id = get_current_user_id();
    $most_recent_weight = get_most_recent_weight($user_id);
    
    if ($most_recent_weight !== null) {
        wp_send_json_success($most_recent_weight);
    } else {
        wp_send_json_error('No weight data found');
    }
}
add_action('wp_ajax_get_most_recent_weight', 'handle_get_most_recent_weight_ajax');

/**
 * Get exercise tests data
 *
 * @return array Array of exercise tests with their details
 */
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

/**
 * AJAX handler for exercise progress submission
 */
function handle_exercise_progress_submission() {
    log_body_composition_data("Received AJAX request for exercise progress submission", $_POST);

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
    
    log_body_composition_data("Exercise progress updated successfully", array(
        'user_id' => $user_id,
        'exercise_key' => $exercise_key,
        'value' => $value,
        'date' => $date
    ));
    
    wp_send_json_success('Progress updated successfully');
}
add_action('wp_ajax_handle_exercise_progress_submission', 'handle_exercise_progress_submission');

/**
 * AJAX handler for getting exercise progress
 */
function handle_get_exercise_progress() {
    log_body_composition_data("Received AJAX request for getting exercise progress", $_POST);

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $user_id = get_current_user_id();
    $exercise_key = sanitize_text_field($_POST['exercise_key']);
    
    $progress = get_user_meta($user_id, "{$exercise_key}_progress", true);
    
    if (!is_array($progress)) {
        $progress = array();
    }

    log_body_composition_data("Retrieved exercise progress", array(
        'user_id' => $user_id,
        'exercise_key' => $exercise_key,
        'progress_count' => count($progress)
    ));

    wp_send_json_success($progress);
}
add_action('wp_ajax_get_exercise_progress', 'handle_get_exercise_progress');

/**
 * Migrate old weight progress data to the new body composition format
 *
 * @return string Migration result message
 */
function migrate_body_composition_data() {
    $users = get_users(array('fields' => 'ID'));
    $migration_log = array();

    foreach ($users as $user_id) {
        $old_progress = get_user_meta($user_id, 'weight_progress', true);
        $new_progress = array();

        if (is_array($old_progress)) {
            foreach ($old_progress as $entry) {
                $new_entry = array(
                    'date' => $entry['date'],
                    'weight' => floatval($entry['weight']),
                    'body_fat_percentage' => null,
                    'muscle_mass' => null,
                    'bmi' => null
                );
                $new_progress[] = $new_entry;
            }

            update_user_meta($user_id, 'body_composition_progress', $new_progress);
            delete_user_meta($user_id, 'weight_progress');
            $migration_log[] = sprintf(__("User ID %d: Migrated %d entries.", 'athlete-dashboard'), $user_id, count($new_progress));
        } else {
            $migration_log[] = sprintf(__("User ID %d: No weight progress data found.", 'athlete-dashboard'), $user_id);
        }
    }

    $log_file_path = WP_CONTENT_DIR . '/body_composition_migration_log.txt';
    file_put_contents($log_file_path, implode("\n", $migration_log));

    return sprintf(__("Migration completed. Log file created at %s", 'athlete-dashboard'), $log_file_path);
}

/**
 * Rollback the body composition data migration
 *
 * @return string Rollback result message
 */
function rollback_body_composition_migration() {
    $users = get_users(array('fields' => 'ID'));
    $rollback_log = array();

    foreach ($users as $user_id) {
        $new_progress = get_user_meta($user_id, 'body_composition_progress', true);
        $old_progress = array();

        if (is_array($new_progress)) {
            foreach ($new_progress as $entry) {
                $old_entry = array(
                    'date' => $entry['date'],
                    'weight' => $entry['weight'],
                    'unit' => 'kg'
                );
                $old_progress[] = $old_entry;
            }

            update_user_meta($user_id, 'weight_progress', $old_progress);
            delete_user_meta($user_id, 'body_composition_progress');
            $rollback_log[] = sprintf(__("User ID %d: Rolled back %d entries.", 'athlete-dashboard'), $user_id, count($old_progress));
        } else {
            $rollback_log[] = sprintf(__("User ID %d: No body composition progress data found.", 'athlete-dashboard'), $user_id);
        }
    }

    $log_file_path = WP_CONTENT_DIR . '/body_composition_rollback_log.txt';
    file_put_contents($log_file_path, implode("\n", $rollback_log));

    return sprintf(__("Rollback completed. Log file created at %s", 'athlete-dashboard'), $log_file_path);
}

// Uncomment the following line to run the migration
// echo migrate_body_composition_data();

// Uncomment the following line to run the rollback if needed
// echo rollback_body_composition_migration();