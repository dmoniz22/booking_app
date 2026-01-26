# OAuth 2.0 Google Calendar Integration Guide

**Version:** 1.2.0  
**Status:** Implementation Guide  
**Date:** 2026-01-26

---

## Overview

This guide outlines the implementation of OAuth 2.0 authentication for Google Calendar integration, replacing the current service account method. OAuth provides a simpler, more secure, and user-friendly authentication flow.

---

## Why OAuth Instead of Service Account?

### Current Issues with Service Account
- Complex JSON key file management
- Difficult setup process for non-technical users
- Authentication errors on shared hosting
- Security concerns with key file storage
- No user-friendly authorization flow

### Benefits of OAuth 2.0
- One-click "Authorize with Google" button
- No JSON file management
- Automatic token refresh
- Better error handling
- Works reliably on all hosting environments
- User can revoke access anytime from Google account

---

## Implementation Status

**Note:** OAuth implementation has been designed but not yet coded due to the requirement for external Google Cloud Console configuration and testing environment. The following sections provide complete implementation specifications.

---

## Architecture

### OAuth Flow Diagram

```
1. Admin clicks "Authorize with Google" button
   ↓
2. Redirect to Google OAuth consent screen
   ↓
3. User authorizes calendar access
   ↓
4. Google redirects back to callback URL with authorization code
   ↓
5. Plugin exchanges code for access token + refresh token
   ↓
6. Tokens stored securely in WordPress options
   ↓
7. Calendar sync enabled automatically
```

---

## Required Files

### 1. OAuth Handler Class
**File:** `includes/class-antigravity-booking-google-oauth.php`

```php
<?php
/**
 * Google OAuth 2.0 Handler
 */
class Antigravity_Booking_Google_OAuth
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        $this->client_id = get_option('antigravity_gcal_oauth_client_id');
        $this->client_secret = get_option('antigravity_gcal_oauth_client_secret');
        $this->redirect_uri = admin_url('admin.php?page=antigravity-oauth-callback');
        
        add_action('admin_init', array($this, 'handle_oauth_callback'));
    }
    
    /**
     * Get authorization URL
     */
    public function get_auth_url() {
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('antigravity_oauth_state'),
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'antigravity-oauth-callback') {
            return;
        }
        
        // Verify state
        if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'antigravity_oauth_state')) {
            wp_die('Invalid state parameter');
        }
        
        // Check for errors
        if (isset($_GET['error'])) {
            wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=' . urlencode($_GET['error'])));
            exit;
        }
        
        // Exchange code for tokens
        if (isset($_GET['code'])) {
            $tokens = $this->exchange_code_for_tokens($_GET['code']);
            
            if ($tokens) {
                update_option('antigravity_gcal_oauth_access_token', $tokens['access_token']);
                update_option('antigravity_gcal_oauth_refresh_token', $this->encrypt_token($tokens['refresh_token']));
                update_option('antigravity_gcal_oauth_expires_at', time() + $tokens['expires_in']);
                update_option('antigravity_gcal_oauth_authorized', true);
                
                wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_success=1'));
                exit;
            }
        }
        
        wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=token_exchange_failed'));
        exit;
    }
    
    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code) {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }
    
    /**
     * Refresh access token
     */
    public function refresh_access_token() {
        $refresh_token = $this->decrypt_token(get_option('antigravity_gcal_oauth_refresh_token'));
        
        if (!$refresh_token) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            update_option('antigravity_gcal_oauth_access_token', $body['access_token']);
            update_option('antigravity_gcal_oauth_expires_at', time() + $body['expires_in']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    public function get_access_token() {
        $expires_at = get_option('antigravity_gcal_oauth_expires_at', 0);
        
        // Refresh if token expires in less than 5 minutes
        if (time() > ($expires_at - 300)) {
            $this->refresh_access_token();
        }
        
        return get_option('antigravity_gcal_oauth_access_token');
    }
    
    /**
     * Disconnect OAuth
     */
    public function disconnect() {
        delete_option('antigravity_gcal_oauth_access_token');
        delete_option('antigravity_gcal_oauth_refresh_token');
        delete_option('antigravity_gcal_oauth_expires_at');
        delete_option('antigravity_gcal_oauth_authorized');
    }
    
    /**
     * Encrypt token for storage
     */
    private function encrypt_token($token) {
        if (function_exists('openssl_encrypt')) {
            $key = wp_salt('auth');
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }
        return base64_encode($token); // Fallback (less secure)
    }
    
    /**
     * Decrypt token from storage
     */
    private function decrypt_token($encrypted_token) {
        if (function_exists('openssl_decrypt')) {
            $key = wp_salt('auth');
            $data = base64_decode($encrypted_token);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        }
        return base64_decode($encrypted_token); // Fallback
    }
}
```

---

## Settings Page Integration

### Add OAuth Fields to Settings

```php
// In class-antigravity-booking-settings.php register_settings()

// OAuth Client ID
add_settings_field(
    'antigravity_gcal_oauth_client_id',
    'OAuth Client ID',
    array($this, 'render_oauth_client_id_field'),
    'antigravity-booking-settings',
    'antigravity_booking_gcal'
);
register_setting('antigravity_booking_settings', 'antigravity_gcal_oauth_client_id', array(
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
));

// OAuth Client Secret
add_settings_field(
    'antigravity_gcal_oauth_client_secret',
    'OAuth Client Secret',
    array($this, 'render_oauth_client_secret_field'),
    'antigravity-booking-settings',
    'antigravity_booking_gcal'
);
register_setting('antigravity_booking_settings', 'antigravity_gcal_oauth_client_secret', array(
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
));
```

### Render OAuth UI

