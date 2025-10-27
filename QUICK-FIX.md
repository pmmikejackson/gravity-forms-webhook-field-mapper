# Quick Fix: Webhooks Not Firing Automatically

## Problem Identified

Your debug logs show: `[event_type] => NOT SET`

This means the webhook feeds don't have an event type configured, which prevents them from firing automatically even though:
- ✅ The Webhooks Add-On is working
- ✅ Manual resend works perfectly
- ✅ The webhook endpoints are reachable

## Automatic Solution (Recommended)

### Use the Built-In Fix Tool:

1. **Go to:** WordPress Admin → **Webhook Manager** → **Troubleshooting**

2. **Look for the yellow box:** "⚠️ Quick Fix: Missing Event Types"

3. **Click:** "🔧 Fix Missing Event Types" button

4. **Confirm** the action when prompted

5. **Done!** All webhook feeds will now have `event = "form_submission"` set

6. **Test:** Submit your form again - webhooks should now fire automatically

This is the fastest and safest way to fix the issue.

## Manual Solution (If UI Option Available)

If the event type field is available in your Webhooks Add-On UI:

1. **WordPress Admin** → **Forms** → Select your form → **Settings** → **Webhooks**

2. **Find your webhook feed** (e.g., "Guard Momentum Webhook")

3. **Click "Edit"** on the webhook

4. **Look for the "Event" dropdown** - This is usually near the top of the feed settings

5. **Select:** `form_submission` or **"Form is submitted"**
   - ⚠️ NOT "Payment completed" or other events (unless you specifically need those)

6. **Click "Save Settings"** or "Update Feed"

7. **Test:** Submit your form again

**Note:** Some versions of the Webhooks Add-On don't show this field in the UI, which is why the automatic fix tool was created.

## Why This Fixes It

- **Manual Resend:** Bypasses event checks and sends immediately
- **Automatic Submission:** Gravity Forms checks if the webhook event matches the current action
  - If `event => not set`, the webhook is **skipped**
  - If `event => form_submission`, the webhook **fires on form submission**

## Verify the Fix

After setting the event type:

1. **Clear your debug log** (or note the current time)
2. **Submit a test form**
3. **Check the debug log** at `wp-content/debug.log`
4. **Look for:**
   ```
   [GF Webhook Field Mapper] ========== WEBHOOK FEEDS PRE-PROCESSING (AUTOMATIC) ==========
   [GF Webhook Field Mapper] event_type => form_submission
   [GF Webhook Field Mapper] will_execute => YES - This feed should fire
   ```

## If It Still Doesn't Work

Check these in order:

1. **Is the feed ACTIVE?**
   - Edit the webhook feed
   - Ensure the "Active" checkbox/toggle is enabled

2. **Conditional Logic met?**
   - If conditional logic is enabled, ensure your test submission meets all conditions
   - Or temporarily disable conditional logic to test

3. **Check the Troubleshooting Page:**
   - WordPress Admin → Webhook Manager → Troubleshooting
   - Look for red ✗ or orange ⚠ status indicators

## Expected Event Types

| Event Type | When It Fires |
|------------|---------------|
| `form_submission` | Immediately when form is submitted ✓ **USE THIS** |
| `form_payment_completed` | Only after a successful payment (requires payment gateway) |
| `form_payment_failed` | Only when a payment fails |

For standard forms without payments, always use **`form_submission`**.

---

**Updated:** 2025-10-27
**Branch:** troubleshoot/webhook-auto-submission
