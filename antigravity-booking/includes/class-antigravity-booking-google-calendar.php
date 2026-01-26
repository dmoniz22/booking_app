<?php
/**
 * Google Calendar Integration
 * Syncs approved bookings to admin's Google Calendar using OAuth 2.0
 */
class Antigravity_Booking_Google_Calendar
{

    private $client;
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
     * Get Google Client with OAuth authentication
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

        // Check if OAuth is authorized
        if (!get_option('antigravity_gcal_oauth_authorized', false)) {
            throw new Exception('Google Calendar not authorized. Please authorize in plugin settings.');
        }

        try {
            error_log('Antigravity Booking: Instantiating Google_Client with OAuth');
            $client = new Google_Client();
            $client->setApplicationName('Simplified Booking');
            $client->setScopes(array('https://www.googleapis.com/auth/calendar'));
            
            // Get OAuth instance
            if (!$this->oauth) {
                $this->oauth = new Antigravity_Booking_Google_OAuth();
            }
            
            // Get valid access token (will refresh if needed)
            $access_token = $this->oauth->get_access_token();
            
            if (!$access_token) {
                throw new Exception('No valid access token available. Please re-authorize in settings.');
            }
            
            $client->setAccessToken($access_token);
            
            // Check if token is expired
            if ($client->isAccessTokenExpired()) {
                error_log('Antigravity Booking: Access token expired, refreshing...');
                if ($this->oauth->refresh_access_token()) {
                    $access_token = $this->oauth->get_access_token();
                    $client->setAccessToken($access_token);
                } else {
                    throw new Exception('Failed to refresh access token. Please re-authorize in settings.');
                }
            }
            
            $this->client = $client;
            error_log('Antigravity Booking: Google_Client authenticated successfully with OAuth');
            return $this->client;
            
        } catch (Throwable $t) {
            error_log('Antigravity Booking: OAuth authentication failed: ' . $t->getMessage());
            throw new Exception('Google Calendar authentication failed: ' . $t->getMessage());
        }
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
