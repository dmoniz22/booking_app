<?php
class Antigravity_Booking_Deactivator {
	public static function deactivate() {
		// Clear scheduled events so they don't keep firing after deactivation
		wp_clear_scheduled_hook('antigravity_send_reminders');
		wp_clear_scheduled_hook('antigravity_check_expired_bookings');

		flush_rewrite_rules();
	}
}
