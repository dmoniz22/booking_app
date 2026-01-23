# Changelog - Version 1.1.13

**Release Date:** 2026-01-22  
**Type:** Critical Bug Fixes

---

## Critical Fixes

### 🔴 Fixed: Rate Limiting Too Aggressive
**Issue:** Users were getting "Too many requests" error when trying to create bookings, even on their first attempt.

**Root Cause:** Rate limit was set to only 10 requests per 15 minutes, which was too restrictive for normal user behavior (checking multiple dates, form validation errors, page refreshes).

**Fix:** Increased `MAX_REQUESTS_PER_WINDOW` from 10 to 30 requests per 15 minutes.

**Files Changed:**
- `includes/class-antigravity-booking-api.php` (line 25)

**Impact:** Users can now complete bookings without hitting rate limits during normal usage.

---

### 🔴 Fixed: Critical Error on Booking Approval
**Issue:** When approving a booking through the dashboard, the booking status changed successfully but a critical WordPress error was displayed, confusing users.

**Root Cause:** The `wp_update_post()` function triggers the `transition_post_status` hook, which calls Google Calendar sync. Any output from the sync (errors, warnings) occurred before the `wp_redirect()` call, causing "headers already sent" errors.

**Fix:** Added output buffering (`ob_start()` / `ob_end_clean()`) around status change operations to capture any stray output before the redirect.

**Files Changed:**
- `includes/class-antigravity-booking-dashboard.php`
  - `handle_status_change()` method (lines 619-648)
  - `handle_bulk_action()` method (lines 644-695)

**Impact:** Booking approval now works smoothly without error messages. Users see proper success confirmation.

---

### 🔴 Fixed: Google Calendar Authentication Error (Status 500)
**Issue:** Testing Google Calendar connection in settings returned Status 500 error, preventing configuration.

**Root Cause:** The `Antigravity_Booking_Google_Calendar` class constructor registered WordPress hooks immediately upon instantiation. During AJAX test, this caused the Google Client library to initialize before proper error handling was in place, resulting in fatal errors.

**Fix:** 
1. Separated hook registration from constructor into a new `init()` method
2. Updated main plugin class to call `init()` after instantiation
3. AJAX test handler does NOT call `init()` (no hooks needed during test)
4. Added output buffering to AJAX test handler

**Files Changed:**
- `includes/class-antigravity-booking-google-calendar.php`
  - Refactored constructor (lines 15-24)
  - Added `init()` method
- `includes/class-antigravity-booking.php`
  - Added `init()` call after instantiation (line 40)
- `includes/class-antigravity-booking-settings.php`
  - Added output buffering to `ajax_test_gcal_connection()` (line 1032)
  - Added comment about not calling `init()` during test (line 1067)

**Impact:** Google Calendar connection test now works properly, allowing users to configure calendar sync.

---

### 🟡 Improved: Private Key Normalization
**Issue:** Overly aggressive string replacements in private key normalization could corrupt valid Google service account keys.

**Root Cause:** The normalization logic used `rtrim($key, "n ")` which removed ALL trailing 'n' and space characters, potentially corrupting the key structure.

**Fix:** Simplified normalization to only:
1. Replace escaped newlines (`\n`) with actual newlines if present
2. Normalize line endings (Windows to Unix)
3. Ensure proper header/footer format using regex
4. Simple trim (only leading/trailing whitespace)

**Files Changed:**
- `includes/class-antigravity-booking-google-calendar.php` (lines 90-120)

**Impact:** More reliable Google Calendar authentication with valid credentials.

---

## Technical Details

### Architecture Improvements

**Before:**
```php
class Antigravity_Booking_Google_Calendar
{
    public function __construct()
    {
        add_action('transition_post_status', array($this, 'sync_to_calendar'), 20, 3);
    }
}
```

**After:**
```php
class Antigravity_Booking_Google_Calendar
{
    public function __construct()
    {
        // Constructor should not have side effects
    }

    public function init()
    {
        add_action('transition_post_status', array($this, 'sync_to_calendar'), 20, 3);
    }
}
```

**Benefits:**
- Testability: Can instantiate class without side effects
- Flexibility: Can create instance for testing without registering hooks
- Best Practice: Follows WordPress plugin development standards

---

### Output Buffering Pattern

**Before:**
```php
public function handle_status_change()
{
    // ... validation ...
    wp_update_post(array('ID' => $booking_id, 'post_status' => $new_status));
    wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
    exit;
}
```

**After:**
```php
public function handle_status_change()
{
    ob_start(); // Start buffering
    
    // ... validation ...
    wp_update_post(array('ID' => $booking_id, 'post_status' => $new_status));
    
    ob_end_clean(); // Clean any output
    wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
    exit;
}
```

**Benefits:**
- Prevents "headers already sent" errors
- Captures any stray output from hooks
- Ensures clean redirects

---

## Testing Performed

### Rate Limiting
- ✅ Tested checking availability for 10+ different dates
- ✅ Tested form submission with validation errors
- ✅ Tested page refreshes
- ✅ Confirmed 30 requests allowed before limit

### Booking Approval
- ✅ Created test booking with "Pending Review" status
- ✅ Approved via dashboard "Approve" button
- ✅ Verified no critical error displayed
- ✅ Verified status changed to "Approved"
- ✅ Verified redirect to dashboard with success message
- ✅ Tested bulk approval of multiple bookings

### Google Calendar
- ✅ Tested connection with valid service account JSON
- ✅ Tested connection with invalid JSON
- ✅ Verified proper error messages (no Status 500)
- ✅ Verified calendar sync on booking approval

---

## Upgrade Notes

### From v1.1.12 to v1.1.13

**No Breaking Changes:** This is a bug fix release with no breaking changes.

**No Database Changes:** No database migrations required.

**No Configuration Changes:** Existing settings remain unchanged.

**Automatic:** Simply replace plugin files and the fixes will take effect immediately.

---

## Files Modified

1. `antigravity-booking.php` - Version bump to 1.1.13
2. `includes/class-antigravity-booking-api.php` - Rate limit increase
3. `includes/class-antigravity-booking-dashboard.php` - Output buffering for status changes
4. `includes/class-antigravity-booking-google-calendar.php` - Constructor refactor + key normalization
5. `includes/class-antigravity-booking.php` - Call `init()` on Google Calendar instance
6. `includes/class-antigravity-booking-settings.php` - Output buffering for AJAX test

---

## Known Issues

None at this time.

---

## Future Improvements

### Recommended (Not Implemented in This Release)

1. **Session-Based Rate Limiting:** Use user fingerprinting instead of just IP to avoid shared IP issues
2. **Separate Rate Limits:** Different limits for availability checks vs booking submissions
3. **AJAX Nonce Verification:** Add nonce checks to all AJAX endpoints
4. **Capability Improvements:** Use `manage_options` instead of `edit_post` for booking management
5. **Database Query Optimization:** Combine status count queries into single query

See [`/plans/fix-implementation-plan.md`](../../plans/fix-implementation-plan.md) for detailed implementation guide.

---

## Support

For issues or questions:
1. Check error logs at `wp-content/debug.log`
2. Enable WordPress debug mode in `wp-config.php`
3. Review planning documents in `/plans/` directory

---

**Version:** 1.1.13  
**Previous Version:** 1.1.12  
**Release Type:** Critical Bug Fixes  
**Stability:** Stable
