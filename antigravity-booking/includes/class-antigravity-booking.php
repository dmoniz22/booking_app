<?php
class Antigravity_Booking
{
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $cpt;
    protected $availability;
    public $emails;
    public $blackout;
    public $google_oauth;
    public $google_calendar;
    public $shortcode;
    public $api;
    public $dashboard;
    public $settings;

    public function __construct()
    {
        $this->plugin_name = 'antigravity-booking';
        $this->version = '1.3.0';
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
        // No public AJAX hooks are registered here.
        // The front-end booking form uses the antigravity_get_availability and
        // antigravity_create_booking endpoints defined in Antigravity_Booking_API
        // (nonce-protected + rate limited). The legacy check_availability and
        // get_calendar_events endpoints were dead code (the front-end JS never
        // called them) and their backing method get_calendar_events() did not
        // exist — removed in 1.3.0.
    }

    public function ajax_calculate_cost()
    {
        check_ajax_referer('calculate_booking_cost', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

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

    public function run()
    {
        // Components are already initialized
    }
}
