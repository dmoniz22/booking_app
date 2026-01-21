<?php

/**
 * Validates Shortcode and Enqueues Assets
 */
class Antigravity_Booking_Shortcode
{

    public function __construct()
    {
        add_shortcode('antigravity_booking_calendar', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue front-end scripts and styles
     */
    public function enqueue_assets()
    {
        // Only enqueue if the shortcode is present (optimized loading)
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'antigravity_booking_calendar')) {

            // Flatpickr CSS
            wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');

            // Plugin CSS
            wp_enqueue_style('antigravity-booking-css', plugin_dir_url(dirname(__FILE__)) . 'public/css/antigravity-booking-public.css', array(), '1.0.0');

            // Flatpickr JS
            wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);

            // Plugin JS
            wp_enqueue_script('antigravity-booking-js', plugin_dir_url(dirname(__FILE__)) . 'public/js/antigravity-booking-public.js', array('jquery', 'flatpickr-js'), '1.0.1', true);

            // Localize script to pass AJAX URL and nonce
            wp_localize_script('antigravity-booking-js', 'antigravity_booking_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('antigravity_booking_nonce'),
                'settings' => array(
                    'hourly_rate' => get_option('antigravity_booking_hourly_rate', 100),
                    'overnight_enabled' => get_option('antigravity_booking_overnight_enabled', true),
                )
            ));
        }
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts)
    {
        ob_start();
        ?>
        <div id="antigravity-booking-app" role="application" aria-label="Booking Calendar">
            <!-- Calendar Container -->
            <div id="antigravity-calendar-container">
                <div class="antigravity-calendar-header">
                    <h3>Select a Date</h3>
                </div>
                <!-- Flatpickr will attach here -->
                <div id="antigravity-datepicker"
                     role="button"
                     aria-label="Select booking date"
                     tabindex="0">
                </div>
            </div>

            <!-- Time Slots Container (Hidden initially) -->
            <div id="antigravity-time-slots" style="display:none;" aria-live="polite">
                <h3>Available Times</h3>
                <div class="slots-grid"
                     role="listbox"
                     aria-label="Available time slots"
                     aria-describedby="range-instruction">
                </div>
                <div id="range-instruction" style="margin-top:20px; text-align:center;">
                    <p style="font-size:0.9em; margin-bottom:10px;">Select a start time and an end time.</p>
                    <button class="back-button" id="back-to-cal-2" type="button">Pick a different date</button>
                </div>
            </div>

            <!-- Booking Form (Hidden initially) -->
            <div id="antigravity-booking-form" style="display:none;">
                <h3>Complete Your Booking</h3>
                <form id="antigravity-booking-form-el" aria-label="Booking details form">
                    <input type="hidden" name="action" value="antigravity_create_booking">
                    <input type="hidden" id="selected_date" name="date">
                    <input type="hidden" id="selected_start_time" name="start_time">
                    <input type="hidden" id="selected_end_time" name="end_time">

                    <div class="form-group">
                        <label for="customer_name" id="name-label">Name *</label>
                        <input type="text"
                               id="customer_name"
                               name="customer_name"
                               required
                               aria-labelledby="name-label"
                               aria-required="true"
                               autocomplete="name">
                    </div>

                    <div class="form-group">
                        <label for="customer_email" id="email-label">Email *</label>
                        <input type="email"
                               id="customer_email"
                               name="customer_email"
                               required
                               aria-labelledby="email-label"
                               aria-required="true"
                               autocomplete="email">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone" id="phone-label">Phone Number</label>
                        <input type="tel"
                               id="customer_phone"
                               name="customer_phone"
                               aria-labelledby="phone-label"
                               autocomplete="tel">
                    </div>

                    <div class="form-group">
                        <label for="guest_count" id="guests-label">Estimated Number of Guests *</label>
                        <input type="number"
                               id="guest_count"
                               name="guest_count"
                               min="1"
                               required
                               aria-labelledby="guests-label"
                               aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="event_description" id="desc-label">Brief Description of Event *</label>
                        <textarea id="event_description"
                                  name="event_description"
                                  rows="3"
                                  required
                                  aria-labelledby="desc-label"
                                  aria-required="true"></textarea>
                    </div>

                    <div class="booking-summary" role="region" aria-label="Booking summary">
                        <p><strong>Date:</strong> <span id="summary-date"></span></p>
                        <p><strong>Time:</strong> <span id="summary-time"></span></p>
                        <p><strong>Total Cost:</strong> $<span id="summary-cost"></span></p>
                        <p id="overnight-note" style="display:none; color: #666; font-size: 0.9em;">
                            *Includes overnight stay until 10:00 AM next day.
                        </p>
                    </div>

                    <button type="submit" class="submit-button" aria-label="Submit booking">Book Now</button>
                    <button type="button" class="back-button" aria-label="Go back to time selection">Back</button>
                </form>
            </div>

            <div id="antigravity-booking-message" role="alert" aria-live="assertive"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
