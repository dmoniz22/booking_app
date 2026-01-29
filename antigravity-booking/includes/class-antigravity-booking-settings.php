<?php
/**
 * Admin Settings Page
 * Provides configuration interface for the booking plugin
 */
class Antigravity_Booking_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'), 11);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_antigravity_test_gcal', array($this, 'ajax_test_gcal_connection'));
        add_action('admin_footer', array($this, 'render_admin_scripts'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_submenu_page(
            'antigravity-booking',
            'Simplified Booking Settings',
            'Settings',
            'manage_options',
            'antigravity-booking-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register all settings
     */
    public function register_settings()
    {
        // General Settings Section
        add_settings_section(
            'antigravity_booking_general',
            'General Settings',
            array($this, 'render_general_section'),
            'antigravity-booking-settings'
        );

        // Hourly Rate
        add_settings_field(
            'antigravity_booking_hourly_rate',
            'Hourly Rate ($)',
            array($this, 'render_hourly_rate_field'),
            'antigravity-booking-settings',
            'antigravity_booking_general'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_hourly_rate', array(
            'type' => 'number',
            'sanitize_callback' => 'floatval',
            'default' => 100,
        ));

        // Timezone
        add_settings_field(
            'antigravity_booking_timezone',
            'Timezone',
            array($this, 'render_timezone_field'),
            'antigravity-booking-settings',
            'antigravity_booking_general'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_timezone', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'America/Los_Angeles',
        ));

        // Booking Cutoff Time (Hours)
        add_settings_field(
            'antigravity_booking_cutoff_hours',
            'Booking Cutoff Time (Hours)',
            array($this, 'render_cutoff_hours_field'),
            'antigravity-booking-settings',
            'antigravity_booking_general'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_cutoff_hours', array(
            'type' => 'number',
            'default' => 48,
        ));

        // Success Redirect URL
        add_settings_field(
            'antigravity_booking_success_redirect',
            'Success Redirect URL',
            array($this, 'render_success_redirect_field'),
            'antigravity-booking-settings',
            'antigravity_booking_general'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_success_redirect', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));

        // Availability Settings Section
        add_settings_section(
            'antigravity_booking_availability',
            'Availability Settings',
            array($this, 'render_availability_section'),
            'antigravity-booking-settings'
        );

        // Days of Week
        add_settings_field(
            'antigravity_booking_available_days',
            'Available Days',
            array($this, 'render_available_days_field'),
            'antigravity-booking-settings',
            'antigravity_booking_availability'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_available_days', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_array'),
            'default' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
        ));


        // Per-Day Business Hours
        add_settings_field(
            'antigravity_booking_hours_per_day',
            'Business Hours (Per Day)',
            array($this, 'render_hours_per_day_field'),
            'antigravity-booking-settings',
            'antigravity_booking_availability'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_hours_per_day', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_hours_per_day'),
            'default' => array(
                'monday' => array('start' => '09:00', 'end' => '22:00'),
                'tuesday' => array('start' => '09:00', 'end' => '22:00'),
                'wednesday' => array('start' => '09:00', 'end' => '22:00'),
                'thursday' => array('start' => '09:00', 'end' => '22:00'),
                'friday' => array('start' => '09:00', 'end' => '22:00'),
                'saturday' => array('start' => '09:00', 'end' => '22:00'),
                'sunday' => array('start' => '09:00', 'end' => '22:00'),
            ),
        ));

        // Blackout Dates
        add_settings_field(
            'antigravity_booking_blackout_dates',
            'Blackout Dates',
            array($this, 'render_blackout_dates_field'),
            'antigravity-booking-settings',
            'antigravity_booking_availability'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_blackout_dates', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ));

        // Overnight Pricing Section
        add_settings_section(
            'antigravity_booking_overnight',
            'Overnight Pricing Rules',
            array($this, 'render_overnight_section'),
            'antigravity-booking-settings'
        );

        // Overnight Days
        add_settings_field(
            'antigravity_booking_overnight_days',
            'Overnight Booking Days',
            array($this, 'render_overnight_days_field'),
            'antigravity-booking-settings',
            'antigravity_booking_overnight'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_overnight_days', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_array'),
            'default' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
        ));

        // Overnight Cutoff Time
        add_settings_field(
            'antigravity_booking_overnight_cutoff',
            'Overnight Cutoff Time',
            array($this, 'render_overnight_cutoff_field'),
            'antigravity-booking-settings',
            'antigravity_booking_overnight'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_overnight_cutoff', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '22:00',
        ));

        // Overnight Extend To Time
        add_settings_field(
            'antigravity_booking_overnight_extend',
            'Overnight Extend To',
            array($this, 'render_overnight_extend_field'),
            'antigravity-booking-settings',
            'antigravity_booking_overnight'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_overnight_extend', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '10:00',
        ));

        // Per-Day Overnight Times
        add_settings_field(
            'antigravity_booking_overnight_times',
            'Overnight Times (Per Day)',
            array($this, 'render_overnight_times_field'),
            'antigravity-booking-settings',
            'antigravity_booking_overnight'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_overnight_times', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_overnight_times'),
            'default' => array(
                'monday' => array('start' => '22:00', 'end' => '10:00'),
                'tuesday' => array('start' => '22:00', 'end' => '10:00'),
                'wednesday' => array('start' => '22:00', 'end' => '10:00'),
                'thursday' => array('start' => '22:00', 'end' => '10:00'),
                'friday' => array('start' => '22:00', 'end' => '10:00'),
                'saturday' => array('start' => '22:00', 'end' => '10:00'),
                'sunday' => array('start' => '22:00', 'end' => '10:00'),
            ),
        ));

        // Special Date Overrides
        add_settings_field(
            'antigravity_booking_special_hours',
            'Special Date Overrides',
            array($this, 'render_special_hours_field'),
            'antigravity-booking-settings',
            'antigravity_booking_overnight'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_special_hours', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_special_hours'),
            'default' => array(),
        ));

        // Google Calendar Section
        add_settings_section(
            'antigravity_booking_gcal',
            'Google Calendar Integration (OAuth 2.0)',
            array($this, 'render_gcal_section'),
            'antigravity-booking-settings'
        );

        // OAuth Client ID
        add_settings_field(
            'antigravity_gcal_oauth_client_id',
            'OAuth Client ID',
            array($this, 'render_oauth_client_id_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_oauth_client_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // OAuth Client Secret
        add_settings_field(
            'antigravity_gcal_oauth_client_secret',
            'OAuth Client Secret',
            array($this, 'render_oauth_client_secret_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_oauth_client_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // OAuth Authorization Status
        add_settings_field(
            'antigravity_gcal_oauth_status',
            'Authorization Status',
            array($this, 'render_oauth_status_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );

        // Google Calendar ID
        add_settings_field(
            'antigravity_gcal_calendar_id',
            'Calendar ID',
            array($this, 'render_calendar_id_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_calendar_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'primary',
        ));

        // Google Calendar Sync Status Filter
        add_settings_field(
            'antigravity_gcal_sync_statuses',
            'Sync Booking Statuses',
            array($this, 'render_gcal_sync_statuses_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_sync_statuses', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_array'),
            'default' => array('approved'),
        ));

        // Email Settings: Customer Notifications
        add_settings_section(
            'antigravity_booking_email_customer',
            'Customer Notification Settings',
            array($this, 'render_customer_email_section'),
            'antigravity-booking-settings'
        );

        // Customer Instructions
        add_settings_field(
            'antigravity_booking_customer_instructions',
            'Submission Confirmation Message',
            array($this, 'render_customer_instructions_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_customer_instructions', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => 'Please complete all required forms and payment within 48 hours to secure your booking.',
        ));

        // First Reminder
        add_settings_field(
            'antigravity_booking_reminder_1_days',
            'First Reminder - Days Before',
            array($this, 'render_reminder_1_days_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_1_days', array(
            'type' => 'number',
            'sanitize_callback' => 'intval',
            'default' => 7,
        ));

        add_settings_field(
            'antigravity_booking_reminder_1_subject',
            'First Reminder - Subject',
            array($this, 'render_reminder_1_subject_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_1_subject', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Upcoming Booking Reminder',
        ));

        add_settings_field(
            'antigravity_booking_reminder_1_message',
            'First Reminder - Message Body',
            array($this, 'render_reminder_1_message_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_1_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => 'Your booking is coming up soon! Please ensure all requirements are completed.',
        ));

        // Second Reminder
        add_settings_field(
            'antigravity_booking_reminder_2_hours',
            'Second Reminder - Hours Before',
            array($this, 'render_reminder_2_hours_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_2_hours', array(
            'type' => 'number',
            'sanitize_callback' => 'intval',
            'default' => 48,
        ));

        add_settings_field(
            'antigravity_booking_reminder_2_subject',
            'Second Reminder - Subject',
            array($this, 'render_reminder_2_subject_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_2_subject', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Final Reminder: Booking in 48 Hours',
        ));

        add_settings_field(
            'antigravity_booking_reminder_2_message',
            'Second Reminder - Message Body',
            array($this, 'render_reminder_2_message_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_customer'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_reminder_2_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => 'Your booking is in 48 hours. Please ensure final payment is received and all requirements are met.',
        ));

        // Admin Notification Email Section
        add_settings_section(
            'antigravity_booking_email_admin',
            'Admin Notification Settings',
            array($this, 'render_admin_email_section'),
            'antigravity-booking-settings'
        );

        add_settings_field(
            'antigravity_booking_admin_email',
            'Notify Email Address',
            array($this, 'render_admin_email_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_admin'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_admin_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ));

        add_settings_field(
            'antigravity_booking_admin_subject',
            'Notify Subject',
            array($this, 'render_admin_subject_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_admin'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_admin_subject', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'New Booking Request Received',
        ));

        add_settings_field(
            'antigravity_booking_admin_message',
            'Notify Message Body',
            array($this, 'render_admin_message_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_admin'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_admin_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => "A new booking request has been submitted.\n\nCustomer: {customer_name}\nEmail: {customer_email}\nStart: {start_date}\nEnd: {end_date}\nEstimated Cost: {cost}\n\nView in Dashboard: {dashboard_url}",
        ));

        // Booking Approval Email Section
        add_settings_section(
            'antigravity_booking_email_approval',
            'Booking Approval Email',
            array($this, 'render_approval_email_section'),
            'antigravity-booking-settings'
        );

        add_settings_field(
            'antigravity_booking_approval_subject',
            'Approval Subject',
            array($this, 'render_approval_subject_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_approval'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_approval_subject', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Booking Confirmed!',
        ));

        add_settings_field(
            'antigravity_booking_approval_message',
            'Approval Message Body',
            array($this, 'render_approval_message_field'),
            'antigravity-booking-settings',
            'antigravity_booking_email_approval'
        );
        register_setting('antigravity_booking_settings', 'antigravity_booking_approval_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => "Hello {customer_name},\n\nGreat news! Your booking has been approved and confirmed.\n\nBooking Details:\nStart: {start_date}\nEnd: {end_date}\n\nYou will receive reminder emails 1 week and 2 days before your event.\n\nAn .ics calendar file is attached for your convenience.\n\nThank you!",
        ));
    }

    public function render_success_redirect_field()
    {
        $value = get_option('antigravity_booking_success_redirect', '');
        ?>
        <input type="url" name="antigravity_booking_success_redirect" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Optional: Enter a URL to redirect users to after a successful booking. Leave empty to show a success message on the same page.</p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'antigravity_booking_messages',
                'antigravity_booking_message',
                'Settings Saved',
                'updated'
            );
        }

        settings_errors('antigravity_booking_messages');
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('antigravity_booking_settings');
                do_settings_sections('antigravity-booking-settings');
                submit_button('Save Settings');
                ?>
            </form>

            <hr>
            <h2>System Status</h2>
            <table class="widefat">
                <tr>
                    <th>Google API Client</th>
                    <td>
                        <?php echo class_exists('Google_Client') ? '<span style="color: green;">✓ Installed</span>' : '<span style="color: red;">✗ Not Installed</span>'; ?>
                    </td>
                </tr>
                <tr>
                    <th>GCal Credentials</th>
                    <td>
                        <?php
                        $json_creds = get_option('antigravity_gcal_credentials_json');
                        $file_creds = get_option('antigravity_gcal_credentials_file');
                        
                        if ($json_creds) {
                            $decoded = json_decode(wp_unslash($json_creds), true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                echo '<span style="color: green;">✓ JSON Credentials Valid</span>';
                            } else {
                                echo '<span style="color: red;">✗ JSON Credentials Invalid: ' . esc_html(json_last_error_msg()) . '</span>';
                            }
                        } elseif ($file_creds && file_exists($file_creds)) {
                            echo '<span style="color: green;">✓ File Credentials Found</span>';
                        } else {
                            echo '<span style="color: orange;">⚠ Not configured or missing</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>WP-Cron</th>
                    <td>
                        <?php
                        $next_expiry = wp_next_scheduled('antigravity_check_expired_bookings');
                        $next_reminder = wp_next_scheduled('antigravity_send_reminders');
                        if ($next_expiry || $next_reminder) {
                            echo '<span style="color: green;">✓ Scheduled</span>';
                            if ($next_expiry) {
                                echo '<br><small>Next expiry check: ' . date('Y-m-d H:i:s', $next_expiry) . '</small>';
                            }
                        } else {
                            echo '<span style="color: orange;">⚠ Not scheduled (will be scheduled on next page load)</span>';
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>Quick Start Guide</h2>
            <ol>
                <li>Set your <strong>Hourly Rate</strong> above</li>
                <li>Follow the <a href="<?php echo plugin_dir_url(dirname(__FILE__)) . 'GOOGLE_CALENDAR_SETUP.md'; ?>"
                        target="_blank">Google Calendar Setup Guide</a></li>
                <li>Copy the contents of your Service Account JSON file</li>
                <li>Paste it into the <strong>Service Account JSON Credentials</strong> area above and save</li>
                <li>(Optional) Alternatively, enter the <strong>absolute path</strong> to the JSON file on your server</li>
                <li>Click <strong>Test Connection</strong> to verify it works</li>
                <li>Customize email messages to match your brand</li>
                <li>Create a page with <code>[antigravity_booking_calendar]</code> shortcode</li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render admin scripts in footer for better reliability
     */
    public function render_admin_scripts()
    {
        $screen = get_current_screen();
        // Be more inclusive with screen ID to avoid missing it
        if (!$screen || strpos($screen->id, 'antigravity-booking-settings') === false) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Antigravity Settings Script Loaded');

            $('#antigravity-clear-json').on('click', function(e) {
                if(confirm('Are you sure you want to clear the JSON credentials? This will allow you to use the Legacy File Path instead.')) {
                    $('textarea[name="antigravity_gcal_credentials_json"]').val('');
                    $('#submit').click(); // Auto-save to clear database
                }
            });

            $('#antigravity-test-gcal').on('click', function(e) {
                e.preventDefault();
                const btn = $(this);
                const status = $('#antigravity-gcal-test-status');
                
                console.log('Test Connection clicked');
                btn.prop('disabled', true).text('Testing...');
                status.text('... Connecting to Google API ...').css('color', 'orange');

                $.post(ajaxurl, {
                    action: 'antigravity_test_gcal',
                    nonce: '<?php echo wp_create_nonce("antigravity_test_gcal"); ?>'
                }, function(response) {
                    console.log('AJAX Response:', response);
                    btn.prop('disabled', false).text('Test Connection');
                    if (response.success) {
                        status.text('✓ ' + response.data.message).css('color', 'green');
                    } else {
                        const errorMsg = response.data ? response.data.message : 'Unknown Server Error (Check logs)';
                        status.text('✗ ' + errorMsg).css('color', 'red');
                    }
                }).fail(function(xhr) {
                    console.error('AJAX Failure:', xhr);
                    btn.prop('disabled', false).text('Test Connection');
                    status.text('✗ Server Request Failed (Status: ' + xhr.status + ')').css('color', 'red');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Section callbacks
     */
    public function render_general_section()
    {
        echo '<p>Configure basic plugin settings.</p>';
    }

    public function render_gcal_section()
    {
        $redirect_uri = site_url('/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1');
        echo '<p>Connect your Google Calendar using OAuth 2.0 for secure, easy authentication. No JSON files needed!</p>';
        echo '<p><strong>Setup Steps:</strong></p>';
        echo '<ol>';
        echo '<li>Create OAuth 2.0 credentials in <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>';
        echo '<li>Add this redirect URI to your OAuth client: <br><code style="background: #f0f0f1; padding: 5px; display: inline-block; margin-top: 5px;">' . esc_html($redirect_uri) . '</code></li>';
        echo '<li>Enter Client ID and Secret below</li>';
        echo '<li>Save settings, then click "Authorize with Google"</li>';
        echo '</ol>';
        echo '<p><strong>Important:</strong> The redirect URI must match EXACTLY in Google Cloud Console (including https://).</p>';
    }

    public function render_customer_email_section()
    {
        echo '<p>Customize the automated emails sent to customers after submission and as reminders.</p>';
    }

    public function render_admin_email_section()
    {
        echo '<p>Configure the notifications sent to the administrator when new bookings arrive.</p>';
    }

    public function render_approval_email_section()
    {
        echo '<p>This email is sent to the customer immediately when you change their booking status to <strong>Approved</strong>. Use <code>{customer_name}</code>, <code>{start_date}</code>, and <code>{end_date}</code> as placeholders.</p>';
    }


    /**
     * Field callbacks
     */
    public function render_hourly_rate_field()
    {
        $value = get_option('antigravity_booking_hourly_rate', 100);
        ?>
        <input type="number" step="0.01" name="antigravity_booking_hourly_rate" value="<?php echo esc_attr($value); ?>"
            class="regular-text">
        <p class="description">Used for automatic cost calculation. Overnight bookings (past 10 PM) auto-extend to 10 AM.</p>
        <?php
    }

    public function render_timezone_field()
    {
        $value = get_option('antigravity_booking_timezone', 'America/Los_Angeles');
        $timezones = timezone_identifiers_list();
        ?>
        <select name="antigravity_booking_timezone" class="regular-text">
            <?php foreach ($timezones as $tz): ?>
                <option value="<?php echo esc_attr($tz); ?>" <?php selected($value, $tz); ?>>
                    <?php echo esc_html($tz); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Timezone for bookings and calendar events.</p>
        <?php
    }

    public function render_oauth_client_id_field()
    {
        $value = get_option('antigravity_gcal_oauth_client_id', '');
        ?>
        <input type="text" name="antigravity_gcal_oauth_client_id"
               value="<?php echo esc_attr($value); ?>" class="large-text">
        <p class="description">OAuth 2.0 Client ID from Google Cloud Console.
           <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get credentials</a></p>
        <?php
    }

    public function render_oauth_client_secret_field()
    {
        $value = get_option('antigravity_gcal_oauth_client_secret', '');
        ?>
        <input type="password" name="antigravity_gcal_oauth_client_secret"
               value="<?php echo esc_attr($value); ?>" class="large-text">
        <p class="description">OAuth 2.0 Client Secret from Google Cloud Console</p>
        <?php
    }

    public function render_oauth_status_field()
    {
        $authorized = get_option('antigravity_gcal_oauth_authorized', false);
        $client_id = get_option('antigravity_gcal_oauth_client_id', '');
        $client_secret = get_option('antigravity_gcal_oauth_client_secret', '');
        
        if ($authorized) {
            $expires_at = get_option('antigravity_gcal_oauth_expires_at', 0);
            $expires_in = $expires_at - time();
            ?>
            <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                <p style="margin: 0; color: #155724;">
                    <span class="dashicons dashicons-yes-alt" style="color: #28a745;"></span>
                    <strong>Connected to Google Calendar</strong>
                </p>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #155724;">
                    Token expires in <?php echo human_time_diff(time(), $expires_at); ?>
                </p>
            </div>
            <p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                    <input type="hidden" name="action" value="disconnect_google_oauth">
                    <?php wp_nonce_field('disconnect_google_oauth'); ?>
                    <button type="submit" class="button"
                            onclick="return confirm('Are you sure you want to disconnect Google Calendar? You will need to re-authorize.');">
                        Disconnect Google Calendar
                    </button>
                </form>
            </p>
            <?php
        } elseif ($client_id && $client_secret) {
            // Create OAuth instance to get auth URL
            $oauth = new Antigravity_Booking_Google_OAuth();
            $auth_url = $oauth->get_auth_url();
            ?>
            <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                <p style="margin: 0; color: #856404;">
                    <span class="dashicons dashicons-warning" style="color: #ffc107;"></span>
                    <strong>Not Connected</strong>
                </p>
                <p style="margin: 10px 0 0 0;">Click the button below to authorize this plugin to access your Google Calendar.</p>
            </div>
            <p>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
                    Authorize with Google
                </a>
            </p>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #666;">Debug Information (click to expand)</summary>
                <div style="background: #f0f0f1; padding: 10px; margin-top: 5px; font-family: monospace; font-size: 11px;">
                    <p><strong>Client ID (first 30 chars):</strong> <?php echo esc_html(substr($client_id, 0, 30)); ?>...</p>
                    <p><strong>Client ID Length:</strong> <?php echo strlen($client_id); ?> characters</p>
                    <p><strong>Redirect URI:</strong> <?php echo esc_html(site_url('/wp-admin/admin.php?page=antigravity-booking-settings&oauth_callback=1')); ?></p>
                    <p><strong>Auth URL (first 150 chars):</strong><br><?php echo esc_html(substr($auth_url, 0, 150)); ?>...</p>
                </div>
            </details>
            <?php
        } else {
            ?>
            <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                <p style="margin: 0; color: #721c24;">
                    <span class="dashicons dashicons-info" style="color: #dc3545;"></span>
                    <strong>Configuration Required</strong>
                </p>
                <p style="margin: 10px 0 0 0;">Enter your OAuth Client ID and Client Secret above, then save settings to enable authorization.</p>
            </div>
            <?php
        }
        
        // Show OAuth success/error messages
        if (isset($_GET['oauth_success'])) {
            ?>
            <div class="notice notice-success" style="margin-top: 10px;">
                <p><strong>Success!</strong> Google Calendar has been authorized successfully.</p>
            </div>
            <?php
        }
        
        if (isset($_GET['oauth_error'])) {
            $error = sanitize_text_field($_GET['oauth_error']);
            ?>
            <div class="notice notice-error" style="margin-top: 10px;">
                <p><strong>Authorization Error:</strong> <?php echo esc_html($error); ?></p>
            </div>
            <?php
        }
        
        if (isset($_GET['oauth_disconnected'])) {
            ?>
            <div class="notice notice-info" style="margin-top: 10px;">
                <p>Google Calendar has been disconnected.</p>
            </div>
            <?php
        }
    }

    public function render_calendar_id_field()
    {
        $value = get_option('antigravity_gcal_calendar_id', 'primary');
        ?>
        <input type="text" name="antigravity_gcal_calendar_id" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Usually "primary" or your calendar ID from Google Calendar settings.</p>
        <?php
    }

    public function render_customer_instructions_field()
    {
        $value = get_option('antigravity_booking_customer_instructions', 'Please complete all required forms and payment within 48 hours to secure your booking.');
        ?>
        <textarea name="antigravity_booking_customer_instructions" rows="4"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Instructions sent to customers after they submit a booking request.</p>
        <?php
    }

    public function render_reminder_message_field()
    {
        $value = get_option('antigravity_booking_reminder_message', 'Please ensure all requirements are completed before your event.');
        ?>
        <textarea name="antigravity_booking_reminder_message" rows="4"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Message included in reminder emails (1 week and 2 days before event).</p>
        <?php
    }

    public function render_admin_email_field()
    {
        $value = get_option('antigravity_booking_admin_email', get_option('admin_email'));
        ?>
        <input type="email" name="antigravity_booking_admin_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Email address to receive booking notifications. Defaults to site admin email.</p>
        <?php
    }

    public function render_cutoff_hours_field()
    {
        $value = get_option('antigravity_booking_cutoff_hours', 48);
        ?>
                <input type="number" min="0" name="antigravity_booking_cutoff_hours" value="<?php echo esc_attr($value); ?>"
                    class="small-text">
                <p class="description">Hours after submission before a pending booking expires (set to 0 to disable expiry).</p>
                <?php
    }

    public function render_gcal_sync_statuses_field()
    {
        $selected = get_option('antigravity_gcal_sync_statuses', array('approved'));
        $statuses = array(
            'pending_review' => 'Pending Review',
            'approved' => 'Approved',
        );
        ?>
                <fieldset>
                    <?php foreach ($statuses as $value => $label): ?>
                            <label>
                                <input type="checkbox" name="antigravity_gcal_sync_statuses[]" value="<?php echo esc_attr($value); ?>"
                                    <?php checked(in_array($value, (array) $selected)); ?>>
                                <?php echo esc_html($label); ?>
                            </label><br>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description">Select which booking statuses should sync to Google Calendar.</p>
                <?php
    }

    public function render_reminder_1_days_field()
    {
        $value = get_option('antigravity_booking_reminder_1_days', 7);
        ?>
                <input type="number" min="0" name="antigravity_booking_reminder_1_days" value="<?php echo esc_attr($value); ?>"
                    class="small-text">
                <p class="description">Days before the booking to send the first reminder.</p>
                <?php
    }

    public function render_reminder_1_subject_field()
    {
        $value = get_option('antigravity_booking_reminder_1_subject', 'Upcoming Booking Reminder');
        ?>
                <input type="text" name="antigravity_booking_reminder_1_subject" value="<?php echo esc_attr($value); ?>"
                    class="large-text">
                <?php
    }

    public function render_reminder_1_message_field()
    {
        $value = get_option('antigravity_booking_reminder_1_message', 'Your booking is coming up soon! Please ensure all requirements are completed.');
        ?>
                <textarea name="antigravity_booking_reminder_1_message" rows="4" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php
    }

    public function render_reminder_2_hours_field()
    {
        $value = get_option('antigravity_booking_reminder_2_hours', 48);
        ?>
                <input type="number" min="0" name="antigravity_booking_reminder_2_hours" value="<?php echo esc_attr($value); ?>"
                    class="small-text">
                <p class="description">Hours before the booking to send the second reminder.</p>
                <?php
    }

    public function render_reminder_2_subject_field()
    {
        $value = get_option('antigravity_booking_reminder_2_subject', 'Final Reminder: Booking in 48 Hours');
        ?>
                <input type="text" name="antigravity_booking_reminder_2_subject" value="<?php echo esc_attr($value); ?>"
                    class="large-text">
                <?php
    }

    public function render_reminder_2_message_field()
    {
        $value = get_option('antigravity_booking_reminder_2_message', 'Your booking is in 48 hours. Please ensure final payment is received and all requirements are met.');
        ?>
                <textarea name="antigravity_booking_reminder_2_message" rows="4" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php
    }

    // Section descriptions
    public function render_availability_section() {
        echo '<p>Configure which days and times your space is available for booking.</p>';
    }

    public function render_overnight_section() {
        echo '<p>Configure overnight booking rules. If a booking starts after the cutoff time, it will automatically extend to the next morning.</p>';
    }

    // Availability Fields
    public function render_available_days_field() {
        $value = get_option('antigravity_booking_available_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'));
        $days = array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        );
        ?>
        <fieldset>
            <?php foreach ($days as $key => $label): ?>
                <label style="display: inline-block; margin-right: 15px;">
                    <input type="checkbox" name="antigravity_booking_available_days[]" value="<?php echo esc_attr($key); ?>" 
                           <?php checked(in_array($key, $value)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">Select which days of the week are available for bookings.</p>
        <?php
    }

    public function render_hours_per_day_field() {
        $values = get_option('antigravity_booking_hours_per_day', array(
            'monday' => array('start' => '09:00', 'end' => '22:00'),
            'tuesday' => array('start' => '09:00', 'end' => '22:00'),
            'wednesday' => array('start' => '09:00', 'end' => '22:00'),
            'thursday' => array('start' => '09:00', 'end' => '22:00'),
            'friday' => array('start' => '09:00', 'end' => '22:00'),
            'saturday' => array('start' => '09:00', 'end' => '22:00'),
            'sunday' => array('start' => '09:00', 'end' => '22:00'),
        ));

        $days = array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        );

        ?>
        <table class="widefat" style="max-width: 400px;">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $key => $label): 
                    $start = isset($values[$key]['start']) ? $values[$key]['start'] : '09:00';
                    $end = isset($values[$key]['end']) ? $values[$key]['end'] : '22:00';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td>
                            <input type="time" 
                                   name="antigravity_booking_hours_per_day[<?php echo esc_attr($key); ?>][start]" 
                                   value="<?php echo esc_attr($start); ?>">
                        </td>
                        <td>
                            <input type="time" 
                                   name="antigravity_booking_hours_per_day[<?php echo esc_attr($key); ?>][end]" 
                                   value="<?php echo esc_attr($end); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">Set business hours for each day of the week. Only uncheck unavailable days above.</p>
        <?php
    }

    public function render_blackout_dates_field() {
        $value = get_option('antigravity_booking_blackout_dates', '');
        ?>
        <textarea name="antigravity_booking_blackout_dates" rows="4" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Enter unavailable dates, one per line. Format: YYYY-MM-DD (e.g., 2026-12-25 for Christmas)</p>
        <?php
    }

    // Overnight Pricing Fields
    public function render_overnight_days_field() {
        $value = get_option('antigravity_booking_overnight_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'));
        $days = array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        );
        ?>
        <fieldset>
            <?php foreach ($days as $key => $label): ?>
                <label style="display: inline-block; margin-right: 15px;">
                    <input type="checkbox" name="antigravity_booking_overnight_days[]" value="<?php echo esc_attr($key); ?>" 
                           <?php checked(in_array($key, (array)$value)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">Select which days support overnight bookings (starting on that day).</p>
        <?php
    }

    public function render_overnight_cutoff_field() {
        $value = get_option('antigravity_booking_overnight_cutoff', '22:00');
        ?>
        <input type="time" name="antigravity_booking_overnight_cutoff" value="<?php echo esc_attr($value); ?>">
        <p class="description">If booking starts at or after this time, it becomes an overnight booking (default: 22:00 / 10 PM).</p>
        <?php
    }

    public function render_overnight_extend_field() {
        $value = get_option('antigravity_booking_overnight_extend', '10:00');
        ?>
        <input type="time" name="antigravity_booking_overnight_extend" value="<?php echo esc_attr($value); ?>">
        <p class="description">Overnight bookings automatically extend to this time the next morning (default: 10:00 / 10 AM).</p>
        <?php
    }

    public function render_overnight_times_field() {
        $value = get_option('antigravity_booking_overnight_times', array(
            'monday' => array('start' => '22:00', 'end' => '10:00'),
            'tuesday' => array('start' => '22:00', 'end' => '10:00'),
            'wednesday' => array('start' => '22:00', 'end' => '10:00'),
            'thursday' => array('start' => '22:00', 'end' => '10:00'),
            'friday' => array('start' => '22:00', 'end' => '10:00'),
            'saturday' => array('start' => '22:00', 'end' => '10:00'),
            'sunday' => array('start' => '22:00', 'end' => '10:00'),
        ));
        
        $days = array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        );
        ?>
        <p class="description">Configure different overnight times for each day of the week. Start time is when overnight pricing begins, end time is when it ends the next morning.</p>
        <table class="widefat" style="max-width: 600px; margin-top: 10px;">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Start Time (Evening)</th>
                    <th>End Time (Next Morning)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day_key => $day_label): ?>
                    <tr>
                        <td><strong><?php echo esc_html($day_label); ?></strong></td>
                        <td>
                            <input type="time"
                                   name="antigravity_booking_overnight_times[<?php echo esc_attr($day_key); ?>][start]"
                                   value="<?php echo esc_attr($value[$day_key]['start'] ?? '22:00'); ?>"
                                   style="width: 120px;">
                        </td>
                        <td>
                            <input type="time"
                                   name="antigravity_booking_overnight_times[<?php echo esc_attr($day_key); ?>][end]"
                                   value="<?php echo esc_attr($value[$day_key]['end'] ?? '10:00'); ?>"
                                   style="width: 120px;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_special_hours_field() {
        $value = get_option('antigravity_booking_special_hours', array());
        ?>
        <p class="description">Override overnight times for specific dates (e.g., holidays). These take priority over day-specific times.</p>
        <div id="special-hours-container">
            <table class="widefat" style="max-width: 700px; margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="special-hours-list">
                    <?php if (!empty($value)): ?>
                        <?php foreach ($value as $date => $times): ?>
                            <tr class="special-hour-row">
                                <td>
                                    <input type="date"
                                           name="antigravity_booking_special_hours_dates[]"
                                           value="<?php echo esc_attr($date); ?>"
                                           style="width: 150px;">
                                </td>
                                <td>
                                    <input type="time"
                                           name="antigravity_booking_special_hours_start[]"
                                           value="<?php echo esc_attr($times['start'] ?? '22:00'); ?>"
                                           style="width: 120px;">
                                </td>
                                <td>
                                    <input type="time"
                                           name="antigravity_booking_special_hours_end[]"
                                           value="<?php echo esc_attr($times['end'] ?? '10:00'); ?>"
                                           style="width: 120px;">
                                </td>
                                <td>
                                    <button type="button" class="button remove-special-hour">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="button" id="add-special-hour" style="margin-top: 10px;">+ Add Special Date</button>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#add-special-hour').on('click', function() {
                const row = $('<tr class="special-hour-row">' +
                    '<td><input type="date" name="antigravity_booking_special_hours_dates[]" style="width: 150px;"></td>' +
                    '<td><input type="time" name="antigravity_booking_special_hours_start[]" value="22:00" style="width: 120px;"></td>' +
                    '<td><input type="time" name="antigravity_booking_special_hours_end[]" value="10:00" style="width: 120px;"></td>' +
                    '<td><button type="button" class="button remove-special-hour">Remove</button></td>' +
                    '</tr>');
                $('#special-hours-list').append(row);
            });
            
            $(document).on('click', '.remove-special-hour', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    // Sanitization callbacks
    public function sanitize_checkbox($value) {
        return $value ? 1 : 0;
    }

    public function sanitize_array($value) {
        return is_array($value) ? array_map('sanitize_text_field', $value) : array();
    }

    public function sanitize_hours_per_day($value) {
        if (!is_array($value)) {
            return array();
        }

        $sanitized = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        foreach ($days as $day) {
            if (isset($value[$day]) && is_array($value[$day])) {
                $sanitized[$day] = array(
                    'start' => isset($value[$day]['start']) ? sanitize_text_field($value[$day]['start']) : '09:00',
                    'end' => isset($value[$day]['end']) ? sanitize_text_field($value[$day]['end']) : '22:00',
                );
            } else {
                $sanitized[$day] = array('start' => '09:00', 'end' => '22:00');
            }
        }

        return $sanitized;
    }

    public function sanitize_overnight_times($value) {
        if (!is_array($value)) {
            return array();
        }

        $sanitized = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        foreach ($days as $day) {
            if (isset($value[$day]) && is_array($value[$day])) {
                $sanitized[$day] = array(
                    'start' => isset($value[$day]['start']) ? sanitize_text_field($value[$day]['start']) : '22:00',
                    'end' => isset($value[$day]['end']) ? sanitize_text_field($value[$day]['end']) : '10:00',
                );
            } else {
                $sanitized[$day] = array('start' => '22:00', 'end' => '10:00');
            }
        }

        return $sanitized;
    }

    public function sanitize_special_hours($value) {
        // Special hours come in as separate arrays from the form
        if (isset($_POST['antigravity_booking_special_hours_dates'])) {
            $dates = $_POST['antigravity_booking_special_hours_dates'];
            $starts = $_POST['antigravity_booking_special_hours_start'];
            $ends = $_POST['antigravity_booking_special_hours_end'];
            
            $sanitized = array();
            
            for ($i = 0; $i < count($dates); $i++) {
                $date = sanitize_text_field($dates[$i]);
                if (!empty($date)) {
                    $sanitized[$date] = array(
                        'start' => isset($starts[$i]) ? sanitize_text_field($starts[$i]) : '22:00',
                        'end' => isset($ends[$i]) ? sanitize_text_field($ends[$i]) : '10:00',
                    );
                }
            }
            
            return $sanitized;
        }
        
        return is_array($value) ? $value : array();
    }
    public function render_admin_subject_field()
    {
        $value = get_option('antigravity_booking_admin_subject', 'New Booking Request Received');
        ?>
        <input type="text" name="antigravity_booking_admin_subject" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_admin_message_field()
    {
        $value = get_option('antigravity_booking_admin_message', "A new booking request has been submitted.\n\nCustomer: {customer_name}\nEmail: {customer_email}\nStart: {start_date}\nEnd: {end_date}\nEstimated Cost: {cost}\n\nView in Dashboard: {dashboard_url}");
        ?>
        <textarea name="antigravity_booking_admin_message" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Available placeholders: {customer_name}, {customer_email}, {start_date}, {end_date}, {cost}, {phone}, {guests}, {description}, {dashboard_url}</p>
        <?php
    }

    public function render_approval_subject_field()
    {
        $value = get_option('antigravity_booking_approval_subject', 'Booking Confirmed!');
        ?>
        <input type="text" name="antigravity_booking_approval_subject" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_approval_message_field()
    {
        $value = get_option('antigravity_booking_approval_message', "Hello {customer_name},\n\nGreat news! Your booking has been approved and confirmed.\n\nBooking Details:\nStart: {start_date}\nEnd: {end_date}\n\nYou will receive reminder emails 1 week and 2 days before your event.\n\nAn .ics calendar file is attached for your convenience.\n\nThank you!");
        ?>
        <textarea name="antigravity_booking_approval_message" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Available placeholders: {customer_name}, {start_date}, {end_date}</p>
        <?php
    }

    /**
     * AJAX: Test Google Calendar Connection
     */
    public function ajax_test_gcal_connection()
    {
        // Start output buffering to catch any stray output
        ob_start();
        
        // Force errors to be logged but NOT displayed (prevents breaking JSON)
        @ini_set('display_errors', 0);
        
        // Capture any fatal errors that might occur during the check
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
                error_log('Antigravity Booking: FATAL ERROR during AJAX: ' . print_r($error, true));
                // We can't easily return JSON here if output already started, but we logged it
            }
        });

        check_ajax_referer('antigravity_test_gcal', 'nonce');

        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        try {
            error_log('Antigravity Booking: AJAX Connection Test Started');
            
            // 1. Ensure the class file exists and is loaded
            $path = dirname(__FILE__) . '/class-antigravity-booking-google-calendar.php';
            if (!file_exists($path)) {
                error_log('Antigravity Booking: FILE NOT FOUND: ' . $path);
                throw new Exception('File not found: ' . $path);
            }
            require_once $path;

            if (!class_exists('Antigravity_Booking_Google_Calendar')) {
                 throw new Exception('Antigravity_Booking_Google_Calendar class not found after require.');
            }

            // 2. Initialize (DO NOT call init() - we don't want to register hooks during test)
            error_log('Antigravity Booking: Initializing GCal Class');
            $gcal = new Antigravity_Booking_Google_Calendar();
            
            // 3. Test connection
            error_log('Antigravity Booking: Calling test_connection()');
            $gcal->test_connection();
            
            error_log('Antigravity Booking: AJAX Connection Test Success');
            
            // Clean output buffer before sending success
            if (ob_get_length()) ob_clean();
            wp_send_json_success(array('message' => 'Connection successful! (Google Calendar is reachable)'));
        } catch (Error $e) {
            error_log('Antigravity Booking: AJAX Connection Test FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (ob_get_length()) ob_clean();
            wp_send_json_error(array('message' => 'PHP Fatal Error: ' . $e->getMessage()));
        } catch (Exception $e) {
            error_log('Antigravity Booking: AJAX Connection Test EXCEPTION: ' . $e->getMessage());
            if (ob_get_length()) ob_clean();
            wp_send_json_error(array('message' => $e->getMessage()));
        } catch (Throwable $t) {
            error_log('Antigravity Booking: AJAX Connection Test THROWABLE: ' . $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine());
            if (ob_get_length()) ob_clean();
            wp_send_json_error(array('message' => 'Generic Error: ' . $t->getMessage()));
        }
        exit;
    }
}
