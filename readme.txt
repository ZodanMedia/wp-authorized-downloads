=== Z Authorized Downloads ===
Contributors: zodannl, martenmoolenaar
Tags: downloads, files, authorization, protected download
Requires at least: 5.5
Tested up to: 6.8
Version: 1.2.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protect documents from unauthorized direct download.

== Description ==
This plugin allows site admins to protect specific attachment file types (e.g., PDF, DOCX) from direct access. Instead, the files are served through WordPress, allowing you to check if a user is logged in or has the required permissions before granting access.
Adds an "Authorized only" meta field to attachments (visible in attachment edit screen and media modal) and manages a .htaccess rewrite section.


**Features:**
* Adds a checkbox to media attachments to mark them as "Authorized only".
* Creates an internal page and rewrite rules to intercept requests to protected file types.
* Checks user login status (or other custom logic you add) before serving files.
* Provides a settings page to specify which file types should be protected.


== Installation ==
1. Upload the plugin files to `/wp-content/plugins/z-authorized-downloads/` or install via the Plugins screen.
2. Activate the plugin through the 'Plugins' screen.
3. Go to **Settings → Authorized Downloads** to set which file types should be protected.
4. Mark attachments as "Authorized only" in the Media Library to restrict access.


== Frequently Asked Questions ==

= Can I customize the authorization logic? =
Yes. The plugin checks if the user is logged in by default, but you can extend `handle_protected_request()` to add role checks or other conditions.


= What happens to unprotected files? =
Files that are not marked as "Authorized only" will still be accessible directly by URL.


= Do I need to manually edit .htaccess? =
No. The plugin will manage the `.htaccess` rules for you when you save settings.


== Changelog ==
= 1.2.0 =
* Added default Allowed roles to download files
* Added file-specific Allowed roles to the media file meta-box

= 1.1.1 =
* Added settings page for specifying protected file types.
* Improved security: uses WP_Filesystem for file reads.
* Added caching to database lookups for performance.

= 1.0.0 =
* Initial release with meta box and basic rewrite rules.


== Upgrade Notice ==
= 1.1.1 =
You must visit the settings page after upgrading to ensure `.htaccess` rules are regenerated with your chosen file types.


== Screenshots ==
1. Settings page where you define protected file types.
2. Media modal showing the "Authorized only" checkbox.
3. Example access denied screen when an unauthorized user attempts to access a file.


== License ==
This plugin is licensed under GPLv2 or later.


















== Installation ==

= Install the plugin from within WordPress =

1. Visit the plugins page within your dashboard and select ‘Add New’;
1. Search for ‘Z Authorized Downloads’;
1. Activate the plugin from your Plugins page;
1. Go to ‘after activation’ below.

= Install manually =

1. Unzip the Z Authorized Downloads zip file
2. Upload the unzipped folder to the /wp-content/plugins/ directory;
3. Activate the plugin through the ‘Plugins’ menu in WordPress;
4. Go to ‘after activation’ below.

= After activation =

1. On the Plugins page in WordPress you will see a 'settings' link below the plugin name;
2. On the settings page, add the filetypes you want to be protected in a comma separated list, for example: .pdf,.doc,.docx,.zip
3. Save your settings and you’re done!

== Frequently Asked Questions ==

= Can I add additional styling? =

Yes you can. By adding custom styles in the WordPress customizer under /Appearance/Customize. The parent element of the button has the `.zLikeButton` class, the label containing the icon is styled using the `zLikeLabel` class.

= Do you have plans to improve the plugin =

Yes. We currently have on our roadmap:
* Adding translations
* Adding more features for both the button and the 'My liks list' (ordering in time, grouping by post type)
* Adding option for minifying the assets

== Screenshots ==

1. Plugin settings
2. Plugin default rendering
3. Plugin metabox: you can remove the like button for any post or content individually and even cheat with you counters with manual editing :P


== Changelog ==

= 1.1.1 =
* Added option to include/exclude not-logged-in users
* Added option to remove likes directly from My Likes List
* Refactoring

= 1.1.0 =
* Added option to include/exclude not-logged-in users
* Added option to remove likes directly from My Likes List
* Refactoring

= 0.0.5 =
* Added security improvements
* Changed the wp_json_encoded output
* Changed some function names to satisfy the plugin check

= 0.0.4 =
* Added color selection for the icons

= 0.0.3 =
* Added shortcode for a "My liked posts" overview

= 0.0.2 =
* Optimized validation, added icon selection

= 0.0.1 =
* Pre-release