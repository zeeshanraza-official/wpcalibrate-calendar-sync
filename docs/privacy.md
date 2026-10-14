# Privacy & Data Handling Policy

## 1. Overview
**WPCalibrate Calendar Sync** connects a WordPress site to Google Calendar via OAuth 2.0 to synchronize events. Respect for user privacy and minimal data exposure are foundational principles of the plugin.

---

## 2. External Service Disclosure
- **Third-Party Provider:** Google LLC (Google Calendar API).
- **Service URLs:**
  - Google Calendar API: `https://developers.google.com/calendar`
  - Google Privacy Policy: `https://policies.google.com/privacy`
  - Google Terms of Service: `https://policies.google.com/terms`
- **Purpose of Interaction:** Authenticating administrator access via OAuth 2.0, fetching calendar metadata, synchronizing calendar events, and receiving resource change notifications.

---

## 3. Data Synchronized & Stored Locally
The plugin caches only the event data necessary to display and manage calendars:
- **Event Identifiers:** External Google Event ID, recurring series ID, ETag.
- **Event Metadata:** Title/Summary, description, location, start date/time (UTC), end date/time (UTC), all-day flag, original timezone, status (`confirmed`, `tentative`, `cancelled`).
- **Sync State:** Content checksum hash, synchronization direction, timestamps.

### Data Explicitly Excluded in Version 1.0.0
- **No Attendees:** Attendee names, email addresses, response statuses, and contact details are NOT synchronized or stored.
- **No Private Credentials in Database in Plaintext:** OAuth Client Secret and Refresh Tokens are stored encrypted with Sodium or OpenSSL (AES-256-GCM).
- **No End-User Tracking:** Frontend visitors browsing public calendars are neither tracked nor logged. No cookies or personal data are collected from visitors.

---

## 4. Suggested WordPress Privacy Policy Content
Site administrators may incorporate the following wording into their site's public Privacy Policy:

> **Google Calendar Integration**
> When you interact with events displayed on our site, calendar schedule details are synchronized from Google Calendar. We do not store personal attendee information from Google Calendar. Synchronization is conducted between our server and Google via secure, encrypted channels. If you contact us regarding scheduled events, event details are processed according to our standard data policies. Google's privacy practices are governed by Google's Privacy Policy at https://policies.google.com/privacy.

---

## 5. Data Retention & Deletion
- **Uninstall Data Retention Setting:** By default, plugin settings and synchronized events are retained upon plugin deactivation or deletion to prevent accidental data loss.
- **Complete Deletion Option:** Administrators can enable *"Delete all plugin data when uninstalled"* under **WPCalibrate > Calendar Sync > Settings**. When enabled, deleting the plugin through WordPress permanently removes all custom tables (`wpcs_events`), encrypted options, logs, and scheduled tasks.
- **Disconnection:** Disconnecting the Google Account in the settings interface immediately clears stored access tokens, refresh tokens, and client secrets, and stops active webhook subscriptions where feasible.
