# OAuth Troubleshooting Guide

**Version:** 1.2.0  
**Date:** 2026-01-28

---

## Error: "The OAuth client was deleted" (Error 401: deleted_client)

### What This Means
Google is saying it doesn't recognize the Client ID you're using. This can happen even if the client exists in Google Cloud Console.

### Common Causes & Solutions

#### 1. Client ID Mismatch
**Problem:** The Client ID in WordPress doesn't match Google Cloud Console

**Solution:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** > **Credentials**
3. Find your OAuth 2.0 Client ID
4. Click the edit icon (pencil)
5. **Copy the Client ID exactly** (use the copy button, don't manually select)
6. Go to WordPress Settings
7. **Clear the Client ID field completely**
8. **Paste the new Client ID** (Ctrl+V / Cmd+V)
9. Do the same for Client Secret
10. Click **Save Settings**
11. Try authorizing again

#### 2. Extra Spaces or Characters
**Problem:** Hidden spaces or characters in the credentials

**Solution:**
1. In WordPress, select all text in Client ID field
2. Delete it completely
3. Paste fresh from Google Cloud Console
4. Check there are no spaces before or after
5. Same for Client Secret
6. Save and try again

#### 3. Wrong OAuth Client Type
**Problem:** You created a different type of OAuth client

**Solution:**
1. In Google Cloud Console > Credentials
2. Verify the client type is **"Web application"**
3. If it says "Desktop app" or "Mobile app", delete it
4. Create a new OAuth client:
   - Type: **Web application**
   - Name: Simplified Booking
   - Authorized redirect URIs: (see below)

#### 4. Client Was Actually Deleted
**Problem:** The OAuth client was deleted and recreated

**Solution:**
1. If you deleted and recreated the OAuth client, you have NEW credentials
2. The old Client ID/Secret no longer work
3. Copy the NEW Client ID and Secret
4. Update in WordPress settings
5. Try again

---

## Redirect URI Configuration

### Correct Redirect URI Format

The redirect URI in Google Cloud Console must **EXACTLY** match what the plugin uses:

```
https://yourdomain.com/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1
```

**Important:**
- Replace `yourdomain.com` with your actual domain
- Must use `https://` (not `http://`)
- Must include `/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1`
- No trailing slashes
- Case sensitive
- Must match exactly

### How to Set Redirect URI

1. Go to Google Cloud Console > Credentials
2. Click your OAuth 2.0 Client ID
3. Under **Authorized redirect URIs**, click **+ ADD URI**
4. Paste the exact URI from your WordPress settings page
5. Click **Save**
6. Wait 5 minutes for changes to propagate
7. Try authorizing again

---

## Step-by-Step Verification

### 1. Verify Google Cloud Console Setup

**Check Project:**
- [ ] Correct project selected in Google Cloud Console
- [ ] Google Calendar API is enabled
- [ ] OAuth consent screen is configured

**Check OAuth Client:**
- [ ] Type is "Web application"
- [ ] Client ID and Secret are visible
- [ ] Redirect URI is added and saved
- [ ] No errors or warnings shown

### 2. Verify WordPress Settings

**Check Credentials:**
- [ ] Client ID field has no extra spaces
- [ ] Client Secret field has no extra spaces
- [ ] Both fields are saved (click Save Settings)
- [ ] Settings page shows the redirect URI to use

**Check Authorization:**
- [ ] "Authorize with Google" button appears
- [ ] Button is clickable (not grayed out)
- [ ] Clicking button redirects to Google

### 3. Test Authorization Flow

1. Click "Authorize with Google"
2. You should see Google's consent screen
3. Select your Google account
4. Review permissions
5. Click "Allow" or "Continue"
6. You should be redirected back to WordPress
7. Settings page should show "Connected to Google Calendar"

---

## Common Error Messages

### "Error 401: invalid_client"
- Client ID is wrong or has extra characters
- Copy fresh from Google Cloud Console

### "Error 401: deleted_client"
- OAuth client was deleted
- Create new OAuth client with new credentials

### "Error 400: redirect_uri_mismatch"
- Redirect URI in Google doesn't match plugin
- Copy exact URI from WordPress settings
- Add to Google Cloud Console

### "Error 403: access_denied"
- You clicked "Cancel" during authorization
- Try authorizing again and click "Allow"

### "Sorry, you are not allowed to access this page"
- Callback page issue (should be fixed now)
- Make sure you're logged in as admin
- Clear browser cache and try again

---

## Debug Checklist

If authorization still fails:

1. **Clear Everything and Start Fresh:**
   ```
   - Delete OAuth client in Google Cloud Console
   - Create new OAuth client (Web application)
   - Copy NEW Client ID and Secret
   - Clear WordPress settings fields
   - Paste new credentials
   - Save settings
   - Update redirect URI in Google
   - Wait 5 minutes
   - Try again
   ```

2. **Verify Redirect URI:**
   - Copy from WordPress settings page
   - Paste into Google Cloud Console
   - Make sure it's EXACTLY the same
   - Check for https:// (not http://)

3. **Check Browser:**
   - Clear browser cache
   - Try in incognito/private mode
   - Try different browser

4. **Check WordPress:**
   - Make sure you're logged in as administrator
   - Check WordPress error logs
   - Verify plugin is activated

---

## Alternative: Create New OAuth Client

If nothing works, create a completely new OAuth client:

1. In Google Cloud Console > Credentials
2. Click **Create Credentials** > **OAuth client ID**
3. Type: **Web application**
4. Name: **Simplified Booking v2** (different name)
5. Add redirect URI from WordPress settings
6. Click **Create**
7. Copy the NEW Client ID and Secret
8. Paste into WordPress (replacing old ones)
9. Save settings
10. Try authorizing

---

## Success Indicators

When OAuth is working correctly:

- ✅ "Authorize with Google" button redirects to Google
- ✅ Google shows consent screen (not error)
- ✅ After clicking "Allow", redirects back to WordPress
- ✅ WordPress shows "Connected to Google Calendar" (green)
- ✅ Token expiration time is shown
- ✅ "Disconnect" button appears

---

## Still Having Issues?

### Check WordPress Error Logs
Location: `wp-content/debug.log`

Look for lines containing:
- "OAuth"
- "Google"
- "antigravity"

### Enable WordPress Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Contact Information
The error message from Google usually tells you exactly what's wrong:
- "invalid_client" = Wrong Client ID
- "deleted_client" = Client was deleted or doesn't exist
- "redirect_uri_mismatch" = Redirect URI doesn't match

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-28  
**Plugin Version:** 1.2.0
