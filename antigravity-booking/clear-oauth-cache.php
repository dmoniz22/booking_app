<?php
/**
 * Clear OAuth Cache Utility
 * Run this file directly to clear all OAuth-related cached data
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

echo '<h1>Clear OAuth Cache</h1>';

// Clear all OAuth options
delete_option('antigravity_gcal_oauth_access_token');
delete_option('antigravity_gcal_oauth_refresh_token');
delete_option('antigravity_gcal_oauth_expires_at');
delete_option('antigravity_gcal_oauth_authorized');

echo '<p style="color: green;">✓ Cleared OAuth tokens and authorization status</p>';

// Clear WordPress object cache
wp_cache_flush();
echo '<p style="color: green;">✓ Flushed WordPress object cache</p>';

// Clear any transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_antigravity%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_antigravity%'");
echo '<p style="color: green;">✓ Cleared plugin transients</p>';

echo '<hr>';
echo '<h2>Current OAuth Settings:</h2>';
echo '<p><strong>Client ID:</strong> ' . (get_option('antigravity_gcal_oauth_client_id') ? 'Set (' . strlen(get_option('antigravity_gcal_oauth_client_id')) . ' characters)' : 'Not set') . '</p>';
echo '<p><strong>Client Secret:</strong> ' . (get_option('antigravity_gcal_oauth_client_secret') ? 'Set (' . strlen(get_option('antigravity_gcal_oauth_client_secret')) . ' characters)' : 'Not set') . '</p>';
echo '<p><strong>Authorized:</strong> ' . (get_option('antigravity_gcal_oauth_authorized') ? 'Yes' : 'No') . '</p>';

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=antigravity-booking-settings') . '" class="button button-primary">Return to Settings</a></p>';
echo '<p><small>You can now try authorizing with Google again.</small></p>';
