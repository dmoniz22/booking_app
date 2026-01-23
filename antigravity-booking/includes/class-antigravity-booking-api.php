<?php

/**
 * Handles AJAX requests for the booking plugin
 *
 * @package Antigravity_Booking
 * @version 1.1.0
 */

class Antigravity_Booking_API
{
    /**
     * Rate limiting cache key prefix
     */
    const RATE_LIMIT_KEY = 'antigravity_rate_limit_';
    
    /**
     * Rate limit window in seconds (15 minutes)
     */
    const RATE_LIMIT_WINDOW = 900;
    
    /**
     * Maximum requests per window
     * Increased from 10 to 30 to allow normal user behavior:
     * - 10-15 availability checks (checking different dates)
     * - 3-5 booking attempts (form validation errors)
     * - Buffer for page refreshes
     */
    const MAX_REQUESTS_PER_WINDOW = 30;

    public function __construct()
    {
        // Availability Endpoint
        add_action('wp_ajax_antigravity_get_availability', array($this, 'get_availability'));
        add_action('wp_ajax_nopriv_antigravity_get_availability', array($this, 'get_availability'));

        // Booking Creation Endpoint
        add_action('wp_ajax_antigravity_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_nopriv_antigravity_create_booking', array($this, 'create_booking'));
    }
    
