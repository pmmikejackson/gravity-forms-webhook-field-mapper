# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Gravity Forms Webhook Field Mapper" that modifies Gravity Forms webhook payloads to send human-readable field names instead of numeric field IDs.

## Plugin Architecture

### Core Functionality
The plugin intercepts Gravity Forms webhook data through WordPress filters and transforms the payload structure:
- Hooks into `gform_webhooks_request_data` and `gform_zapier_request_body` filters
- Completely replaces numeric field IDs with sanitized field labels
- Includes empty fields in the payload (mapped to empty strings or arrays)

### Field Name Priority
The plugin determines field names using this hierarchy:
1. **Field Label** (what users see on the form) - PRIMARY
2. **Admin Label** (if set in field settings) - FALLBACK
3. **Field Type + ID** (e.g., "text_1") - LAST RESORT

Field labels are sanitized by:
- Removing HTML tags
- Converting spaces to underscores
- Removing special characters (keeping only alphanumeric and underscores)
- Converting to lowercase
- Prefixing with "field_" if starting with a number

### Special Field Handling
The plugin handles complex Gravity Forms field types:
- **Name fields**: Split into sub-components (first, last, prefix, suffix, middle, full)
- **Address fields**: Split into sub-components (street, street2, city, state, zip, country)
- **Checkbox fields**: Returns array of selected values
- **List fields**: Unserializes and returns the list data
- **Fields with inputs**: Handles confirmation fields appropriately

## Testing the Plugin

### Manual Testing
1. Install the plugin in `/wp-content/plugins/gravity-forms-webhook-field-mapper/`
2. Activate through WordPress admin
3. Create a Gravity Form with various field types
4. Configure a webhook in the form settings
5. Submit the form and verify the webhook payload contains field names instead of IDs

### Webhook Testing Tools
- Use services like webhook.site or requestbin.com to inspect webhook payloads
- Check that all fields appear with readable names
- Verify empty fields are included with empty values

## Dependencies

- WordPress 5.0+
- Gravity Forms plugin (core requirement)
- Gravity Forms Webhooks Add-On (for webhook functionality)

## Important Implementation Notes

- The plugin completely replaces the original webhook data structure (no duplicates)
- All form fields are included in webhooks, even when empty
- The plugin auto-initializes when included - no configuration needed
- Works with both the official Webhooks Add-On and Zapier integration