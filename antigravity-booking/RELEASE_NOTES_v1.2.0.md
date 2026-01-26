# Release Notes - Version 1.2.0

**Release Date:** 2026-01-26  
**Type:** Major Feature Release  
**Previous Version:** 1.1.14

---

## 🎉 What's New

Version 1.2.0 brings significant improvements to the Simplified Booking plugin with 7 major new features, enhanced user experience, and a complete overhaul of Google Calendar integration.

---

## ✨ New Features

### 1. Booking Approval Checklist ✅
Track booking requirements with a visual checklist system.

**Features:**
- 5 checklist items per booking:
  - Rental Agreement
  - Deposit Received
  - Certificate of Insurance
  - Key Arrangement
  - Deposit Returned
- Auto-calculated progress percentage (0-100%)
- Progress bar on booking edit page
- Progress column in dashboard
- Saves automatically with booking

**Benefits:**
- Systematic tracking of booking requirements
- Visual progress indication
- Reduces manual tracking overhead
- At-a-glance status in dashboard

---

### 2. Dashboard Sorting 📊
Sort bookings by any column with visual indicators.

**Features:**
- Sortable columns: Customer Name, Start Date, Cost, Status
- Click column header to sort
- Toggle between ascending (↑) and descending (↓)
- Visual indicators show current sort state (↕ for unsorted)
- Sort state preserved with all filters

**Benefits:**
- Quickly find specific bookings
- Better data organization
- Improved workflow efficiency
- Works with search and date filters

---

### 3. Flexible Overnight Times ⏰
Configure different overnight times for each day of the week.

**Features:**
- Per-day overnight time configuration
- Different start/end times for Monday-Sunday
- Special date overrides for holidays
- Priority system: Special dates > Day-specific > Default
- Backward compatible with existing settings

**Benefits:**
- Accommodate different schedules per day
- Handle holidays and special events
- More flexible pricing rules
- Better matches real-world needs

---

### 4. Blackout Dates Management 📅
Visual interface for managing blackout dates.

**Features:**
- Custom post type for blackout dates
- AJAX-powered add/remove interface
- Date range support (single day or multi-day)
- Optional reason field
- Integrated with availability checking
- Shows in WordPress admin

**Benefits:**
- Easy blackout date management
- No more manual text entry
- Better organization
- Prevents bookings during blackout periods

---

### 5. OAuth 2.0 Google Calendar 🔐
Simple, secure Google Calendar integration.

**Features:**
- One-click "Authorize with Google" button
- No JSON file management
- Automatic token refresh
- Secure token encryption
- Visual authorization status
- Easy disconnect/reconnect

**Benefits:**
- Simpler setup process
- More reliable authentication
- Works on all hosting environments
- Better security
- User-friendly authorization flow

**Migration:**
- Service account authentication removed
- OAuth is now the only authentication method
- See [`GOOGLE_OAUTH_SETUP.md`](GOOGLE_OAUTH_SETUP.md) for setup instructions

---

### 6. Enhanced Save Functionality 💾
Clear feedback when saving bookings.

**Features:**
- Custom update messages
- Contextual feedback for different actions
- Success confirmations
- Better user experience

---

### 7. Improved Availability Logic 🎯
Smarter booking validation.

**Features:**
- Checks blackout dates (CPT-based)
- Uses flexible overnight times
- Priority-based time resolution
- Better error messages

---

## 🔧 Technical Improvements

### Database Changes
**New Meta Fields (Booking):**
- `_checklist_rental_agreement`
- `_checklist_deposit`
- `_checklist_insurance`
- `_checklist_key_arrangement`
- `_checklist_deposit_returned`
- `_checklist_progress`

**New Options:**
- `antigravity_booking_overnight_times` (array)
- `antigravity_booking_special_hours` (array)
- `antigravity_gcal_oauth_client_id`
- `antigravity_gcal_oauth_client_secret`
- `antigravity_gcal_oauth_access_token`
- `antigravity_gcal_oauth_refresh_token`
- `antigravity_gcal_oauth_expires_at`
- `antigravity_gcal_oauth_authorized`

**New Custom Post Type:**
- `blackout_date` with meta fields:
  - `_blackout_start_date`
  - `_blackout_end_date`
  - `_blackout_reason`

**Removed Options:**
- `antigravity_gcal_credentials_json` (deprecated)
- `antigravity_gcal_credentials_file` (deprecated)

---

## 📁 New Files

- `includes/class-antigravity-booking-google-oauth.php` - OAuth handler
- `includes/class-antigravity-booking-blackout.php` - Blackout date management
- `GOOGLE_OAUTH_SETUP.md` - OAuth setup guide
- `OAUTH_IMPLEMENTATION_GUIDE.md` - Technical implementation details
- `CHANGELOG-v1.2.0.md` - Detailed changelog

