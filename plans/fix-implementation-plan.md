# Fix Implementation Plan - Antigravity Booking Plugin

**Date:** 2026-01-22  
**Priority:** CRITICAL  
**Estimated Complexity:** Medium

---

## Overview

This document provides step-by-step instructions for fixing the two critical bugs and implementing high-priority improvements in the Antigravity Booking WordPress plugin.

---

## Phase 1: Critical Bug Fixes

### Fix #1: Google Calendar AJAX Handler (Status 500 Error)

**Problem:** AJAX test connection returns Status 500 due to fatal errors during Google Calendar class instantiation.

**Root Cause:** Constructor registers hooks and may throw exceptions before error handling is in place.

#### Implementation Steps

**Step 1.1: Modify Google Calendar Class Constructor**

File: `includes/class-antigravity-booking-google-calendar.php`

```php
// BEFORE (Lines 15-18):
public function __construct()
{
    add_action('transition_post_status', array($this, 'sync_to_calendar'), 20, 3);
}

// AFTER:
public function __construct()
{
    // Constructor should not have side effects
    // Hook registration moved to init() method
}

/**
 * Initialize hooks
 * Call this method after instantiation to register WordPress hooks
 */
public function init()
{
    add_action('transition_post_status', array($this, 'sync_to_calendar'), 20, 3);
}
```

**Step 1.2: Update Main Plugin Class**

File: `includes/class-antigravity-booking.php`

```php
// BEFORE (Line 39):
$this->google_calendar = new Antigravity_Booking_Google_Calendar();

// AFTER:
$this->google_calendar = new Antigravity_Booking_Google_Calendar();
$this->google_calendar->init(); // Initialize hooks
```

**Step 1.3: Update AJAX Test Handler**

File: `includes/class-antigravity-booking-settings.php`

```php
// BEFORE (Lines 1065-1071):
error_log('Antigravity Booking: Initializing GCal Class');
$gcal = new Antigravity_Booking_Google_Calendar();

error_log('Antigravity Booking: Calling test_connection()');
$gcal->test_connection();

// AFTER:
error_log('Antigravity Booking: Initializing GCal Class');
$gcal = new Antigravity_Booking_Google_Calendar();
// DO NOT call init() - we don't want to register hooks during test

error_log('Antigravity Booking: Calling test_connection()');
$gcal->test_connection();
```

**Step 1.4: Add Output Buffer Protection**

File: `includes/class-antigravity-booking-settings.php`

```php
// Add at the very beginning of ajax_test_gcal_connection() method (after line 1030):
public function ajax_test_gcal_connection()
{
    // Start output buffering to catch any stray output
    ob_start();
    
    // Force errors to be logged but NOT displayed
    @ini_set('display_errors', 0);
    
    // ... rest of existing code ...
    
    // Before any wp_send_json_* call, clean the buffer:
    if (ob_get_length()) ob_clean();
    wp_send_json_success(array('message' => 'Connection successful!'));
}
```

**Testing:**
1. Navigate to Settings > Google Calendar
2. Paste valid service account JSON
3. Click "Test Connection"
4. Should see green success message or specific error (not Status 500)

---

### Fix #2: Booking Approval Critical Error

**Problem:** Approving a booking shows critical error despite successful status change.

**Root Cause:** Google Calendar sync during `wp_update_post()` may output errors before `wp_redirect()`.

#### Implementation Steps

**Step 2.1: Add Output Buffering to Status Change Handler**

File: `includes/class-antigravity-booking-dashboard.php`

```php
// BEFORE (Lines 619-639):
public function handle_status_change()
{
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

    if (!wp_verify_nonce($_POST['_wpnonce'], 'change_booking_status_' . $booking_id)) {
        wp_die('Security check failed');
    }

    if (!current_user_can('edit_post', $booking_id)) {
        wp_die('You do not have permission to edit this booking');
    }

    wp_update_post(array(
        'ID' => $booking_id,
        'post_status' => $new_status,
    ));

    wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
    exit;
}

// AFTER:
public function handle_status_change()
{
    // Start output buffering to prevent any output before redirect
    ob_start();
    
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

    if (!wp_verify_nonce($_POST['_wpnonce'], 'change_booking_status_' . $booking_id)) {
        ob_end_clean();
        wp_die('Security check failed');
    }

    if (!current_user_can('edit_post', $booking_id)) {
        ob_end_clean();
        wp_die('You do not have permission to edit this booking');
    }

    // Update post status (this triggers transition_post_status hook)
    wp_update_post(array(
        'ID' => $booking_id,
        'post_status' => $new_status,
    ));

    // Clean any output that may have occurred during hooks
    ob_end_clean();
    
    // Now safe to redirect
    wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
    exit;
}
```

