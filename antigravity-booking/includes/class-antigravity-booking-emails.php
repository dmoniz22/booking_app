<?php
/**
 * Email Notification System
 * Handles all booking-related emails
 */
class Antigravity_Booking_Emails
{

    public function __construct()
    {
        // Status change hooks
        add_action('transition_post_status', array($this, 'handle_status_change'), 10, 3);

        // Reminder cron
        add_action('antigravity_send_reminders', array($this, 'send_reminders'));

        // Schedule reminder check
        if (!wp_next_scheduled('antigravity_send_reminders')) {
            wp_schedule_event(time(), 'daily', 'antigravity_send_reminders');
        }
    }

    /**
     * Handle booking status changes
     */
    public function handle_status_change($new_status, $old_status, $post)
    {
        if ($post->post_type !== 'booking') {
            return;
        }

        // New booking submitted (pending_review)
        // REMOVED: Handled explicitly by API create_booking to avoid race condition
        // if ($new_status === 'pending_review' && $old_status !== 'pending_review') {
        //    $this->send_new_booking_email_to_customer($post->ID);
        //    $this->send_new_booking_email_to_admin($post->ID);
        // }

        try {
            // Booking approved
            if ($new_status === 'approved' && $old_status !== 'approved') {
                $this->send_approval_email_to_customer($post->ID);
                $this->schedule_reminders($post->ID);
            }
        } catch (Throwable $t) {
            error_log("Antigravity Booking Error in handle_status_change: " . $t->getMessage());
        }
    }

    /**
     * Send email to customer when booking is submitted
     */
    public function send_submission_email($booking_id)
    {
        $this->send_new_booking_email_to_customer($booking_id);
        $this->send_new_booking_email_to_admin($booking_id);
    }

    /**
     * Send email to customer when booking is submitted
     */
    private function send_new_booking_email_to_customer($booking_id)
    {
        $customer_email = get_post_meta($booking_id, '_customer_email', true);
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end = get_post_meta($booking_id, '_booking_end_datetime', true);
        $cost = get_post_meta($booking_id, '_estimated_cost', true);

        $subject = 'Booking Request Received - Next Steps';

        $message = "Hello {$customer_name},\n\n";
        $message .= "Thank you for your booking request!\n\n";
        $message .= "Booking Details:\n";
        $message .= "Start: " . date('F j, Y g:i A', strtotime($start)) . "\n";
        $message .= "End: " . date('F j, Y g:i A', strtotime($end)) . "\n";
        $message .= "Estimated Cost: \${$cost}\n\n";
        $message .= "Next Steps:\n";
        $message .= get_option('antigravity_booking_customer_instructions', 'Please complete all required forms and payment within 48 hours to secure your booking.');
        $message .= "\n\nThank you!";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        if ($customer_email) {
            wp_mail($customer_email, $subject, $message, $headers);
            error_log("Sent new booking email to customer: {$customer_email}");
        }
    }

    /**
     * Send email to admin when new booking is submitted
     */
    /**
     * Send email to admin when new booking is submitted
     */
    private function send_new_booking_email_to_admin($booking_id)
    {
        $admin_email = get_option('antigravity_booking_admin_email', get_option('admin_email'));
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $customer_email = get_post_meta($booking_id, '_customer_email', true);
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end = get_post_meta($booking_id, '_booking_end_datetime', true);
        $cost = get_post_meta($booking_id, '_estimated_cost', true);

        $phone = get_post_meta($booking_id, '_customer_phone', true);
        $guests = get_post_meta($booking_id, '_guest_count', true);
        $description = get_post_meta($booking_id, '_event_description', true);

        // Get configured subject and message/template
        $subject = get_option('antigravity_booking_admin_subject', 'New Booking Request Received');
        $message_template = get_option('antigravity_booking_admin_message', "A new booking request has been submitted.\n\nCustomer: {customer_name}\nEmail: {customer_email}\nStart: {start_date}\nEnd: {end_date}\nEstimated Cost: {cost}\n\nView in Dashboard: {dashboard_url}");

        // Replace placeholders
        $placeholders = array(
            '{customer_name}' => $customer_name,
            '{customer_email}' => $customer_email,
            '{start_date}' => date('F j, Y g:i A', strtotime($start)),
            '{end_date}' => date('F j, Y g:i A', strtotime($end)),
            '{cost}' => '$' . $cost,
            '{phone}' => $phone,
            '{guests}' => $guests,
            '{description}' => $description,
            '{dashboard_url}' => admin_url("post.php?post={$booking_id}&action=edit")
        );

        $message = str_replace(array_keys($placeholders), array_values($placeholders), $message_template);

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($admin_email, $subject, $message, $headers);

        error_log("Sent new booking notification to admin: {$admin_email}");
    }

