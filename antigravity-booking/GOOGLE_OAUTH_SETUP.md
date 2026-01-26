# Google Calendar OAuth 2.0 Setup Guide

**Version:** 1.2.0  
**Date:** 2026-01-26

---

## Overview

This guide walks you through setting up OAuth 2.0 authentication for Google Calendar integration. OAuth provides a simple, secure way to connect your WordPress site to Google Calendar without managing JSON key files.

---

## Prerequisites

- WordPress site with HTTPS enabled (required for OAuth)
- Google account with access to Google Cloud Console
- Simplified Booking plugin v1.2.0 or higher installed

---

## Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **Select a project** dropdown at the top
3. Click **New Project**
4. Enter project name: "Simplified Booking" (or your choice)
5. Click **Create**
6. Wait for project creation to complete

---

## Step 2: Enable Google Calendar API

1. In Google Cloud Console, ensure your new project is selected
2. Navigate to **APIs & Services** > **Library**
3. Search for "Google Calendar API"
4. Click on **Google Calendar API**
5. Click **Enable**
6. Wait for API to be enabled

---

## Step 3: Configure OAuth Consent Screen

1. Navigate to **APIs & Services** > **OAuth consent screen**
2. Select **External** user type (unless you have Google Workspace)
3. Click **Create**

### App Information
- **App name:** Simplified Booking
- **User support email:** Your email address
- **App logo:** (Optional) Upload your logo
- **Application home page:** Your website URL
- **Application privacy policy link:** Your privacy policy URL (if available)
- **Application terms of service link:** Your terms URL (if available)
- **Authorized domains:** Your domain (e.g., `yourdomain.com`)
- **Developer contact information:** Your email address

4. Click **Save and Continue**

### Scopes
5. Click **Add or Remove Scopes**
6. Search for "Google Calendar API"
7. Select: `https://www.googleapis.com/auth/calendar` (Read/write access to Calendars)
8. Click **Update**
9. Click **Save and Continue**

### Test Users (Optional)
10. Add your email as a test user if app is in testing mode
11. Click **Save and Continue**

### Summary
12. Review your settings
13. Click **Back to Dashboard**

---

## Step 4: Create OAuth 2.0 Credentials

1. Navigate to **APIs & Services** > **Credentials**
2. Click **Create Credentials** > **OAuth client ID**
3. Select **Application type:** Web application
4. Enter **Name:** Simplified Booking OAuth

### Authorized Redirect URIs
5. Click **Add URI** under "Authorized redirect URIs"
6. Enter your callback URL:
   ```
   https://yourdomain.com/wp-admin/admin.php?page=antigravity-oauth-callback
   ```
   **Important:** Replace `yourdomain.com` with your actual domain
   **Important:** Must use HTTPS (not HTTP)

7. Click **Create**

### Save Your Credentials
8. A dialog will appear with your credentials
9. **Copy the Client ID** - You'll need this
10. **Copy the Client Secret** - You'll need this
11. Click **OK**

**Important:** Keep these credentials secure. Don't share them publicly.

---

## Step 5: Configure Plugin Settings

1. Log in to your WordPress admin dashboard
2. Navigate to **Simplified Booking** > **Settings**
3. Scroll to **Google Calendar Integration (OAuth 2.0)** section

### Enter OAuth Credentials
4. Paste **Client ID** into the "OAuth Client ID" field
5. Paste **Client Secret** into the "OAuth Client Secret" field
6. Click **Save Settings**

---

## Step 6: Authorize with Google

1. After saving settings, you'll see an **"Authorize with Google"** button
2. Click the button
3. You'll be redirected to Google's authorization page
4. Review the permissions requested:
   - See, edit, share, and permanently delete all calendars you can access using Google Calendar
5. Click **Continue** or **Allow**
6. You'll be redirected back to your WordPress site
7. You should see a success message: "Connected to Google Calendar"

---

## Step 7: Configure Calendar Settings

1. In the same settings page, find **Calendar ID** field
2. Enter your calendar ID:
   - Use `primary` for your main calendar
   - Or enter a specific calendar ID from Google Calendar settings
3. Select which booking statuses to sync (default: Approved only)
4. Click **Save Settings**

---

## Verification

### Check Authorization Status
In the settings page, you should see:
- ✓ **Connected to Google Calendar** (green checkmark)
- Token expiration time
- **Disconnect** button

### Test Calendar Sync
1. Create a test booking
2. Approve the booking
3. Check your Google Calendar
4. The booking should appear as an event

