<?php
/**
 * Availability Checker
 * Centralized logic for checking booking availability based on settings
 */
class Antigravity_Booking_Availability
{

    /**
     * Check if a date/time range is available for booking
     *
     * @param string $start_datetime Start datetime (Y-m-d H:i:s format)
     * @param string $end_datetime End datetime (Y-m-d H:i:s format)
     * @return array ['available' => bool, 'errors' => array]
     */
    /**
     * Get configured timezone
     * @return DateTimeZone
     */
    public static function get_timezone()
    {
        $tz_string = get_option('antigravity_booking_timezone', 'America/Los_Angeles');
        try {
            return new DateTimeZone($tz_string);
        } catch (Exception $e) {
            return new DateTimeZone('America/Los_Angeles');
        }
    }

    /**
     * Check if a date/time range is available for booking
     *
     * @param string $start_datetime Start datetime (Y-m-d H:i:s format)
     * @param string $end_datetime End datetime (Y-m-d H:i:s format)
     * @return array ['available' => bool, 'errors' => array]
     */
    public static function check_availability($start_datetime, $end_datetime, $is_overnight = false)
    {
        $errors = array();
        $timezone = self::get_timezone();

        // Parse datetimes with configured timezone
        try {
            $start = new DateTime($start_datetime, $timezone);
            $end = new DateTime($end_datetime, $timezone);
        } catch (Exception $e) {
            return array('available' => false, 'errors' => array('Invalid date format.'));
        }

        // Check 1: Is the day of week available?
        $day_name = strtolower($start->format('l')); // monday, tuesday, etc.
        $available_days = get_option('antigravity_booking_available_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'));

        if (!in_array($day_name, $available_days)) {
            $errors[] = ucfirst($day_name) . 's are not available for booking.';
        }

        // Check 2: Is the date a blackout date?
        $date_only = $start->format('Y-m-d');
        $blackout_dates = get_option('antigravity_booking_blackout_dates', '');
        $blackout_list = array_filter(array_map('trim', explode("\n", $blackout_dates)));

        if (in_array($date_only, $blackout_list)) {
            $errors[] = 'This date is not available for booking.';
        }

        // Check 3: Are the times within business hours for this day?
        // If it's an overnight booking, we skip standard business hours check
        if (!$is_overnight && !self::is_overnight_booking($start_datetime)) {
            $hours_per_day = get_option('antigravity_booking_hours_per_day', array());

            if (isset($hours_per_day[$day_name])) {
                $business_start = $hours_per_day[$day_name]['start'];
                $business_end = $hours_per_day[$day_name]['end'];

                $booking_start_time = $start->format('H:i');
                $booking_end_time = $end->format('H:i');

                // If end time is e.g. 00:30 (next day), we treat it as > business_end for standard bookings
                // We compare times as strings, which works for 24h format unless crossing midnight
                // If crossing midnight and NOT overnight, it's definitely invalid for a standard day slot
                if ($booking_end_time < $booking_start_time) {
                    $errors[] = 'Standard bookings cannot span across midnight.';
                } elseif ($booking_start_time < $business_start || $booking_end_time > $business_end) {
                    $errors[] = 'Booking times must be within business hours (' . date('g:i A', strtotime($business_start)) . ' - ' . date('g:i A', strtotime($business_end)) . ').';
                }
            }
        }

        // Check 4: Booking cutoff time
        $cutoff_hours = get_option('antigravity_booking_cutoff_hours', 48);
        $cutoff = new DateTime('now', $timezone);
        $cutoff->modify("+{$cutoff_hours} hours");

        if ($start < $cutoff) {
            $errors[] = "Bookings must be made at least {$cutoff_hours} hours in advance.";
        }

        return array(
            'available' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Check if overnight pricing should apply
     *
     * @param string $start_datetime Start datetime (Y-m-d H:i:s format)
     * @return bool
     */
    public static function is_overnight_booking($start_datetime)
    {
        $overnight_days = get_option('antigravity_booking_overnight_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'));

        // Get day of week from start datetime
        try {
            $start_dt_obj = new DateTime($start_datetime, self::get_timezone());
            $day_name = strtolower($start_dt_obj->format('l'));

            if (!in_array($day_name, (array) $overnight_days)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        $overnight_cutoff = get_option('antigravity_booking_overnight_cutoff', '22:00');
        try {
            $start = new DateTime($start_datetime, self::get_timezone());
            $start_time = $start->format('H:i');
            return $start_time >= $overnight_cutoff;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the extended end time for overnight booking
     *
     * @param string $start_datetime Start datetime (Y-m-d H:i:s format)
     * @return string Extended end datetime (Y-m-d H:i:s format)
     */
    public static function get_overnight_end($start_datetime)
    {
        $overnight_extend = get_option('antigravity_booking_overnight_extend', '10:00');
        $start = new DateTime($start_datetime, self::get_timezone());

        // Set to next day at extend time
        $end = clone $start;
        $end->modify('+1 day');
        $end->setTime(
            (int) substr($overnight_extend, 0, 2),
            (int) substr($overnight_extend, 3, 2)
        );

        return $end->format('Y-m-d H:i:s');
    }
}
