=== Gravity Forms Webhook Field Mapper ===
Contributors: mikejackson, claude
Tags: gravity forms, webhook, field mapping, api
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Maps Gravity Forms webhook data to use field names instead of field IDs.

== Description ==

This plugin modifies the Gravity Forms webhook data to send field names along with the data instead of just numeric field IDs. This makes it much easier to work with the webhook data in external systems.

Features:
* Automatically converts field IDs to field names in webhook data
* Handles all Gravity Forms field types including complex fields (name, address, etc.)
* Preserves form metadata
* Compatible with Gravity Forms Webhooks Add-On

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gravity-forms-webhook-field-mapper` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically modify all Gravity Forms webhook requests

== Requirements ==

* Gravity Forms plugin must be installed and activated
* Gravity Forms Webhooks Add-On (for webhook functionality)

== Changelog ==

= 1.0.0 =
* Initial release