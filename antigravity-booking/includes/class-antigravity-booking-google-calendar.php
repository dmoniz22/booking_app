<?php
/**
 * Google Calendar Integration
 * Syncs approved bookings to admin's Google Calendar using OAuth 2.0 via REST API
 */
class Antigravity_Booking_Google_Calendar
{
    private $oauth;

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
     * Helper to make Google Calendar API requests
     */
    private function make_api_request($method, $endpoint, $body = null)
    {
        // Get OAuth instance
        if (!$this->oauth) {
            $this->oauth = new Antigravity_Booking_Google_OAuth();
        }

        // Get valid access token (will refresh if needed)
        $access_token = $this->oauth->get_access_token();

        if (!$access_token) {
            throw new Exception('No valid access token available. Please re-authorize in settings.');
        }

        $base_url = 'https://www.googleapis.com/calendar/v3';
        $url = $base_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($body) {
            $args['body'] = json_encode($body);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Antigravity Booking: GCal API Request: $method $url");
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Google API Request Failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code >= 400) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API Error';
            throw new Exception("Google API Error ($response_code): $error_msg");
        }

        return $data;
    }

    /**
     * Sync booking to Google Calendar when status changes
     */
    public function sync_to_calendar($new_status, $old_status, $post)
    {
        error_log("Antigravity Booking: Sync triggered. Post ID: {$post->ID}, Type: {$post->post_type}, New Status: {$new_status}, Old: {$old_status}");

        if ($post->post_type !== 'booking') {
            return;
        }

        // Get which statuses should sync
        $sync_statuses = get_option('antigravity_gcal_sync_statuses', array('approved'));
        error_log("Antigravity Booking: Configured sync statuses: " . print_r($sync_statuses, true));

        // Check if new status should be synced
        if (!in_array($new_status, $sync_statuses)) {
            error_log("Antigravity Booking: Status '{$new_status}' is not in sync list. Checking for deletion.");
            // If the booking had an event and the status is no longer syncable, delete it
            $existing_event_id = get_post_meta($post->ID, '_gcal_event_id', true);
            if ($existing_event_id) {
                $this->delete_from_calendar($post->ID);
            }
            return;
        }

        error_log("Antigravity Booking: Status '{$new_status}' match. Proceeding to sync.");

        try {
            $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');

            // Get booking details
            $customer_name = get_post_meta($post->ID, '_customer_name', true);
            $customer_email = get_post_meta($post->ID, '_customer_email', true);
            $start = get_post_meta($post->ID, '_booking_start_datetime', true);
            $end = get_post_meta($post->ID, '_booking_end_datetime', true);
            $cost = get_post_meta($post->ID, '_estimated_cost', true);

            // Timezone
            $timezone = get_option('antigravity_booking_timezone', 'America/Los_Angeles');

            // Build Event Body
            try {
                $start_dt = new DateTime($start, new DateTimeZone($timezone));
                $end_dt = new DateTime($end, new DateTimeZone($timezone));
            } catch (Exception $e) {
                // Fallback if timezone is invalid
                $start_dt = new DateTime($start);
                $end_dt = new DateTime($end);
            }

            $event_body = array(
                'summary' => "Booking - {$customer_name}",
                'description' => "Customer: {$customer_name}\nEmail: {$customer_email}\nCost: \${$cost}\n\nManaged by Antigravity Booking",
                'start' => array(
                    'dateTime' => $start_dt->format('c'),
                    'timeZone' => $timezone
                ),
                'end' => array(
                    'dateTime' => $end_dt->format('c'),
                    'timeZone' => $timezone
                ),
                'attendees' => array(
                    array('email' => $customer_email)
                )
            );

            // Check if event already exists
            $existing_event_id = get_post_meta($post->ID, '_gcal_event_id', true);

            if ($existing_event_id) {
                // Update existing event
                try {
                    $endpoint = "/calendars/" . urlencode($calendar_id) . "/events/" . urlencode($existing_event_id);
                    $this->make_api_request('PUT', $endpoint, $event_body);
                    error_log("Antigravity Booking: Updated Google Calendar event: {$existing_event_id}");
                } catch (Exception $e) {
                    error_log("Antigravity Booking: Error updating Google Calendar event: " . $e->getMessage());
                    // If update fails (e.g. 404), maybe try to create new? For now, just log.
                }
            } else {
                // Create new event
                try {
                    $endpoint = "/calendars/" . urlencode($calendar_id) . "/events";
                    $response = $this->make_api_request('POST', $endpoint, $event_body);

                    if (isset($response['id'])) {
                        update_post_meta($post->ID, '_gcal_event_id', $response['id']);
                        error_log("Antigravity Booking: Created Google Calendar event: " . $response['id']);
                    }
                } catch (Exception $e) {
                    error_log("Antigravity Booking: Error creating Google Calendar event: " . $e->getMessage());
                }
            }
        } catch (Throwable $t) {
            error_log("Antigravity Booking: Critical Error during GCal Sync: " . $t->getMessage());
        }
    }

    /**
     * Delete event from calendar (if booking is cancelled)
     */
    public function delete_from_calendar($booking_id)
    {
        try {
            $event_id = get_post_meta($booking_id, '_gcal_event_id', true);
            if (!$event_id) {
                return;
            }

            $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');
            $endpoint = "/calendars/" . urlencode($calendar_id) . "/events/" . urlencode($event_id);

            $this->make_api_request('DELETE', $endpoint);

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
        try {
            $calendar_id = get_option('antigravity_gcal_calendar_id', 'primary');
            // List 1 event to test access
            $endpoint = "/calendars/" . urlencode($calendar_id) . "/events?maxResults=1";
            $this->make_api_request('GET', $endpoint);
            return true;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '404') !== false || strpos($msg, 'Not Found') !== false) {
                $msg = 'Calendar Not Found. Please ensure: 1) The Calendar ID is correct. 2) You have shared the calendar with the service account email.';
            } elseif (strpos($msg, '403') !== false || strpos($msg, 'Forbidden') !== false) {
                $msg = 'Permission Denied. Please ensure you have authorized the correct Google Account.';
            }
            throw new Exception('Google Calendar Error: ' . $msg);
        }
    }
}
