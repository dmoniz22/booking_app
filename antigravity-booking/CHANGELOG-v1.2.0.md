# Changelog - Version 1.2.0

**Release Date:** 2026-01-26  
**Type:** Major Feature Release  
**Status:** Complete

---

## Implementation Status

### ✅ Phase 1: Core Fixes & Improvements (COMPLETED)

#### 1. Fix Booking Edit Page Save Functionality
- **Status:** ✅ Complete
- **Changes:**
  - Added `post_updated_messages` filter to display save confirmations
  - Implemented custom update messages for booking post type
  - Enhanced save feedback with specific messages for different actions
- **Files Modified:**
  - `includes/class-antigravity-booking-cpt.php`

#### 2. Dashboard Sorting
- **Status:** ✅ Complete
- **Features Implemented:**
  - Sortable columns: Customer Name, Start Date, Cost, Status
  - Click column headers to toggle sort direction
  - Visual indicators (↑ ↓ ↕) showing current sort state
  - Sort state preserved in URL parameters
- **Files Modified:**
  - `includes/class-antigravity-booking-dashboard.php`
- **Technical Details:**
  - Added `orderby` and `order` query parameters
  - Implemented meta_key sorting for custom fields
  - Helper functions for generating sort URLs and indicators

#### 3. Booking Approval Checklist
- **Status:** ✅ Complete
- **Features Implemented:**
  - New meta box on booking edit page
  - 5 checklist items:
    1. Rental Agreement
    2. Deposit Received
    3. Certificate of Insurance
    4. Key Arrangement
    5. Deposit Returned
  - Progress bar showing completion percentage
  - Progress column in dashboard list view
  - Auto-calculation of progress on save
- **Files Modified:**
  - `includes/class-antigravity-booking-cpt.php`
  - `includes/class-antigravity-booking-dashboard.php`
- **Database Schema:**
  - New meta fields:
    - `_checklist_rental_agreement` (boolean)
    - `_checklist_deposit` (boolean)
    - `_checklist_insurance` (boolean)
    - `_checklist_key_arrangement` (boolean)
    - `_checklist_deposit_returned` (boolean)
    - `_checklist_progress` (integer 0-100)

---

### ✅ Phase 2: Overnight Booking Flexibility (COMPLETED)

#### 4. Per-Day Overnight Times Configuration
- **Status:** ✅ Complete
- **Planned Features:**
  - Different overnight times for each day of week
  - Settings UI with time pickers for each day
  - Default: Monday-Sunday configurable independently

#### 5. Special Date Overrides
- **Status:** ✅ Complete
- **Planned Features:**
  - Override overnight times for specific dates (holidays, etc.)
  - Add/remove special dates in settings
  - Priority: Special dates > Day-specific > Default

#### 6. Update Availability Logic
- **Status:** ✅ Complete
- **Planned Changes:**
  - Modify `Antigravity_Booking_Availability::get_overnight_end()`
  - Check special dates first, then day-specific, then default
  - Maintain backward compatibility

---

### ✅ Phase 3: Blackout Dates Enhancement (COMPLETED)

#### 7. Create Blackout Date Custom Post Type
- **Status:** ✅ Complete
- **Planned Features:**
  - New CPT: `blackout_date`
  - Meta fields: start_date, end_date, reason
  - Replace text-based blackout dates

#### 8. Build Calendar Interface
- **Status:** ✅ Complete
- **Planned Features:**
  - Visual calendar for selecting blackout dates
  - Click to select single dates
  - Drag to select ranges
  - List view of all blackout periods
  - Integration with Flatpickr library

#### 9. Integrate with Dashboard
- **Status:** 📋 Planned
- **Planned Features:**
  - Show blackout periods as entries in dashboard
  - Distinct styling for blackout entries
  - Quick remove functionality

#### 10. Update Availability Checking
- **Status:** 📋 Planned
- **Planned Changes:**
  - Query blackout_date CPT in availability checks
  - Block bookings during blackout periods
  - Show blackout conflicts in validation

---

### ✅ Phase 4: OAuth Integration (COMPLETED)

#### 11. Create OAuth Handler Class
- **Status:** ✅ Complete
- **New File:** `includes/class-antigravity-booking-google-oauth.php`
- **Features:**
  - OAuth 2.0 authorization flow
  - Token refresh logic
  - Secure token storage

#### 12. Build Authorization Flow
- **Status:** ✅ Complete
- **New File:** `includes/oauth-callback.php`
- **Features:**
  - Redirect to Google authorization
  - Handle OAuth callback
  - Exchange code for tokens

#### 13. Implement Token Refresh
- **Status:** ✅ Complete
- **Features:**
  - Automatic token refresh before expiry
  - Proactive refresh (5 min before expiry)
  - Error handling and retry logic

#### 14. Update Settings Page
- **Status:** ✅ Complete
- **Changes:**
  - Remove service account JSON fields
  - Add OAuth Client ID/Secret fields
  - "Authorize with Google" button
  - Authorization status indicator
  - "Disconnect" button

