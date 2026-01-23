# Installation Guide - Antigravity Booking v1.1.13

**Plugin Version:** 1.1.13  
**Release Date:** 2026-01-22  
**Package:** [`antigravity-booking-v1.1.13.zip`](antigravity-booking-v1.1.13.zip)

---

## Quick Start

### Option 1: WordPress Admin Upload (Recommended)

1. **Backup Current Plugin:**
   - Go to WordPress Admin > Plugins
   - Deactivate "Simplified Booking" plugin
   - Download a backup (optional but recommended)

2. **Upload New Version:**
   - Go to Plugins > Add New
   - Click "Upload Plugin"
   - Choose [`antigravity-booking-v1.1.13.zip`](antigravity-booking-v1.1.13.zip)
   - Click "Install Now"
   - Click "Activate Plugin"

3. **Verify Installation:**
   - Go to Plugins page
   - Confirm version shows **1.1.13**
   - Check that all settings are preserved

---

### Option 2: FTP/SFTP Upload

1. **Backup Current Plugin:**
   ```bash
   # Via SSH
   cd wp-content/plugins
   mv antigravity-booking antigravity-booking-backup-$(date +%Y%m%d)
   ```

2. **Extract and Upload:**
   - Extract `antigravity-booking-v1.1.13.zip` on your computer
   - Upload the `antigravity-booking` folder to `wp-content/plugins/`
   - Via FTP, SFTP, or file manager

3. **Activate:**
   - Go to WordPress Admin > Plugins
   - Activate "Simplified Booking"

---

### Option 3: SSH/Command Line

```bash
# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins

# Backup current version
mv antigravity-booking antigravity-booking-backup-$(date +%Y%m%d)

# Upload and extract new version
# (Upload antigravity-booking-v1.1.13.zip first, then:)
unzip antigravity-booking-v1.1.13.zip

# Set proper permissions
chown -R www-data:www-data antigravity-booking
chmod -R 755 antigravity-booking

# Activate via WP-CLI (if available)
wp plugin activate antigravity-booking
```

---

## What's New in v1.1.13

### Critical Fixes

✅ **Rate Limiting:** Increased from 10 to 30 requests per 15 minutes  
✅ **Booking Approval:** Fixed critical error on status change  
✅ **Google Calendar:** Fixed Status 500 error on connection test  
✅ **Private Key:** Improved normalization for better reliability

See [`CHANGELOG-v1.1.13.md`](antigravity-booking/CHANGELOG-v1.1.13.md) for complete details.

---

## Post-Installation Testing

### 1. Test Rate Limiting
- [ ] Open booking form
- [ ] Check availability for 5+ different dates
- [ ] Submit a test booking
- [ ] Verify no "too many requests" error

### 2. Test Booking Approval
- [ ] Create a test booking (status: Pending Review)
- [ ] Go to Simplified Booking > Dashboard
- [ ] Click "Approve" button
- [ ] Verify no critical error
- [ ] Verify status changes to "Approved"
- [ ] Verify redirect to dashboard with success message

### 3. Test Google Calendar (if configured)
- [ ] Go to Simplified Booking > Settings
- [ ] Scroll to Google Calendar section
- [ ] Click "Test Connection"
- [ ] Verify success message (no Status 500)
- [ ] Approve a booking
- [ ] Check Google Calendar for new event

### 4. Test Bulk Actions
- [ ] Select multiple bookings
- [ ] Use bulk approve action
- [ ] Verify no errors
- [ ] Verify all statuses updated

---

## Troubleshooting

### Issue: Plugin won't activate

**Solution:**
1. Check PHP version (requires 7.4+)
2. Check WordPress version (requires 5.0+)
3. Check error logs: `wp-content/debug.log`

### Issue: Settings are lost

**Solution:**
Settings should be preserved automatically. If lost:
1. Restore from backup
2. Reconfigure settings manually

### Issue: Google Calendar still not working

**Solution:**
1. Re-paste service account JSON
2. Click "Test Connection"
3. Check error logs for specific error
4. Verify calendar is shared with service account email
5. Verify service account has "Make changes to events" permission

### Issue: Still getting "too many requests"

**Solution:**
1. Verify version is 1.1.13 (check Plugins page)
2. Clear WordPress object cache
3. Clear browser cache
4. If still occurring, increase limit further:
   - Edit `includes/class-antigravity-booking-api.php`
   - Line 25: Change `30` to `50`

---

## Rollback Instructions

If you need to rollback to the previous version:

### Via WordPress Admin
1. Deactivate plugin
2. Delete plugin
3. Upload previous version
4. Activate

### Via SSH
```bash
cd wp-content/plugins
rm -rf antigravity-booking
mv antigravity-booking-backup-YYYYMMDD antigravity-booking
```

Then activate via WordPress Admin.

---

## File Integrity Check

After installation, verify these files were updated:

```bash
# Check version in main file
grep "Version:" antigravity-booking/antigravity-booking.php
# Should show: Version: 1.1.13

# Check modified files exist
ls -l antigravity-booking/includes/class-antigravity-booking-api.php
ls -l antigravity-booking/includes/class-antigravity-booking-dashboard.php
ls -l antigravity-booking/includes/class-antigravity-booking-google-calendar.php
ls -l antigravity-booking/includes/class-antigravity-booking.php
ls -l antigravity-booking/includes/class-antigravity-booking-settings.php
```

---

## Support Files Included

- **[`CHANGELOG-v1.1.13.md`](antigravity-booking/CHANGELOG-v1.1.13.md)** - Complete changelog
- **[`FIXES_IMPLEMENTED.md`](FIXES_IMPLEMENTED.md)** - Summary of all fixes
- **[`plans/`](plans/)** - Technical documentation and analysis

---

## Security Notes

- ✅ All user input is sanitized
- ✅ All output is escaped
- ✅ Nonce verification on admin actions
- ✅ Capability checks on privileged operations
- ✅ No SQL injection vulnerabilities
- ✅ No XSS vulnerabilities

---

## Performance Notes

- ✅ Optimized for WordPress 5.0+
- ✅ Compatible with PHP 7.4 - 8.3
- ✅ No database schema changes
- ✅ Minimal performance impact
- ✅ Works with caching plugins

---

## Compatibility

**WordPress:** 5.0 or higher  
**PHP:** 7.4 or higher  
**MySQL:** 5.6 or higher  
**Tested with:**
- WordPress 6.0+
- PHP 8.2
- MySQL 8.0

---

## Next Steps

After successful installation:

1. **Monitor for 24 hours** - Check error logs for any issues
2. **Test all features** - Run through the testing checklist above
3. **Update documentation** - If you have custom documentation
4. **Train users** - If needed, inform users of any changes

---

## Getting Help

If you encounter issues:

1. **Check error logs:** `wp-content/debug.log`
2. **Enable debug mode:** Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
3. **Review documentation:** See files in `/plans/` directory
4. **Check common issues:** See Troubleshooting section above

---

## Success Criteria

Installation is successful when:

- ✅ Plugin version shows 1.1.13
- ✅ All settings are preserved
- ✅ Bookings can be created without rate limit errors
- ✅ Booking approval works without critical errors
- ✅ Google Calendar test connection works (if configured)
- ✅ No errors in debug log

---

**Installation Package:** [`antigravity-booking-v1.1.13.zip`](antigravity-booking-v1.1.13.zip) (43 MB)  
**Installation Time:** 5-10 minutes  
**Downtime:** None (if using proper deployment process)  
**Risk Level:** Low (backward compatible, no breaking changes)
