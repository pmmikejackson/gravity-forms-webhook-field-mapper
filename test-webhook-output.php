<?php
/**
 * Test script to verify webhook field mapping with sub-fields
 * Run this in a WordPress environment with Gravity Forms active
 */

// Mock form with various field types
$test_form = array(
    'id' => 1,
    'title' => 'Test Form',
    'fields' => array()
);

// Add name field
$name_field = new stdClass();
$name_field->id = 1;
$name_field->type = 'name';
$name_field->label = 'Full Name';
$name_field->inputs = array(
    array('id' => '1.2', 'label' => 'Prefix'),
    array('id' => '1.3', 'label' => 'First'),
    array('id' => '1.4', 'label' => 'Middle'),
    array('id' => '1.6', 'label' => 'Last'),
    array('id' => '1.8', 'label' => 'Suffix')
);
$test_form['fields'][] = $name_field;

// Add address field
$address_field = new stdClass();
$address_field->id = 2;
$address_field->type = 'address';
$address_field->label = 'Home Address';
$address_field->inputs = array(
    array('id' => '2.1', 'label' => 'Street Address'),
    array('id' => '2.2', 'label' => 'Address Line 2'),
    array('id' => '2.3', 'label' => 'City'),
    array('id' => '2.4', 'label' => 'State'),
    array('id' => '2.5', 'label' => 'ZIP'),
    array('id' => '2.6', 'label' => 'Country')
);
$test_form['fields'][] = $address_field;

// Add date field
$date_field = new stdClass();
$date_field->id = 3;
$date_field->type = 'date';
$date_field->label = 'Birth Date';
$date_field->inputs = array(
    array('id' => '3.1', 'label' => 'Month'),
    array('id' => '3.2', 'label' => 'Day'),
    array('id' => '3.3', 'label' => 'Year')
);
$test_form['fields'][] = $date_field;

// Add checkbox field
$checkbox_field = new stdClass();
$checkbox_field->id = 4;
$checkbox_field->type = 'checkbox';
$checkbox_field->label = 'Interests';
$checkbox_field->inputs = array(
    array('id' => '4.1', 'label' => 'Sports'),
    array('id' => '4.2', 'label' => 'Music'),
    array('id' => '4.3', 'label' => 'Technology')
);
$test_form['fields'][] = $checkbox_field;

// Mock entry data
$test_entry = array(
    'id' => 123,
    'date_created' => '2024-01-15 10:30:00',
    'source_url' => 'https://example.com/form',
    'user_agent' => 'Mozilla/5.0',
    'ip' => '192.168.1.1',
    // Name field data
    '1' => 'Dr. John Michael Smith Jr.',
    '1.2' => 'Dr.',
    '1.3' => 'John',
    '1.4' => 'Michael',
    '1.6' => 'Smith',
    '1.8' => 'Jr.',
    // Address field data
    '2.1' => '123 Main Street',
    '2.2' => 'Apt 4B',
    '2.3' => 'New York',
    '2.4' => 'NY',
    '2.5' => '10001',
    '2.6' => 'United States',
    // Date field data
    '3.1' => '06',
    '3.2' => '15',
    '3.3' => '1990',
    // Checkbox field data (only selected ones)
    '4.1' => 'Sports',
    '4.3' => 'Technology'
);

// Initialize the mapper if not already loaded
if (class_exists('GF_Webhook_Field_Mapper')) {
    $mapper = new GF_Webhook_Field_Mapper();

    // Test the webhook data transformation
    $original_data = $test_entry;
    $mapped_data = $mapper->modify_webhook_data($original_data, array(), $test_entry, $test_form);

    echo "=== WEBHOOK OUTPUT TEST ===\n\n";
    echo "Original Entry Fields:\n";
    echo "- Field 1 (name): " . $test_entry['1'] . "\n";
    echo "- Field 2.3 (city): " . $test_entry['2.3'] . "\n";
    echo "- Field 3.1 (month): " . $test_entry['3.1'] . "\n";
    echo "- Field 4.1 (checkbox): " . (isset($test_entry['4.1']) ? $test_entry['4.1'] : 'not selected') . "\n\n";

    echo "Mapped Webhook Data:\n";
    echo json_encode($mapped_data, JSON_PRETTY_PRINT);

    echo "\n\n=== SUB-FIELD VERIFICATION ===\n";

    // Check name sub-fields
    if (isset($mapped_data['full_name']) && is_array($mapped_data['full_name'])) {
        echo "✓ Name field contains sub-fields: " . count($mapped_data['full_name']) . " components\n";
        foreach ($mapped_data['full_name'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    } else {
        echo "✗ Name field missing sub-field structure\n";
    }

    // Check address sub-fields
    if (isset($mapped_data['home_address']) && is_array($mapped_data['home_address'])) {
        echo "✓ Address field contains sub-fields: " . count($mapped_data['home_address']) . " components\n";
        foreach ($mapped_data['home_address'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    } else {
        echo "✗ Address field missing sub-field structure\n";
    }

    // Check date sub-fields
    if (isset($mapped_data['birth_date']) && is_array($mapped_data['birth_date'])) {
        echo "✓ Date field contains sub-fields: " . count($mapped_data['birth_date']) . " components\n";
        foreach ($mapped_data['birth_date'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    } else {
        echo "✗ Date field missing sub-field structure\n";
    }

} else {
    echo "Error: GF_Webhook_Field_Mapper class not found. Make sure the plugin is loaded.\n";
}