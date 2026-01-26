<?php
/**
 * Plugin Name:       Simplified Booking
 * Description:       Custom booking plugin with time blocks, cost estimation, and Google Calendar sync.
 * Version:           1.2.0
 * Author:            monizes
 * Text Domain:       antigravity-booking
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

define('ANTIGRAVITY_BOOKING_VERSION', '1.2.0');

// Load Composer autoloader if it exists
$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoloader)) {
	require_once $autoloader;
}

/**
 * The code that runs during plugin activation.
 */
function activate_antigravity_booking()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-antigravity-booking-activator.php';
	Antigravity_Booking_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_antigravity_booking()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-antigravity-booking-deactivator.php';
	Antigravity_Booking_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_antigravity_booking');
register_deactivation_hook(__FILE__, 'deactivate_antigravity_booking');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-antigravity-booking.php';

function run_antigravity_booking()
{
	$plugin = new Antigravity_Booking();
	$plugin->run();
}
run_antigravity_booking();
