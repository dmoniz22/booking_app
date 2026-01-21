<?php
/**
 * Google Calendar Integration
 * Syncs approved bookings to admin's Google Calendar
 *
 * Supports two authentication methods:
 * 1. JSON credentials stored in WordPress options (recommended)
 * 2. Credentials file path (legacy)
 */
class Antigravity_Booking_Google_Calendar
{

    private $client;

    public function __construct()
    {
        add_action('transition_post_status', array($this, 'sync_to_calendar'), 20, 3);
    }

    /**
     * Get Google Client
     *
     * Supports both JSON credentials (stored in options) and file-based credentials
     */
    private function get_client()
    {
        if ($this->client) {
            return $this->client;
        }

        // Try to load Composer autoloader if it exists
        $autoloader = plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        // Check if Google Client library is available
        if (!class_exists('Google_Client')) {
            throw new Exception('Google API Client library not found. Please run "composer require google/apiclient:^2.0" in the plugin directory.');
        }

        $client = new Google_Client();
        $client->setApplicationName('Simplified Booking');
        $client->setScopes(array(Google_Service_Calendar::CALENDAR));

        // Try JSON credentials first (new method)
        $credentials_json = get_option('antigravity_gcal_credentials_json');

        if (!empty($credentials_json)) {
            // WordPress might add slashes to JSON strings
            $credentials_json = wp_unslash($credentials_json);
            $credentials_data = json_decode($credentials_json, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($credentials_data)) {
                // Fix potential private key formatting issues
                if (isset($credentials_data['private_key'])) {
                    $key = $credentials_data['private_key'];

                    // Normalize newlines: handle literal \n strings vs actual newlines
                    $key = str_replace('\\n', "\n", $key);
                    // Ensure the key is trimmed of any accidental whitespace
                    $key = trim($key);

                    // Diagnostic check: point PHP's OpenSSL at it directly to see what it thinks
                    if (function_exists('openssl_pkey_get_private')) {
                        $res = openssl_pkey_get_private($key);
                        if (!$res) {
                            $openssl_err = openssl_error_string();
                            throw new Exception("OpenSSL Error: {$openssl_err}. PHP is unable to parse the private key. Please insure you copied the FULL JSON content without any changes.");
                        }
                    }

                    $credentials_data['private_key'] = $key;
                }

                try {
                    $client->setAuthConfig($credentials_data);
                    $this->client = $client;
                    return $this->client;
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'OpenSSL') !== false) {
                        $msg .= ' (Note: Your server\'s OpenSSL version may have specific formatting requirements for private keys. Try re-downloading the JSON file from Google Console.)';
                    }
                    throw new Exception('Error in Google JSON Authentication: ' . $msg);
                }
            } else {
                throw new Exception('Invalid JSON credentials format. JSON Error: ' . json_last_error_msg());
            }
        }

        // Fall back to file-based credentials (legacy method)
        $credentials_file = get_option('antigravity_gcal_credentials_file');

        if (!empty($credentials_file) && file_exists($credentials_file)) {
            try {
                $client->setAuthConfig($credentials_file);
                $this->client = $client;
                return $this->client;
            } catch (Exception $e) {
                throw new Exception('Error loading credentials file: ' . $e->getMessage());
            }
        }

        throw new Exception('Google Calendar credentials not configured. Please paste your JSON credentials in settings.');
    }

    /**
     * Sync booking to Google Calendar when status changes
     */
    public function sync_to_calendar($new_status, $old_status, $post)
    {
        if ($post->post_type !== 'booking') {
            return;
        }

        // Get which statuses should sync
        $sync_statuses = get_option('antigravity_gcal_sync_statuses', array('approved'));

        // Check if new status should be synced
        if (!in_array($new_status, $sync_statuses)) {
            // If the booking had an event and the status is no longer syncable, delete it
            $existing_event_id = get_post_meta($post->ID, '_gcal_event_id', true);
            if ($existing_event_id) {
                $this->delete_from_calendar($post->ID);
            }
            return;
        }

        $client = $this->get_client();
        if (!$client) {
            return;
        }

        $service = new Google_Service_Calendar($client);
        $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');

        // Get booking details
        $customer_name = get_post_meta($post->ID, '_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_customer_email', true);
        $start = get_post_meta($post->ID, '_booking_start_datetime', true);
        $end = get_post_meta($post->ID, '_booking_end_datetime', true);
        $cost = get_post_meta($post->ID, '_estimated_cost', true);

        // Check if event already exists
        $existing_event_id = get_post_meta($post->ID, '_gcal_event_id', true);

        if ($existing_event_id) {
            // Update existing event
            try {
                $event = $service->events->get($calendar_id, $existing_event_id);
                $this->update_event_details($event, $customer_name, $customer_email, $start, $end, $cost);
                $service->events->update($calendar_id, $existing_event_id, $event);
                error_log("Updated Google Calendar event: {$existing_event_id}");
            } catch (Exception $e) {
                error_log("Error updating Google Calendar event: " . $e->getMessage());
            }
        } else {
            // Create new event
            $event = new Google_Service_Calendar_Event();
            $this->update_event_details($event, $customer_name, $customer_email, $start, $end, $cost);

            try {
                $created_event = $service->events->insert($calendar_id, $event);
                update_post_meta($post->ID, '_gcal_event_id', $created_event->getId());
                error_log("Created Google Calendar event: " . $created_event->getId());
            } catch (Exception $e) {
                error_log("Error creating Google Calendar event: " . $e->getMessage());
            }
        }
    }

    /**
     * Update event details
     */
    private function update_event_details(&$event, $customer_name, $customer_email, $start, $end, $cost)
    {
        $event->setSummary("Booking - {$customer_name}");
        $event->setDescription("Customer: {$customer_name}\nEmail: {$customer_email}\nCost: \${$cost}");

        $start_datetime = new Google_Service_Calendar_EventDateTime();
        $start_datetime->setDateTime(date('c', strtotime($start)));
        $start_datetime->setTimeZone(get_option('antigravity_booking_timezone', 'America/Los_Angeles'));
        $event->setStart($start_datetime);

        $end_datetime = new Google_Service_Calendar_EventDateTime();
        $end_datetime->setDateTime(date('c', strtotime($end)));
        $end_datetime->setTimeZone(get_option('antigravity_booking_timezone', 'America/Los_Angeles'));
        $event->setEnd($end_datetime);

        $attendee = new Google_Service_Calendar_EventAttendee();
        $attendee->setEmail($customer_email);
        $event->setAttendees(array($attendee));
    }

    /**
     * Delete event from calendar (if booking is cancelled)
     */
    public function delete_from_calendar($booking_id)
    {
        $client = $this->get_client();
        if (!$client) {
            return;
        }

        $event_id = get_post_meta($booking_id, '_gcal_event_id', true);
        if (!$event_id) {
            return;
        }

        $service = new Google_Service_Calendar($client);
        $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');

        try {
            $service->events->delete($calendar_id, $event_id);
            delete_post_meta($booking_id, '_gcal_event_id');
            error_log("Deleted Google Calendar event: {$event_id}");
        } catch (Exception $e) {
            error_log("Error deleting Google Calendar event: " . $e->getMessage());
        }
    }

    /**
     * Test Connection
     *
     * @throws Exception If connection fails
     */
    public function test_connection()
    {
        $client = $this->get_client();
        if (!$client) {
            throw new Exception('Could not initialize Google Client. Check your JSON formatting in settings.');
        }

        try {
            $service = new Google_Service_Calendar($client);
            $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');

            // Try to list 1 event just to see if we have access
            $service->events->listEvents($calendar_id, array('maxResults' => 1));
            return true;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '404') !== false || strpos($msg, 'Not Found') !== false) {
                $msg = 'Calendar Not Found. Please ensure: 1) The Calendar ID is correct. 2) You have shared the calendar with the service account email.';
            } elseif (strpos($msg, '403') !== false || strpos($msg, 'Forbidden') !== false) {
                $msg = 'Permission Denied. Please ensure the service account email has "Make changes to events" permissions on your calendar.';
            }
            throw new Exception('Google Calendar Error: ' . $msg);
        }
    }
}
