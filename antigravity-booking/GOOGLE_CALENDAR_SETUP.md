# Setting Up Google Calendar Integration

## Prerequisites
You need to install the Google API PHP Client library via Composer.

## Installation Steps

### 1. Install Composer Dependencies
Run this command in the plugin directory:
```bash
cd antigravity-booking/
composer require google/apiclient:^2.0
```

### 2. Create Google Service Account

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable **Google Calendar API**
4. Go to **IAM & Admin** → **Service Accounts**
5. Create a service account (e.g., `booking-sync@...`)
6. Go to the **Keys** tab for that service account and click **Add Key** → **Create new key** (JSON)
7. Download the JSON file. **Open it and copy the entire contents.**
8. **CRITICAL STEP:** Open your Google Calendar in a browser. Go to **Settings and sharing** for the calendar you want to use. Scroll to **Share with specific people** and add the service account's email address (the one ending in `@...iam.gserviceaccount.com`). Give it **"Make changes to events"** permissions.

### 3. Configure Plugin Settings

In WordPress admin, go to **Settings** → **Antigravity Booking** and configure:

- **Service Account JSON Credentials**: Paste the entire contents of the JSON file you downloaded.
- **Google Calendar ID**: Your email address (if using your primary calendar) or the unique ID found in the Calendar settings under "Integrate calendar".
- **Test Connection**: Click the button to verify immediately.

## Testing

1. Create a test booking
2. Change status to "Approved"
3. Check your Google Calendar for the event
4. Verify customer receives approval email with .ics file

## Troubleshooting

Check WordPress debug logs if events aren't syncing:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will be in `wp-content/debug.log`