**Step 2.2: Ensure Google Calendar Sync Doesn't Output**

File: `includes/class-antigravity-booking-google-calendar.php`

Verify that all error handling uses `error_log()` and never `echo`, `print`, or `var_dump()`.

```php
// Review lines 138-206 in sync_to_calendar() method
// Ensure all catch blocks only use error_log():

} catch (Exception $e) {
    error_log("Antigravity Booking: Error creating Google Calendar event: " . $e->getMessage());
    // NEVER use echo, print, var_dump, etc.
}
```

**Step 2.3: Add Similar Protection to Bulk Actions**

File: `includes/class-antigravity-booking-dashboard.php`

```php
// Update handle_bulk_action() method (lines 644-686):
public function handle_bulk_action()
{
    // Start output buffering
    ob_start();
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_booking_action')) {
        ob_end_clean();
        wp_die('Security check failed');
    }

    // ... existing code ...

    // Clean buffer before redirect
    ob_end_clean();
    
    wp_redirect(admin_url('admin.php?page=antigravity-booking&bulk_updated=' . count($booking_ids)));
    exit;
}
```

**Testing:**
1. Create a test booking with status "Pending Review"
2. Go to Bookings Dashboard
3. Click "Approve" button
4. Should redirect to dashboard with success message (no critical error)
5. Verify booking status changed to "Approved"
6. Check if Google Calendar event was created (if configured)

---

### Fix #3: Simplify Private Key Normalization

**Problem:** Overly aggressive string replacements may corrupt valid private keys.

**File:** `includes/class-antigravity-booking-google-calendar.php`

#### Implementation Steps

```php
// BEFORE (Lines 81-105):
if (isset($credentials_data['private_key'])) {
    $key = $credentials_data['private_key'];

    // 1. Double Unslash
    $key = str_replace('\\\\n', "\n", $key);
    $key = str_replace('\\n', "\n", $key);

    // 2. Structural Recovery
    $key = str_replace('-----BEGIN PRIVATE KEY-----n', "-----BEGIN PRIVATE KEY-----\n", $key);
    $key = str_replace('n-----END PRIVATE KEY-----', "\n-----END PRIVATE KEY-----", $key);

    // 3. Remove trailing literal 'n'
    $key = rtrim($key, "n ");

    // 4. Remove non-breaking spaces
    $key = str_replace(array("\xc2\xa0", "\xa0"), ' ', $key);

    // 5. Normalize line endings
    $key = str_replace("\r", "", $key);
    $key = trim($key);

    $credentials_data['private_key'] = $key;
}

// AFTER:
if (isset($credentials_data['private_key'])) {
    $key = $credentials_data['private_key'];
    
    // Only normalize if key appears to have escaping issues
    // Check for literal \n sequences (not actual newlines)
    if (strpos($key, '\\n') !== false) {
        error_log('Antigravity Booking: Normalizing escaped newlines in private key');
        $key = str_replace('\\n', "\n", $key);
    }
    
    // Normalize line endings (Windows to Unix)
    $key = str_replace("\r\n", "\n", $key);
    $key = str_replace("\r", "\n", $key);
    
    // Ensure proper header format (add newline after header if missing)
    if (preg_match('/^-----BEGIN PRIVATE KEY-----[^\\n]/', $key)) {
        $key = str_replace('-----BEGIN PRIVATE KEY-----', "-----BEGIN PRIVATE KEY-----\n", $key);
    }
    
    // Ensure proper footer format (add newline before footer if missing)
    if (preg_match('/[^\\n]-----END PRIVATE KEY-----$/', $key)) {
        $key = str_replace('-----END PRIVATE KEY-----', "\n-----END PRIVATE KEY-----", $key);
    }
    
    // Final trim (safe - only removes leading/trailing whitespace)
    $key = trim($key);
    
    $credentials_data['private_key'] = $key;
    
    error_log('Antigravity Booking: Private key normalized successfully');
}
```

**Testing:**
1. Test with properly formatted service account JSON
2. Test with JSON that has `\n` instead of actual newlines
3. Test with JSON copied from Windows (CRLF line endings)
4. Verify authentication works in all cases

---

## Phase 2: High Priority Security Fixes

