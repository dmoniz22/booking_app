<?php
class Antigravity_Booking
{
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $cpt;
    protected $availability;

    public function __construct()
    {
        $this->plugin_name = 'antigravity-booking';
        $this->version = '1.2.0';
        $this->load_dependencies();
        $this->init_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-cpt.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-availability.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-emails.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-google-oauth.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-google-calendar.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-dashboard.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-blackout.php';

        // Frontend
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-shortcode.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-antigravity-booking-api.php';
    }

    private function init_components()
    {
        $this->cpt = new Antigravity_Booking_CPT();
        $this->availability = new Antigravity_Booking_Availability();
        $this->emails = new Antigravity_Booking_Emails();
        $this->blackout = new Antigravity_Booking_Blackout();
        $this->google_oauth = new Antigravity_Booking_Google_OAuth();
        
        // Google Calendar Integration with OAuth
        $this->google_calendar = new Antigravity_Booking_Google_Calendar();
        $this->google_calendar->init(); // Initialize hooks

        // Frontend Init
        $this->shortcode = new Antigravity_Booking_Shortcode();
        $this->api = new Antigravity_Booking_API();

        // Admin-only components
        if (is_admin()) {
            $this->dashboard = new Antigravity_Booking_Dashboard();
            $this->settings = new Antigravity_Booking_Settings();
        }
    }

    private function define_admin_hooks()
    {
        // Admin hooks - AJAX for cost calculation
        add_action('wp_ajax_calculate_booking_cost', array($this, 'ajax_calculate_cost'));
    }

    private function define_public_hooks()
    {
        // Public hooks - AJAX for availability check
        add_action('wp_ajax_check_availability', array($this, 'ajax_check_availability'));
        add_action('wp_ajax_nopriv_check_availability', array($this, 'ajax_check_availability'));

        add_action('wp_ajax_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_nopriv_get_calendar_events', array($this, 'ajax_get_calendar_events'));
    }

    public function ajax_calculate_cost()
    {
        $start = sanitize_text_field($_POST['start']);
        $end = sanitize_text_field($_POST['end']);

        $start_dt = new DateTime($start);
        $end_dt = new DateTime($end);
        $diff = $start_dt->diff($end_dt);

        $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        $hourly_rate = get_option('antigravity_booking_hourly_rate', 100);
        $cost = round($hours * $hourly_rate, 2);

        wp_send_json_success(array('cost' => $cost));
    }

    public function ajax_check_availability()
    {
        $start = sanitize_text_field($_POST['start']);
        $end = sanitize_text_field($_POST['end']);

        $result = $this->availability->check_availability($start, $end);
        wp_send_json_success($result);
    }

    public function ajax_get_calendar_events()
    {
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : null;
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : null;

        $events = $this->availability->get_calendar_events($start, $end);
        wp_send_json_success($events);
    }

    public function run()
    {
        // Components are already initialized
    }
}
