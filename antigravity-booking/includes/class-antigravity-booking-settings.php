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

        // Google Calendar Section
        add_settings_section(
            'antigravity_booking_gcal',
            'Google Calendar Integration',
            array($this, 'render_gcal_section'),
            'antigravity-booking-settings'
        );

        // Google Calendar Credentials (JSON) - NEW STREAMLINED METHOD
        add_settings_field(
            'antigravity_gcal_credentials_json',
            'Service Account JSON Credentials',
            array($this, 'render_credentials_json_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_credentials_json', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
        ));

        // Google Calendar Credentials File (Legacy) - Keep for backward compatibility
        add_settings_field(
            'antigravity_gcal_credentials_file',
            'Service Account JSON File Path (Legacy)',
            array($this, 'render_credentials_field'),
            'antigravity-booking-settings',
            'antigravity_booking_gcal'
        );
        register_setting('antigravity_booking_settings', 'antigravity_gcal_credentials_file', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

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
                            echo '<span style="color: green;">✓ JSON Credentials Present</span>';
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
        <script>
        jQuery(document).ready(function($) {
            $('#antigravity-test-gcal').on('click', function(e) {
                e.preventDefault();
                const btn = $(this);
                const status = $('#antigravity-gcal-test-status');
                
                btn.prop('disabled', true).text('Testing...');
                status.text('').removeClass('updated error');

                $.post(ajaxurl, {
                    action: 'antigravity_test_gcal',
                    nonce: '<?php echo wp_create_nonce("antigravity_test_gcal"); ?>'
                }, function(response) {
                    btn.prop('disabled', false).text('Test Connection');
                    if (response.success) {
                        status.text('✓ ' + response.data.message).css('color', 'green');
                    } else {
                        status.text('✗ ' + response.data.message).css('color', 'red');
                    }
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
        echo '<p>Connect your Google Calendar to automatically sync approved bookings. See <a href="' . plugin_dir_url(dirname(__FILE__)) . 'GOOGLE_CALENDAR_SETUP.md" target="_blank">setup guide</a>.</p>';
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

    public function render_credentials_json_field()
    {
        $value = get_option('antigravity_gcal_credentials_json', '');
        ?>
        <textarea name="antigravity_gcal_credentials_json" rows="10" cols="50" class="large-text code" placeholder='{"type": "service_account", ...}'><?php echo esc_textarea($value); ?></textarea>
        <p class="description">Paste the entire contents of your Google Service Account JSON file here.</p>
        <button type="button" id="antigravity-test-gcal" class="button button-secondary">Test Connection</button>
        <span id="antigravity-gcal-test-status" style="margin-left: 10px; font-weight: bold;"></span>
        <?php
    }

    public function render_credentials_field()
    {
        $value = get_option('antigravity_gcal_credentials_file', '');
        ?>
        <input type="text" name="antigravity_gcal_credentials_file" value="<?php echo esc_attr($value); ?>"
            class="large-text code">
        <p class="description">Legacy: Absolute server path to your Google Service Account JSON file (e.g.,
            <code>/var/www/credentials.json</code>).
        </p>
        <?php
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
}
