# Troubleshooting Guide - Antigravity Booking v1.1.13

**If you're still experiencing issues after uploading v1.1.13**

---

## 🔍 Step 1: Verify Fixes Were Applied

### Run Diagnostic Check

1. **Upload diagnostic script:**
   - File is located at: `antigravity-booking/diagnostic-check.php`
   - It's already included in the v1.1.13 ZIP file

2. **Access the diagnostic page:**
   - Go to: `https://yoursite.com/wp-content/plugins/antigravity-booking/diagnostic-check.php`
   - Replace `yoursite.com` with your actual domain

3. **Check results:**
   - ✅ All green = Fixes applied correctly
   - ❌ Any red = Files didn't upload correctly

### If Diagnostic Shows Failures

**The files didn't upload correctly.** Try this:

1. **Deactivate plugin** in WordPress Admin
2. **Delete plugin folder** via FTP/SFTP:
   ```
   wp-content/plugins/antigravity-booking/
   ```
3. **Re-upload fresh** from the ZIP file
4. **Activate plugin**
5. **Run diagnostic again**

---

## 🔴 Issue: Google Calendar Still Shows Status 500

### Possible Causes

#### Cause #1: Caching Issue
**Solution:**
```bash
# Clear WordPress object cache
wp cache flush

# Or via plugin settings
# Go to your caching plugin and clear all caches
```

#### Cause #2: Google API Client Not Loaded
**Check:**
1. Go to diagnostic page (see above)
2. Look for "Google_Client Class" check
3. If it fails, the vendor directory is missing

**Solution:**
```bash
# SSH into your server
cd /path/to/wp-content/plugins/antigravity-booking
composer install
```

Or manually upload the `vendor/` directory from the ZIP file.

#### Cause #3: PHP Error in AJAX Handler
**Check error logs:**
```bash
# View last 50 lines of error log
tail -50 wp-content/debug.log
```

**Look for:**
- "Antigravity Booking: AJAX Connection Test"
- Any PHP fatal errors
- Google API errors

**Common errors:**
- "Class 'Google_Client' not found" → Vendor directory missing
- "Call to undefined method" → Old version still cached
- "Private key is invalid" → JSON formatting issue

#### Cause #4: Old Files Still Cached

**Solution:**
1. **Deactivate and reactivate** the plugin
2. **Clear PHP opcache:**
   ```php
   // Add to wp-config.php temporarily
   opcache_reset();
   ```
3. **Restart PHP-FPM** (if applicable):
   ```bash
   sudo systemctl restart php8.2-fpm
   ```

---

## 🔴 Issue: Critical Error on Booking Approval

### Possible Causes

#### Cause #1: Output Buffering Not Applied
**Check:**
1. Run diagnostic script
2. Look for "Output Buffering Fix (Dashboard)" check
3. If it fails, files didn't upload

**Solution:** Re-upload plugin files (see above)

#### Cause #2: Google Calendar Sync Outputting Errors
**Check error logs:**
```bash
tail -50 wp-content/debug.log | grep "Antigravity Booking"
```

**Look for:**
- "Error creating Google Calendar event"
- "Error updating Google Calendar event"
- Any exceptions during sync

**Solution:**
If Google Calendar is causing issues, temporarily disable it:
1. Go to Settings > Google Calendar
2. Clear the credentials JSON field
3. Save settings
4. Try approving a booking again

#### Cause #3: Another Plugin Conflict
**Test:**
1. Deactivate all other plugins
2. Try approving a booking
3. If it works, reactivate plugins one by one to find the conflict

**Common conflicts:**
- Other booking plugins
- Calendar plugins
- Email plugins
- Caching plugins

#### Cause #4: Theme Conflict
**Test:**
1. Switch to a default WordPress theme (Twenty Twenty-Four)
2. Try approving a booking
3. If it works, your theme has a conflict

---

## 🔍 Debugging Steps

### Enable WordPress Debug Mode

Add to `wp-config.php` (before "That's all, stop editing!"):

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Check Error Logs

```bash
# View real-time errors
tail -f wp-content/debug.log

# Or view last 100 lines
tail -100 wp-content/debug.log
```

### Test Google Calendar Manually

Create a test PHP file: `test-gcal.php`

```php
<?php
require_once 'wp-load.php';
require_once 'wp-content/plugins/antigravity-booking/vendor/autoload.php';

echo "Testing Google Calendar...\n";

try {
    $client = new Google_Client();
    echo "✓ Google_Client instantiated\n";
    
    $client->setApplicationName('Test');
    $client->setScopes(['https://www.googleapis.com/auth/calendar']);
    echo "✓ Scopes set\n";
    
    // Get credentials from WordPress options
    $json = get_option('antigravity_gcal_credentials_json');
    if (empty($json)) {
        die("✗ No credentials found in WordPress options\n");
    }
    
    $json = wp_unslash($json);
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("✗ JSON parsing failed: " . json_last_error_msg() . "\n");
    }
    
    echo "✓ JSON parsed successfully\n";
    
    $client->setAuthConfig($data);
    echo "✓ Auth config set\n";
    
    $service = new Google_Service_Calendar($client);
    echo "✓ Calendar service created\n";
    
    $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');
    $events = $service->events->listEvents($calendar_id, ['maxResults' => 1]);
    
    echo "✓ SUCCESS! Connected to Google Calendar\n";
    echo "Calendar ID: $calendar_id\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
```

