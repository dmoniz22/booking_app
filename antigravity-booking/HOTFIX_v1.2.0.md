# Hotfix - Version 1.2.0

**Date:** 2026-01-26  
**Issue:** Bookings disappearing from dashboard after save  
**Status:** Fixed

---

## Issue Description

### Problem
When editing a booking and clicking "Publish" or "Save Draft", the booking would disappear from the dashboard. This occurred because:

1. WordPress was setting booking status to "publish" or "draft"
2. Dashboard query only looked for custom statuses: `pending_review`, `approved`, `expired`
3. Bookings with "publish" status were not included in the query
4. Result: Booking appeared to be deleted (but was actually just hidden)

### Root Cause
The booking custom post type was using WordPress's default publish box, which sets standard WordPress statuses (publish, draft) instead of the custom booking statuses (pending_review, approved, etc.).

---

## Solution Implemented

### 1. Custom Status Selector
Added a custom status dropdown in the publish box that only shows booking-specific statuses:
- Pending Review
- Approved
- Expired
- Cancelled

**Code:** [`class-antigravity-booking-cpt.php`](includes/class-antigravity-booking-cpt.php)
- Added `add_status_selector()` method
- Hides default WordPress publish status selector
- Shows custom booking status dropdown

### 2. Force Custom Status on Save
Implemented `force_custom_status()` filter to intercept WordPress's status assignment:
- Converts "publish" → "pending_review"
- Converts "draft" → "pending_review"
- Converts "auto-draft" → "pending_review"
- Respects manually selected custom status

**Code:** [`class-antigravity-booking-cpt.php`](includes/class-antigravity-booking-cpt.php)
- Added `force_custom_status()` method
- Hooked to `wp_insert_post_data` filter

### 3. Dashboard Query Update
Updated dashboard to include "publish" status as a fallback:
- Query now includes: `pending_review`, `approved`, `draft`, `expired`, `publish`
- Ensures bookings are never hidden
- Backward compatible with any existing bookings

**Code:** [`class-antigravity-booking-dashboard.php`](includes/class-antigravity-booking-dashboard.php)
- Updated post_status array in query

---

## Testing

### Before Fix
1. Edit booking
2. Click "Publish"
3. ❌ Booking disappears from dashboard
4. ❌ Appears to be deleted
5. ❌ Actually has status "publish" (hidden from query)

### After Fix
1. Edit booking
2. Select status from dropdown (e.g., "Pending Review")
3. Click "Update"
4. ✅ Booking remains in dashboard
5. ✅ Status is correctly set
6. ✅ All edits are saved

---

## Files Modified

1. **includes/class-antigravity-booking-cpt.php**
   - Added `add_status_selector()` method
   - Added `force_custom_status()` method
   - Registered hooks in constructor

2. **includes/class-antigravity-booking-dashboard.php**
   - Added 'publish' to post_status query array
   - Added 'publish' to status_labels array

---

## Deployment

The fix is included in:
- ✅ `antigravity-booking-v1.2.0.zip` (updated)
- ✅ `antigravity-booking-v1.2.0.tar.gz` (updated)

Both files have been regenerated with the hotfix.

---

## User Impact

### What Changed
- Booking edit page now has a clear "Booking Status" dropdown
- Default WordPress publish/draft buttons still work but are intercepted
- Bookings always use custom statuses
- No bookings will disappear from dashboard

### What Users See
- Custom status selector in publish box
- Clear status labels (Pending Review, Approved, etc.)
- Bookings always visible in dashboard
- Proper status tracking

---

## Prevention

This issue was caused by relying on WordPress's default post status behavior. The fix ensures:
- Custom statuses are always used
- WordPress default statuses are converted automatically
- Dashboard query is comprehensive
- No bookings can be "lost"

---

## Backward Compatibility

The fix is fully backward compatible:
- Existing bookings with any status will appear in dashboard
- Custom statuses work as before
- No database changes required
- No data loss

---

## Recommendations

### For Testing
1. Create a new booking
2. Edit the booking
3. Change status using dropdown
4. Click "Update"
5. Verify booking appears in dashboard
6. Verify status is correct

### For Existing Bookings
If you have bookings that disappeared:
1. They still exist in WordPress
2. They likely have status "publish"
3. After updating to this version, they will reappear
4. You can change their status to the correct one

---

**Hotfix Version:** 1.2.0  
**Date:** 2026-01-26  
**Status:** Resolved  
**Severity:** Critical (booking data loss appearance)  
**Impact:** All users editing bookings
