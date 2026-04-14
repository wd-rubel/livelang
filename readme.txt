=== LiveLang – Smart Multilingual Visual Translator ===
Contributors: papanbiswasbd
Tags: translator, visual translator, translation, multilanguage, visual editor
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inline visual translator for WordPress with multi-language support, SEO-friendly URLs, and high-performance JSON caching.

== Description ==

LiveLang is a powerful visual translation plugin for WordPress that lets you translate or modify any text directly from the frontend — instantly.

Enable Translate Mode → click any text → edit → save.

No file editing. No searching for strings. No .po/.mo files. No duplicate pages.

[youtube https://www.youtube.com/watch?v=x_pem4ZeGWw]

🚀 Multi-Language System (SEO Optimized)

LiveLang provides a structured multi-language system with SEO-friendly URLs and built-in language switching.

You can manage multiple languages within a single page — no need to create duplicate pages or manage complex translation files.

Example URL structure:
domain.com/en/page  
domain.com/bn/page

Clean, scalable, and SEO-friendly.

🚀 High-Performance JSON Cache System

LiveLang uses a smart JSON-based caching system to deliver fast performance.

Translations are cached after the first load, significantly reducing database queries and improving page speed.

Benefits:
- Faster page loading
- Reduced database queries
- Smooth multilingual experience
- Scalable performance for large websites

== Key Features ==

- Visual inline text editing (frontend)
- Full multi-language support
- Translate multiple languages within the same page
- Page-based & global translation modes
- SEO-friendly language URL structure
- Built-in language switcher
- Language switcher shortcode: [livelang_language_switcher]
- Shortcode support for posts, pages, menus, and PHP (do_shortcode)
- Country flags for each language
- Automatic lang attribute support (SEO & accessibility)
- JSON-based translation caching system
- Works with any theme, builder, or plugin
- WooCommerce compatible
- Lightweight and optimized
- No .po/.mo files required
- Update-proof (translations stored safely in database)

Free version allows up to 3 languages.  
Pro version unlocks unlimited languages: https://livelang.pro/pricing/

LiveLang is ideal for developers, agencies, and website owners who want full control over translations without complexity.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/livelang` or install via WordPress Plugins screen.
2. Activate the plugin.
3. Go to Settings → LiveLang Settings to configure languages.
4. Add the language switcher using shortcode, menu, or PHP.
5. Visit your frontend and click the “Translate” button to start editing.

== Frequently Asked Questions ==

= How do I use LiveLang? =
Enable Translate Mode from the frontend, click any text, edit it, and save instantly.

= How many languages can I use? =
The free version supports up to 3 languages. Pro version supports unlimited languages.

= Do I need separate pages for each language? =
No. LiveLang allows multiple languages within the same page.

= Is it SEO-friendly? =
Yes. LiveLang generates clean language-based URLs (example: /en/, /bn/) and adds proper lang attributes to HTML.

= How does the language switcher work? =
Use the shortcode [livelang_language_switcher] anywhere — posts, pages, menus, or PHP files using do_shortcode().

= Will translations be lost after updates? =
No. All translations are stored safely in the database and remain intact after updates.

= Does it slow down my website? =
No. The built-in JSON caching system minimizes database queries and ensures high performance.

== Screenshots ==

1. LiveLang Translate Mode on frontend
2. Inline text editing interface
3. Multi-language editing within the same page
4. Language switcher with flags
5. SEO-friendly URL structure
6. Global vs page-based translation options

== Changelog ==

= 1.0.4 =
* Fixed 404 issues for multi language

= 1.0.3 =
* Remove language code from url for default language

= 1.0.2 =
* Added full compatibility with Pro version (supports unlimited languages)
* Improved multi-language system stability and consistency
* Fixed number filtering issue in translations
* Added dynamic lang attribute (e.g., lang="en-US") to HTML tag for better SEO & accessibility
* Fixed inconsistencies between global and page-based translations
* Resolved REST API URL issues for subdirectory WordPress installations
* Improved translation handling logic
* Fixed multiple minor bugs and improved overall performance

= 1.0.1 =
* Added full multi-language support
* Added SEO-friendly language URL structure (domain.com/lang/slug)
* Added built-in language switcher
* Added language switcher shortcode
* Added shortcode support for menus and PHP theme files
* Added country flags for each language
* Added high-performance JSON translation cache system
* Improved frontend UX/UI performance
* Free version limited to 3 languages
* Fixed bugs from previous version

= 1.0.0 =
* Initial release
* Added visual inline translation editor
* Added page-based and global translation modes
* WooCommerce compatibility
* Database-based translation storage