Run via: `php test-gcal.php`

---

## 🔧 Alternative Google Calendar Integration

If the current integration continues to fail, here are alternatives:

### Option 1: Use OAuth Instead of Service Account

**Pros:**
- More user-friendly
- No JSON key management
- Better for single-user scenarios

**Cons:**
- Requires user to authorize
- Token expires and needs refresh

**Implementation:** Would require significant code changes

### Option 2: Use Zapier/Make.com Integration

**Pros:**
- No code changes needed
- Visual workflow builder
- Can integrate with many services

**Cons:**
- Requires third-party service
- May have monthly costs
- Adds latency

**How:**
1. Create webhook in Zapier/Make
2. Modify plugin to send booking data to webhook
3. Zapier creates Google Calendar event

### Option 3: Use Google Calendar API Directly (Simplified)

**Pros:**
- Simpler authentication
- Less dependencies
- More control

**Cons:**
- Requires rewriting integration
- More maintenance

### Option 4: Disable Google Calendar Sync

**Temporary solution:**
1. Go to Settings > Google Calendar
2. Clear credentials
3. Save settings
4. Bookings will work without calendar sync
5. Manually add events to calendar

---

## 🐛 Common Error Messages & Solutions

### "Class 'Google_Client' not found"
**Cause:** Vendor directory missing  
**Solution:** Run `composer install` in plugin directory

### "Private key is invalid"
**Cause:** JSON formatting issue  
**Solution:** 
1. Download fresh JSON from Google Cloud Console
2. Open in text editor (not Word)
3. Copy entire contents
4. Paste in settings (don't modify)

### "Calendar not found (404)"
**Cause:** Wrong calendar ID or not shared  
**Solution:**
1. Verify calendar ID (from Google Calendar settings)
2. Share calendar with service account email
3. Give "Make changes to events" permission

### "Permission denied (403)"
**Cause:** Service account lacks permissions  
**Solution:**
1. Go to Google Calendar
2. Settings > Share with specific people
3. Add service account email
4. Set permission to "Make changes to events"

### "Headers already sent"
**Cause:** Output before redirect  
**Solution:** Should be fixed in v1.1.13. If persisting:
1. Check if other plugins are outputting
2. Check theme functions.php for echo/print statements
3. Disable other plugins temporarily

---

## 📊 System Requirements Check

### PHP Version
```bash
php -v
# Should be 7.4 or higher
```

### WordPress Version
```bash
wp core version
# Should be 5.0 or higher
```

### Composer Installed
```bash
composer --version
# If not installed, install from getcomposer.org
```

### File Permissions
```bash
# Plugin directory should be readable
ls -la wp-content/plugins/antigravity-booking/
# Files: 644, Directories: 755
```

---

## 🆘 Emergency Fixes

### If Nothing Works

#### Quick Fix #1: Disable Google Calendar Completely

Edit `includes/class-antigravity-booking.php`:

```php
// Line 39 - Comment out Google Calendar
// $this->google_calendar = new Antigravity_Booking_Google_Calendar();
// $this->google_calendar->init();
```

This will disable calendar sync but allow bookings to work.

#### Quick Fix #2: Disable Rate Limiting Completely

Edit `includes/class-antigravity-booking-api.php`:

```php
// Line 263 and 424 - Comment out rate limiting
// if ($this->is_rate_limited()) {
//     wp_send_json_error('Too many requests...');
// }
```

This will remove rate limiting entirely.

---

## 📞 Getting More Help

### Information to Provide

When seeking help, please provide:

1. **Diagnostic check results** (screenshot or copy/paste)
2. **Error log entries** (last 50 lines from debug.log)
3. **PHP version** (`php -v`)
4. **WordPress version**
5. **Active plugins list**
6. **Theme name**
7. **Exact error messages** (screenshots)

### Where to Check

1. **WordPress debug.log:** `wp-content/debug.log`
2. **PHP error log:** Usually `/var/log/php/error.log` or `/var/log/apache2/error.log`
3. **Browser console:** Press F12, check Console tab
4. **Network tab:** Press F12, check Network tab for failed requests

---

## 🔄 Clean Reinstall Procedure

If all else fails, try a completely clean reinstall:

1. **Export bookings** (if you have any):
   - Go to Dashboard > Export to CSV

2. **Deactivate plugin**

3. **Delete plugin completely:**
   ```bash
   rm -rf wp-content/plugins/antigravity-booking
   ```

4. **Clear all caches:**
   - WordPress object cache
   - Page cache
   - Browser cache
   - PHP opcache

5. **Upload fresh v1.1.13 ZIP**

6. **Activate plugin**

7. **Reconfigure settings**

8. **Run diagnostic check**

9. **Test functionality**

---

## ✅ Success Indicators

You'll know everything is working when:

- ✅ Diagnostic check shows all green
- ✅ Can create bookings without rate limit errors
- ✅ Can approve bookings without critical errors
- ✅ Google Calendar test shows success (if configured)
- ✅ No errors in debug.log

---

**Last Updated:** 2026-01-22  
**Version:** 1.1.13  
**Status:** Active Support
