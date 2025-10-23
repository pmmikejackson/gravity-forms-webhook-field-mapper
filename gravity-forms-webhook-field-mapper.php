<?php
/**
 * Plugin Name: Gravity Forms Webhook Field Mapper
 * Plugin URI: https://github.com/mjhome/gravity-forms-webhook-field-mapper
 * Description: Maps Gravity Forms field IDs to field names in webhook data
 * Version: 1.3.0
 * Author: Mike Jackson with Claude
 * License: GPL v2 or later
 * Text Domain: gf-webhook-field-mapper
 *
 * @package GravityFormsWebhookFieldMapper
 */

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnused */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GF_Webhook_Field_Mapper {

    /**
     * Field filtering configuration
     *
     * Configure which fields to include in webhook payloads:
     * - 'mode' => 'whitelist' (only send specified fields) or 'blacklist' (send all except specified fields)
     * - 'fields' => array of field admin labels or field labels to include/exclude
     * - 'include_empty' => true/false (whether to include fields with empty values)
     * - 'required_fields' => array of field admin labels that should always be included even if empty
     */
    private $field_config = array(
        'mode' => 'all',  // Options: 'all', 'whitelist', 'blacklist', 'admin_label_only'
        'fields' => array(), // Array of field labels/admin labels to include or exclude
        'include_empty' => false, // Whether to include empty fields
        'required_fields' => array(), // Fields to always include even if empty
    );

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('init', array($this, 'init'));

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Add metabox to entry detail page
        add_action('gform_entry_detail_sidebar_middle', array($this, 'add_resend_metabox'), 10, 2);

        // Load field configuration from WordPress options
        $this->load_field_config();
    }

    /**
     * Load field configuration from WordPress options
     */
    private function load_field_config() {
        $saved_config = get_option('gf_webhook_field_mapper_config', array());

        if (!empty($saved_config)) {
            $this->field_config = array_merge($this->field_config, $saved_config);
        }
    }

    /**
     * Save field configuration to WordPress options
     */
    private function save_field_config() {
        update_option('gf_webhook_field_mapper_config', $this->field_config);
    }

    /**
     * Get current field configuration
     */
    public function get_field_config() {
        return $this->field_config;
    }

    /**
     * Update field configuration
     *
     * @param array $config New configuration settings
     */
    public function update_field_config($config) {
        $this->field_config = array_merge($this->field_config, $config);
        $this->save_field_config();
    }

    /**
     * Hook into Gravity Forms
     */
    public function init() {
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            return;
        }

        // Hook into the webhook request data
        add_filter('gform_webhooks_request_data', array($this, 'modify_webhook_data'), 10, 4);

        // Alternative hook for older versions
        add_filter('gform_zapier_request_body', array($this, 'modify_webhook_data'), 10, 4);

        // Hook to log when webhooks are actually sent (for debugging)
        add_action('gform_post_send_entry_to_webhook', array($this, 'log_webhook_sent'), 10, 4);
    }

    /**
     * Log debug information
     *
     * @param string $message The message to log
     * @param mixed $data Additional data to log
     */
    private function log_debug($message, $data = null) {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = '[GF Webhook Field Mapper] ' . $message;

        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }

    /**
     * Log when webhook is sent (debugging hook)
     *
     * @param array $response The response from the webhook
     * @param array $feed The webhook feed
     * @param array $entry The entry
     * @param array $form The form
     */
    public function log_webhook_sent($response, $feed, $entry, $form) {
        $this->log_debug('Webhook sent', array(
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'entry_id' => $entry['id'],
            'webhook_name' => isset($feed['meta']['feedName']) ? $feed['meta']['feedName'] : 'Unknown',
            'webhook_url' => isset($feed['meta']['requestURL']) ? $feed['meta']['requestURL'] : 'Unknown',
            'response_code' => is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : 'Unknown'
        ));
    }

    /**
     * Modify webhook data to use field names instead of IDs
     *
     * @param array $request_data The data being sent to the webhook (unused but required by filter)
     * @param array $feed The webhook feed configuration (unused but required by filter)
     * @param array $entry The form entry
     * @param array $form The form object
     * @return array Modified request data
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function modify_webhook_data($request_data, $feed, $entry, $form) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $this->log_debug('Webhook data modification started', array(
            'form_id' => $form['id'],
            'entry_id' => $entry['id'],
            'filter_mode' => $this->field_config['mode'],
            'include_empty' => $this->field_config['include_empty']
        ));

        // Track statistics for logging
        $stats = array(
            'total_fields' => 0,
            'included_fields' => 0,
            'excluded_by_filter' => 0,
            'excluded_by_empty' => 0
        );

        // Create new data array with field names (completely replace the original)
        $mapped_data = array();

        // Add form metadata
        $mapped_data['form_id'] = $form['id'];
        $mapped_data['form_title'] = $form['title'];
        $mapped_data['entry_id'] = $entry['id'];
        $mapped_data['date_created'] = $entry['date_created'];

        // Map each field (respecting filter configuration)
        foreach ($form['fields'] as $field) {
            $stats['total_fields']++;

            $field_id = $field->id;
            $field_label = $this->get_field_label($field);

            // Ensure unique field labels - if this label already exists, append the field ID
            if (isset($mapped_data[$field_label])) {
                $field_label = $field_label . '_' . $field_id;
            }

            // Check if this field should be included based on configuration
            if (!$this->should_include_field($field, $field_label)) {
                $stats['excluded_by_filter']++;
                $this->log_debug('Field excluded by filter', array(
                    'field_id' => $field_id,
                    'field_label' => $field_label,
                    'has_admin_label' => !empty($field->adminLabel)
                ));
                continue; // Skip this field
            }

            // Variable to hold the field value before adding to mapped_data
            $field_value = null;

            // Handle different field types with special sub-field structures
            if ($field->type == 'name') {
                // Handle name fields with sub-fields
                $name_parts = array();

                // Include all possible name sub-fields
                $name_parts['prefix'] = isset($entry[$field_id . '.2']) ? $entry[$field_id . '.2'] : '';
                $name_parts['first'] = isset($entry[$field_id . '.3']) ? $entry[$field_id . '.3'] : '';
                $name_parts['middle'] = isset($entry[$field_id . '.4']) ? $entry[$field_id . '.4'] : '';
                $name_parts['last'] = isset($entry[$field_id . '.6']) ? $entry[$field_id . '.6'] : '';
                $name_parts['suffix'] = isset($entry[$field_id . '.8']) ? $entry[$field_id . '.8'] : '';

                // Include full name if available
                if (isset($entry[$field_id]) && $entry[$field_id] !== '') {
                    $name_parts['full'] = $entry[$field_id];
                }

                $field_value = $name_parts;

            } elseif ($field->type == 'address') {
                // Handle address fields with sub-fields
                $address_parts = array();

                // Include all address sub-fields
                $address_parts['street'] = isset($entry[$field_id . '.1']) ? $entry[$field_id . '.1'] : '';
                $address_parts['street2'] = isset($entry[$field_id . '.2']) ? $entry[$field_id . '.2'] : '';
                $address_parts['city'] = isset($entry[$field_id . '.3']) ? $entry[$field_id . '.3'] : '';
                $address_parts['state'] = isset($entry[$field_id . '.4']) ? $entry[$field_id . '.4'] : '';
                $address_parts['zip'] = isset($entry[$field_id . '.5']) ? $entry[$field_id . '.5'] : '';
                $address_parts['country'] = isset($entry[$field_id . '.6']) ? $entry[$field_id . '.6'] : '';

                $field_value = $address_parts;

            } elseif ($field->type == 'date') {
                // Handle date fields with sub-fields
                if (is_array($field->inputs)) {
                    // Date field with separate inputs (month, day, year)
                    $date_parts = array();
                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        $input_label = !empty($input['label']) ? $this->sanitize_label($input['label']) : 'input_' . str_replace('.', '_', $input_id);
                        $date_parts[$input_label] = isset($entry[$input_id]) ? $entry[$input_id] : '';
                    }
                    $field_value = $date_parts;
                } else {
                    // Single date input
                    $field_value = isset($entry[$field_id]) ? $entry[$field_id] : '';
                }

            } elseif ($field->type == 'time') {
                // Handle time fields with sub-fields
                if (is_array($field->inputs)) {
                    // Time field with separate inputs (hour, minute, am/pm)
                    $time_parts = array();
                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        $input_label = !empty($input['label']) ? $this->sanitize_label($input['label']) : 'input_' . str_replace('.', '_', $input_id);
                        $time_parts[$input_label] = isset($entry[$input_id]) ? $entry[$input_id] : '';
                    }
                    $field_value = $time_parts;
                } else {
                    // Single time input
                    $field_value = isset($entry[$field_id]) ? $entry[$field_id] : '';
                }

            } elseif ($field->type == 'checkbox') {
                // Handle checkbox fields
                $checkbox_values = array();
                $inputs = $field->inputs;

                if (is_array($inputs)) {
                    foreach ($inputs as $input) {
                        $input_id = $input['id'];
                        if (!empty($entry[$input_id])) {
                            $checkbox_values[] = $entry[$input_id];
                        }
                    }
                }

                // Always return checkbox values as an array for consistency
                $field_value = $checkbox_values;

            } elseif ($field->type == 'list') {
                // Handle list fields
                $list_values = '';
                if (isset($entry[$field_id])) {
                    $list_values = maybe_unserialize($entry[$field_id]); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_maybe_unserialize
                }

                $field_value = $list_values ? $list_values : '';

            } else {
                // Handle standard fields
                $value = isset($entry[$field_id]) ? $entry[$field_id] : '';

                // Handle fields with multiple inputs
                if (!is_array($field->inputs) || empty($field->inputs)) {
                    // Simple field with no inputs - use the main field value
                    $field_value = $value;
                } else {
                    // For fields with inputs (like email with confirmation, date fields, time fields)
                    $input_values = array();
                    $has_multiple_inputs = count($field->inputs) > 1;

                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        $input_label = !empty($input['label']) ? $this->sanitize_label($input['label']) : 'input_' . str_replace('.', '_', $input_id);
                        $input_value = isset($entry[$input_id]) ? $entry[$input_id] : '';

                        if ($has_multiple_inputs) {
                            // Include all inputs as sub-fields
                            $input_values[$input_label] = $input_value;
                        } else {
                            // Single input field - use the value directly
                            $field_value = $input_value;
                        }
                    }

                    if ($has_multiple_inputs) {
                        // For multi-input fields, include all sub-fields
                        $field_value = $input_values;
                    }

                    // If we still don't have a value but the main field has data, use that
                    if ((!isset($field_value) || $field_value === '') && $value !== '') {
                        $field_value = $value;
                    }
                }
            }

            // Safety net: if field_value is still null, try to get it from entry
            if ($field_value === null) {
                // First, try the direct field ID
                if (isset($entry[$field_id]) && $entry[$field_id] !== '') {
                    $field_value = $entry[$field_id];
                }
                // If empty, check if this field has inputs and try to get the first input value
                elseif (is_array($field->inputs) && !empty($field->inputs)) {
                    // Try each input to find a value
                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        if (isset($entry[$input_id]) && $entry[$input_id] !== '') {
                            $field_value = $entry[$input_id];
                            break; // Use the first non-empty input value
                        }
                    }
                } else {
                    $field_value = '';
                }
            }

            // Check if we should include this field based on empty value configuration
            $is_empty = $this->is_empty_value($field_value);
            $is_required = $this->is_required_field($field_label, $field);

            // Include the field if:
            // 1. include_empty is true, OR
            // 2. The value is not empty, OR
            // 3. The field is required (should always be included)
            if ($this->field_config['include_empty'] || !$is_empty || $is_required) {
                $mapped_data[$field_label] = $field_value;
                $stats['included_fields']++;
            } else {
                $stats['excluded_by_empty']++;
                $this->log_debug('Field excluded (empty value)', array(
                    'field_id' => $field_id,
                    'field_label' => $field_label
                ));
            }
        }

        // Process any remaining entry fields that weren't in $form['fields']
        // This catches edge cases like hidden fields, special fields, etc.
        $processed_field_ids = array();
        foreach ($form['fields'] as $field) {
            $processed_field_ids[] = (string)$field->id;
        }

        // Standard entry metadata fields to skip
        $metadata_fields = array('id', 'form_id', 'post_id', 'date_created', 'date_updated', 'is_starred',
                                 'is_read', 'ip', 'source_url', 'user_agent', 'currency', 'payment_status',
                                 'payment_date', 'payment_amount', 'payment_method', 'transaction_id',
                                 'is_fulfilled', 'created_by', 'transaction_type', 'status', 'source_id');

        foreach ($entry as $key => $value) {
            // Skip metadata fields
            if (in_array($key, $metadata_fields)) {
                continue;
            }

            // Skip if this field was already processed
            $base_field_id = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : $key;
            if (in_array($base_field_id, $processed_field_ids)) {
                continue;
            }

            // Skip sub-fields (they would have been handled by their parent field)
            if (strpos($key, '.') !== false) {
                continue;
            }

            // Skip if not a numeric field ID
            if (!is_numeric($key)) {
                continue;
            }

            // This is an unprocessed field - try to find it in the form or create a generic mapping
            $field_found = false;
            foreach ($form['fields'] as $field) {
                if ($field->id == $key) {
                    $field_found = true;
                    break;
                }
            }

            if (!$field_found) {
                // Field not found in form structure, but exists in entry
                // Map it with a generic name based on field ID
                $field_label = 'field_' . $key;
                $mapped_data[$field_label] = $value;
                $processed_field_ids[] = (string)$key;
            }
        }

        // Add source URL
        $mapped_data['source_url'] = isset($entry['source_url']) ? $entry['source_url'] : '';

        // Add user agent if available
        $mapped_data['user_agent'] = isset($entry['user_agent']) ? $entry['user_agent'] : '';

        // Add IP address if available
        $mapped_data['ip_address'] = isset($entry['ip']) ? $entry['ip'] : '';

        // Log summary
        $this->log_debug('Webhook data modification completed', array(
            'total_fields_processed' => $stats['total_fields'],
            'fields_included' => $stats['included_fields'],
            'fields_excluded_by_filter' => $stats['excluded_by_filter'],
            'fields_excluded_by_empty' => $stats['excluded_by_empty'],
            'total_fields_in_payload' => count($mapped_data)
        ));

        $this->log_debug('Final webhook payload fields', array_keys($mapped_data));

        // Completely replace the original data - no duplicates
        return $mapped_data;
    }

    /**
     * Get field label with fallback to admin label or field type
     *
     * @param object $field The field object
     * @return string The field label
     */
    private function get_field_label($field) {
        // Prefer admin label if set (allows for custom unique identifiers)
        if (!empty($field->adminLabel)) {
            return $this->sanitize_label($field->adminLabel);
        }

        // Fall back to field label
        if (!empty($field->label)) {
            return $this->sanitize_label($field->label);
        }

        // Last resort: field type with ID
        return $field->type . '_' . $field->id;
    }

    /**
     * Check if a field should be included based on configuration
     *
     * @param object $field The field object
     * @param string $field_label The sanitized field label
     * @return bool Whether to include this field
     */
    private function should_include_field($field, $field_label) {
        $mode = $this->field_config['mode'];

        // Mode: all - include everything
        if ($mode === 'all') {
            return true;
        }

        // Mode: admin_label_only - only include if field has admin label
        if ($mode === 'admin_label_only') {
            return !empty($field->adminLabel);
        }

        // Mode: whitelist - only include if in the list
        if ($mode === 'whitelist') {
            return in_array($field_label, $this->field_config['fields']) ||
                   (!empty($field->adminLabel) && in_array($this->sanitize_label($field->adminLabel), $this->field_config['fields'])) ||
                   (!empty($field->label) && in_array($this->sanitize_label($field->label), $this->field_config['fields']));
        }

        // Mode: blacklist - exclude if in the list
        if ($mode === 'blacklist') {
            $is_blacklisted = in_array($field_label, $this->field_config['fields']) ||
                              (!empty($field->adminLabel) && in_array($this->sanitize_label($field->adminLabel), $this->field_config['fields'])) ||
                              (!empty($field->label) && in_array($this->sanitize_label($field->label), $this->field_config['fields']));
            return !$is_blacklisted;
        }

        return true; // Default to including
    }

    /**
     * Check if a field value is empty
     *
     * @param mixed $value The value to check
     * @return bool Whether the value is considered empty
     */
    private function is_empty_value($value) {
        // Handle arrays (like checkbox values, name parts, etc.)
        if (is_array($value)) {
            // Check if all array values are empty
            foreach ($value as $v) {
                if (!$this->is_empty_value($v)) {
                    return false;
                }
            }
            return true;
        }

        // Handle strings and other scalar values
        return $value === '' || $value === null;
    }

    /**
     * Check if a field is required (should always be included even if empty)
     *
     * @param string $field_label The sanitized field label
     * @param object $field The field object
     * @return bool Whether this is a required field
     */
    private function is_required_field($field_label, $field) {
        return in_array($field_label, $this->field_config['required_fields']) ||
               (!empty($field->adminLabel) && in_array($this->sanitize_label($field->adminLabel), $this->field_config['required_fields'])) ||
               (!empty($field->label) && in_array($this->sanitize_label($field->label), $this->field_config['required_fields']));
    }

    /**
     * Sanitize label for use as array key
     *
     * @param string $label The label to sanitize
     * @return string Sanitized label
     */
    private function sanitize_label($label) {
        // Remove HTML tags
        $label = strip_tags($label);

        // Replace spaces with underscores
        $label = str_replace(' ', '_', $label);

        // Remove special characters but keep underscores and alphanumeric
        $label = preg_replace('/[^A-Za-z0-9_-]/', '', $label);

        // Convert to lowercase for consistency
        $label = strtolower($label);

        // Ensure it doesn't start with a number
        if (is_numeric(substr($label, 0, 1))) {
            $label = 'field_' . $label;
        }

        return $label;
    }

    /**
     * Create webhook log table on plugin activation
     */
    public function create_webhook_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_webhook_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            form_id bigint(20) NOT NULL,
            feed_id bigint(20) NOT NULL,
            webhook_name varchar(255) DEFAULT NULL,
            webhook_url text DEFAULT NULL,
            status varchar(20) NOT NULL,
            response_code int(11) DEFAULT NULL,
            response_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY entry_id (entry_id),
            KEY form_id (form_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add admin menu - standalone top-level menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Webhook Manager',                          // Page title
            'Webhook Manager',                          // Menu title
            'manage_options',                           // Capability
            'gf-webhook-manager',                       // Menu slug
            array($this, 'render_admin_page'),          // Callback
            'dashicons-networking',                     // Icon
            80                                          // Position (after Settings)
        );

        // Add submenu for settings
        add_submenu_page(
            'gf-webhook-manager',                       // Parent slug
            'Field Configuration',                      // Page title
            'Field Configuration',                      // Menu title
            'manage_options',                           // Capability
            'gf-webhook-field-config',                  // Menu slug
            array($this, 'render_config_page')          // Callback
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Handle form submission for resending webhooks
        if (isset($_POST['resend_webhook_submit']) && check_admin_referer('resend_webhook_action', 'resend_webhook_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.'));
            }
            $this->handle_webhook_resend();
        }

        // Determine which tab to show
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'entries';

        ?>
        <div class="wrap">
            <h1>Webhook Manager</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=gf-webhook-manager&tab=entries" class="nav-tab <?php echo $active_tab === 'entries' ? 'nav-tab-active' : ''; ?>">
                    Resend Entries
                </a>
                <a href="?page=gf-webhook-manager&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    Webhook Log
                </a>
            </h2>

            <?php
            if ($active_tab === 'logs') {
                $this->render_log_viewer();
            } else {
                $this->render_entry_list();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render field configuration page
     */
    public function render_config_page() {
        // Handle form submission
        if (isset($_POST['save_field_config']) && check_admin_referer('save_field_config_action', 'field_config_nonce')) {
            $this->handle_config_save();
        }

        // Display admin notice if present
        if ($message = get_transient('gf_webhook_mapper_admin_notice')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            delete_transient('gf_webhook_mapper_admin_notice');
        }

        $config = $this->field_config;
        ?>
        <div class="wrap">
            <h1>Webhook Field Configuration</h1>
            <p>Configure which fields are included in webhook payloads.</p>

            <form method="post" action="">
                <?php wp_nonce_field('save_field_config_action', 'field_config_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="filter_mode">Filter Mode</label>
                        </th>
                        <td>
                            <select name="filter_mode" id="filter_mode">
                                <option value="all" <?php selected($config['mode'], 'all'); ?>>Send All Fields</option>
                                <option value="admin_label_only" <?php selected($config['mode'], 'admin_label_only'); ?>>Only Fields with Admin Labels</option>
                                <option value="whitelist" <?php selected($config['mode'], 'whitelist'); ?>>Whitelist (Only Specified Fields)</option>
                                <option value="blacklist" <?php selected($config['mode'], 'blacklist'); ?>>Blacklist (Exclude Specified Fields)</option>
                            </select>
                            <p class="description">
                                <strong>All Fields:</strong> Send all form fields (current behavior)<br/>
                                <strong>Admin Labels Only:</strong> Send only fields that have an admin label set<br/>
                                <strong>Whitelist:</strong> Only send fields specified below<br/>
                                <strong>Blacklist:</strong> Send all fields except those specified below
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="field_list">Field List</label>
                        </th>
                        <td>
                            <textarea name="field_list" id="field_list" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(implode("\n", $config['fields'])); ?></textarea>
                            <p class="description">
                                Enter one field admin label or field label per line.<br/>
                                Used for whitelist or blacklist modes.<br/>
                                Example: company_name
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="include_empty">Include Empty Fields</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_empty" id="include_empty" value="1" <?php checked($config['include_empty'], true); ?> />
                                Include fields with empty or null values in webhook payload
                            </label>
                            <p class="description">
                                When unchecked, fields with empty values will be excluded from the webhook payload (unless they're in the Required Fields list below).
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="required_fields">Required Fields</label>
                        </th>
                        <td>
                            <textarea name="required_fields" id="required_fields" rows="5" cols="50" class="large-text code"><?php echo esc_textarea(implode("\n", $config['required_fields'])); ?></textarea>
                            <p class="description">
                                Enter one field admin label or field label per line.<br/>
                                These fields will always be included in the webhook, even if they are empty.<br/>
                                Example: form_id
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="save_field_config" class="button button-primary">Save Configuration</button>
                </p>
            </form>

            <hr/>

            <h2>Debug Information</h2>
            <h3>Current Configuration</h3>
            <pre><?php echo esc_html(print_r($config, true)); ?></pre>
        </div>
        <?php
    }

    /**
     * Handle configuration save
     */
    private function handle_config_save() {
        // Add capability check as defense in depth
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Validate mode against whitelist
        $allowed_modes = array('all', 'whitelist', 'blacklist', 'admin_label_only');
        $mode = isset($_POST['filter_mode']) ? sanitize_text_field($_POST['filter_mode']) : 'all';
        if (!in_array($mode, $allowed_modes, true)) {
            $mode = 'all'; // Fallback to safe default
        }

        $new_config = array(
            'mode' => $mode,
            'include_empty' => !empty($_POST['include_empty']) && $_POST['include_empty'] === '1',
        );

        // Parse field list
        $field_list = isset($_POST['field_list']) ? sanitize_textarea_field($_POST['field_list']) : '';
        $fields = array_filter(array_map('trim', explode("\n", $field_list)));
        $new_config['fields'] = $fields;

        // Parse required fields list
        $required_list = isset($_POST['required_fields']) ? sanitize_textarea_field($_POST['required_fields']) : '';
        $required_fields = array_filter(array_map('trim', explode("\n", $required_list)));
        $new_config['required_fields'] = $required_fields;

        $this->update_field_config($new_config);

        // Use WordPress transients for admin notices
        set_transient('gf_webhook_mapper_admin_notice', 'Configuration saved successfully!', 45);
    }

    /**
     * Render entry list table
     */
    private function render_entry_list() {
        // Get all forms
        $forms = GFAPI::get_forms();
        if (is_wp_error($forms)) {
            error_log('[GF Webhook Field Mapper] Error fetching forms: ' . $forms->get_error_message());
            echo '<div class="notice notice-error"><p>Error loading forms. Please try again.</p></div>';
            return;
        }

        // Get selected form ID from query string
        $selected_form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;

        ?>
        <div class="gf-webhook-manager">
            <h2>Form Entries</h2>

            <!-- Form Filter -->
            <form method="get" action="">
                <input type="hidden" name="page" value="gf-webhook-manager" />
                <label for="form_id">Select Form:</label>
                <select name="form_id" id="form_id" onchange="this.form.submit()">
                    <option value="0">-- All Forms --</option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_form_id, $form['id']); ?>>
                            <?php echo esc_html($form['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php
            // Get entries for selected form or all forms
            $search_criteria = array();
            if ($selected_form_id > 0) {
                $entries = GFAPI::get_entries($selected_form_id);
                if (is_wp_error($entries)) {
                    error_log('[GF Webhook Field Mapper] Error fetching entries: ' . $entries->get_error_message());
                    $entries = array();
                }
                $current_form = GFAPI::get_form($selected_form_id);
                if (is_wp_error($current_form)) {
                    error_log('[GF Webhook Field Mapper] Error fetching form: ' . $current_form->get_error_message());
                    $current_form = null;
                }
            } else {
                // Get entries from all forms
                $entries = array();
                foreach ($forms as $form) {
                    $form_entries = GFAPI::get_entries($form['id']);
                    if (!is_wp_error($form_entries) && is_array($form_entries)) {
                        $entries = array_merge($entries, $form_entries);
                    }
                }
            }

            if (empty($entries)): ?>
                <p><em>No entries found.</em></p>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('resend_webhook_action', 'resend_webhook_nonce'); ?>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="select-all" /></th>
                                <th>Entry ID</th>
                                <th>Company Name</th>
                                <th>Form</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry):
                                $form = GFAPI::get_form($entry['form_id']);
                                $company_name = $this->get_company_name_from_entry($entry, $form);
                            ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr($entry['id']); ?>" />
                                    </th>
                                    <td><?php echo esc_html($entry['id']); ?></td>
                                    <td><?php echo esc_html($company_name); ?></td>
                                    <td><?php echo esc_html($form['title']); ?></td>
                                    <td><?php echo esc_html($entry['date_created']); ?></td>
                                    <td><?php echo esc_html($entry['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($selected_form_id > 0):
                        // Specific form selected - show webhooks for that form
                        $webhooks = $this->get_form_webhooks($selected_form_id);
                        if (!empty($webhooks)): ?>
                            <h3>Select Webhook(s) to Resend:</h3>
                            <?php foreach ($webhooks as $webhook): ?>
                                <label>
                                    <input type="checkbox" name="webhook_ids[]" value="<?php echo esc_attr($webhook['id']); ?>" />
                                    <?php echo esc_html($webhook['meta']['feedName']); ?>
                                    (<?php echo esc_html($webhook['meta']['requestURL']); ?>)
                                </label><br/>
                            <?php endforeach; ?>

                            <p>
                                <button type="submit" name="resend_webhook_submit" class="button button-primary">
                                    Resend Selected Entries
                                </button>
                            </p>
                        <?php else: ?>
                            <p><em>No webhooks configured for this form.</em></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- All Forms view - resend to each entry's form webhooks -->
                        <h3>Resend Options:</h3>
                        <p>
                            <label>
                                <input type="radio" name="resend_mode" value="all_webhooks" checked />
                                Resend to ALL webhooks configured for each entry's form
                            </label>
                        </p>
                        <p>
                            <button type="submit" name="resend_webhook_submit" class="button button-primary">
                                Resend Selected Entries to Their Form Webhooks
                            </button>
                        </p>
                        <p class="description">
                            Each selected entry will be resent to all webhooks configured for its respective form.
                        </p>
                    <?php endif; ?>
                </form>

                <script>
                document.getElementById('select-all').addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('input[name="entry_ids[]"]');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = document.getElementById('select-all').checked;
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render log viewer
     */
    private function render_log_viewer() {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'gf_webhook_log');

        // Get filter parameters
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_form_id = isset($_GET['filter_form_id']) ? absint($_GET['filter_form_id']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Build query
        $where_clauses = array();
        $query_params = array();

        if (!empty($filter_status)) {
            $where_clauses[] = "status = %s";
            $query_params[] = $filter_status;
        }

        if ($filter_form_id > 0) {
            $where_clauses[] = "form_id = %d";
            $query_params[] = $filter_form_id;
        }

        if (!empty($search)) {
            $where_clauses[] = "(webhook_name LIKE %s OR webhook_url LIKE %s OR entry_id = %d)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = absint($search);
        }

        // Fix: Only add WHERE if we have clauses
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
        if (!empty($query_params)) {
            $total_count = $wpdb->get_var($wpdb->prepare($total_query, $query_params));
        } else {
            $total_count = $wpdb->get_var($total_query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;
        $total_pages = ceil($total_count / $per_page);

        // Get logs
        $logs_query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $final_params = array_merge($query_params, array($per_page, $offset));
        $logs = $wpdb->get_results($wpdb->prepare($logs_query, $final_params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Get all forms for filter
        $forms = GFAPI::get_forms();
        if (is_wp_error($forms)) {
            error_log('[GF Webhook Field Mapper] Error fetching forms in log viewer: ' . $forms->get_error_message());
            $forms = array();
        }

        ?>
        <div class="gf-webhook-log-viewer">
            <h2>Webhook Log</h2>

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="gf-webhook-manager" />
                        <input type="hidden" name="tab" value="logs" />

                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="success" <?php selected($filter_status, 'success'); ?>>Success</option>
                            <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                        </select>

                        <select name="filter_form_id">
                            <option value="0">All Forms</option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($filter_form_id, $form['id']); ?>>
                                    <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search entry ID, webhook name, or URL..." style="width: 300px;" />

                        <button type="submit" class="button">Filter</button>
                        <a href="?page=gf-webhook-manager&tab=logs" class="button">Reset</a>
                    </form>
                </div>
            </div>

            <!-- Log Table -->
            <?php if (empty($logs)): ?>
                <p><em>No log entries found.</em></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Entry ID</th>
                            <th>Form</th>
                            <th>Webhook</th>
                            <th>URL</th>
                            <th>Status</th>
                            <th>Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            $form = GFAPI::get_form($log->form_id);
                            $form_title = $form ? $form['title'] : 'Unknown Form';
                        ?>
                            <tr>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=gf_entries&view=entry&id=' . $log->form_id . '&lid=' . $log->entry_id); ?>">
                                        #<?php echo esc_html($log->entry_id); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($form_title); ?></td>
                                <td><?php echo esc_html($log->webhook_name); ?></td>
                                <td><small><?php echo esc_html($log->webhook_url); ?></small></td>
                                <td>
                                    <span style="color: <?php echo $log->status === 'success' ? 'green' : 'red'; ?>; font-weight: bold;">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($log->response_code); ?>
                                    <?php if (!empty($log->response_message)): ?>
                                        <br/><small><?php echo esc_html(substr($log->response_message, 0, 100)); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo number_format($total_count); ?> items</span>
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get company name from entry
     *
     * @param array $entry The entry data
     * @param array $form The form object
     * @return string Company name or empty string
     */
    private function get_company_name_from_entry($entry, $form) {
        // Look for common company name field labels/admin labels
        $company_field_labels = array('company_name', 'company', 'business_name', 'organization');

        foreach ($form['fields'] as $field) {
            // Check admin label first
            $field_key = '';
            if (!empty($field->adminLabel)) {
                $field_key = strtolower(str_replace(' ', '_', $field->adminLabel));
            } elseif (!empty($field->label)) {
                $field_key = strtolower(str_replace(' ', '_', $field->label));
            }

            // Check if this field matches a company name pattern
            foreach ($company_field_labels as $company_label) {
                if (strpos($field_key, $company_label) !== false) {
                    $value = isset($entry[$field->id]) ? $entry[$field->id] : '';
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }
        }

        return '(No Company Name)';
    }

    /**
     * Get webhooks configured for a form
     *
     * @param int $form_id Form ID
     * @return array Array of webhook feeds
     */
    private function get_form_webhooks($form_id) {
        $feeds = GFAPI::get_feeds(null, $form_id, 'gravityformswebhooks');
        return $feeds ? $feeds : array();
    }

    /**
     * Handle webhook resend request
     */
    private function handle_webhook_resend() {
        // Get selected entries
        $entry_ids = isset($_POST['entry_ids']) ? array_map('absint', $_POST['entry_ids']) : array();

        if (empty($entry_ids)) {
            echo '<div class="notice notice-error"><p>No entries selected.</p></div>';
            return;
        }

        // Check resend mode
        $resend_mode = isset($_POST['resend_mode']) ? sanitize_text_field($_POST['resend_mode']) : 'specific';

        $success_count = 0;
        $error_count = 0;

        foreach ($entry_ids as $entry_id) {
            $entry = GFAPI::get_entry($entry_id);
            if (is_wp_error($entry)) {
                error_log('[GF Webhook Field Mapper] Error fetching entry ' . $entry_id . ': ' . $entry->get_error_message());
                $error_count++;
                continue;
            }

            $form = GFAPI::get_form($entry['form_id']);
            if (is_wp_error($form)) {
                error_log('[GF Webhook Field Mapper] Error fetching form ' . $entry['form_id'] . ': ' . $form->get_error_message());
                $error_count++;
                continue;
            }

            // Determine which webhooks to send to
            $webhooks = array();
            if ($resend_mode === 'all_webhooks') {
                // All Forms view - get all webhooks for this entry's form
                $webhooks = $this->get_form_webhooks($entry['form_id']);
            } else {
                // Specific form view - use selected webhooks
                $selected_webhook_ids = isset($_POST['webhook_ids']) ? array_map('absint', $_POST['webhook_ids']) : array();
                if (!empty($selected_webhook_ids)) {
                    $all_webhooks = $this->get_form_webhooks($entry['form_id']);
                    foreach ($all_webhooks as $webhook) {
                        if (in_array($webhook['id'], $selected_webhook_ids)) {
                            $webhooks[] = $webhook;
                        }
                    }
                }
            }

            if (empty($webhooks)) {
                $error_count++;
                continue;
            }

            // Send to each webhook
            foreach ($webhooks as $webhook) {
                $result = $this->send_webhook($entry, $form, $webhook);

                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }

                // Log the attempt
                $this->log_webhook_attempt($entry_id, $entry['form_id'], $webhook['id'], $webhook, $result);
            }
        }

        // Display results
        if ($success_count > 0) {
            echo '<div class="notice notice-success"><p>';
            echo sprintf('Successfully resent %d webhook(s).', $success_count);
            echo '</p></div>';
        }

        if ($error_count > 0) {
            echo '<div class="notice notice-error"><p>';
            echo sprintf('Failed to resend %d webhook(s). Check the log for details.', $error_count);
            echo '</p></div>';
        }
    }

    /**
     * Send webhook for an entry
     *
     * @param array $entry Entry data
     * @param array $form Form object
     * @param array $webhook Webhook feed configuration
     * @return array Result with 'success', 'response_code', and 'message'
     */
    private function send_webhook($entry, $form, $webhook) {
        // Get the webhook URL
        $url = isset($webhook['meta']['requestURL']) ? $webhook['meta']['requestURL'] : '';

        if (empty($url)) {
            return array(
                'success' => false,
                'response_code' => 0,
                'message' => 'No webhook URL configured'
            );
        }

        // Map the entry data using our field mapper
        $mapped_data = $this->modify_webhook_data(array(), array(), $entry, $form);

        // Send the webhook using wp_remote_post
        $response = wp_remote_post($url, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'body'        => wp_json_encode($mapped_data),
            'cookies'     => array(),
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'response_code' => 0,
                'message' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return array(
            'success' => ($response_code >= 200 && $response_code < 300),
            'response_code' => $response_code,
            'message' => $response_body
        );
    }

    /**
     * Log webhook attempt to database
     *
     * @param int $entry_id Entry ID
     * @param int $form_id Form ID
     * @param int $feed_id Webhook feed ID
     * @param array $webhook Webhook configuration
     * @param array $result Result from send_webhook
     */
    private function log_webhook_attempt($entry_id, $form_id, $feed_id, $webhook, $result) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_webhook_log';

        $wpdb->insert(
            $table_name,
            array(
                'entry_id' => $entry_id,
                'form_id' => $form_id,
                'feed_id' => $feed_id,
                'webhook_name' => isset($webhook['meta']['feedName']) ? $webhook['meta']['feedName'] : '',
                'webhook_url' => isset($webhook['meta']['requestURL']) ? $webhook['meta']['requestURL'] : '',
                'status' => $result['success'] ? 'success' : 'failed',
                'response_code' => $result['response_code'],
                'response_message' => substr($result['message'], 0, 1000), // Limit message length
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Add resend metabox to entry detail page
     *
     * @param array $form The form object
     * @param array $entry The entry object
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function add_resend_metabox($form, $entry) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        // Handle resend submission
        if (isset($_POST['gf_resend_webhook_submit']) && check_admin_referer('gf_resend_webhook_' . $entry['id'], 'gf_resend_webhook_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to perform this action.'));
            }
            $this->handle_single_entry_resend($entry, $form);
        }

        // Get webhooks for this form
        $webhooks = $this->get_form_webhooks($form['id']);

        // Get recent log entries for this entry
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_webhook_log';
        $recent_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE entry_id = %d ORDER BY created_at DESC LIMIT 5",
            $entry['id']
        ));

        ?>
        <div class="postbox">
            <h3><span>Resend to Webhook</span></h3>
            <div class="inside">
                <?php if (!empty($webhooks)): ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('gf_resend_webhook_' . $entry['id'], 'gf_resend_webhook_nonce'); ?>

                        <p><strong>Select webhook(s) to resend:</strong></p>
                        <?php foreach ($webhooks as $webhook): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="webhook_ids[]" value="<?php echo esc_attr($webhook['id']); ?>" />
                                <?php echo esc_html($webhook['meta']['feedName']); ?>
                                <br/><small style="margin-left: 20px;"><?php echo esc_html($webhook['meta']['requestURL']); ?></small>
                            </label>
                        <?php endforeach; ?>

                        <p style="margin-top: 15px;">
                            <button type="submit" name="gf_resend_webhook_submit" class="button button-primary">
                                Resend to Selected Webhooks
                            </button>
                        </p>
                    </form>

                    <?php if (!empty($recent_logs)): ?>
                        <hr style="margin: 15px 0;" />
                        <p><strong>Recent Webhook History:</strong></p>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Webhook</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log->created_at); ?></td>
                                        <td><?php echo esc_html($log->webhook_name); ?></td>
                                        <td>
                                            <span class="<?php echo $log->status === 'success' ? 'gf-icon-check' : 'gf-icon-close'; ?>" style="color: <?php echo $log->status === 'success' ? 'green' : 'red'; ?>;">
                                                <?php echo esc_html(ucfirst($log->status)); ?>
                                                (<?php echo esc_html($log->response_code); ?>)
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <p><em>No webhooks configured for this form.</em></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle single entry resend from detail page
     *
     * @param array $entry Entry data
     * @param array $form Form object
     */
    private function handle_single_entry_resend($entry, $form) {
        $webhook_ids = isset($_POST['webhook_ids']) ? array_map('absint', $_POST['webhook_ids']) : array();

        if (empty($webhook_ids)) {
            echo '<div class="notice notice-error"><p>Please select at least one webhook.</p></div>';
            return;
        }

        $webhooks = $this->get_form_webhooks($form['id']);
        $success_count = 0;
        $error_count = 0;

        foreach ($webhooks as $webhook) {
            if (in_array($webhook['id'], $webhook_ids)) {
                $result = $this->send_webhook($entry, $form, $webhook);

                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }

                $this->log_webhook_attempt($entry['id'], $form['id'], $webhook['id'], $webhook, $result);
            }
        }

        if ($success_count > 0) {
            echo '<div class="notice notice-success"><p>Successfully resent to ' . $success_count . ' webhook(s).</p></div>';
        }

        if ($error_count > 0) {
            echo '<div class="notice notice-error"><p>Failed to resend to ' . $error_count . ' webhook(s).</p></div>';
        }
    }
}

// Initialize the plugin
$gf_webhook_mapper = new GF_Webhook_Field_Mapper();

// Register activation hook
register_activation_hook(__FILE__, array($gf_webhook_mapper, 'create_webhook_log_table'));