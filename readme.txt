=== WPCalibrate Calendar Sync ===
Contributors: wpcalibrate
Tags: google calendar, calendar sync, events, google oauth, agenda
Requires at least: 6.0
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronize Google Calendar events with WordPress via secure OAuth 2.0. Features two-way sync, Gutenberg block, shortcode, and push notifications.

== Description ==

**WPCalibrate Calendar Sync** provides two-way synchronization between your Google Calendar and WordPress website. Built on the official Google Calendar API v3 and Google OAuth 2.0 web-server flow, it securely synchronizes events directly with WordPress.

Events are stored locally in an optimized database table for performance, ensuring your site visitors never wait on third-party API calls during page loads.

### Key Features

* **Official Google OAuth 2.0**: Secure authentication flow with automated token refresh and state validation.
* **Encrypted Secrets at Rest**: OAuth refresh tokens and client secrets are encrypted using Sodium or OpenSSL (AES-256-GCM). Plaintext secret storage is prohibited.
* **Flexible Sync Directions**: Configure each calendar as Two-way, Google to WordPress (read-only), or WordPress to Google.
* **Multiple Calendars**: Select and synchronize one or multiple Google Calendars from your connected account.
* **Incremental Synchronization**: Utilizes Google sync tokens for incremental syncs, processing only changed and deleted events.
* **Automatic 410 Recovery**: Handles expired Google sync tokens with controlled full resynchronization.
* **Google Push Notifications**: Real-time push updates via secure REST webhook channels with cryptographic token validation.
* **Scheduled WP-Cron Recovery**: Configurable background recovery sync ensures no missed events.
* **Frontend Displays**: Clean, responsive Agenda (list) and Month views.
* **Gutenberg Block & Shortcode**: Dynamic server-rendered block and shortcode `[wpcalibrate_calendar]` usable in any block editor or page builder.
* **Admin Event Manager**: Filter, search, create, edit, and delete events directly from your WordPress dashboard.
* **Zero Secrets in Logs**: Leveled diagnostic logger with automatic sanitization and redaction of credentials.

== External Service Disclosure ==

This plugin connects to external services provided by Google LLC:
* **Google Calendar API (v3)**: Used for synchronizing events, retrieving calendar metadata, and receiving push notification updates.
* **Google OAuth 2.0**: Used for authenticating administrator access to Google Calendar.
* **Privacy Policy**: https://policies.google.com/privacy
* **Terms of Service**: https://policies.google.com/terms

Synchronization occurs only after an authorized administrator provides OAuth credentials and explicitly connects a Google account. No attendee or personal visitor data is synchronized or sent to external servers.

== Installation ==

1. Upload the `wpcalibrate-calendar-sync` folder to your `/wp-content/plugins/` directory, or install the ZIP file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WPCalibrate > Calendar Sync > Connection**.
4. Enter your Google Cloud Client ID and Client Secret, and copy the provided Authorized Redirect URI into your Google Cloud Console.
5. Click **Connect Google Account** to authorize calendar access.
6. Under the **Calendars** tab, select which calendars to synchronize and configure their synchronization direction.
7. Use the Gutenberg block or the shortcode `[wpcalibrate_calendar]` to display events on your site.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
No. WooCommerce is completely optional. The plugin coexists safely with WooCommerce without imposing any dependencies.

= How are client secrets and tokens protected? =
Secrets are encrypted at rest using Sodium (`crypto_secretbox`) or OpenSSL authenticated AES-256-GCM. Encryption keys are derived from installation-specific WordPress salts. If neither encryption engine is available on the server, OAuth connection is prevented to protect your security.

= What happens when a sync token expires? =
Google sync tokens can expire (HTTP 410 Gone). WPCalibrate Calendar Sync automatically detects this condition, resets the invalid token, and executes a full resync without data loss.

= Can I use Elementor or other page builders? =
Yes. You can use the shortcode `[wpcalibrate_calendar]` in Elementor Shortcode widgets or any standard text/HTML module.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Google OAuth 2.0 web-server flow.
* Encrypted secret storage with Sodium / OpenSSL AES-256-GCM.
* Two-way, Google-to-WP, and WP-to-Google sync directions.
* Incremental sync with HTTP 410 invalid token recovery.
* Webhook push notification channels and WP-Cron recovery scheduler.
* Frontend Agenda and Month calendar renderers.
* Gutenberg block and `[wpcalibrate_calendar]` shortcode.
* Admin event manager with search, filters, and pagination.