---

## Troubleshooting

### "redirect_uri_mismatch" Error
**Problem:** The redirect URI doesn't match what's configured in Google Cloud Console

**Solution:**
1. Go back to Google Cloud Console > Credentials
2. Edit your OAuth client
3. Verify the redirect URI exactly matches:
   ```
   https://yourdomain.com/wp-admin/admin.php?page=antigravity-oauth-callback
   ```
4. Check for:
   - Correct protocol (HTTPS)
   - Correct domain
   - No trailing slashes
   - Exact path match

### "invalid_client" Error
**Problem:** Client ID or Secret is incorrect

**Solution:**
1. Verify you copied the credentials correctly
2. Check for extra spaces or characters
3. Re-copy from Google Cloud Console if needed

### "access_denied" Error
**Problem:** You clicked "Cancel" or "Deny" during authorization

**Solution:**
1. Click "Authorize with Google" again
2. Click "Allow" when prompted

### Token Refresh Fails
**Problem:** Refresh token is invalid or expired

**Solution:**
1. Click "Disconnect Google Calendar"
2. Click "Authorize with Google" again
3. Complete authorization flow

### Calendar Events Not Syncing
**Problem:** Events aren't appearing in Google Calendar

**Checklist:**
- [ ] OAuth is authorized (green checkmark in settings)
- [ ] Calendar ID is correct
- [ ] Booking status is set to sync (check "Sync Booking Statuses")
- [ ] Booking status is "Approved"
- [ ] Check WordPress error logs for sync errors

---

## Security Best Practices

### Protect Your Credentials
- Never commit Client Secret to version control
- Don't share credentials publicly
- Use environment variables for sensitive data (optional)

### HTTPS Required
- OAuth requires HTTPS in production
- Google will reject HTTP redirect URIs
- Ensure your site has a valid SSL certificate

### Token Security
- Refresh tokens are encrypted before storage
- Access tokens are short-lived (1 hour)
- Tokens auto-refresh before expiration

### Revoke Access
To revoke plugin access to your calendar:
1. Click "Disconnect Google Calendar" in plugin settings
2. Or visit [Google Account Permissions](https://myaccount.google.com/permissions)
3. Find "Simplified Booking" and click "Remove Access"

---

## Advanced Configuration

### Using a Specific Calendar
1. Open [Google Calendar](https://calendar.google.com/)
2. Find the calendar you want to use
3. Click the three dots next to it
4. Select **Settings and sharing**
5. Scroll to **Integrate calendar**
6. Copy the **Calendar ID** (looks like: `abc123@group.calendar.google.com`)
7. Paste into plugin settings

### Multiple Calendars
Currently, the plugin syncs to one calendar. To sync to multiple calendars, you would need to:
1. Create separate calendar IDs
2. Modify the sync logic (custom development)

---

## Maintenance

### Token Refresh
- Access tokens expire after 1 hour
- Plugin automatically refreshes tokens 5 minutes before expiry
- Refresh tokens are long-lived (no manual refresh needed)

### Re-Authorization
You only need to re-authorize if:
- You click "Disconnect"
- You revoke access from Google Account settings
- Refresh token becomes invalid (rare)

---

## Comparison: OAuth vs Service Account

| Feature | OAuth 2.0 | Service Account |
|---------|-----------|-----------------|
| Setup Complexity | ⭐⭐ Easy | ⭐⭐⭐⭐⭐ Complex |
| User Experience | One-click authorize | JSON file management |
| Security | Excellent | Good |
| Token Management | Automatic | Manual |
| Hosting Compatibility | All hosts | Issues on shared hosting |
| Revocation | Easy (Google Account) | Delete JSON file |
| Recommended | ✅ Yes | ❌ No (deprecated) |

---

## Support

### Getting Help
- Check WordPress error logs: `wp-content/debug.log`
- Review plugin settings for error messages
- Verify Google Cloud Console configuration
- Check HTTPS is working on your site

### Common Issues
1. **HTTPS not enabled** - OAuth requires HTTPS
2. **Wrong redirect URI** - Must match exactly
3. **API not enabled** - Enable Google Calendar API
4. **Consent screen not configured** - Complete OAuth consent screen setup

---

## Next Steps

After successful authorization:
1. Test booking creation and approval
2. Verify events appear in Google Calendar
3. Test booking cancellation (event should be removed)
4. Configure email notifications
5. Customize booking checklist items

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-26  
**Plugin Version:** 1.2.0
