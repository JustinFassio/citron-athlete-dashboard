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
    error_log('Attempting to store data for user ' . $user_id . ': ' . print_r($entry, true));
    
    $result = sanitize_and_validate_body_composition_entry($entry);
    if (!empty($result['errors'])) {
        error_log('Validation errors: ' . print_r($result['errors'], true));
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

    $updated = update_user_meta($user_id, 'body_composition_progress', $progress);
    error_log('User meta update result: ' . ($updated ? 'success' : 'failure'));

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

    return $progress;
}

/**
 * AJAX handler for retrieving body composition progress data
 */
function handle_get_body_composition_progress_ajax() {
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

/**
 * AJAX handler for storing body composition progress data
 */
function handle_store_body_composition_progress_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'athlete_dashboard_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    error_log('Received body composition data: ' . print_r($_POST, true));

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

// Add AJAX action hooks
add_action('wp_ajax_get_body_composition_progress', 'handle_get_body_composition_progress_ajax');
add_action('wp_ajax_store_body_composition_progress', 'handle_store_body_composition_progress_ajax');
add_action('wp_ajax_get_most_recent_weight', 'handle_get_most_recent_weight_ajax');