    /**
     * Send approval email with .ics attachment
     */
    private function send_approval_email_to_customer($booking_id)
    {
        $customer_email = get_post_meta($booking_id, '_customer_email', true);
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end = get_post_meta($booking_id, '_booking_end_datetime', true);

        // Get configured subject and message
        $subject_template = get_option('antigravity_booking_approval_subject', 'Booking Confirmed!');
        $message_template = get_option('antigravity_booking_approval_message', "Hello {customer_name},\n\nGreat news! Your booking has been approved and confirmed.\n\nBooking Details:\nStart: {start_date}\nEnd: {end_date}\n\nYou will receive reminder emails 1 week and 2 days before your event.\n\nAn .ics calendar file is attached for your convenience.\n\nThank you!");

        // Replace placeholders
        $placeholders = array(
            '{customer_name}' => $customer_name,
            '{start_date}' => date('F j, Y g:i A', strtotime($start)),
            '{end_date}' => date('F j, Y g:i A', strtotime($end))
        );

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject_template);
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $message_template);

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        try {
            // Generate .ics file
            $ics_file = $this->generate_ics_file($booking_id);

            if ($ics_file && file_exists($ics_file)) {
                wp_mail($customer_email, $subject, $message, $headers, array($ics_file));
                // Delete temp file
                @unlink($ics_file);
            } else {
                wp_mail($customer_email, $subject, $message, $headers);
            }
        } catch (Throwable $t) {
            error_log("Antigravity Booking Error sending approval email: " . $t->getMessage());
            // Try to send without attachment if generation failed
            wp_mail($customer_email, $subject, $message, $headers);
        }

        error_log("Sent approval email to customer: {$customer_email}");
    }

    /**
     * Generate .ics calendar file
     */
    private function generate_ics_file($booking_id)
    {
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end = get_post_meta($booking_id, '_booking_end_datetime', true);

        if (empty($start) || empty($end)) {
            error_log("Antigravity Booking: Missing dates for ICS generation (ID: {$booking_id})");
            return false;
        }

        try {
            $start_dt = new DateTime($start);
            $end_dt = new DateTime($end);
        } catch (Exception $e) {
            error_log("Antigravity Booking: Invalid dates for ICS generation: {$start} / {$end}");
            return false;
        }

        $ics_start = $start_dt->format('Ymd\THis');
        $ics_end = $end_dt->format('Ymd\THis');
        $now = gmdate('Ymd\THis\Z');

        $ics_content = "BEGIN:VCALENDAR\r\n";
        $ics_content .= "VERSION:2.0\r\n";
        $ics_content .= "PRODID:-//Simplified Booking//EN\r\n";
        $ics_content .= "BEGIN:VEVENT\r\n";
        $ics_content .= "UID:booking-{$booking_id}@antigravity\r\n";
        $ics_content .= "DTSTAMP:{$now}\r\n";
        $ics_content .= "DTSTART:{$ics_start}\r\n";
        $ics_content .= "DTEND:{$ics_end}\r\n";
        $ics_content .= "SUMMARY:Booking - {$customer_name}\r\n";
        $ics_content .= "DESCRIPTION:Confirmed booking\r\n";
        $ics_content .= "END:VEVENT\r\n";
        $ics_content .= "END:VCALENDAR\r\n";

        $temp_dir = sys_get_temp_dir();
        if (!is_writable($temp_dir)) {
            error_log("Antigravity Booking: Temp directory not writable: {$temp_dir}");
            return false;
        }

        $ics_file = $temp_dir . "/booking-{$booking_id}.ics";
        if (false === file_put_contents($ics_file, $ics_content)) {
            error_log("Antigravity Booking: Failed to write ICS file: {$ics_file}");
            return false;
        }

        return $ics_file;
    }

    /**
     * Schedule reminder emails
     */
    private function schedule_reminders($booking_id)
    {
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $start_dt = new DateTime($start);

        // Store reminder flags
        update_post_meta($booking_id, '_reminder_1_sent', false);
        update_post_meta($booking_id, '_reminder_2_sent', false);
    }

    /**
     * Send reminder emails (cron job)
     */
    public function send_reminders()
    {
        $now = new DateTime();

        // 1. Get all approved bookings
        $bookings = get_posts(array(
            'post_type' => 'booking',
            'post_status' => 'approved',
            'posts_per_page' => -1,
        ));

        // Get configurable timeframes
        $reminder_1_days = get_option('antigravity_booking_reminder_1_days', 7);
        $reminder_2_hours = get_option('antigravity_booking_reminder_2_hours', 48);

        foreach ($bookings as $booking) {
            $start = get_post_meta($booking->ID, '_booking_start_datetime', true);
            $start_dt = new DateTime($start);

            $diff = $now->diff($start_dt);

            // Calculate time until booking
            $total_hours = ($diff->days * 24) + $diff->h;
            $days_until = $diff->days;

            // First reminder (configurable days)
            if ($days_until <= $reminder_1_days && $days_until > 0 && !get_post_meta($booking->ID, '_reminder_1_sent', true)) {
                $this->send_reminder_email($booking->ID, 1);
                update_post_meta($booking->ID, '_reminder_1_sent', true);
            }

            // Second reminder (configurable hours)
            if ($total_hours <= $reminder_2_hours && $total_hours > 0 && !get_post_meta($booking->ID, '_reminder_2_sent', true)) {
                $this->send_reminder_email($booking->ID, 2);
                update_post_meta($booking->ID, '_reminder_2_sent', true);
            }
        }
    }

    /**
     * Send individual reminder email
     */
    /**
     * Send individual reminder email
     * @param int $booking_id Booking ID
     * @param int $reminder_num Reminder number (1 or 2)
     */
    private function send_reminder_email($booking_id, $reminder_num)
    {
        $customer_email = get_post_meta($booking_id, '_customer_email', true);
        $customer_name = get_post_meta($booking_id, '_customer_name', true);
        $start = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end = get_post_meta($booking_id, '_booking_end_datetime', true);
        $cost = get_post_meta($booking_id, '_estimated_cost', true);

        // Get template based on reminder number
        if ($reminder_num == 1) {
            $subject = get_option('antigravity_booking_reminder_1_subject', 'Upcoming Booking Reminder');
            $message_template = get_option('antigravity_booking_reminder_1_message', 'Your booking is coming up soon! Please ensure
all requirements are completed.');
        } else {
            $subject = get_option('antigravity_booking_reminder_2_subject', 'Final Reminder: Booking in 48 Hours');
            $message_template = get_option('antigravity_booking_reminder_2_message', 'Your booking is in 48 hours. Please ensure
final payment is received and all requirements are met.');
        }

        // Replace placeholders
        $placeholders = array(
            '{customer_name}' => $customer_name,
            '{start_date}' => date('F j, Y g:i A', strtotime($start)),
            '{end_date}' => date('F j, Y g:i A', strtotime($end)),
            '{cost}' => '$' . $cost,
        );

        $message = str_replace(array_keys($placeholders), array_values($placeholders), $message_template);

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($customer_email, $subject, $message, $headers);

        error_log("Sent reminder {$reminder_num} to: {$customer_email}");
    }
}
