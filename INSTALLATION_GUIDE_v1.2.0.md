# Installation Guide - Version 1.2.0

**Plugin:** Simplified Booking  
**Version:** 1.2.0  
**Date:** 2026-01-26

---

## Quick Start

### 1. Upload Plugin

**Option A: WordPress Admin (Recommended)**
1. Log in to WordPress admin
2. Navigate to **Plugins** > **Add New**
3. Click **Upload Plugin**
4. Choose `antigravity-booking-v1.2.0.zip`
5. Click **Install Now**
6. Click **Activate Plugin**

**Option B: FTP/SFTP**
1. Extract `antigravity-booking-v1.2.0.zip`
2. Upload `antigravity-booking` folder to `/wp-content/plugins/`
3. Log in to WordPress admin
4. Navigate to **Plugins**
5. Find "Simplified Booking" and click **Activate**

---

### 2. Install Dependencies

The plugin requires Google API Client library for calendar integration.

**Via SSH (Recommended):**
```bash
cd /path/to/wordpress/wp-content/plugins/antigravity-booking
composer install
```

**If Composer is not available:**
The plugin will display a notice in the admin. You can:
1. Install Composer on your server
2. Or contact your hosting provider
3. Or use the plugin without Google Calendar sync

---

### 3. Basic Configuration

1. Navigate to **Simplified Booking** > **Settings**

#### General Settings
- **Hourly Rate:** Enter your hourly rate (e.g., 100)
- **Timezone:** Select your timezone
- **Booking Cutoff Time:** Hours in advance required (default: 48)

#### Availability Settings
- **Available Days:** Check days you accept bookings
- **Business Hours:** Set hours for each day
- **Blackout Dates:** Add dates when bookings are not allowed

#### Overnight Pricing
- **Overnight Days:** Select days that allow overnight bookings
- **Overnight Times (Per Day):** Configure start/end times for each day
- **Special Date Overrides:** Add holiday-specific times

2. Click **Save Settings**

---

### 4. Google Calendar Setup (Optional)

If you want bookings to sync to Google Calendar:

1. Follow the complete guide: [`GOOGLE_OAUTH_SETUP.md`](antigravity-booking/GOOGLE_OAUTH_SETUP.md)

**Quick Summary:**
1. Create OAuth 2.0 credentials in Google Cloud Console
2. Enter Client ID and Secret in plugin settings
3. Click "Authorize with Google"
4. Approve calendar access
5. Done!

**Note:** Google Calendar sync is optional. The plugin works perfectly without it.

---

### 5. Create Booking Page

1. Navigate to **Pages** > **Add New**
2. Enter page title: "Book Now" (or your choice)
3. Add the shortcode:
   ```
   [antigravity_booking_calendar]
   ```
4. Click **Publish**
5. Visit the page to see the booking calendar

---

### 6. Test Booking Flow

1. Visit your booking page
2. Select a date and time
3. Fill in customer details
4. Submit booking
5. Check **Simplified Booking** > **Dashboard**
6. Verify booking appears
7. Try approving the booking
8. If Google Calendar is configured, check if event appears

---

## Upgrading from v1.1.14

### Before Upgrading

**Backup Your Site:**
1. Backup WordPress database
2. Backup plugin files
3. Test on staging site first (recommended)

### Upgrade Process

1. **Deactivate** (don't delete) current version
2. Upload and activate v1.2.0
3. Existing bookings will be preserved
4. Checklist items will be initialized to 0% for existing bookings

### Post-Upgrade Steps

#### Required: Set Up OAuth
- Old service account authentication no longer works
- Follow [`GOOGLE_OAUTH_SETUP.md`](antigravity-booking/GOOGLE_OAUTH_SETUP.md)
- Set up OAuth credentials
- Authorize with Google

#### Optional: Configure New Features
- Review per-day overnight times
- Add special date overrides
- Create blackout dates using new interface
- Explore booking checklist feature

---

## New Features in v1.2.0

### 1. Booking Approval Checklist
- Track 5 requirements per booking
- Visual progress bars
- Dashboard progress column

### 2. Dashboard Sorting
- Sort by Customer, Start Date, Cost, Status
- Click column headers
- Visual indicators (↑ ↓ ↕)

### 3. Flexible Overnight Times
- Different times per day of week
- Special date overrides
- Smart priority system

### 4. Blackout Dates Management
- Custom post type
- Easy add/remove interface
- Integrated with availability

### 5. OAuth Google Calendar
- One-click authorization
- No JSON files
- Automatic token refresh
- More reliable

---

## Troubleshooting

### Plugin Won't Activate
**Cause:** PHP version too old  
**Solution:** Requires PHP 7.4 or higher

### Google Calendar Not Working
**Cause:** OAuth not configured  
**Solution:** Follow [`GOOGLE_OAUTH_SETUP.md`](antigravity-booking/GOOGLE_OAUTH_SETUP.md)

### Bookings Not Saving
**Cause:** Permissions issue  
**Solution:** Check user has `edit_posts` capability

### Checklist Not Showing
**Cause:** Cache issue  
**Solution:** Clear WordPress cache and browser cache

---

## File Structure

```
antigravity-booking/
├── antigravity-booking.php          # Main plugin file
├── includes/
│   ├── class-antigravity-booking.php              # Core class
│   ├── class-antigravity-booking-cpt.php          # Booking CPT
│   ├── class-antigravity-booking-dashboard.php    # Dashboard
│   ├── class-antigravity-booking-settings.php     # Settings
│   ├── class-antigravity-booking-availability.php # Availability logic
│   ├── class-antigravity-booking-google-oauth.php # OAuth handler (NEW)
│   ├── class-antigravity-booking-google-calendar.php # Calendar sync
│   ├── class-antigravity-booking-blackout.php     # Blackout dates (NEW)
│   ├── class-antigravity-booking-emails.php       # Email notifications
│   ├── class-antigravity-booking-shortcode.php    # Frontend shortcode
│   └── class-antigravity-booking-api.php          # REST API
├── admin/                            # Admin assets
├── public/                           # Public assets
├── GOOGLE_OAUTH_SETUP.md            # OAuth setup guide (NEW)
├── RELEASE_NOTES_v1.2.0.md          # Release notes (NEW)
└── CHANGELOG-v1.2.0.md              # Changelog (NEW)
```

---

## System Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **HTTPS:** Required for OAuth (Google Calendar)
- **Composer:** Required for Google Calendar sync

---

## Support

### Documentation
- [`GOOGLE_OAUTH_SETUP.md`](antigravity-booking/GOOGLE_OAUTH_SETUP.md) - OAuth setup
- [`RELEASE_NOTES_v1.2.0.md`](antigravity-booking/RELEASE_NOTES_v1.2.0.md) - What's new
- [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) - Common issues

### Getting Help
1. Check documentation first
2. Review WordPress error logs
3. Test on staging site
4. Contact support with error details

---

## Next Steps

After installation:
1. ✅ Configure basic settings
2. ✅ Set up Google OAuth (if using calendar sync)
3. ✅ Create booking page
4. ✅ Test booking flow
5. ✅ Customize email templates
6. ✅ Configure checklist items
7. ✅ Set up blackout dates
8. ✅ Go live!

---

**Version:** 1.2.0  
**Last Updated:** 2026-01-26  
**Status:** Production Ready