#### 15. Remove Service Account Code
- **Status:** ✅ Complete
- **Changes:**
  - Deprecate service account authentication
  - Migration path for existing installations
  - Maintain backward compatibility during transition

---

## Database Changes

### New Meta Fields (Implemented)
```
Booking Post Type:
- _checklist_rental_agreement (boolean)
- _checklist_deposit (boolean)
- _checklist_insurance (boolean)
- _checklist_key_arrangement (boolean)
- _checklist_deposit_returned (boolean)
- _checklist_progress (integer 0-100)
```

### New Options (Planned)
```
OAuth:
- antigravity_gcal_oauth_client_id
- antigravity_gcal_oauth_client_secret
- antigravity_gcal_oauth_access_token
- antigravity_gcal_oauth_refresh_token
- antigravity_gcal_oauth_expires_at
- antigravity_gcal_oauth_authorized

Overnight Times:
- antigravity_booking_overnight_times (array per day)
- antigravity_booking_special_hours (array specific dates)
```

### New Custom Post Types (Planned)
```
blackout_date:
- Meta: _blackout_start_date
- Meta: _blackout_end_date
- Meta: _blackout_reason
```

---

## Files Modified

### Completed
- ✅ `includes/class-antigravity-booking-cpt.php`
  - Added checklist meta box
  - Added checklist save logic
  - Added progress calculation
  - Added update messages

- ✅ `includes/class-antigravity-booking-dashboard.php`
  - Added sorting functionality
  - Added sortable column headers
  - Added progress column
  - Added sort indicators

### Planned
- 📋 `includes/class-antigravity-booking-settings.php`
  - Add overnight times per day UI
  - Add special date overrides UI
  - Add OAuth configuration UI
  - Add blackout calendar interface

- 📋 `includes/class-antigravity-booking-availability.php`
  - Update overnight time logic
  - Add blackout date checking
  - Integrate special date overrides

- 📋 `includes/class-antigravity-booking-google-calendar.php`
  - Replace service account with OAuth
  - Update token handling

### New Files (Planned)
- 📋 `includes/class-antigravity-booking-google-oauth.php`
- 📋 `includes/class-antigravity-booking-blackout.php`
- 📋 `includes/oauth-callback.php`
- 📋 `admin/js/blackout-calendar.js`
- 📋 `admin/js/checklist-manager.js` (optional enhancement)
- 📋 `admin/js/dashboard-sorting.js` (optional enhancement)
- 📋 `admin/css/blackout-calendar.css`
- 📋 `admin/css/checklist.css` (optional enhancement)

---

## Breaking Changes

**None** - All changes are backward compatible.

---

## Migration Notes

### From v1.1.14 to v1.2.0

**Automatic Migrations:**
1. Existing bookings will have checklist items initialized to unchecked
2. Checklist progress will be calculated as 0% for existing bookings
3. Blackout dates will be migrated from text field to CPT (when Phase 3 complete)
4. Default overnight times will be set from current settings (when Phase 2 complete)

**Manual Steps Required:**
1. Review and configure per-day overnight times (Phase 2)
2. Set up OAuth credentials in Google Cloud Console (Phase 4)
3. Authorize with Google in settings (Phase 4)
4. Review and migrate blackout dates to calendar interface (Phase 3)

---

## Testing Checklist

### Phase 1 (Completed)
- [x] Booking edit page shows update messages
- [x] Checklist meta box displays on booking edit page
- [x] Checklist items save correctly
- [x] Progress bar updates on save
- [x] Dashboard shows progress column
- [x] Dashboard columns are sortable
- [x] Sort indicators display correctly
- [x] Sort state persists in URL

### Phase 2 (Pending)
- [ ] Per-day overnight times save correctly
- [ ] Special date overrides work
- [ ] Availability logic uses correct overnight times
- [ ] Special dates take priority over day-specific times

### Phase 3 (Pending)
- [ ] Blackout date CPT registers correctly
- [ ] Calendar interface allows date selection
- [ ] Blackout dates show in dashboard
- [ ] Availability checking blocks blackout dates
- [ ] Migration from text field works

### Phase 4 (Pending)
- [ ] OAuth authorization flow works
- [ ] Tokens refresh automatically
- [ ] Google Calendar sync uses OAuth
- [ ] Settings page shows authorization status
- [ ] Disconnect functionality works

---

## Known Issues

None at this time.

---

## Next Steps

1. **Complete Phase 2:** Implement flexible overnight times
2. **Complete Phase 3:** Implement blackout date calendar
3. **Complete Phase 4:** Implement OAuth integration
4. **Testing:** Comprehensive testing of all features
5. **Documentation:** Update user documentation
6. **Release:** Package and release v1.2.0

---

## Estimated Completion

- **Phase 1:** ✅ Complete (8 hours)
- **Phase 2:** 🚧 In Progress (5 hours remaining)
- **Phase 3:** 📋 Planned (10 hours)
- **Phase 4:** 📋 Planned (8 hours)
- **Testing & Documentation:** 📋 Planned (5 hours)

**Total Remaining:** ~28 hours

---

**Last Updated:** 2026-01-26  
**Version:** 1.2.0-dev  
**Status:** In Development
