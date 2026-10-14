# Google Cloud & Calendar API Setup Guide

This guide details how to configure Google Cloud Platform (GCP) credentials for **WPCalibrate Calendar Sync**.

---

## Prerequisites
- A Google Account (Google Workspace or personal `@gmail.com`).
- Access to the [Google Cloud Console](https://console.cloud.google.com/).
- A WordPress website running PHP 8.2+ with HTTPS (HTTPS is mandatory for push notifications and standard OAuth security).

---

## Step 1: Create or Select a Google Cloud Project
1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. In the top project selector dropdown, click **New Project**.
3. Name your project (e.g., `WPCalibrate Calendar Sync`) and assign an organization or leave as **No organization**.
4. Click **Create** and wait for the project initialization notification, then switch to the newly created project.

---

## Step 2: Enable the Google Calendar API
1. In the Google Cloud Console navigation menu, navigate to **APIs & Services > Library**.
2. In the search box, search for `Google Calendar API`.
3. Click on **Google Calendar API** from the results.
4. Click **Enable**.

---

## Step 3: Configure the OAuth Consent Screen
1. In the left navigation, go to **APIs & Services > OAuth consent screen**.
2. Select the **User Type**:
   - **Internal:** Available only if you use Google Workspace and wish to limit access exclusively to users within your organization.
   - **External:** Required for standard Google accounts. Note: If in "Testing" mode, you must add your Google email under **Test users**.
3. Click **Create**.
4. Fill in the **App Information**:
   - **App name:** `WPCalibrate Calendar Sync` (or your website name).
   - **User support email:** Select your email address.
   - **Developer contact information:** Provide your email address.
5. Click **Save and Continue**.
6. On the **Scopes** page:
   - Click **Add or Remove Scopes**.
   - Search for and add:
     - `.../auth/calendar` (See, edit, share, and permanently delete all the calendars you can access using Google Calendar).
     - Or `.../auth/calendar.readonly` if your site will only use read-only synchronization.
   - Click **Update** and then **Save and Continue**.
7. If the app is External and in Testing status:
   - Add your Google account under **Test users**.
   - Click **Save and Continue**.
8. Review your settings and click **Back to Dashboard**.

---

## Step 4: Create OAuth 2.0 Web Application Credentials
1. In the left navigation, click **APIs & Services > Credentials**.
2. Click **+ Create Credentials** at the top and select **OAuth client ID**.
3. In the **Application type** dropdown, select **Web application**.
4. Name the client (e.g., `WordPress Calendar Sync Client`).
5. Under **Authorized redirect URIs**:
   - Click **+ Add URI**.
   - Enter the exact **Authorized Redirect URI** provided in your WordPress admin under:
     **WPCalibrate > Calendar Sync > Connection**.
     *(Format: `https://yourdomain.com/wp-admin/admin.php?page=wpcalibrate-calendar-sync&tab=connection&wpcs_action=oauth_callback`)*
6. Click **Create**.
7. A dialog will display your **Client ID** and **Client Secret**.
   - Copy both values securely.

---

## Step 5: Connect in WordPress
1. Open your WordPress dashboard and navigate to **WPCalibrate > Calendar Sync > Connection**.
2. Paste the **Client ID** and **Client Secret** into the respective fields.
3. Click **Save Credentials**.
4. Click **Connect Google Account**.
5. You will be redirected to Google to authorize access. Select your Google account and grant permissions.
6. Upon approval, you are returned to WordPress with an active connection status.

---

## Step 6: Select Calendars & Synchronize
1. Go to the **Calendars** tab in WordPress.
2. Click **Refresh Calendars** if not already listed.
3. Check the calendars you wish to synchronize.
4. Choose the sync direction for each calendar:
   - **Two-way:** Bidirectional changes between WordPress and Google.
   - **Google to WordPress:** Read-only cached import.
   - **WordPress to Google:** Local events pushed to Google.
5. Click **Save Calendar Settings**.
6. Head to **Synchronization** tab and click **Sync All Calendars Now** to execute the initial synchronization.

---

## Push Notifications Requirements (Webhooks)
To receive real-time push updates from Google Calendar:
- Your site **must** use a publicly accessible domain with valid HTTPS (Google cannot reach `localhost` or HTTP sites).
- Google Cloud requires domain verification in Google Search Console if registering domain webhooks outside OAuth channel pushes. For standard client-channel push subscriptions, ensure your site returns a valid HTTP 200 on the REST endpoint `/wp-json/wpcalibrate/v1/webhook`.
