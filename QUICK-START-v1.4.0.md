# Quick Start Guide - v1.4.0-dev

## For Momentum Integration

### Recommended Configuration

1. **Go to:** WordPress Admin → Webhook Manager → Field Configuration

2. **Set these options:**
   ```
   Filter Mode: Only Fields with Admin Labels
   Include Empty Fields: ☐ (UNCHECKED)
   ```

3. **Add these required fields** (if you want them even when empty):
   ```
   form_id
   entry_id
   date_created
   ```

4. **Click:** Save Configuration

### Why This Configuration?

- **Admin Labels Only:** Ensures only the fields you've explicitly labeled are sent to Momentum
- **No Empty Fields:** Prevents sending null/empty values that clutter the data
- **Required Fields:** Metadata fields that Momentum might need for tracking

## Enable Debug Logging (Recommended for Testing)

Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will appear in: `/wp-content/debug.log`

## Testing Your First Form

1. Make sure your form fields have **Admin Labels** set
   - Edit your form in Gravity Forms
   - For each field, set an "Admin Field Label" (in Advanced tab)
   - Use lowercase, underscores, no spaces: `company_name`, `contact_email`, etc.

2. Submit a test form with:
   - Some fields filled in
   - Some fields left empty

3. Check your webhook endpoint (e.g., webhook.site)

4. Verify:
   - ✅ Only fields with admin labels appear
   - ✅ Empty fields are NOT in the payload
   - ✅ Metadata fields (form_id, entry_id) ARE in the payload

## View Webhook Logs

**Go to:** Webhook Manager → Webhook Log

You can:
- Filter by status (success/failed)
- Filter by form
- Search by entry ID or webhook name
- See response codes from Momentum

## Common Filter Modes

### Mode 1: Admin Labels Only (RECOMMENDED)
**Use when:** You want only specific fields sent to Momentum
**Setup:** Just add admin labels to the fields you want to send

### Mode 2: Whitelist
**Use when:** You want explicit control over exact field names
**Setup:**
- Set mode to "Whitelist"
- Add field names one per line:
  ```
  company_name
  contact_email
  phone_number
  ```

### Mode 3: Blacklist
**Use when:** You want to send everything EXCEPT certain fields
**Setup:**
- Set mode to "Blacklist"
- Add field names to exclude:
  ```
  internal_notes
  admin_only_field
  ```

### Mode 4: All Fields (Default)
**Use when:** You want backward compatibility with v1.3.0
**Setup:** Just select "Send All Fields"

## Troubleshooting

### Webhook not firing
```bash
# Check if webhook feed is active
WordPress Admin → Forms → Settings → Webhooks
```

### Too many fields in webhook
```
Solution: Change to "Admin Labels Only" mode
Only add admin labels to fields you want sent
```

### Missing expected fields
```
Solution 1: Check admin labels are set correctly
Solution 2: Add to "Required Fields" list if they should always be sent
Solution 3: Check "Include Empty Fields" if you want empty values sent
```

### PDF not populating in Momentum
```
1. Check debug logs for what's being sent
2. Verify field names match what Momentum expects
3. Ensure empty value filtering isn't removing needed fields
4. Add critical fields to "Required Fields" list
```

## Debug Log Examples

### Successful Webhook
```
[GF Webhook Field Mapper] Webhook data modification started | Data: Array (
    [form_id] => 3
    [entry_id] => 42
    [filter_mode] => admin_label_only
    [include_empty] =>
)
[GF Webhook Field Mapper] Webhook data modification completed | Data: Array (
    [total_fields_processed] => 25
    [fields_included] => 8
    [excluded_by_filter] => 17
    [excluded_by_empty] => 0
)
[GF Webhook Field Mapper] Webhook sent | Data: Array (
    [form_id] => 3
    [entry_id] => 42
    [response_code] => 200
)
```

### Field Excluded (as expected)
```
[GF Webhook Field Mapper] Field excluded by filter | Data: Array (
    [field_id] => 12
    [field_label] => text_12
    [has_admin_label] =>
)
```

### Empty Field Excluded
```
[GF Webhook Field Mapper] Field excluded (empty value) | Data: Array (
    [field_id] => 5
    [field_label] => company_website
)
```

## Configuration Storage

Settings are stored in WordPress options table as:
- Option name: `gf_webhook_field_mapper_config`
- Persists across plugin updates
- Can be reset by deleting the option in phpMyAdmin if needed

## What Changed from v1.3.0?

### New
- ✅ Field filtering (admin labels, whitelist, blacklist)
- ✅ Empty value filtering
- ✅ Required fields configuration
- ✅ Debug logging
- ✅ Settings page
- ✅ Better security

### Unchanged
- ✅ Field name mapping (still works the same)
- ✅ Webhook resend functionality
- ✅ All field types supported (name, address, checkbox, etc.)
- ✅ Default behavior (when using "All Fields" mode)

### Improved
- ✅ More secure (capability checks, input validation)
- ✅ Better error handling
- ✅ Removed hardcoded field IDs
- ✅ Consistent checkbox handling

## Next Steps

1. Install v1.4.0-dev
2. Configure for "Admin Labels Only" mode
3. Test with a sample form
4. Check debug logs
5. Verify Momentum receives correct data
6. Report any issues

## Support

If you encounter issues:
1. Check debug logs first
2. Verify configuration in Field Configuration page
3. Test with "All Fields" mode to isolate the issue
4. Provide debug log excerpts when reporting issues
