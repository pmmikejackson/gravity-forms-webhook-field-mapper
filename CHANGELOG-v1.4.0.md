# Changelog - v1.4.0 Development

## v1.4.0-dev.6 (2025-10-23)

### Added
- Enhanced diagnostic logging for field mapping transformation
- BEFORE transformation logging showing original entry data structure
- AFTER transformation logging showing mapped webhook payload
- Full webhook payload logging for complete inspection
- Manual resend path logging with detailed step tracking
- HTTP response logging with status codes and body preview

### Changed
- Improved visibility into field mapping process
- Added comprehensive logging to `modify_webhook_data()` method
- Added detailed logging to `send_webhook()` method for manual resends
- Better error tracking for webhook send failures

### Testing
- Enable `WP_DEBUG` and `WP_DEBUG_LOG` in wp-config.php
- Perform manual resend from Webhook Manager
- Check wp-content/debug.log for transformation details
- Look for these log markers:
  - `MANUAL RESEND - Starting webhook send`
  - `BEFORE TRANSFORMATION - Original entry data`
  - `AFTER TRANSFORMATION - Mapped webhook data`
  - `FULL WEBHOOK PAYLOAD`
  - `MANUAL RESEND - Webhook response received`

### Purpose
This release adds extensive diagnostic logging to help verify:
1. That field IDs are being replaced with admin labels/field names
2. What data structure is sent to Momentum
3. Which fields are included/excluded based on configuration
4. HTTP response details from webhook endpoint

---

## v1.4.0-dev.2 (2025-10-23)

### Fixed
- Add `gform_after_submission` logging to diagnose webhook firing
- Improved debug logging to track form submission and webhook execution flow
- Added note that `gform_post_send_entry_to_webhook` may not exist in all GF versions

### Changed
- Enhanced logging in `log_form_submission()` to verify webhook processing

### Notes
- **Issue:** Webhooks fire correctly on manual resend but may not fire on initial form submission
- **Diagnosis:** Added logging to help identify if this is a configuration issue or a hook timing issue
- **Testing:** Enable WP_DEBUG and check debug.log for "Form submitted - webhooks should fire now" message
- **Next Steps:** If form submission logs appear but webhook modification logs don't, the issue is with webhook feed configuration in Gravity Forms

---

## v1.4.0-dev (2025-10-23)

### Added
- Field filtering configuration with 4 modes:
  - All Fields (default, maintains backward compatibility)
  - Admin Labels Only (send only fields with admin labels set)
  - Whitelist (send only specified fields)
  - Blacklist (send all except specified fields)
- Empty value filtering with required fields support
- Comprehensive debug logging (activated with WP_DEBUG)
- Settings page for configuration management (Webhook Manager → Field Configuration)
- Field filtering statistics tracking and logging
- Configuration persistence via WordPress options API

### Security
- Fixed CSRF vulnerability in config save handler with capability checks
- Fixed XSS vulnerability in debug output with proper escaping
- Added capability checks to all admin action handlers (resend webhooks, config save)
- Added input validation for configuration mode against whitelist
- Fixed SQL injection risks in log viewer with prepared statements and esc_sql()
- Added comprehensive error handling for all GFAPI calls

### Changed
- Removed hardcoded field IDs (174, 175) for better portability
- Consistent checkbox field handling (always returns arrays)
- Use WordPress transients for admin notices (prevents headers already sent errors)
- Improved WHERE clause construction in database queries
- Enhanced error logging throughout the plugin

### Technical
- Helper methods: `should_include_field()`, `is_empty_value()`, `is_required_field()`
- Configuration stored in: `wp_options.gf_webhook_field_mapper_config`
- Debug logging respects WP_DEBUG constant
- Proper use of WordPress sanitization functions
- Comprehensive PHPDoc blocks

### Backward Compatibility
- ✅ Default mode is "All Fields" - behaves exactly like v1.3.0
- ✅ All existing webhook configurations continue to work
- ✅ No database migrations required
- ✅ Automatic activation hook creates log table if needed

---

## Upgrade Path

### From v1.3.0 to v1.4.0-dev.2
1. Deactivate v1.3.0
2. Delete v1.3.0 plugin files
3. Upload and activate v1.4.0-dev.2
4. Go to Webhook Manager → Field Configuration
5. Configure your desired field filtering (or leave as "All Fields" for v1.3.0 behavior)

### Configuration After Upgrade
- Default configuration maintains v1.3.0 behavior (all fields, including empty)
- No action required if you want to keep current behavior
- Visit Field Configuration page to enable new filtering features

---

## Known Issues

### v1.4.0-dev.2
- Webhooks may not fire on initial form submission (under investigation)
- Workaround: Webhooks can be manually resent from Webhook Manager
- Cause: Potentially a webhook feed configuration issue or hook timing
- Diagnosis: Check debug.log for "Form submitted" vs "Webhook data modification" messages

### v1.4.0-dev
- Same as above

---

## Testing Notes

### Debug Log Messages to Look For

**Successful Flow:**
```
[GF Webhook Field Mapper] Form submitted - webhooks should fire now
[GF Webhook Field Mapper] Webhook data modification started
[GF Webhook Field Mapper] Webhook data modification completed
[GF Webhook Field Mapper] Webhook sent
```

**Problem Indicators:**
- See "Form submitted" but NOT "Webhook data modification" = Webhook feed not configured to fire on submission
- See neither message = WP_DEBUG not enabled or form not submitting correctly
- See "Webhook data modification" but NOT "Webhook sent" = Webhook request failing

### Verification Steps
1. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php
2. Submit a test form
3. Check /wp-content/debug.log
4. Look for the log sequence above
5. If missing steps, check Gravity Forms webhook feed settings

---

## Next Release Plan

### For v1.4.0 (Production Release)
- [ ] Resolve webhook auto-firing issue
- [ ] Complete end-to-end testing with Momentum
- [ ] Verify all 16 test cases pass
- [ ] Update documentation
- [ ] Create release package
- [ ] Merge to main branch
- [ ] Tag release as v1.4.0

### Potential v1.4.1 Features
- Form-specific field configurations
- Export/import configuration
- Bulk field management UI
- Webhook testing tool
- Field mapping preview
