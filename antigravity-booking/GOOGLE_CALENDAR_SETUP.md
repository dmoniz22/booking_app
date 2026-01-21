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
5. Create a service account
6. Download the JSON credentials file
7. Share your Google Calendar with the service account email (found in JSON file)

### 3. Configure Plugin Settings

In WordPress admin, go to **Settings** → **Antigravity Booking** and configure:

- **Google Calendar Credentials File**: Upload or specify path to JSON file
- **Google Calendar ID**: Usually `primary` or your calendar ID
- **Hourly Rate**: Set your $/hr rate
- **Customer Instructions**: Text sent to customers after booking submission
- **Reminder Message**: Text included in reminder emails

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
