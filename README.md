# WPCalibrate Calendar Sync

[![WordPress Tested](https://img.shields.io/badge/WordPress-6.0%20to%207.1.3-blue.svg)](https://wordpress.org)
[![PHP Tested](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20Strict-777bb4.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)
[![Security: Sodium / AES-256-GCM](https://img.shields.io/badge/Security-Sodium%20%2F%20AES--256--GCM-red.svg)](docs/architecture.md)
[![Automated Tests](https://img.shields.io/badge/Tests-41%20Passing%20(100%25)-brightgreen.svg)](tests/)

Enterprise-grade, two-way Google Calendar synchronization for WordPress. Store events locally in a dedicated custom table for lightning-fast frontend delivery with zero external API calls on page visits. Protect OAuth credentials with authenticated symmetric encryption (Sodium or OpenSSL AES-256-GCM), and automate synchronization via real-time push webhooks and WP-Cron recovery.

---

## Table of Contents
1. [About](#about)
2. [Key Features](#features)
3. [Documentation & Setup](#documentation--setup)
   - [System Requirements](#system-requirements)
   - [Google Cloud Platform (GCP) Configuration](#google-cloud-platform-gcp-configuration)
   - [Installation & Activation](#installation--activation)
   - [1-Click Demo Mode (Instant Evaluation)](#1-click-demo-mode-instant-evaluation)
   - [Shortcode & Gutenberg Block](#shortcode--gutenberg-block)
4. [Architecture & Security](#architecture--security)
5. [Changelog](#changelog)
6. [Support & Contributing](#support--contributing)
7. [License](#license)

---

## About

Traditional WordPress calendar plugins make live API requests to Google Calendar when public visitors load a webpage. This legacy architecture severely degrades page speed, consumes external Google API quotas, and causes website timeouts or fatal errors whenever Google or network connections experience latency.

**WPCalibrate Calendar Sync** completely decouples external API synchronization from public visitor presentation:
- **Zero Visitor API Calls:** Events are synchronized in the background and stored in an optimized, indexed custom database table (`wp_wpcs_events`).
- **Sub-Millisecond Loading:** When visitors view your calendar, events are queried directly from the local MySQL/MariaDB database in milliseconds.
- **Enterprise-Grade Security:** OAuth refresh tokens and client secrets are stored using authenticated encryption (`crypto_secretbox` or OpenSSL AES-256-GCM) with salt-derived keys.
- **Automated Two-Way Sync:** Outbound event changes in WordPress push to Google instantly, while inbound changes sync via Google push notification webhooks and background WP-Cron recovery.

---

## Features

- **Two-Way, Inbound, & Outbound Modes:** Choose between full two-way sync, Google-to-WordPress (read-only), or WordPress-to-Google on a per-calendar basis.
- **Multi-Calendar Support:** Manage multiple Google Calendars from a single connected Google account, each with custom colors and sync directions.
- **Simulated Demo Data Subsystem:** 1-click test suite to install a simulated Google OAuth 2.0 connection, 3 color-coded sample calendars, and 12 realistic relative-date events without configuring GCP credentials.
- **Encrypted Secret Storage:** Sodium (`crypto_secretbox`) with automatic authenticated OpenSSL AES-256-GCM fallback. Autoload disabled in `wp_options`.
- **Concurrency Mutex Locking:** Transient-based locks (`SyncLock` with 300-second TTL per calendar) prevent race conditions between webhooks, cron, and admin actions.
- **HTTP 410 Invalid Sync Token Self-Healing:** Automatically clears expired Google sync tokens and reconciles records without data loss.
- **Dual-Engine Frontend Calendar:**
  - **Agenda View:** Clean, chronological upcoming event list grouped by date.
  - **Month Grid View:** Interactive, accessible calendar grid with month navigation and modal event popups.
  - Fully accessible (WCAG 2.2 AA compliant, ARIA-labeled, responsive down to 320px mobile screens).
- **Gutenberg Block & Shortcode:** Embed calendars effortlessly using the block editor or `[wpcalibrate_calendar]` in any page builder.
- **Admin Event Manager:** Filter, search, and manage local synchronized events directly from the WordPress dashboard.
- **Diagnostics & Audit Logger:** Leveled logger with automated redaction of sensitive credentials and one-click JSON diagnostic export.
- **Seamless GitHub Updates:** Direct dashboard update notifications and 1-click updates when new releases are published on GitHub.

---

## Documentation & Setup

### System Requirements
- **PHP:** 8.2 or 8.3 (strictly typed with `declare(strict_types=1);`).
- **WordPress:** 6.0 or higher (tested up to WordPress 7.1.3).
- **PHP Extensions:** `ext-sodium` (recommended) or `ext-openssl`, `ext-curl`, `ext-json`, `ext-mbstring`.
- **Database:** MySQL 5.7+ or MariaDB 10.3+.

---

### Google Cloud Platform (GCP) Configuration

Follow these 4 simple steps to connect Google Calendar:

1. **Create a GCP Project:**
   - Go to [Google Cloud Console](https://console.cloud.google.com/).
   - Create a new project or select an existing one.
   - Navigate to **APIs & Services > Library**, search for **Google Calendar API**, and click **Enable**.

2. **Configure OAuth Consent Screen:**
   - Go to **APIs & Services > OAuth consent screen**.
   - Choose User Type (**External** for public sites or **Internal** for Google Workspace).
   - Fill in your App Name and Developer Contact Email.
   - Add scopes: `https://www.googleapis.com/auth/calendar` and `https://www.googleapis.com/auth/calendar.readonly`.
   - If User Type is External and in Testing status, add your Google account under **Test users**.

3. **Create OAuth 2.0 Web Client Credentials:**
   - Go to **APIs & Services > Credentials > Create Credentials > OAuth client ID**.
   - Select **Web application** as the Application type.
   - Copy the **Authorized Redirect URI** provided in **WPCalibrate Calendar Sync > Connection** (e.g. `https://yourdomain.com/wp-admin/admin.php?page=wpcalibrate-calendar-sync&tab=connection&wpcs_action=oauth_callback`) and paste it into Google.
   - Click **Create** to receive your **Client ID** and **Client Secret**.

4. **Connect in WordPress:**
   - Enter your Client ID and Client Secret in **WPCalibrate Calendar Sync > Connection** and click **Save Credentials**.
   - Click **Connect Google Account** to authenticate and authorize calendar access.

---

### Installation & Activation

1. Download `wpcalibrate-calendar-sync-1.0.0.zip` from [GitHub Releases](https://github.com/zeeshanraza-official/wpcalibrate-calendar-sync/releases).
2. In your WordPress admin, navigate to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.
5. Navigate to the new **WPCalibrate** menu in your WordPress dashboard.

---

### 1-Click Demo Mode (Instant Evaluation)

Want to evaluate the calendar layout, block, and shortcode without creating a Google Cloud Project immediately?
1. Go to **WPCalibrate Calendar Sync > Connection**.
2. Scroll to the **Testing & Evaluation: One-Click Demo Data** card.
3. Click **Load Demo Connection & Sample Data**.
4. The plugin will immediately simulate an active Google OAuth 2.0 connection, install 3 sample color-coded calendars, and populate 12 dynamic relative-date events.
5. When finished testing, click **Remove Demo Data & Reset** to return to a clean state.

---

### Shortcode & Gutenberg Block

#### Shortcode Usage
Embed calendars anywhere shortcodes are supported:

```text
[wpcalibrate_calendar]
```

#### Supported Shortcode Attributes
| Attribute | Allowed Values | Default | Description |
|---|---|---|---|
| `view` | `agenda`, `month` | `agenda` | Display layout mode |
| `calendar` | Calendar ID string | `all` | Specific Google Calendar ID or `all` |
| `limit` | Positive integer | `20` | Maximum number of upcoming events (Agenda view) |
| `show_desc` | `1`, `0`, `true`, `false` | `1` | Show event descriptions |
| `show_loc` | `1`, `0`, `true`, `false` | `1` | Show event locations |

**Examples:**
```text
[wpcalibrate_calendar view="month"]
[wpcalibrate_calendar view="agenda" limit="10" show_loc="0"]
[wpcalibrate_calendar calendar="primary" view="month"]
```

#### Gutenberg Block
Search for **WPCalibrate Calendar** in the block inserter. Use the block inspector sidebar to configure view mode, calendar filtering, and display toggles with real-time editor preview.

---

## Architecture & Security

```
+-------------------------------------------------------------+
|                  Google Calendar API v3                     |
+-------------------------------------------------------------+
         ^                                           |
         | Outbound Changes (SyncService)            | Webhook Push
         |                                           v
+------------------+                    +---------------------+
| WordPress Admin  |                    |  REST Webhook       |
| Event Controller |                    |  (/wpcs/v1/webhook) |
+------------------+                    +---------------------+
         |                                           |
         v                                           v
+-------------------------------------------------------------+
|               Transient Mutex Lock (SyncLock)               |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|                Database Cache (wp_wpcs_events)              |
+-------------------------------------------------------------+
                              |
           +------------------+------------------+
           |                                     |
           v                                     v
+-----------------------+             +-----------------------+
|  Frontend Agenda View |             |  Frontend Month View  |
|  (Zero External APIs) |             |  (Zero External APIs) |
+-----------------------+             +-----------------------+
```

### Security Invariants
- **Secret Encryption at Rest:** Client secrets and refresh tokens are encrypted using `crypto_secretbox` (Sodium) or OpenSSL AES-256-GCM. Autoload is disabled on `wpcs_secrets`.
- **Timing-Safe Webhook Auth:** Push notifications validate `X-Goog-Channel-Token` against database tokens using `hash_equals()`.
- **Privilege Separation:** All administrative actions and REST endpoints enforce `manage_options` capability checks and nonce validation.
- **SQL Injection Prevention:** Every database query is executed through prepared statements (`$wpdb->prepare()`).

---

## Changelog

### [1.0.2] - 2026-10-14
- **New Feature:** Added `DemoDataManager` providing 1-click simulated Google OAuth 2.0 connection, 3 color-coded sample calendars, and 12 relative-date sample events.
- **New Feature:** Added Demo Mode card, active status banner, and clean removal actions in Admin Connection tab.
- **New Feature:** Added automated GitHub release update checker (`GitHubUpdater`) for native WordPress dashboard update notifications.
- **Enhancement:** Added demo calendar sync bypass in `SyncService` to avoid live Google API errors during evaluation.
- **Testing:** Expanded test suite with `DemoDataManagerTest` (41 passing tests, 100% pass rate).

### [1.0.1] - 2026-10-14
- **Bug Fix:** Constrained left-hand admin sidebar logo globally in `admin_head` (`20px x 20px`, `padding-top: 7px`, Dashicon-matching opacity) preventing oversized logo overflow on all admin pages.
- **New Feature:** Implemented dedicated Plugin Overview tab controller (`src/Admin/Overview.php`) with 6-step walkthrough and methods data flow table.
- **Packaging:** Integrated automated FTP synchronization pipeline (`sync-ftp.ps1`).

### [1.0.0] - 2026-10-13
- **Initial Release:** Complete two-way Google Calendar synchronization for WordPress.
- High-performance local caching table (`{$wpdb->prefix}wpcs_events`).
- Sodium / OpenSSL AES-256-GCM authenticated encryption for OAuth secrets.
- Push notification webhooks with timing-safe validation.
- Responsive, WCAG 2.2 AA compliant Agenda list and Month grid calendar views.
- Gutenberg block and `[wpcalibrate_calendar]` shortcode.
- WP-Cron background recovery engine.

---

## Support & Contributing

- **Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
- **Support Email:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **Marketplace & Add-ons:** [https://marketplace.wpcalibrate.com/](https://marketplace.wpcalibrate.com/)
- **Issues & Contributions:** Please open an issue or pull request on [GitHub](https://github.com/zeeshanraza-official/wpcalibrate-calendar-sync/issues).

---

## License

This project is licensed under the GNU General Public License v2.0 or later - see the [LICENSE](LICENSE) file for details.