```php
public function render_oauth_client_id_field() {
    $value = get_option('antigravity_gcal_oauth_client_id', '');
    ?>
    <input type="text" name="antigravity_gcal_oauth_client_id" 
           value="<?php echo esc_attr($value); ?>" class="regular-text">
    <p class="description">OAuth 2.0 Client ID from Google Cloud Console</p>
    <?php
}

public function render_oauth_client_secret_field() {
    $value = get_option('antigravity_gcal_oauth_client_secret', '');
    $authorized = get_option('antigravity_gcal_oauth_authorized', false);
    ?>
    <input type="password" name="antigravity_gcal_oauth_client_secret" 
           value="<?php echo esc_attr($value); ?>" class="regular-text">
    <p class="description">OAuth 2.0 Client Secret from Google Cloud Console</p>
    
    <?php if ($authorized): ?>
        <p style="color: green;">✓ Authorized with Google Calendar</p>
        <button type="button" class="button" id="disconnect-google">Disconnect</button>
    <?php else: ?>
        <?php if ($value): ?>
            <a href="<?php echo esc_url($this->get_oauth_auth_url()); ?>" 
               class="button button-primary">Authorize with Google</a>
        <?php else: ?>
            <p style="color: orange;">⚠ Enter Client ID and Secret above, then save settings to authorize</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}
```

---

## Google Cloud Console Setup

### Step 1: Create OAuth 2.0 Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project (or create new one)
3. Navigate to **APIs & Services** > **Credentials**
4. Click **Create Credentials** > **OAuth client ID**
5. Select **Web application**
6. Add authorized redirect URI:
   ```
   https://yoursite.com/wp-admin/admin.php?page=antigravity-oauth-callback
   ```
7. Click **Create**
8. Copy **Client ID** and **Client Secret**

### Step 2: Enable Google Calendar API

1. Navigate to **APIs & Services** > **Library**
2. Search for "Google Calendar API"
3. Click **Enable**

### Step 3: Configure OAuth Consent Screen

1. Navigate to **APIs & Services** > **OAuth consent screen**
2. Select **External** (unless using Google Workspace)
3. Fill in required fields:
   - App name
   - User support email
   - Developer contact email
4. Add scope: `https://www.googleapis.com/auth/calendar`
5. Save and continue

---

## Migration from Service Account

### Automatic Migration

When OAuth is enabled, the plugin should:

1. Check if service account credentials exist
2. Display migration notice in admin
3. Allow both methods to coexist temporarily
4. Provide "Switch to OAuth" button
5. After successful OAuth authorization, deprecate service account

### Migration Code

```php
public function check_migration_needed() {
    $has_service_account = get_option('antigravity_gcal_credentials_json') || 
                          get_option('antigravity_gcal_credentials_file');
    $has_oauth = get_option('antigravity_gcal_oauth_authorized');
    
    if ($has_service_account && !$has_oauth) {
        add_action('admin_notices', array($this, 'show_migration_notice'));
    }
}

public function show_migration_notice() {
    ?>
    <div class="notice notice-info">
        <p><strong>Google Calendar Integration Update Available</strong></p>
        <p>We recommend switching to OAuth 2.0 for easier and more reliable Google Calendar integration.</p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=antigravity-booking-settings#oauth'); ?>" 
               class="button button-primary">Switch to OAuth</a>
            <button type="button" class="button" onclick="this.parentElement.parentElement.remove()">Dismiss</button>
        </p>
    </div>
    <?php
}
```

---

## Security Considerations

### Token Storage
- Refresh tokens MUST be encrypted before storage
- Use WordPress `wp_salt('auth')` as encryption key
- Access tokens can be stored in plain text (short-lived)
- Never expose tokens in JavaScript or HTML

### State Parameter
- Always verify state parameter in callback
- Use WordPress nonces for state generation
- Prevents CSRF attacks

### HTTPS Required
- OAuth requires HTTPS in production
- Redirect URI must use HTTPS
- Google will reject HTTP redirect URIs

---

## Testing Checklist

- [ ] OAuth authorization flow completes successfully
- [ ] Access token is stored correctly
- [ ] Refresh token is encrypted
- [ ] Token refresh works automatically
- [ ] Calendar events sync after authorization
- [ ] Disconnect button works
- [ ] Re-authorization works after disconnect
- [ ] Error handling displays user-friendly messages
- [ ] Migration notice appears for service account users
- [ ] Both methods can coexist during migration

---

## Troubleshooting

### "redirect_uri_mismatch" Error
- Verify redirect URI in Google Cloud Console matches exactly
- Check for trailing slashes
- Ensure HTTPS is used

### "invalid_client" Error
- Verify Client ID and Secret are correct
- Check if OAuth client is enabled in Google Cloud Console

### Token Refresh Fails
- Check if refresh token is stored correctly
- Verify encryption/decryption works
- Ensure Client Secret hasn't changed

---

## Future Enhancements

1. **Multiple Calendar Support** - Allow syncing to multiple calendars
2. **Selective Sync** - Choose which booking statuses to sync
3. **Two-Way Sync** - Import events from Google Calendar
4. **Conflict Detection** - Warn about calendar conflicts
5. **Batch Operations** - Sync multiple bookings at once

---

## Implementation Timeline

**Estimated Time:** 8-10 hours

1. Create OAuth handler class (2 hours)
2. Update settings page UI (2 hours)
3. Implement callback handling (2 hours)
4. Update Google Calendar class to use OAuth (2 hours)
5. Testing and debugging (2 hours)

---

## Conclusion

OAuth 2.0 provides a significantly better user experience compared to service accounts. While the initial implementation requires more code, the long-term benefits in usability, security, and reliability make it the preferred authentication method for Google Calendar integration.

**Status:** Ready for implementation when Google Cloud Console credentials are available.

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-26  
**Author:** Development Team
