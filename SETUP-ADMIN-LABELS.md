# Setup Guide: Admin Labels Only Configuration

## Overview
This guide explains how to configure the Gravity Forms Webhook Field Mapper plugin to only send fields with admin labels to Momentum webhook.

## Why Admin Labels Only?
- **Predictable field names**: Admin labels provide consistent, API-friendly field names
- **Better control**: Only explicitly labeled fields are sent to external systems
- **Cleaner payloads**: Reduces unnecessary data in webhook requests
- **Easier mapping**: Momentum can reliably map fields by their admin labels

## Step 1: Configure Plugin Settings

1. Log in to WordPress admin
2. Navigate to **Webhook Manager → Field Configuration**
3. Configure the following settings:
   - **Filter Mode**: Select "Only Fields with Admin Labels"
   - **Include Empty Fields**: Uncheck (recommended) - only sends fields with values
   - **Required Fields**: Leave empty unless specific fields must always be included
4. Click **Save Configuration**

## Step 2: Set Admin Labels on Forms

### For Private Investigator Form:
1. Go to **Forms → All Forms** in WordPress admin
2. Find and edit the **Private Investigator Form**
3. For each field that should be sent to Momentum:
   - Click the field to open its settings
   - Go to the **Advanced** tab
   - Set the **Admin Field Label** with a descriptive, API-friendly name

### Recommended Admin Label Naming Convention:
- Use lowercase letters
- Use underscores instead of spaces
- Be descriptive but concise
- Examples:
  - `company_name`
  - `contact_email`
  - `contact_phone`
  - `contact_name`
  - `service_type`
  - `investigation_type`
  - `case_description`

## Step 3: Configure Webhook Feed

1. In the form editor, go to **Settings → Webhooks**
2. Create or edit the Momentum webhook feed
3. Set the **Request URL** to your Momentum webhook endpoint
4. Set **Send When** to "Form is submitted"
5. Save the webhook configuration

## Step 4: Test the Configuration

1. Navigate to **Webhook Manager → Field Configuration**
2. Review the **Debug Information** section to confirm settings
3. Submit a test form entry
4. Check the webhook payload at Momentum to verify:
   - Only fields with admin labels are present
   - Field names match the admin labels you set
   - Empty fields are excluded (if configured)

## Verification Checklist

- [ ] Plugin filter mode set to "Only Fields with Admin Labels"
- [ ] Admin labels set on all required fields in Private Investigator Form
- [ ] Admin labels follow naming convention (lowercase, underscores)
- [ ] Momentum webhook configured on the form
- [ ] Test submission sent successfully
- [ ] Momentum receives correct field names

## Troubleshooting

### Fields not appearing in webhook payload
- **Check**: Does the field have an admin label set?
- **Check**: Is the field empty and "Include Empty Fields" unchecked?
- **Solution**: Set admin label in field Advanced settings

### Wrong field names in payload
- **Check**: Is the admin label sanitized correctly?
- **Solution**: Admin labels are automatically sanitized (lowercase, underscores, no special chars)

### All fields being sent (not filtered)
- **Check**: Plugin configuration in Webhook Manager → Field Configuration
- **Solution**: Ensure "Only Fields with Admin Labels" is selected and saved

## Additional Notes

- The plugin automatically processes webhooks during form submission
- No code changes needed - all configuration via WordPress admin
- Changes to admin labels take effect immediately on next form submission
- Multiple forms can share the same webhook with different field mappings
