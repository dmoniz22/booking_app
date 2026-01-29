<?php
/**
 * Google OAuth 2.0 Handler
 * Handles OAuth authentication flow for Google Calendar integration
 */
class Antigravity_Booking_Google_OAuth
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;

    public function __construct()
    {
        // Trim credentials to remove any whitespace
        $this->client_id = trim(get_option('antigravity_gcal_oauth_client_id', ''));
        $this->client_secret = trim(get_option('antigravity_gcal_oauth_client_secret', ''));
        $this->redirect_uri = site_url('/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1');

        // Log for debugging (remove in production)
        if (!empty($this->client_id)) {
            error_log('OAuth Client ID (first 20 chars): ' . substr($this->client_id, 0, 20) . '...');
            error_log('OAuth Redirect URI: ' . $this->redirect_uri);
        }

        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('admin_post_disconnect_google_oauth', array($this, 'handle_disconnect'));
    }

    /**
     * Get authorization URL
     */
    public function get_auth_url()
    {
        // Get fresh credentials from database (in case they were just saved)
        $client_id = trim(get_option('antigravity_gcal_oauth_client_id', ''));

        if (empty($client_id)) {
            return '';
        }

        $redirect_uri = site_url('/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1');

        $params = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('antigravity_oauth_state'),
        );

        // Log the auth URL for debugging
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        error_log('OAuth Auth URL: ' . $auth_url);
        error_log('OAuth Client ID being used: ' . substr($client_id, 0, 20) . '... (length: ' . strlen($client_id) . ')');

        return $auth_url;
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback()
    {
        // Check if this is an OAuth callback
        if (!isset($_GET['oauth_callback']) || !isset($_GET['page']) || $_GET['page'] !== 'antigravity-booking-settings') {
            return;
        }

        // Verify state
        if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'antigravity_oauth_state')) {
            wp_die('Invalid state parameter. Please try authorizing again.');
        }

        // Check for errors
        if (isset($_GET['error'])) {
            $error_msg = sanitize_text_field($_GET['error']);
            wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=' . urlencode($error_msg)));
            exit;
        }

        // Exchange code for tokens
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $result = $this->exchange_code_for_tokens($code);

            if (is_array($result) && isset($result['access_token'])) {
                update_option('antigravity_gcal_oauth_access_token', $result['access_token']);

                if (isset($result['refresh_token'])) {
                    update_option('antigravity_gcal_oauth_refresh_token', $this->encrypt_token($result['refresh_token']));
                }

                $expires_at = time() + (isset($result['expires_in']) ? intval($result['expires_in']) : 3600);
                update_option('antigravity_gcal_oauth_expires_at', $expires_at);
                update_option('antigravity_gcal_oauth_authorized', true);

                wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_success=1'));
                exit;
            } elseif (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                error_log('OAuth Error Redirecting user with message: ' . $error_msg);
                wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=' . urlencode('Token Exchange Error: ' . $error_msg)));
                exit;
            } elseif (is_array($result) && isset($result['error'])) {
                $error_msg = $result['error_description'] ?? $result['error'];
                wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=' . urlencode('Google API Error: ' . $error_msg)));
                exit;
            }
        }

        wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_error=unknown_token_exchange_failure'));
        exit;
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code)
    {
        $params = array(
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        );

        error_log('OAuth Token Exchange Params: ' . print_r(array_merge($params, array('client_secret' => '***')), true));

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => $params,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('OAuth token exchange HTTP error: ' . $response->get_error_message());
            return $response;
        }

        $body_raw = wp_remote_retrieve_body($response);
        error_log('OAuth Token Exchange Response: ' . $body_raw);

        $body = json_decode($body_raw, true);

        if (isset($body['error'])) {
            error_log('OAuth token exchange API error: ' . $body['error']);
            return $body; // Return error array
        }

        return $body;
    }

    /**
     * Refresh access token
     */
    public function refresh_access_token()
    {
        $refresh_token = $this->decrypt_token(get_option('antigravity_gcal_oauth_refresh_token'));

        if (!$refresh_token) {
            error_log('No refresh token available for OAuth');
            return false;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('OAuth token refresh error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            update_option('antigravity_gcal_oauth_access_token', $body['access_token']);
            $expires_at = time() + (isset($body['expires_in']) ? intval($body['expires_in']) : 3600);
            update_option('antigravity_gcal_oauth_expires_at', $expires_at);
            return true;
        }

        if (isset($body['error'])) {
            error_log('OAuth token refresh error: ' . $body['error']);
        }

        return false;
    }

    /**
     * Get valid access token (refresh if needed)
     */
    public function get_access_token()
    {
        $expires_at = get_option('antigravity_gcal_oauth_expires_at', 0);

        // Refresh if token expires in less than 5 minutes
        if (time() > ($expires_at - 300)) {
            $this->refresh_access_token();
        }

        return get_option('antigravity_gcal_oauth_access_token');
    }

    /**
     * Check if OAuth is authorized
     */
    public function is_authorized()
    {
        return (bool) get_option('antigravity_gcal_oauth_authorized', false);
    }

    /**
     * Handle disconnect request
     */
    public function handle_disconnect()
    {
        check_admin_referer('disconnect_google_oauth');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $this->disconnect();

        wp_redirect(admin_url('admin.php?page=antigravity-booking-settings&oauth_disconnected=1'));
        exit;
    }

    /**
     * Disconnect OAuth
     */
    public function disconnect()
    {
        delete_option('antigravity_gcal_oauth_access_token');
        delete_option('antigravity_gcal_oauth_refresh_token');
        delete_option('antigravity_gcal_oauth_expires_at');
        delete_option('antigravity_gcal_oauth_authorized');
    }

    /**
     * Encrypt token for storage
     */
    private function encrypt_token($token)
    {
        if (function_exists('openssl_encrypt')) {
            $key = wp_salt('auth');
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }
        // Fallback (less secure but better than nothing)
        return base64_encode($token);
    }

    /**
     * Decrypt token from storage
     */
    private function decrypt_token($encrypted_token)
    {
        if (empty($encrypted_token)) {
            return '';
        }

        if (function_exists('openssl_decrypt')) {
            $key = wp_salt('auth');
            $data = base64_decode($encrypted_token);
            if ($data === false) {
                return '';
            }
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return $decrypted !== false ? $decrypted : '';
        }
        // Fallback
        return base64_decode($encrypted_token);
    }
}
