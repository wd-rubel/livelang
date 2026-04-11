=== LiveLang - Smart Visual Translator ===
Contributors: papanbiswasbd
Tags: translation, visual editor, frontend editor, multilingual, language switcher
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inline visual translator for WordPress with multi-language support, SEO-friendly URLs, and high-performance JSON caching.

== Description ==

LiveLang is a smart visual translator for WordPress that allows you to translate or modify any text directly from the frontend.

Enable Translate Mode → click any text → edit → save.

No file editing. No searching for strings. No .po/.mo files. No duplicate pages.

[youtube https://www.youtube.com/watch?v=x_pem4ZeGWw]

🚀 Version 1.1.0 – Multi-Language & Performance Upgrade

LiveLang now includes structured multi-language support with SEO-friendly URL architecture and a built-in language switcher.

You can translate multiple languages within the same page without creating separate pages. One page, multiple languages — clean and efficient.

🚀 High-Performance JSON Cache System

To ensure optimal speed, LiveLang now uses a JSON-based cache system.  
Translations are cached after the first load, reducing repeated database queries and improving frontend performance.

Benefits:
- Faster page loading
- Reduced database queries
- Smooth multilingual experience
- Optimized performance at scale

== Key Features ==

- Visual inline text editing
- Full multi-language support
- Translate multiple languages on the same page
- Page-based & global translation modes
- SEO-friendly language URL structure (example: domain.com/bn/slug)
- Built-in language switcher
- Language switcher shortcode **[livelang_language_switcher]**
- Shortcode usable in posts, pages, menus, and PHP theme files
- Country flags for each language
- JSON-based translation caching system
- Works with any theme, builder, or plugins
- Lightweight & optimized
- No .po/.mo files required
- Update-proof (translations stored in the database)

Free version allows up to 3 languages. [Pro Version](https://livelang.pro/pricing/) unlocks unlimited languages.

LiveLang is ideal for developers, agencies, and website owners who want full translation control without complexity.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/livelang` or install it from the WordPress plugins screen.
2. Activate the plugin.
3. Go to Settings → LiveLang Settings to configure languages.
4. Add the language switcher via shortcode, menu, or PHP.
5. Visit the frontend and click the “Translate” button to begin editing.

== Frequently Asked Questions ==

= How do I use LiveLang? =
Go to your site frontend, click the “Translate” button, click any text, edit, and save.

= How many languages can I add? =
Free version supports up to 3 languages.

= Do I need to create separate pages per language? =
No. Multiple languages can be managed within the same page.

= Does it support SEO-friendly URLs? =
Yes. Language URLs follow this format: domain.com/lang/slug.

= How does the language switcher work? =
Use the shortcode inside posts, pages, menus, or inside theme files using do_shortcode().

= Will translations be lost after updates? =
No. All translations are safely stored in the database.

= Does it affect website performance? =
No. The JSON caching system minimizes database queries and keeps performance optimized.

== Screenshots ==

1. LiveLang Translate Mode active on the frontend.
2. Inline text editing experience.
3. Multi-language editing on the same page.
4. Language switcher with country flags.
5. SEO-friendly language URL structure.
6. Global vs page-based translation selection.

== Changelog ==

= 1.0.2 =
* Made compatible for pro version
* Fixed REST API URL issue for sub directory
* Fixed Number filtering issue

= 1.0.1 =
* Added full multi-language support.
* Added SEO-friendly language URL structure (domain.com/lang/slug).
* Added built-in language switcher.
* Added language switcher shortcode.
* Added shortcode support for menus and PHP theme files.
* Added country flags for each language.
* Added high-performance JSON translation cache system.
* Improved frontend UX/UI performance.
* Free version limited to 3 languages.
* Fixed bugs from previous version.

= 1.0.0 =
* Initial release.
* Added visual inline translation editor.
* Added page-based and global translation modes.
* WooCommerce compatibility.
* Database-based translation storage.