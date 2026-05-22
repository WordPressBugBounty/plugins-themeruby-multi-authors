=== ThemeRuby Multi Authors – Assign Multiple Writers to Posts ===
Contributors: themeruby
Tags: multiple authors, co-authors, guest authors, team, byline
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A lightweight plugin that allows you to assign multiple writers to posts, fast and easy to use.

== Description ==

**ThemeRuby Multi Authors** is a plugin that enables assigning multiple writers to posts and custom post types. Perfect for collaborative blogging, editorial teams, and news websites.

It is specifically designed for **news sites, magazines, agencies, and content teams** who need flexible author attribution with zero performance impact. SEO-optimized and featuring an easy-to-use editor panel.

= 🎯 Why Choose ThemeRuby Multi Authors? =

**Powerful Features**
* **Unlimited Authors:** Assign as many writers as needed per post.
* **Author Page:** Automatically filters to show all multi-author posts.
* **Custom Post Types:** Enable multi-authors for any post type.
* **Ready Shortcodes:** Display authors anywhere with flexible shortcodes.
* **Author Box:** Beautiful author bio boxes in single post.

**SEO Optimized & Fast Performance**
* **Popular SEO Plugins:** Full compatibility with major SEO plugin schema systems.
* **Optimized Queries:** Efficient database lookups.
* **Built-in Caching:** Smart caching reduces load.

**Developer Friendly**
* **Clean Public API:** Simple functions: `tmauthors_get_post_authors()`, `tmauthors_the_authors()`.
* **WP_Query Compatible:** Works seamlessly with `author`, `author_name`, `author__in` parameters.

= Key Features =

**Multi-Author Management**
* Unlimited co-authors per post.
* Search and select from all site users.
* Works with custom post types.
* Author archives show all posts (primary + co-authored).
* GDPR compliant.

**Display & Shortcodes**
* `[tmauthors]` - Display author names with links.
* `[tmauthors_box]` - Full author box with avatars and bios.
* `[tmauthors_count]` - Show number of authors.
* `[tmauthors_list]` - Authors as formatted list.
* `[tmauthors_avatars]` - Author avatars only.

**Developer Friendly**
* Clean API: `tmauthors_get_post_authors()`, `tmauthors_the_authors()`.
* WP_Query compatible: `author`, `author__in`, `author_name` parameters.
* Extensive hooks and filters.

= For Developers =

The plugin provides a complete developer toolkit. For further details, please refer to the documentation.

* [Documentation](https://themeruby.com/multi-authors/developer-guide/overview)

= Shortcode Usage =

Display authors anywhere using flexible shortcodes:

**Basic usage:**
`[tmauthors]`

**With custom separator:**
`[tmauthors separator=" & " before="By "]`

**Author box:**
`[tmauthors_box show_avatar="true" avatar_size="120"]`

**All options:**
`[tmauthors post_id="123" separator=", " link="true" before="By " after=""]`
`[tmauthors_box post_id="123" show_avatar="true" show_bio="true" avatar_size="120"]`

Available shortcodes: `[tmauthors]`, `[tmauthors_box]`, `[tmauthors_count]`, `[tmauthors_list]`, `[tmauthors_avatars]`.

= 🔗 Useful Links =

* [Documentation](https://themeruby.com/multi-authors/).
* [ThemeRuby Website](https://themeruby.com/).
* [Support Forum](https://wordpress.org/support/plugin/themeruby-multi-authors/).

== Installation ==

1. Go to **Plugins > Add New**.
2. Search for "ThemeRuby Multi Authors".
3. Click **Install Now** and then **Activate**.
4. Navigate to **Settings > ThemeRuby Multi Authors** to configure (optional).

**Manual Installation:**
Upload the plugin folder to `/wp-content/plugins/` via FTP and activate.

= Getting Started =

1. Edit any post (Block or Classic Editor).
2. Look for "Authors" panel in sidebar.
3. Select multiple authors from dropdown.
4. Save post.

**Display Options:**
* Use shortcodes: `[tmauthors]` or `[tmauthors_box]`.
* Use template tag: `<?php tmauthors_the_authors(); ?>`.
* Enable auto display in Settings.

== Frequently Asked Questions ==

= Will this plugin slow down my website? =

No! Built on core with no custom database tables. Built-in caching and efficient queries ensure zero performance impact.

= Does this work with Block Editor and Classic Editor? =

Yes! Fully compatible with both editors. Block Editor gets a sidebar panel, Classic Editor gets a meta box.

= Will this affect my existing posts? =

No! Original `post_author` field is preserved. You can add co-authors anytime without affecting existing content.

= Does this work with custom post types? =

Yes! Go to Settings > ThemeRuby Multi Authors and select which post types to enable.

= How do I display authors? =

* Shortcode: `[tmauthors]` or `[tmauthors_box]`.
* Template tag: `<?php tmauthors_the_authors(); ?>`.
* Auto display: Enable in Settings.

= Does this work with SEO plugins? =

Yes! Automatic Schema.org markup integration with major SEO plugins. No configuration needed.

= Will author pages show co-authored posts? =

Yes! Author pages automatically include both primary authored and co-authored posts with accurate post counts.

= Can I query posts by multiple authors? =

Yes! All standard WP_Query author parameters work: `author`, `author__in`, `author_name`, `author__not_in`.

= Is there a developer API? =

Yes! See "For Developers" section above for full list of functions, hooks, and filters.

= What happens when I delete the plugin? =

Deactivation: Multi-author data preserved. Deletion: All plugin data removed, original post_author field preserved.

== Changelog ==

= 1.3.0 =

* Improved: Static caching for post authors to reduce redundant queries
* Improved: Batch user fetching in get_post_authors() for better performance
* Compatibility: Tested with WordPress 7.0

= 1.2.0 =
* Fixed: Multi-author meta box only appeared on Posts, ignoring custom post type settings.

= 1.1.0 =
* Security: Fixed stored XSS vulnerability in shortcode attribute.

= 1.0.0 =
* Initial release.
