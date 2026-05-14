=== Website Manager ===
Contributors: bluedogdigital
Tags: utility, admin, functions, toggles, performance, security
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A collection of toggleable WordPress utility features for agency use.

== Description ==

Website Manager provides a clean admin interface to toggle commonly needed WordPress code snippets on or off — no manual code editing required.

Features are grouped into categories:
* Admin – dashboard & menu tweaks
* Security – XML-RPC, file editing, version exposure
* Performance – emoji removal, query strings, RSS, JS deferral
* Content – comments, excerpts, external links
* Media – image sizes, SVG support

== Auto-updates via GitHub ==

This plugin self-updates from its GitHub repository. No WordPress.org account needed.

To avoid GitHub API rate limits across many sites, add a personal access token to wp-config.php:

  define( 'WEBSITE_MANAGER_GH_TOKEN', 'ghp_your_token_here' );

A classic token with no scopes (read-only public repo access) is sufficient for public repositories.

== Export / Import Settings ==

Settings can be exported as a base64 string and pasted into another site via the Import panel on the settings page.

== Changelog ==

= 1.0.0 =
* Initial release.
