# Testing Guide for v1.4.0-dev

## Installation

1. **Backup your current plugin** (if already installed)
2. Deactivate and delete the old version
3. Upload and activate `gravity-forms-webhook-field-mapper-1.4.0-dev.zip`
4. The plugin will automatically create the necessary database table

## New Features to Test

### 1. Field Filtering Configuration

**Location:** WordPress Admin → Webhook Manager → Field Configuration

#### Test Case 1: Admin Labels Only Mode
1. Go to Field Configuration page
2. Set "Filter Mode" to "Only Fields with Admin Labels"
3. Uncheck "Include Empty Fields"
4. Save Configuration
5. Submit a test form
6. **Expected Result:** Only fields with admin labels should appear in the webhook payload

#### Test Case 2: Whitelist Mode
1. Set "Filter Mode" to "Whitelist (Only Specified Fields)"
2. In "Field List", add field labels (one per line):
   ```
   company_name
   email
   phone
   ```
3. Save Configuration
4. Submit a test form
5. **Expected Result:** Only company_name, email, and phone should be in the webhook

#### Test Case 3: Empty Value Filtering
1. Keep current filter mode
2. **Uncheck** "Include Empty Fields"
3. Add some required fields:
   ```
   form_id
   entry_id
   ```
4. Save Configuration
5. Submit a form with some empty fields
6. **Expected Result:**
   - Empty fields should NOT be in the webhook
   - BUT form_id and entry_id should still be present (required fields)

### 2. Debug Logging

**Prerequisites:** Enable WordPress debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

#### Test Case 4: Review Debug Logs
1. Submit a test form
2. Check `/wp-content/debug.log`
3. **Expected Log Entries:**
   ```
   [GF Webhook Field Mapper] Webhook data modification started
   [GF Webhook Field Mapper] Field excluded by filter
   [GF Webhook Field Mapper] Field excluded (empty value)
   [GF Webhook Field Mapper] Webhook data modification completed
   [GF Webhook Field Mapper] Final webhook payload fields
   [GF Webhook Field Mapper] Webhook sent
   ```

### 3. Settings Page UI

#### Test Case 5: Configuration Persistence
1. Set up a configuration with:
   - Mode: Blacklist
   - Fields to exclude: `hidden_field`, `internal_notes`
   - Include empty: checked
   - Required fields: `company_name`
2. Save Configuration
3. Navigate away from the page
4. Return to Field Configuration
5. **Expected Result:** All settings should be preserved

#### Test Case 6: Success Message
1. Change any setting
2. Click "Save Configuration"
3. **Expected Result:** Green success message appears at top of page

## Security Testing

### Test Case 7: Capability Checks
1. Log in as a user WITHOUT "manage_options" capability (e.g., Editor role)
2. Try to access: `wp-admin/admin.php?page=gf-webhook-field-config`
3. **Expected Result:** Should see "You do not have sufficient permissions" message

### Test Case 8: Invalid Configuration Mode
1. Use browser dev tools to modify the filter_mode select element
2. Add a fake option: `<option value="malicious">Hack</option>`
3. Select it and save
4. Check the configuration
5. **Expected Result:** Should fallback to "all" mode (safe default)

## Backward Compatibility

### Test Case 9: Default Behavior
1. **Without configuring anything**, submit a form
2. **Expected Result:** Should behave exactly like v1.3.0:
   - All fields included
   - Empty fields included
   - Field names mapped correctly

### Test Case 10: Existing Webhooks
1. Ensure your existing webhook configurations still work
2. Submit forms and verify webhooks are sent
3. **Expected Result:** No breaking changes to existing functionality

## Performance Testing

### Test Case 11: Large Forms
1. Test with a form that has 50+ fields
2. Configure to send only 5 fields (whitelist mode)
3. Submit the form
4. Check debug logs for statistics
5. **Expected Result:**
   - Should show "total_fields_processed: 50+"
   - Should show "fields_included: 5"
   - No performance degradation

### Test Case 12: Bulk Resend
1. Go to Webhook Manager → Resend Entries
2. Select multiple entries (10+)
3. Resend to webhooks
4. **Expected Result:**
   - All resends complete successfully
   - Log viewer shows all attempts
   - No timeouts or errors

## Integration Testing with Momentum

### Test Case 13: Momentum PDF Population
1. Configure for Momentum's expected fields:
   - Mode: Admin Labels Only
   - Include empty: **unchecked**
   - Required fields: (add any critical fields)
2. Submit a complete form
3. Check Momentum to see if PDF populates correctly
4. Submit a partially filled form
5. **Expected Result:**
   - Complete form: PDF fully populated
   - Partial form: Only filled fields sent, no empty values cluttering the data

### Test Case 14: Field Name Verification
1. Check what field names Momentum receives
2. Compare with your Gravity Forms admin labels
3. **Expected Result:** Field names should match sanitized admin labels

## Error Handling

### Test Case 15: GFAPI Error Handling
1. Temporarily rename a Gravity Forms file to simulate an error
2. Try to access Webhook Manager
3. **Expected Result:**
   - Graceful error message instead of crash
   - Error logged to debug.log

### Test Case 16: Database Error Handling
1. Check the Webhook Log page when database is working
2. **Expected Result:**
   - Logs display correctly
   - Filtering and pagination work
   - No SQL errors

## Troubleshooting

### If webhooks aren't firing:
1. Check Gravity Forms → Settings → Webhooks Add-On
2. Verify webhook feed is active
3. Enable WP_DEBUG and check debug.log
4. Look for "Webhook sent" log entries

### If fields are missing:
1. Go to Field Configuration
2. Check the "Debug Information" section at bottom
3. Review your filter mode and field list
4. Try setting to "All Fields" temporarily to diagnose

### If you see "headers already sent" errors:
- This shouldn't happen with v1.4.0-dev
- If it does, please report with steps to reproduce

## Success Criteria

✅ Field filtering works correctly in all modes
✅ Empty value filtering respects required fields
✅ Debug logging provides useful information
✅ Configuration persists across page loads
✅ Security checks prevent unauthorized access
✅ Backward compatible with existing webhooks
✅ Momentum integration works as expected
✅ No PHP errors or warnings
✅ Performance is acceptable with large forms

## Reporting Issues

If you find any issues, please provide:
1. Steps to reproduce
2. Expected vs actual behavior
3. Relevant debug log entries
4. Form configuration (number of fields, types)
5. Filter configuration used

## Dev Package Location

**File:** `/Users/mjhome/working/gravity-forms-webhook-field-mapper-1.4.0-dev.zip`

## Rollback Plan

If you need to rollback:
1. Deactivate v1.4.0-dev
2. Delete the plugin
3. Reinstall v1.3.0 from your backup or from `dist/builds/`
4. Reactivate

Note: Configuration settings are stored in `wp_options` table as `gf_webhook_field_mapper_config` and will persist even after rollback.
