<?php
class Antigravity_Booking_Activator {

	public static function activate() {
		// Defensive: make sure the CPT classes are available even if
		// activation runs in a context where the plugin's includes didn't load.
		$plugin_dir = dirname(dirname(__FILE__));
		require_once $plugin_dir . '/includes/class-antigravity-booking-cpt.php';
		require_once $plugin_dir . '/includes/class-antigravity-booking-blackout.php';

		// Register the CPT + blackout CPT so rewrite rules include them
		// immediately after activation (avoids 404s on first visit).
		if (!post_type_exists('booking')) {
			$cpt = new Antigravity_Booking_CPT();
			$cpt->register_booking_cpt();
		}
		if (!post_type_exists('blackout_date')) {
			$blackout = new Antigravity_Booking_Blackout();
			$blackout->register_blackout_cpt();
		}

		flush_rewrite_rules();
	}
}
