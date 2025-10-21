<?php
/**
 * Plugin Name: Gravity Forms Webhook Field Mapper
 * Plugin URI: https://github.com/mjhome/gravity-forms-webhook-field-mapper
 * Description: Maps Gravity Forms field IDs to field names in webhook data
 * Version: 1.2.0
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
     * Initialize the plugin
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
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
        // Create new data array with field names (completely replace the original)
        $mapped_data = array();

        // Add form metadata
        $mapped_data['form_id'] = $form['id'];
        $mapped_data['form_title'] = $form['title'];
        $mapped_data['entry_id'] = $entry['id'];
        $mapped_data['date_created'] = $entry['date_created'];

        // Map each field (including empty ones)
        foreach ($form['fields'] as $field) {
            $field_id = $field->id;
            $field_label = $this->get_field_label($field);

            // Handle different field types with special sub-field structures
            if ($field->type == 'name') {
                // Handle name fields with sub-fields
                $name_parts = array();

                // Always include all possible name sub-fields
                $name_parts['prefix'] = isset($entry[$field_id . '.2']) ? $entry[$field_id . '.2'] : '';
                $name_parts['first'] = isset($entry[$field_id . '.3']) ? $entry[$field_id . '.3'] : '';
                $name_parts['middle'] = isset($entry[$field_id . '.4']) ? $entry[$field_id . '.4'] : '';
                $name_parts['last'] = isset($entry[$field_id . '.6']) ? $entry[$field_id . '.6'] : '';
                $name_parts['suffix'] = isset($entry[$field_id . '.8']) ? $entry[$field_id . '.8'] : '';

                // Include full name if available
                if (isset($entry[$field_id]) && $entry[$field_id] !== '') {
                    $name_parts['full'] = $entry[$field_id];
                }

                // Always include the field with all sub-fields
                $mapped_data[$field_label] = $name_parts;

            } elseif ($field->type == 'address') {
                // Handle address fields with sub-fields
                $address_parts = array();

                // Always include all address sub-fields
                $address_parts['street'] = isset($entry[$field_id . '.1']) ? $entry[$field_id . '.1'] : '';
                $address_parts['street2'] = isset($entry[$field_id . '.2']) ? $entry[$field_id . '.2'] : '';
                $address_parts['city'] = isset($entry[$field_id . '.3']) ? $entry[$field_id . '.3'] : '';
                $address_parts['state'] = isset($entry[$field_id . '.4']) ? $entry[$field_id . '.4'] : '';
                $address_parts['zip'] = isset($entry[$field_id . '.5']) ? $entry[$field_id . '.5'] : '';
                $address_parts['country'] = isset($entry[$field_id . '.6']) ? $entry[$field_id . '.6'] : '';

                // Always include the field with all sub-fields
                $mapped_data[$field_label] = $address_parts;

            } elseif ($field->type == 'date') {
                // Handle date fields with sub-fields
                $date_parts = array();

                if (is_array($field->inputs)) {
                    // Date field with separate inputs (month, day, year)
                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        $input_label = !empty($input['label']) ? $this->sanitize_label($input['label']) : 'input_' . str_replace('.', '_', $input_id);
                        $date_parts[$input_label] = isset($entry[$input_id]) ? $entry[$input_id] : '';
                    }
                    $mapped_data[$field_label] = $date_parts;
                } else {
                    // Single date input
                    $mapped_data[$field_label] = isset($entry[$field_id]) ? $entry[$field_id] : '';
                }

            } elseif ($field->type == 'time') {
                // Handle time fields with sub-fields
                $time_parts = array();

                if (is_array($field->inputs)) {
                    // Time field with separate inputs (hour, minute, am/pm)
                    foreach ($field->inputs as $input) {
                        $input_id = $input['id'];
                        $input_label = !empty($input['label']) ? $this->sanitize_label($input['label']) : 'input_' . str_replace('.', '_', $input_id);
                        $time_parts[$input_label] = isset($entry[$input_id]) ? $entry[$input_id] : '';
                    }
                    $mapped_data[$field_label] = $time_parts;
                } else {
                    // Single time input
                    $mapped_data[$field_label] = isset($entry[$field_id]) ? $entry[$field_id] : '';
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

                // Convert to comma-separated string for specific fields (174 = Training, 175 = Pre-Employment)
                if (in_array($field_id, array(174, 175))) {
                    $mapped_data[$field_label] = !empty($checkbox_values) ? implode(', ', $checkbox_values) : '';
                } else {
                    // Always include the field, even if no checkboxes selected
                    $mapped_data[$field_label] = $checkbox_values;
                }

            } elseif ($field->type == 'list') {
                // Handle list fields
                $list_values = '';
                if (isset($entry[$field_id])) {
                    $list_values = maybe_unserialize($entry[$field_id]); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_maybe_unserialize
                }

                // Always include the field, even if empty
                $mapped_data[$field_label] = $list_values ? $list_values : '';

            } else {
                // Handle standard fields - always include them even if empty
                $value = isset($entry[$field_id]) ? $entry[$field_id] : '';

                // Handle fields with multiple inputs
                if (!is_array($field->inputs) || empty($field->inputs)) {
                    // Simple field with no inputs - use the main field value
                    $mapped_data[$field_label] = $value;
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
                            $mapped_data[$field_label] = $input_value;
                        }
                    }

                    if ($has_multiple_inputs) {
                        // For multi-input fields, include all sub-fields
                        $mapped_data[$field_label] = $input_values;
                    }

                    // If we still don't have a value but the main field has data, use that
                    if ((!isset($mapped_data[$field_label]) || $mapped_data[$field_label] === '') && $value !== '') {
                        $mapped_data[$field_label] = $value;
                    }
                }
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
        // Use field label if available
        if (!empty($field->label)) {
            return $this->sanitize_label($field->label);
        }

        // Fall back to admin label
        if (!empty($field->adminLabel)) {
            return $this->sanitize_label($field->adminLabel);
        }

        // Fall back to field type with ID
        return $field->type . '_' . $field->id;
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
}

// Initialize the plugin
new GF_Webhook_Field_Mapper();