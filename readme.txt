=== Z Authorized Downloads ===
Contributors: zodannl, martenmoolenaar
Tags: protected downloads, authorization, files, attachments, downloads
Requires at least: 5.5
Tested up to: 6.8
Version: 1.2.3
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protect documents from unauthorized download.

== Description ==
This plugin allows site admins to protect specific attachment file types (e.g. Pdf, Doc(x)) from direct access. Instead, the files are served through WordPress, allowing you to check if a user is logged in or has the required permissions before granting access.

**Features:**
* Adds a checkbox to media attachments to mark them as "Authorized only".
* Creates an internal page and rewrite rules to intercept requests to protected file types.
* Checks user login status (or other custom logic you add) before serving files.
* Provides a settings page to specify which file types should be protected.


== Installation ==
1. Upload the plugin files to `/wp-content/plugins/z-authorized-downloads/` or install via the Plugins screen.
2. Activate the plugin through the 'Plugins' screen.
3. Go to **Settings → Authorized Downloads** to set which file types should be protected and which roles can download protected files
4. Mark attachments as "Authorized only" in the Media Library to restrict access.
5. Optionally, override the default permitted roles per attachment in the Media Library.


== Frequently Asked Questions ==

= Can I customize the authorization logic? =
Yes. The plugin checks if the user is logged in by default, but you can extend `handle_protected_request()` to add role checks or other conditions.

= What happens to unprotected files? =
Files that are not marked as "Authorized only" will still be directly accessible by URL.

= Do I need to manually edit .htaccess? =
No. The plugin will manage the `.htaccess` rules for you when you save settings.

= Do you have plans to improve the plugin? =
We currently have on our roadmap:
* Adding information to the media overview screen.
* Make the plugin pluggable.
* Adding a custom capability to manage which users can edit settings (both general and per file).
* Adding more translations.

If you have a feature suggestion, send us an email at [plugins@zodan.nl](plugins@zodan.nl).


== Upgrade Notice ==
= 1.1.1 =
You must visit the settings page after upgrading to ensure `.htaccess` rules are regenerated with your chosen file types.


== Screenshots ==
1. Settings page where you define protected file types and user roles.
2. Media modal showing the "Authorized only" checkbox.


== Changelog ==

= 1.2.3 =
* Added help text on the settings page.
* Added some screenshots.

= 1.2.2 =
* Small adjustments to the rendering of the meta fields to have both versions (meta-box and modal) use the same code.

= 1.2.1 =
* Fixed Bug in handling the meta-box update function where changes were not saved correctly.

= 1.2.0 =
* Renamed plugin to Z Authorized Downloads.
* Added default Allowed roles to download files.
* Added file-specific Allowed roles to the media file meta-box.

= 1.1.1 =
* Added settings page for specifying protected file types.
* Improved security: uses WP_Filesystem for file reads.
* Added caching to database lookups for performance.

= 1.0.0 =
* Initial release with meta box and basic rewrite rules.

== License ==
This plugin is licensed under GPLv2 or later.
