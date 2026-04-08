=== Redirect Gateway Manager ===
Contributors: darkshala321
Donate link: https://quanbui.net
Tags: redirect, gateway, ads, monetization, captcha
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive plugin to manage redirect links, create countdown gateway ads, enforce bot protection, and track detailed click logs for WordPress.

== Description ==

**WP Redirect Gateway & Ads Monetization** is an all-in-one solution to help you manage shortened links, create countdown gateways to increase ad impressions, and secure your links against bots and spam.

Designed with high performance and clean code in mind, this plugin provides all the essential features for Webmasters and MMO (Make Money Online) practitioners.

### Key Features:
* **Countdown Gateway Pages:** Require users to wait for a specific duration before revealing the destination link.
* **Ad Rotation (Round-Robin):** Users must click and view affiliate ad links sequentially to proceed.
* **Maximum Security:** Integrated top-tier bot protection (Google reCAPTCHA v3 & Cloudflare Turnstile). Supports strict server-side Password Protection for specific links.
* **Smart UX Management:** Automatically pauses the countdown timer if the user switches tabs, forcing them to focus on your ad page.
* **Detailed Analytics (Logs):** Track click history, IP addresses, Referrers, Sub-IDs, UTM Parameters, and User Agents.
* **Import/Export Data:** Easily export/import configurations and download Link/Log data as Excel (CSV) files.
* **Auto-Backup System:** Automatically back up your entire plugin database and settings on a scheduled basis (WP Cronjob).
* **100% i18n Ready:** Fully translatable into any language.

== External services ==

This plugin relies on third-party external services to provide anti-bot protection (CAPTCHA) to prevent spam clicks and abuse. Users can choose between two providers in the settings:

1. Google reCAPTCHA v3
- Purpose: Used to verify if the visitor is human without interrupting their experience.
- Data Sent: It may send the user's IP address, browser information, and interactions to Google's servers.
- Privacy Policy: https://policies.google.com/privacy
- Terms of Service: https://policies.google.com/terms

2. Cloudflare Turnstile
- Purpose: Used as a privacy-friendly alternative to verify visitors without visual puzzles.
- Data Sent: It sends necessary browser signals and session data to Cloudflare.
- Privacy Policy: https://www.cloudflare.com/privacypolicy/
- Terms of Service: https://www.cloudflare.com/website-terms/

== Installation ==

1. Upload the plugin folder (or `.zip` file) to the `/wp-content/plugins/` directory on your hosting, or install it directly via the **Plugins > Add New** menu in WordPress.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the **Gateway Redirect** menu on the left sidebar (Admin Dashboard) to get started.
4. **⚠️ VERY IMPORTANT:** Make sure to add the URL of the page containing the countdown shortcode to the "Never Cache" (Exclude from cache) list in your caching plugins (e.g., WP Rocket, LiteSpeed Cache, W3 Total Cache).

== Frequently Asked Questions ==

= How do I create a Gateway page? =
Go to the "Shortcodes" menu and create a new countdown configuration.
The plugin will generate a shortcode for you (e.g., `[wprg_gateway id="xyz"]`).
Create a new Page in WordPress and paste this shortcode into the content area.

= How does the 'Require active tab' feature work? =
When this feature is enabled in Settings, the countdown timer will immediately pause if a user switches to another browser tab or minimizes the window. The timer only resumes when the user returns to your exact page.

= Can I password-protect my links? =
Yes! When creating a new link, you can enter a password in the "Password Protection" field. Visitors must enter this exact password to initiate the countdown process. The system uses secure cookies and strict server-side validation to prevent bypassing.

= How does the Auto-Backup work? =
The plugin utilizes the built-in WordPress Cronjob system. It packages all your Links, Logs, and Settings into a single JSON file stored securely on your server. You can also define a maximum number of retained backups to save disk space.

== Screenshots ==

1. Intuitive Dashboard Analytics.
2. Link Management list (Supports quick copy and tag filtering).
3. Click history table (Logs) with detailed IP, Device, and Referrer tracking.
4. Customizable Round-Robin Ads & Password Protection settings.

== Changelog ==

= 1.0.13 =
* Major Security Upgrade: Implemented Server-side Link Password validation to prevent F12 (Inspect Element) bypass attempts.
* CSS Optimization: Separated independent CSS files for Front-end and Admin areas.
* 100% i18n Integration: Fully implemented translation functions for both PHP and Javascript files.
* Bug Fix: Fixed the Auto-Retry logic displaying "Retrying" when encountering an incorrect password.

= 1.0.0 =
* Initial release with all core features.