### Fix #4: Add Nonce Verification to AJAX Handlers

**File:** `includes/class-antigravity-booking.php`

#### Step 4.1: Add Nonce to Frontend JavaScript

File: `public/js/antigravity-booking-public.js`

```javascript
// Add nonce to AJAX requests
// Assuming WordPress localizes the script with nonce

// For calculate_cost:
$.ajax({
    url: antigravity_booking.ajax_url,
    type: 'POST',
    data: {
        action: 'calculate_booking_cost',
        nonce: antigravity_booking.nonce,  // Add this
        start: start,
        end: end
    },
    // ...
});

// For check_availability:
$.ajax({
    url: antigravity_booking.ajax_url,
    type: 'POST',
    data: {
        action: 'check_availability',
        nonce: antigravity_booking.nonce,  // Add this
        start: start,
        end: end
    },
    // ...
});
```

#### Step 4.2: Localize Script with Nonce

File: `includes/class-antigravity-booking-shortcode.php` (or wherever scripts are enqueued)

```php
// Add to script enqueue function:
wp_localize_script('antigravity-booking-public', 'antigravity_booking', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('antigravity_booking_nonce'),
));
```

#### Step 4.3: Verify Nonce in Handlers

File: `includes/class-antigravity-booking.php`

```php
// BEFORE (Line 68):
public function ajax_calculate_cost()
{
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    // ...
}

// AFTER:
public function ajax_calculate_cost()
{
    // Verify nonce
    check_ajax_referer('antigravity_booking_nonce', 'nonce');
    
    // Validate input exists
    if (!isset($_POST['start']) || !isset($_POST['end'])) {
        wp_send_json_error(array('message' => 'Missing required parameters'));
        return;
    }
    
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    // Validate date format
    if (!strtotime($start) || !strtotime($end)) {
        wp_send_json_error(array('message' => 'Invalid date format'));
        return;
    }
    
    // ... rest of code
}

// Apply same pattern to ajax_check_availability() and ajax_get_calendar_events()
```

---

### Fix #5: Improve Capability Checks

**File:** `includes/class-antigravity-booking-dashboard.php`

```php
// BEFORE (Line 628):
if (!current_user_can('edit_post', $booking_id)) {
    wp_die('You do not have permission to edit this booking');
}

// AFTER:
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to manage bookings');
}
```

Apply to:
- `handle_status_change()` (line 628)
- `handle_bulk_action()` (line 664)
- `export_csv()` (line 697)

---

## Phase 3: Performance Optimizations

### Fix #6: Optimize Status Count Queries

**File:** `includes/class-antigravity-booking-dashboard.php`

```php
// BEFORE (Lines 123-132):
foreach (array('pending_review', 'approved', 'expired') as $status) {
    $count_query = new WP_Query(array(
        'post_type' => 'booking',
        'post_status' => $status,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));
    $counts[$status] = $count_query->found_posts;
    $counts['all'] += $count_query->found_posts;
}

// AFTER:
global $wpdb;
$status_counts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT post_status, COUNT(*) as count 
        FROM {$wpdb->posts} 
        WHERE post_type = %s 
        AND post_status IN ('pending_review', 'approved', 'expired', 'cancelled')
        GROUP BY post_status",
        'booking'
    ),
    OBJECT_K
);

$counts = array(
    'all' => 0,
    'pending_review' => 0,
    'approved' => 0,
    'expired' => 0,
    'cancelled' => 0,
);

foreach ($status_counts as $status => $data) {
    $counts[$status] = (int) $data->count;
    $counts['all'] += (int) $data->count;
}
```

---

## Phase 4: Code Quality Improvements

### Fix #7: Standardize Error Handling

Create a utility method for consistent error logging:

**File:** `includes/class-antigravity-booking.php`

```php
/**
 * Log error with consistent format
 * 
 * @param string $message Error message
 * @param Throwable $exception Optional exception object
 */
public static function log_error($message, $exception = null)
{
    $log_message = 'Antigravity Booking: ' . $message;
    
    if ($exception) {
        $log_message .= ' | ' . $exception->getMessage();
        $log_message .= ' in ' . $exception->getFile() . ':' . $exception->getLine();
    }
    
    error_log($log_message);
}
```

Then use throughout:

```php
// Instead of:
error_log("Antigravity Booking: Error: " . $e->getMessage());

// Use:
Antigravity_Booking::log_error('Error during operation', $e);
```

---