---

## 🔄 Upgrade Instructions

### From v1.1.14 to v1.2.0

**Automatic Migrations:**
1. Existing bookings will have checklist items initialized to unchecked (0% progress)
2. Default overnight times will be set from current settings
3. Blackout dates remain in text field (can be migrated to CPT manually)

**Manual Steps Required:**

#### 1. Set Up Google OAuth (Required for Calendar Sync)
- Follow [`GOOGLE_OAUTH_SETUP.md`](GOOGLE_OAUTH_SETUP.md)
- Create OAuth credentials in Google Cloud Console
- Enter credentials in plugin settings
- Authorize with Google

#### 2. Review Overnight Times (Optional)
- Navigate to Settings > Overnight Pricing Rules
- Configure per-day overnight times if needed
- Add special date overrides for holidays

#### 3. Migrate Blackout Dates (Optional)
- Old text-based blackout dates still work
- Optionally create blackout_date posts for better management
- Both systems work simultaneously

---

## ⚠️ Breaking Changes

**Google Calendar Authentication:**
- Service account authentication has been **completely removed**
- OAuth 2.0 is now the **only** authentication method
- You **must** set up OAuth credentials to use Google Calendar sync
- Existing service account credentials will no longer work

**Action Required:**
If you were using Google Calendar sync with service account:
1. Set up OAuth 2.0 credentials (see setup guide)
2. Authorize with Google in plugin settings
3. Calendar sync will resume automatically

---

## 🐛 Bug Fixes

- Fixed booking edit page save confirmation messages
- Improved overnight time calculation logic
- Enhanced availability checking
- Better error handling for calendar sync

---

## 🎯 Performance Improvements

- Optimized dashboard queries
- Efficient sorting without additional database calls
- Cached blackout date checks
- Proactive token refresh (5 min before expiry)

---

## 📚 Documentation

### New Guides
- [`GOOGLE_OAUTH_SETUP.md`](GOOGLE_OAUTH_SETUP.md) - Step-by-step OAuth setup
- [`OAUTH_IMPLEMENTATION_GUIDE.md`](OAUTH_IMPLEMENTATION_GUIDE.md) - Technical details
- [`CHANGELOG-v1.2.0.md`](CHANGELOG-v1.2.0.md) - Complete changelog

### Updated Guides
- Installation guide updated for OAuth
- Troubleshooting guide updated with OAuth issues

---

## 🔒 Security Enhancements

- OAuth tokens encrypted before storage
- State parameter validation (CSRF protection)
- Nonce verification on all AJAX endpoints
- Secure token refresh mechanism
- HTTPS required for OAuth

---

## 🧪 Testing

All features have been tested for:
- ✅ Functionality
- ✅ Backward compatibility
- ✅ Security
- ✅ Performance
- ✅ User experience

---

## 📊 Statistics

- **Features Added:** 7 major features
- **Files Created:** 5 new files
- **Files Modified:** 6 core files
- **Lines of Code:** ~2,000+ added
- **Backward Compatibility:** 100% (except OAuth migration)
- **Breaking Changes:** 1 (OAuth required for calendar sync)

---

## 🙏 Acknowledgments

Thank you to all users who provided feedback on the service account authentication issues. Your input directly led to the OAuth implementation in this release.

---

## 🚀 Getting Started

### For New Installations
1. Install plugin
2. Configure basic settings (hourly rate, timezone)
3. Set up OAuth for Google Calendar (optional)
4. Create booking page with shortcode
5. Start accepting bookings!

### For Existing Users
1. Update to v1.2.0
2. Review new features in settings
3. Set up OAuth for Google Calendar
4. Configure per-day overnight times (optional)
5. Explore new checklist feature

---

## 📞 Support

For issues or questions:
1. Check [`TROUBLESHOOTING.md`](../TROUBLESHOOTING.md)
2. Review [`GOOGLE_OAUTH_SETUP.md`](GOOGLE_OAUTH_SETUP.md)
3. Check WordPress error logs
4. Contact plugin support

---

## 🔮 Future Roadmap

Potential features for v1.3.0:
- Multiple calendar support
- Two-way calendar sync
- Custom checklist items (admin configurable)
- Bulk checklist updates
- Email notifications for checklist completion
- Advanced reporting and analytics

---

**Version:** 1.2.0  
**Release Date:** 2026-01-26  
**Status:** Production Ready  
**Recommended:** Yes - Major improvements over v1.1.14
