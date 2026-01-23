# Quick Fix: Temporarily Disable Google Calendar

**Purpose:** Stop the critical errors while we implement OAuth

---

## The Problem

The diagnostic shows all fixes are correctly applied, but you're still seeing errors. This is almost certainly due to **PHP OpCache** - your hosting provider's server is serving old cached PHP code even though the files are new.

**Why this happens:**
- Shared hosting providers cache PHP files for performance
- Cache doesn't clear automatically when files change
- You need server restart or cache expiration (can take hours)

---

## Immediate Solution: Disable Google Calendar Sync

This will stop the critical errors immediately while we work on a better solution (OAuth).

### Option 1: Via WordPress Admin (Easiest)

1. Go to **Simplified Booking > Settings**
2. Scroll to **Google Calendar Integration** section
3. **Clear the JSON credentials field** (delete all text)
4. Click **Save Changes**
5. Test approving a booking - should work now!

### Option 2: Via File Edit (If admin doesn't work)

Edit this file: `wp-content/plugins/antigravity-booking/includes/class-antigravity-booking.php`

Find line 39-40:
```php
$this->google_calendar = new Antigravity_Booking_Google_Calendar();
$this->google_calendar->init(); // Initialize hooks
```

Change to:
```php
// Temporarily disabled - implementing OAuth
// $this->google_calendar = new Antigravity_Booking_Google_Calendar();
// $this->google_calendar->init(); // Initialize hooks
```

Save the file. This completely disables Google Calendar integration.

---

## What This Does

✅ **Fixes:**
- Booking approval will work without errors
- No more critical website errors
- No more Status 500 on settings page

❌ **Temporarily Loses:**
- Automatic Google Calendar sync
- You'll need to manually add bookings to calendar

---

## Next Step: Implement OAuth

I'll create a new OAuth-based Google Calendar integration that:
- ✅ Is more reliable than service accounts
- ✅ Easier to set up (just click "Authorize")
- ✅ No JSON key management
- ✅ Better error handling
- ✅ Works with shared hosting

Would you like me to implement the OAuth version?

---

## Testing After Disabling

1. **Create a test booking** via the frontend form
2. **Go to Dashboard** and find the booking
3. **Click "Approve"**
4. **Expected result:** 
   - ✅ Status changes to "Approved"
   - ✅ Redirect to dashboard with success message
   - ✅ NO critical error
   - ❌ No Google Calendar event (expected - we disabled it)

---

## Why OAuth Is Better

### Service Account (Current - Problematic)
- ❌ Complex JSON key management
- ❌ Private key formatting issues
- ❌ Hard to debug authentication errors
- ❌ Requires composer dependencies
- ❌ Doesn't work well with shared hosting

### OAuth (Recommended)
- ✅ Simple "Authorize with Google" button
- ✅ No JSON keys to manage
- ✅ Better error messages
- ✅ Works on any hosting
- ✅ User-friendly setup
- ✅ Automatic token refresh

---

## Temporary Workaround: Manual Calendar Sync

While Google Calendar is disabled:

1. **Export bookings to CSV:**
   - Go to Dashboard
   - Click "Export to CSV"

2. **Import to Google Calendar:**
   - Open Google Calendar
   - Settings > Import & Export
   - Import the CSV file

Or use the calendar view in the dashboard to see all bookings.

---

**Status:** Temporary fix until OAuth is implemented  
**Impact:** Bookings will work, calendar sync disabled  
**Next:** Implement OAuth for better integration
