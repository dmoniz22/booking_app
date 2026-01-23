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
        // Constructor should not have side effects
        // Hook registration moved to init() method
    }

    /**
     * Initialize hooks
     * Call this method after instantiation to register WordPress hooks
     */
    public function init()
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

        // Check if Google Client library is available
        if (!class_exists('Google_Client')) {
            throw new Exception('Google API Client library not found. Please run "composer require google/apiclient:^2.0" in the plugin directory.');
        }

        try {
            error_log('Antigravity Booking: Instantiating Google_Client');
            $client = new Google_Client();
            $client->setApplicationName('Simplified Booking');
            // Use explicit scope string to avoid dependency on service class constants during init
            $client->setScopes(array('https://www.googleapis.com/auth/calendar'));
            error_log('Antigravity Booking: Google_Client instantiated successfully');
        } catch (Throwable $t) {
            error_log('Antigravity Booking: FAILED to instantiate Google_Client: ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());
            throw new Exception('Google Client Initialization Failed: ' . $t->getMessage());
        }

        $auth_error = '';

        // 1. Try file-based credentials first (most reliable in mangled environments)
        $credentials_file = get_option('antigravity_gcal_credentials_file');
        if (!empty($credentials_file)) {
            if (file_exists($credentials_file)) {
                try {
                    error_log('Antigravity Booking: Attempting authentication with file path: ' . $credentials_file);
                    $client->setAuthConfig($credentials_file);
                    $this->client = $client;
                    return $this->client;
                } catch (Exception $e) {
                    $auth_error = 'File Auth: ' . $e->getMessage();
                    error_log('Antigravity Booking: File Auth Failed: ' . $e->getMessage());
                }
            } else {
                $auth_error = 'File not found at: ' . $credentials_file;
                error_log('Antigravity Booking: ' . $auth_error);
            }
        }

        // 2. Try JSON credentials (new method)
        $credentials_json = get_option('antigravity_gcal_credentials_json');

        if (!empty($credentials_json)) {
            error_log('Antigravity Booking: JSON credentials found, attempting to process.');
            // WordPress might add slashes to JSON strings
            $credentials_json = wp_unslash($credentials_json);
            $credentials_data = json_decode($credentials_json, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($credentials_data)) {
                error_log('Antigravity Booking: JSON decoded successfully.');
                // Fix potential private key formatting issues
                if (isset($credentials_data['private_key'])) {
                    error_log('Antigravity Booking: Private key found, normalizing.');
                    $key = $credentials_data['private_key'];

                    // Only normalize if key appears to have escaping issues
                    // Check for literal \n sequences (not actual newlines)
                    if (strpos($key, '\\n') !== false) {
                        error_log('Antigravity Booking: Normalizing escaped newlines in private key');
                        $key = str_replace('\\n', "\n", $key);
                    }
                    
                    // Normalize line endings (Windows to Unix)
                    $key = str_replace("\r\n", "\n", $key);
                    $key = str_replace("\r", "\n", $key);
                    
                    // Ensure proper header format (add newline after header if missing)
                    if (preg_match('/^-----BEGIN PRIVATE KEY-----[^\n]/', $key)) {
                        $key = str_replace('-----BEGIN PRIVATE KEY-----', "-----BEGIN PRIVATE KEY-----\n", $key);
                    }
                    
                    // Ensure proper footer format (add newline before footer if missing)
                    if (preg_match('/[^\n]-----END PRIVATE KEY-----$/', $key)) {
                        $key = str_replace('-----END PRIVATE KEY-----', "\n-----END PRIVATE KEY-----", $key);
                    }
                    
                    // Final trim (safe - only removes leading/trailing whitespace)
                    $key = trim($key);
                    
                    $credentials_data['private_key'] = $key;
                    
                    error_log('Antigravity Booking: Private key normalized successfully');
                }

                try {
                    error_log('Antigravity Booking: Calling setAuthConfig().');
                    $client->setAuthConfig($credentials_data);
                    error_log('Antigravity Booking: setAuthConfig() success.');
                    $this->client = $client;
                    return $this->client;
                } catch (Exception $e) {
                    $auth_error .= ($auth_error ? ' | ' : '') . 'JSON Auth: ' . $e->getMessage();
                    error_log('Antigravity Booking: JSON Auth Failed: ' . $e->getMessage());
                } catch (Throwable $t) {
                    $auth_error .= ($auth_error ? ' | ' : '') . 'JSON Auth Throwable: ' . $t->getMessage();
                    error_log('Antigravity Booking: JSON Auth Throwable: ' . $t->getMessage());
                }
            } else {
                $json_err = json_last_error_msg();
                $auth_error .= ($auth_error ? ' | ' : '') . 'JSON Format: ' . $json_err;
                error_log('Antigravity Booking: JSON Parsing Failed: ' . $json_err);
            }
        }

        // If both failed or are empty
        if ($auth_error) {
            throw new Exception('Connection Failed: ' . $auth_error);
        }

        throw new Exception('Google Calendar credentials not configured. Please paste your JSON in settings or provide a valid file path.');
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

        try {
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
                    error_log("Antigravity Booking: Updated Google Calendar event: {$existing_event_id}");
                } catch (Exception $e) {
                    error_log("Antigravity Booking: Error updating Google Calendar event: " . $e->getMessage());
                }
            } else {
                // Create new event
                $event = new Google_Service_Calendar_Event();
                $this->update_event_details($event, $customer_name, $customer_email, $start, $end, $cost);

                try {
                    $created_event = $service->events->insert($calendar_id, $event);
                    update_post_meta($post->ID, '_gcal_event_id', $created_event->getId());
                    error_log("Antigravity Booking: Created Google Calendar event: " . $created_event->getId());
                } catch (Exception $e) {
                    error_log("Antigravity Booking: Error creating Google Calendar event: " . $e->getMessage());
                }
            }
        } catch (Error $e) {
            error_log("Antigravity Booking: Critical Error during GCal Sync: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Antigravity Booking: Exception during GCal Sync: " . $e->getMessage());
        } catch (Throwable $t) {
            error_log("Antigravity Booking: Throwable during GCal Sync: " . $t->getMessage());
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
        try {
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

            $service->events->delete($calendar_id, $event_id);
            delete_post_meta($booking_id, '_gcal_event_id');
            error_log("Antigravity Booking: Deleted Google Calendar event: {$event_id}");
        } catch (Exception $e) {
            error_log("Antigravity Booking: Error deleting Google Calendar event: " . $e->getMessage());
        } catch (Throwable $t) {
            error_log("Antigravity Booking: Throwable deleting Google Calendar event: " . $t->getMessage());
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
