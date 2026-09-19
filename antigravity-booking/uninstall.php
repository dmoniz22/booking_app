<?php
/**
 * Fired when the plugin is deleted from the Plugins screen.
 *
 * Deletes ALL options this plugin created. Note: booking posts and metadata
 * are intentionally NOT deleted — historic bookings may still be referenced
 * (emails, calendars). If you want a full wipe, delete the "booking" and
 * "blackout_date" posts separately (e.g. via wp-cli) before removing this file.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$antigravity_option_names = array(
    // Booking settings
    'antigravity_booking_admin_email',
    'antigravity_booking_admin_message',
    'antigravity_booking_admin_subject',
    'antigravity_booking_approval_message',
    'antigravity_booking_approval_subject',
    'antigravity_booking_available_days',
    'antigravity_booking_blackout_dates',
    'antigravity_booking_customer_instructions',
    'antigravity_booking_cutoff_hours',
    'antigravity_booking_hourly_rate',
    'antigravity_booking_hours_per_day',
    'antigravity_booking_overnight_cutoff',
    'antigravity_booking_overnight_days',
    'antigravity_booking_overnight_enabled',
    'antigravity_booking_overnight_extend',
    'antigravity_booking_overnight_times',
    'antigravity_booking_reminder_1_days',
    'antigravity_booking_reminder_1_message',
    'antigravity_booking_reminder_1_subject',
    'antigravity_booking_reminder_2_hours',
    'antigravity_booking_reminder_2_message',
    'antigravity_booking_reminder_2_subject',
    'antigravity_booking_reminder_message',
    'antigravity_booking_special_hours',
    'antigravity_booking_success_redirect',
    'antigravity_booking_timezone',
    // Google Calendar / OAuth
    'antigravity_gcal_calendar_id',
    'antigravity_gcal_credentials_file',
    'antigravity_gcal_credentials_json',
    'antigravity_gcal_oauth_access_token',
    'antigravity_gcal_oauth_authorized',
    'antigravity_gcal_oauth_client_id',
    'antigravity_gcal_oauth_client_secret',
    'antigravity_gcal_oauth_expires_at',
    'antigravity_gcal_oauth_refresh_token',
    'antigravity_gcal_sync_statuses',
);

foreach ($antigravity_option_names as $option_name) {
    delete_option($option_name);
}

// Clean up scheduled events
wp_clear_scheduled_hook('antigravity_send_reminders');
wp_clear_scheduled_hook('antigravity_check_expired_bookings');
