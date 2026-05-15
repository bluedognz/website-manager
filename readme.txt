=== Blue Dog Website Manager ===
Contributors: bluedogdigital
Tags: utility, admin, media, comments, performance
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.10
License: GPLv2 or later

A collection of utility modules for managing common WordPress site settings — enable or disable features on a per-site basis.

== Description ==

Blue Dog Website Manager provides a clean admin interface to enable or disable commonly needed WordPress features without manual code editing.

Current modules:
* Replace Dashboard with Beaver Builder Template
* Disable Comments
* Disable Automatic Additional Image Sizes
* Enable SVG Upload Support
* Disable Admin Email Verification Check
* Auto-Set Image Metadata on Upload
* Microthemer: Retain Styles When Deactivated

== Auto-updates via GitHub ==

This plugin self-updates from its GitHub repository. No WordPress.org account needed.

To avoid GitHub API rate limits across many sites, add a personal access token to wp-config.php:

  define( 'WEBSITE_MANAGER_GH_TOKEN', 'ghp_your_token_here' );

A classic token with no scopes (read-only public repo access) is sufficient for public repositories.

== Export / Import Settings ==

Settings can be exported as a base64 string and imported into another site via the Import panel on either the Modules or Settings tab.

== Changelog ==

= 1.0.10 =
* Fixed: Plugin description now displays correctly in WP Plugins list under white label mode

= 1.0.9 =
* Fixed: Plugin now always appears under Tools menu, never in main admin sidebar (including white label mode)
* Fixed: Plugin name now correctly renamed in WP Plugins list when white label is active

= 1.0.8 =
* Changed: Removed plugin from main admin sidebar — accessible via Tools only
* Added: "Open with Page Builder" link next to active BB dashboard template
* Changed: Updated plugin description

= 1.0.7 =
* Added: Disable Admin Email Verification Check module
* Added: Auto-Set Image Metadata on Upload module
* Added: Microthemer: Retain Styles When Deactivated module
* Fixed: Import/Export now works on the Settings tab as well as Modules
* Fixed: Plugin layout now centred correctly
* Fixed: Settings page save no longer wipes the BB dashboard template selection

= 1.0.6 =
* Fixed: Settings page CSS not loading (hook name derived from menu title, not slug)
* Fixed: BB dashboard widget not rendering — resolved closed postbox state on first load
* Fixed: Dashboard widget now forces full-width single-column layout
* Changed: Architectural fix to card/gear/toggle HTML structure
* Changed: Settings page styling reworked to match Modules page
