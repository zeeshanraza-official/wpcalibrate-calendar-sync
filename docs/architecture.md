# WPCalibrate Calendar Sync - Architecture Documentation

## 1. Overview & Identity
- **Plugin Name:** WPCalibrate Calendar Sync
- **Slug:** `wpcalibrate-calendar-sync`
- **Namespace:** `WPCalibrate\CalendarSync`
- **Procedural Prefix:** `wpcs_`
- **Text Domain:** `wpcalibrate-calendar-sync`
- **Requires PHP:** 8.2+
- **Tested Up To WordPress:** 7.1.3
- **Author:** WPCalibrate (`support@wpcalibrate.com`, `https://wpcalibrate.com`)

The plugin provides production-grade, two-way synchronization between Google Calendar (v3 API) and WordPress. It adheres to WordPress coding standards, strict type safety, defensive database operations, robust OAuth 2.0 security, encrypted credentials at rest, and decoupled architecture.

---

## 2. High-Level Architecture

```
+-----------------------------------------------------------------------------------+
|                              WordPress Environment                                |
|                                                                                   |
|  +--------------------+     +---------------------+     +----------------------+  |
|  | Admin UI (Settings |     |  Frontend Displays  |     |   Gutenberg Block    |  |
|  | & Event Manager)   |     | (Agenda/Month View) |     |  (Server Rendered)   |  |
|  +---------+----------+     +----------+----------+     +----------+-----------+  |
|            |                           |                           |              |
|            |                           +-------------+-------------+              |
|            v                                         v                            |
|  +--------------------+                    +-------------------+                  |
|  |    Admin Event     |                    | CalendarRenderer  |                  |
|  |     Controller     |                    +---------+---------+                  |
|  +---------+----------+                              |                            |
|            |                                         |                            |
|            v                                         v                            |
|  +-------------------------------------------------------------+                  |
|  |                     EventService                            |                  |
|  +------------------------------+------------------------------+                  |
|                                 |                                                 |
|                                 v                                                 |
|  +-------------------------------------------------------------+                  |
|  |               EventRepository (Custom wpcs_events)          |                  |
|  +------------------------------+------------------------------+                  |
|                                 ^                                                 |
|                                 |                                                 |
|  +------------------------------+------------------------------+                  |
|  |                       SyncService                           |                  |
|  +---------+--------------------+--------------------+---------+                  |
|            ^                    ^                    ^                            |
|            |                    |                    |                            |
|  +---------+--------+  +--------+---------+  +-------+--------+                   |
|  | Manual Admin UI  |  | Push Notifications| | Recovery Cron  |                   |
|  |   Sync Trigger   |  | (REST Webhook)   |  |  (15m Periodic)|                   |
|  +------------------+  +------------------+  +----------------+                   |
+-----------------------------------------------------------------------------------+
                                  |
                                  v
                      +-----------------------+
                      | Google Calendar API   |
                      | (v3 via OAuth 2.0)    |
                      +-----------------------+
```

---

## 3. Core Subsystems

### 3.1 Bootstrap & Dependency Checker (`src/Plugin.php`)
- Ensures minimum PHP 8.2 and WordPress requirements.
- Safe singleton lifecycle with dependency injection.
- Initializes modules only when prerequisites pass.
- Autoloads via Composer PSR-4.

### 3.2 Secret Storage (`src/Auth/SecretStorage.php`)
- Encrypts OAuth client secrets and refresh tokens at rest.
- Encryption strategy:
  1. `ext-sodium` (preferred: `crypto_secretbox`).
  2. `ext-openssl` authenticated AES-256-GCM fallback.
- Key derivation uses `AUTH_SALT`, `SECURE_AUTH_SALT`, and plugin-specific context via HKDF / PBKDF2 / SHA-256.
- Disallows plaintext storage. If neither engine is available, blocks OAuth connection with clear admin notice.
- Disables autoload on secret options (`wpcs_secrets`).
- Masks secret values in admin views and diagnostic exports.

### 3.3 Google OAuth & API Integration (`src/Auth/`, `src/Api/`)
- Web-server OAuth 2.0 flow.
- Nonce and cryptographically secure state parameter validation.
- Scope minimization (`https://www.googleapis.com/auth/calendar` or `https://www.googleapis.com/auth/calendar.readonly` based on configuration).
- Offline access with automatic refresh token handling.
- Decoupled `GoogleClientFactory` allows mocking for tests and future multi-account extensions.

### 3.4 Database Schema & Migrations (`src/Database/`)
- Custom table: `{$wpdb->prefix}wpcs_events`.
- Columns indexed for fast filtering: `google_event_id`, `google_calendar_id`, `start_utc`, `end_utc`, `event_status`, `sync_status`.
- Stores event boundaries in UTC with site/event timezone metadata preserved.
- Separate all-day handling preventing date-shifting on timezone conversion.
- Idempotent versioned migrations via `MigrationManager`.

### 3.5 Synchronization Engine (`src/Sync/`)
- **Initial Sync:** Paginated import with change detection via MD5 content hash / checksum. Saves `nextSyncToken` upon completion.
- **Incremental Sync:** Consumes Google sync tokens. Processes modified and cancelled events.
- **Invalid Token Recovery:** Detects HTTP 410 / invalid sync token, clears sync token, and runs a safe reconciliation resync without wiping unrelated data.
- **Outbound Sync (WordPress to Google):** Creates/updates/deletes remote Google events when calendar direction allows outbound (`wp_to_google` or `two_way`). Marks local events dirty/pending until verified remote 200 OK.
- **Concurrency & Locking (`SyncLock`):** Transient-based locking per calendar with strict TTL (default 5 minutes) to prevent racing sync runs.
- **Push Notifications (`PushNotificationManager`):** Manages Google watch channels on HTTPS sites. Automatically validates webhook tokens and schedules channel renewal.
- **Recovery Cron (`RecoveryScheduler`):** Periodic WP-Cron checks (default 15m) to catch any missed push notifications or offline periods.

### 3.6 Frontend & Shortcode (`src/Frontend/`)
- Dynamic Gutenberg block registered via `block.json` with editor preview.
- Shortcode `[wpcalibrate_calendar]` reusing unified `CalendarRenderer`.
- Renders Agenda (list) and Month views directly from local database cache—never hits Google on visitor requests.
- Escaped markup, responsive layout, RTL support, and keyboard accessibility compliant with WCAG 2.2 AA.

### 3.7 Admin Interface & WPCalibrate Menu Isolation (`src/Admin/`)
- Inspects existing menu state: reuses top-level `wpcalibrate` slug if created by another WPCalibrate plugin; registers top-level menu only if absent.
- Distinct submenu `wpcalibrate-calendar-sync` with strictly scoped actions, tabs, nonces, and assets.
- Tabs: Connection, Calendars, Synchronization, Display, Diagnostics, License, Support.
- High-contrast, brand-compliant icons using `icon-dark.png` / `icon-white.png`.

### 3.8 Logging & Diagnostics (`src/Logging/`)
- Leveled logs (`info`, `warning`, `error`) stored with bounded retention (default 14 days, auto-pruned).
- Zero secrets policy: redaction of tokens, client secrets, auth codes, and private tokens.
- One-click copy diagnostics tool sanitizing sensitive credentials.

### 3.9 Licensing (`src/License/`)
- `LicenseProviderInterface` and `LicenseManager` abstraction.
- Clear status indicator stating that external licensing integration is unconfigured for development build. No spoofed validation.
