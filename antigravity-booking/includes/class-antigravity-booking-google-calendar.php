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

        // Check if Google Client library is available
        if (!class_exists('Google_Client')) {
            error_log('Google Client library not found. Please install via Composer: composer require google/apiclient');
            return null;
        }

        $client = new Google_Client();
        $client->setApplicationName('Simplified Booking');
        $client->setScopes(Google_Service_Calendar::CALENDAR);

        // Try JSON credentials first (new method)
        $credentials_json = get_option('antigravity_gcal_credentials_json');

        if (!empty($credentials_json)) {
            // Parse JSON credentials
            $credentials_data = json_decode($credentials_json, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($credentials_data)) {
                try {
                    $client->setAuthConfig($credentials_data);
                    $this->client = $client;
                    return $this->client;
                } catch (Exception $e) {
                    error_log('Error loading JSON credentials: ' . $e->getMessage());
                    // Fall through to file-based method
                }
            } else {
                error_log('Invalid JSON credentials format');
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
                error_log('Error loading credentials file: ' . $e->getMessage());
            }
        }

        error_log('Google Calendar credentials not configured. Please add JSON credentials in settings.');
        return null;
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
        $start_datetime->setTimeZone(get_option('timezone_string', 'America/Los_Angeles'));
        $event->setStart($start_datetime);

        $end_datetime = new Google_Service_Calendar_EventDateTime();
        $end_datetime->setDateTime(date('c', strtotime($end)));
        $end_datetime->setTimeZone(get_option('timezone_string', 'America/Los_Angeles'));
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
            throw new Exception('Could not initialize Google Client. Check your credentials.');
        }

        try {
            $service = new Google_Service_Calendar($client);
            $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');

            // Try to list 1 event just to see if we have access
            $service->events->listEvents($calendar_id, array('maxResults' => 1));
            return true;
        } catch (Exception $e) {
            throw new Exception('Google Calendar Error: ' . $e->getMessage());
        }
    }
}