    /**
     * Check if request is rate limited
     *
     * @return bool True if rate limited, false otherwise
     */
    private function is_rate_limited()
    {
        $user_ip = $this->get_client_ip();
        $cache_key = self::RATE_LIMIT_KEY . $user_ip;
        
        $request_count = get_transient($cache_key);
        
        if ($request_count === false) {
            // First request or cache expired
            set_transient($cache_key, 1, self::RATE_LIMIT_WINDOW);
            return false;
        }
        
        if ($request_count >= self::MAX_REQUESTS_PER_WINDOW) {
            return true;
        }
        
        // Increment request count
        set_transient($cache_key, $request_count + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Validate date format
     *
     * @param string $date Date string to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_date($date)
    {
        if (empty($date)) {
            return false;
        }
        
        // Check format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        
        // Validate it's a real date
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format
     *
     * @param string $time Time string to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_time($time)
    {
        if (empty($time)) {
            return false;
        }
        
        // Check format HH:MM
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }
        
        // Validate it's a real time
        $dt = DateTime::createFromFormat('H:i', $time);
        return $dt !== false && $dt->format('H:i') === $time;
    }
    
    /**
     * Validate email format
     *
     * @param string $email Email to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_email($email)
    {
        return is_email($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate phone number (basic validation)
     *
     * @param string $phone Phone number to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_phone($phone)
    {
        if (empty($phone)) {
            return true; // Optional field
        }
        
        // Basic phone validation - allows digits, spaces, dashes, parentheses
        return preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone);
    }
    
    /**
     * Sanitize and validate booking input
     *
     * @param array $input Raw POST data
     * @return array|WP_Error Validated data or error
     */
    private function sanitize_booking_input($input)
    {
        $validated = array();
        $errors = array();
        
        // Date
        if (empty($input['date'])) {
            $errors[] = 'Date is required.';
        } elseif (!$this->validate_date(sanitize_text_field($input['date']))) {
            $errors[] = 'Invalid date format. Use YYYY-MM-DD.';
        } else {
            $validated['date'] = sanitize_text_field($input['date']);
        }
        
        // Start time
        if (empty($input['start_time'])) {
            $errors[] = 'Start time is required.';
        } elseif (!$this->validate_time(sanitize_text_field($input['start_time']))) {
            $errors[] = 'Invalid time format. Use HH:MM (24-hour).';
        } else {
            $validated['start_time'] = sanitize_text_field($input['start_time']);
        }
        
        // Customer name
        if (empty($input['customer_name'])) {
            $errors[] = 'Customer name is required.';
        } else {
            $name = sanitize_text_field($input['customer_name']);
            if (strlen($name) < 2) {
                $errors[] = 'Name must be at least 2 characters.';
            } else {
                $validated['customer_name'] = $name;
            }
        }
        
        // Customer email
        if (empty($input['customer_email'])) {
            $errors[] = 'Email address is required.';
        } else {
            $email = sanitize_email($input['customer_email']);
            if (!$this->validate_email($email)) {
                $errors[] = 'Invalid email address.';
            } else {
                $validated['customer_email'] = $email;
            }
        }
        
        // Customer phone (optional)
        if (!empty($input['customer_phone'])) {
            $phone = sanitize_text_field($input['customer_phone']);
            if (!$this->validate_phone($phone)) {
                $errors[] = 'Invalid phone number format.';
            } else {
                $validated['customer_phone'] = $phone;
            }
        }
        
        // Guest count (optional, default to 1)
        if (!empty($input['guest_count'])) {
            $guests = intval($input['guest_count']);
            if ($guests < 1) {
                $errors[] = 'Guest count must be at least 1.';
            } else {
                $validated['guest_count'] = $guests;
            }
        } else {
            $validated['guest_count'] = 1;
        }
        
        // Event description (optional)
        if (!empty($input['event_description'])) {
            $validated['event_description'] = sanitize_textarea_field($input['event_description']);
        }
        
        // End time (optional, for multi-slot selection)
        if (!empty($input['end_time'])) {
            $end_time = sanitize_text_field($input['end_time']);
            if ($end_time !== 'Overnight' && !$this->validate_time($end_time)) {
                $errors[] = 'Invalid end time format.';
            } else {
                $validated['end_time'] = $end_time;
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        return $validated;
    }

    /**
     * Get available time slots for a specific date
     *
     * @return void Sends JSON response
     */
    public function get_availability()
    {
        // Check rate limiting
        if ($this->is_rate_limited()) {
            wp_send_json_error('Too many requests. Please wait a few minutes and try again.');
        }
        
        check_ajax_referer('antigravity_booking_nonce', 'nonce');

        $date_str = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        // Validate date
        if (!$date_str || !$this->validate_date($date_str)) {
            wp_send_json_error('Invalid date format. Please use YYYY-MM-DD.');
        }

        // 1. Check if date is generally available (day of week, blackout)
        // We assume 00:00 to 23:59 for global day check
        $start_of_day = $date_str . ' 00:00:00';
        $end_of_day = $date_str . ' 23:59:59';

        $day_check = Antigravity_Booking_Availability::check_availability($start_of_day, $end_of_day);

        // If the day is completely blocked (e.g. "Mondays are unavailable"), fail early
        // BUT: specific time slots might be failing in this check if we passed full day range
        // So we need to be careful. check_availability checks specific times.
        // Let's rely on generating slots and checking each one.

        // Get configured hours for this day
        $day_name = strtolower(date('l', strtotime($date_str)));
        $hours_per_day = get_option('antigravity_booking_hours_per_day', array());

        if (!isset($hours_per_day[$day_name])) {
            // Fallback if not set, or maybe closed?
            // Actually check_availability handles the "Is day available" check logic better.
            // If day is not in 'available_days' option, it's closed.
            $available_days = get_option('antigravity_booking_available_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'));
            if (!in_array($day_name, $available_days)) {
                wp_send_json_error('We are closed on ' . ucfirst($day_name) . 's.');
            }
        }

        $business_start = isset($hours_per_day[$day_name]['start']) ? $hours_per_day[$day_name]['start'] : '09:00';
        $business_end = isset($hours_per_day[$day_name]['end']) ? $hours_per_day[$day_name]['end'] : '17:00';

        // Generate slots (e.g. every hour)
        // TODO: Make slot duration configurable? defaulting to 1 hour for now.
        $slots = array();
        $current = strtotime("$date_str $business_start");
        $end_ts = strtotime("$date_str $business_end");

        // Loop through the day in 1-hour increments
        while ($current < $end_ts) {
            $slot_start = date('H:i', $current);
            $slot_end_ts = strtotime('+1 hour', $current);
            $slot_end = date('H:i', $slot_end_ts);

            // Construct datetime strings
            $dt_start = "$date_str $slot_start";
            $dt_end = "$date_str $slot_end"; // This might cross midnight if we support late nights, but simple for now

            // Check availability for this specific slot
            $availability = Antigravity_Booking_Availability::check_availability($dt_start, $dt_end);

            // Also check against EXISTING bookings (overlap check)
            // This is crucial. check_availability only checks RULES, not DB collisions.
            $is_blocked = $this->is_slot_booked($dt_start, $dt_end);

            if ($availability['available'] && !$is_blocked) {
                $slots[] = array(
                    'start' => $slot_start,
                    'end' => $slot_end, // 1 hour duration
                    'label' => date('g:i A', $current) . ' - ' . date('g:i A', $slot_end_ts)
                );
            }

            $current = $slot_end_ts;
        }

        // Add overnight slot if enabled for this day
        $overnight_start = get_option('antigravity_booking_overnight_cutoff', '22:00');
        $overnight_dt = "$date_str $overnight_start";

        if (Antigravity_Booking_Availability::is_overnight_booking($overnight_dt)) {

            // Check collision for overnight
            $overnight_end = Antigravity_Booking_Availability::get_overnight_end($overnight_dt);
            $is_blocked = $this->is_slot_booked($overnight_dt, $overnight_end);

            if (!$is_blocked) {
                $slots[] = array(
                    'start' => $overnight_start,
                    'end' => 'Overnight',
                    'label' => date('g:i A', strtotime($overnight_dt)) . ' (Overnight)',
                    'is_overnight' => true
                );
            }
        }

        wp_send_json_success($slots);
    }

    /**
     * Check if a slot overlaps with existing approved/pending bookings
     */
    private function is_slot_booked($start_dt, $end_dt)
    {
        $cutoff_hours = get_option('antigravity_booking_cutoff_hours', 48);
        $timezone = Antigravity_Booking_Availability::get_timezone();
        $now = new DateTime('now', $timezone);
        $cutoff_limit = clone $now;
        $cutoff_limit->modify("+{$cutoff_hours} hours");
        $cutoff_limit_str = $cutoff_limit->format('Y-m-d H:i:s');

        $args = array(
            'post_type' => 'booking',
            'post_status' => array('approved', 'pending_review'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_booking_end_datetime',
                    'value' => $start_dt,
                    'compare' => '>',
                    'type' => 'DATETIME'
                ),
                array(
                    'key' => '_booking_start_datetime',
                    'value' => $end_dt,
                    'compare' => '<',
                    'type' => 'DATETIME'
                )
            )
        );

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return false;
        }

        foreach ($query->posts as $booking) {
            if ($booking->post_status === 'approved') {
                return true; // Approved always blocks
            }

            // For pending_review, it only blocks if it hasn't passed the cutoff
            $booking_start = get_post_meta($booking->ID, '_booking_start_datetime', true);
            if ($booking_start > $cutoff_limit_str) {
                return true; // Still within booking window
            }
        }

        return false;
    }

    /**
     * Handle booking submission
     *
     * @return void Sends JSON response
     */
    public function create_booking()
    {
        // Check rate limiting
        if ($this->is_rate_limited()) {
            wp_send_json_error('Too many requests. Please wait a few minutes and try again.');
        }
        
        check_ajax_referer('antigravity_booking_nonce', 'nonce');

        // Validate and sanitize all input
        $validated = $this->sanitize_booking_input($_POST);
        
        if (is_wp_error($validated)) {
            wp_send_json_error($validated->get_error_message());
        }
        
        // Extract validated data
        $date = $validated['date'];
        $start_time = $validated['start_time'];
        $customer_name = $validated['customer_name'];
        $customer_email = $validated['customer_email'];
        $customer_phone = isset($validated['customer_phone']) ? $validated['customer_phone'] : '';
        $guest_count = isset($validated['guest_count']) ? $validated['guest_count'] : 1;
        $event_description = isset($validated['event_description']) ? $validated['event_description'] : '';

        $start_datetime = "$date $start_time";

        // Check if end_time was passed (from multi-slot selection)
        if (isset($validated['end_time']) && !empty($validated['end_time'])) {
            $end_time = $validated['end_time'];

            // Special handling for 'Overnight' string if passed
            if ($end_time === 'Overnight') {
                $end_datetime = Antigravity_Booking_Availability::get_overnight_end($start_datetime);
                $is_overnight = true;
            } else {
                // Standard time string
                $end_datetime = "$date $end_time";
                $is_overnight = false;
            }
        } else {
            // Fallback to single slot logic
            if (Antigravity_Booking_Availability::is_overnight_booking($start_datetime)) {
                $end_datetime = Antigravity_Booking_Availability::get_overnight_end($start_datetime);
                $is_overnight = true;
            } else {
                $end_datetime = date('Y-m-d H:i', strtotime($start_datetime . ' +1 hour'));
                $is_overnight = false;
            }
        }

        // Final Availability Check
        $avail = Antigravity_Booking_Availability::check_availability($start_datetime, $end_datetime, $is_overnight);
        if (!$avail['available']) {
            wp_send_json_error(implode(' ', $avail['errors']));
        }

        if ($this->is_slot_booked($start_datetime, $end_datetime)) {
            wp_send_json_error('This slot was just booked by someone else. Please try another.');
        }

        // Create Post
        $post_data = array(
            'post_title' => $customer_name . ' - ' . $start_datetime,
            'post_status' => 'pending_review',
            'post_type' => 'booking'
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            error_log('Antigravity Booking Error: Failed to create booking post - ' . $post_id->get_error_message());
            wp_send_json_error('Failed to create booking. Please try again.');
        }

        // Save Meta
        update_post_meta($post_id, '_booking_start_datetime', $start_datetime);
        update_post_meta($post_id, '_booking_end_datetime', $end_datetime);
        update_post_meta($post_id, '_customer_name', $customer_name);
        update_post_meta($post_id, '_customer_email', $customer_email);
        update_post_meta($post_id, '_customer_phone', $customer_phone);
        update_post_meta($post_id, '_guest_count', $guest_count);
        update_post_meta($post_id, '_event_description', $event_description);

        update_post_meta($post_id, '_is_overnight', $is_overnight);

        // Calculate Cost
        $cp = new Antigravity_Booking_CPT(); // Helper to calculate cost
        $cost = $cp->calculate_cost($post_id);
        update_post_meta($post_id, '_estimated_cost', $cost);

        // Trigger Emails (Status Transition to Pending handled in CPT class? Or separate hook?)
        // Currently emails are sent on "save_post" or status transition.
        // Let's ensure we trigger the "submission" email. 
        // We do this manually here for explicit control or rely on hooks. 
        // For now, let's rely on the fact that we just inserted a post.
        // Actually, we need to send the "Submission Confirmation" email.

        ob_start();
        $mailer = new Antigravity_Booking_Emails();
        $mailer->send_submission_email($post_id);
        ob_end_clean(); // Discard any output from email sending (e.g. SMTP debug)

        $redirect_url = get_option('antigravity_booking_success_redirect', '');
        error_log("Antigravity Booking Debug - Redirect URL: " . $redirect_url);

        wp_send_json_success(array(
            'message' => 'Booking submitted successfully! We will review it shortly.',
            'redirect_url' => $redirect_url
        ));
    }
}
