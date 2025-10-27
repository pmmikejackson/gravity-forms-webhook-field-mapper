# Troubleshooting Webhook Auto-Submission Issues

If webhooks are not firing automatically when forms are submitted, use this guide to diagnose and resolve the issue.

## Quick Diagnostic Tool

**New in v1.4.3:** Navigate to **WordPress Admin → Webhook Manager → Troubleshooting** to run automated diagnostics.

This page will check:
- Gravity Forms installation
- Webhooks Add-On status
- Active webhook configurations
- Conditional logic settings
- Debug logging status

## Common Issues and Solutions

### 1. Webhook Feed is Inactive

**Symptom:** Webhooks don't fire at all on form submission.

**Solution:**
1. Go to WordPress Admin → Forms
2. Select your form → Settings → Webhooks
3. Find the webhook feed(s)
4. Ensure the toggle/checkbox for "Active" is **enabled**
5. Save the form

### 2. Webhooks Add-On Not Installed/Active

**Symptom:** No webhook functionality available in Gravity Forms.

**Solution:**
1. Log into your [GravityForms.com account](https://www.gravityforms.com/)
2. Download the Webhooks Add-On
3. Install and activate it in WordPress → Plugins
4. Verify activation in WordPress Admin → Forms → Settings → Add-Ons

### 3. Conditional Logic Not Met

**Symptom:** Webhooks only fire sometimes, not on every submission.

**Solution:**
1. Go to WordPress Admin → Forms → Select your form → Settings → Webhooks
2. Edit the webhook feed
3. Check if "Conditional Logic" is enabled
4. Review the conditions (e.g., "Send this webhook only if...")
5. Test with a form submission that meets ALL conditions
6. Consider disabling conditional logic temporarily to test

### 4. Wrong Event Type Selected

**Symptom:** Webhooks don't fire on form submission.

**Solution:**
1. Edit the webhook feed in Forms → Settings → Webhooks
2. Check the "Event" dropdown
3. For standard forms, ensure it's set to **"Form is submitted"**
4. Only use payment-related events if you have payment integrations configured

### 5. Invalid or Missing Webhook URL

**Symptom:** Webhook feed is active but nothing happens.

**Solution:**
1. Edit the webhook feed
2. Verify the "Request URL" field has a valid URL
3. Test the URL in a tool like [webhook.site](https://webhook.site) or [requestbin.com](https://requestbin.com)

## Enable Debug Logging

To see detailed webhook processing logs:

### Step 1: Enable WP_DEBUG

Edit `wp-config.php` and add these lines (before "That's all, stop editing!"):

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Submit a Test Form

Submit your form with webhooks configured.

### Step 3: Check Debug Log

1. Access your WordPress site via FTP or file manager
2. Navigate to `wp-content/debug.log`
3. Search for lines starting with `[GF Webhook Field Mapper]`

### What to Look For in Logs

The plugin logs these key events:

```
========== FORM SUBMISSION DETECTED ==========
Form submitted - webhooks should fire now
========== WEBHOOK FEEDS PRE-PROCESSING ==========
Webhooks Add-On pre-processing feeds
Processing webhook feed
BEFORE TRANSFORMATION - Original entry data
AFTER TRANSFORMATION - Mapped webhook data
```

**If you see:**
- "No webhook feeds configured" → Add webhooks to your form
- "All webhook feeds are INACTIVE" → Activate your webhook feeds
- "CRITICAL: No active feeds will be processed" → Check feed activation status
- No logs at all → WP_DEBUG may not be enabled, or the plugin isn't active

## Manual Testing

Test webhook sending manually to isolate the issue:

1. Go to **WordPress Admin → Webhook Manager → Resend Entries**
2. Select your form from the dropdown
3. Select one or more entries
4. Choose the webhook(s) to resend to
5. Click "Resend Selected Entries"

**If manual resend works but auto-submission doesn't:**
- Check conditional logic on the webhook feed
- Verify the webhook feed event type is "Form is submitted"
- Check if the form has multiple pages/confirmation pages that might affect timing

## Verify Webhook Feed Configuration

### Check Feed Settings

1. Navigate to Forms → Your Form → Settings → Webhooks
2. For each webhook feed, verify:
   - ✓ Feed is **Active**
   - ✓ **Request URL** is valid and accessible
   - ✓ **Event** is "Form is submitted" (for standard forms)
   - ✓ **Conditional Logic** is either disabled or conditions are met
   - ✓ **Request Format** is typically "JSON" (recommended)

### Test the Webhook URL

Before relying on form submissions, test the webhook URL:

1. Go to [webhook.site](https://webhook.site) or [requestbin.com](https://requestbin.com)
2. Copy the unique URL provided
3. Set this as your webhook URL in the feed settings
4. Submit a test form or use manual resend
5. Check the webhook testing site for received data

## Advanced Troubleshooting

### Check WordPress Action Hooks

The plugin hooks into these Gravity Forms actions:
- `gform_after_submission` - Logs form submissions
- `gform_gravityformswebhooks_pre_process_feeds` - Logs webhook feed processing
- `gform_webhooks_request_data` - Modifies webhook payload data

If none of these fire, there may be a conflict with another plugin or theme.

### Check for Plugin Conflicts

1. Deactivate all plugins except Gravity Forms, Webhooks Add-On, and this plugin
2. Test webhook submission
3. If it works, reactivate plugins one by one to find the conflict

### Check for Theme Conflicts

1. Switch to a default WordPress theme (Twenty Twenty-Four, etc.)
2. Test webhook submission
3. If it works, your theme may be interfering with form submissions

## Getting Help

If you've tried all the above steps and webhooks still don't fire automatically:

1. **Check the Troubleshooting Page:** WordPress Admin → Webhook Manager → Troubleshooting
2. **Review Debug Logs:** Look for `[GF Webhook Field Mapper]` entries in `wp-content/debug.log`
3. **Test Manual Resend:** Use the Webhook Manager to manually resend an entry
4. **Check Gravity Forms Documentation:** [Webhooks Add-On Docs](https://docs.gravityforms.com/category/add-ons-gravity-forms/webhooks-add-on/)
5. **Contact Support:** Provide debug logs and troubleshooting page screenshots

## Version Information

This troubleshooting guide is for **v1.4.3** and later, which includes:
- Enhanced debug logging with detailed webhook processing info
- Built-in Troubleshooting page in WordPress admin
- Conditional logic detection and logging
- Webhook feed activation status monitoring

If you're on an earlier version, update to v1.4.3 or later for these diagnostic features.