## Testing Checklist

### Critical Fixes Testing

- [ ] **Google Calendar Test Connection**
  - [ ] Test with valid service account JSON
  - [ ] Test with invalid JSON
  - [ ] Test with missing credentials
  - [ ] Verify no Status 500 errors
  - [ ] Verify clear error messages

- [ ] **Booking Approval**
  - [ ] Create pending booking
  - [ ] Approve via dashboard
  - [ ] Verify no critical error
  - [ ] Verify status changes to approved
  - [ ] Verify redirect works
  - [ ] Check Google Calendar sync (if configured)

- [ ] **Bulk Actions**
  - [ ] Select multiple bookings
  - [ ] Approve in bulk
  - [ ] Verify no errors
  - [ ] Verify all statuses updated

### Security Testing

- [ ] **AJAX Nonce Verification**
  - [ ] Test AJAX calls with valid nonce
  - [ ] Test AJAX calls without nonce (should fail)
  - [ ] Test AJAX calls with expired nonce (should fail)

- [ ] **Capability Checks**
  - [ ] Test as admin (should work)
  - [ ] Test as editor (should fail for booking management)
  - [ ] Test as subscriber (should fail)

### Performance Testing

- [ ] **Dashboard Load Time**
  - [ ] Measure before optimization
  - [ ] Measure after optimization
  - [ ] Verify query count reduced

- [ ] **Availability Checks**
  - [ ] Test booking form responsiveness
  - [ ] Check database query count

### Regression Testing

- [ ] **Existing Functionality**
  - [ ] Create new booking via frontend
  - [ ] Edit booking in admin
  - [ ] Delete booking
  - [ ] Export bookings to CSV
  - [ ] Email notifications
  - [ ] Calendar view
  - [ ] Settings page

---

## Deployment Plan

### Pre-Deployment

1. **Backup Current Plugin**
   ```bash
   cd wp-content/plugins
   cp -r antigravity-booking antigravity-booking-backup-$(date +%Y%m%d)
   ```

2. **Enable Debug Mode**
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Test on Staging Environment**
   - Apply all fixes to staging
   - Run full testing checklist
   - Review debug.log for errors

### Deployment

1. **Apply Fixes in Order**
   - Phase 1: Critical fixes (one at a time)
   - Test after each fix
   - Phase 2: Security fixes
   - Phase 3: Performance optimizations
   - Phase 4: Code quality improvements

2. **Monitor Logs**
   ```bash
   tail -f wp-content/debug.log
   ```

3. **Version Bump**
   - Update version to 1.1.13 in main plugin file
   - Update changelog

### Post-Deployment

1. **Verify Critical Functionality**
   - Test Google Calendar connection
   - Test booking approval
   - Test frontend booking form

2. **Monitor for 24 Hours**
   - Check error logs
   - Monitor user reports
   - Check Google Calendar sync

3. **Document Changes**
   - Update CHANGES_SUMMARY.md
   - Update readme.md changelog

---

## Rollback Plan

If critical issues occur:

1. **Immediate Rollback**
   ```bash
   cd wp-content/plugins
   rm -rf antigravity-booking
   mv antigravity-booking-backup-YYYYMMDD antigravity-booking
   ```

2. **Clear Caches**
   - Clear WordPress object cache
   - Clear any page caching
   - Clear browser cache

3. **Investigate**
   - Review debug.log
   - Identify which fix caused issue
   - Revert specific fix only if possible

---

## Success Criteria

### Must Have (Critical)
- ✅ Google Calendar test connection works without Status 500
- ✅ Booking approval works without critical error
- ✅ No regression in existing functionality

### Should Have (High Priority)
- ✅ All AJAX endpoints have nonce verification
- ✅ Proper capability checks on admin actions
- ✅ Improved error messages for users

### Nice to Have (Medium Priority)
- ✅ Optimized database queries
- ✅ Consistent error handling
- ✅ Better code documentation

---

## Estimated Timeline

- **Phase 1 (Critical Fixes):** 2-3 hours
- **Phase 2 (Security Fixes):** 2-3 hours
- **Phase 3 (Performance):** 1-2 hours
- **Phase 4 (Code Quality):** 1-2 hours
- **Testing:** 3-4 hours
- **Total:** 9-14 hours

---

## Notes

- All fixes should maintain backward compatibility
- No database schema changes required
- No changes to public API
- Safe to deploy without user notification
- Consider creating automated tests for future

---

**End of Implementation Plan**
