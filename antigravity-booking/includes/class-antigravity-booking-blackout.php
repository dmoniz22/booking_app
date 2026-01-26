<?php
/**
 * Blackout Dates Management
 * Handles blackout date custom post type and related functionality
 */
class Antigravity_Booking_Blackout
{

    public function __construct()
    {
        add_action('init', array($this, 'register_blackout_cpt'));
        add_action('add_meta_boxes', array($this, 'add_blackout_meta_boxes'));
        add_action('save_post_blackout_date', array($this, 'save_blackout_meta'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_add_blackout_date', array($this, 'ajax_add_blackout_date'));
        add_action('wp_ajax_remove_blackout_date', array($this, 'ajax_remove_blackout_date'));
        add_action('wp_ajax_get_blackout_dates', array($this, 'ajax_get_blackout_dates'));
    }

    /**
     * Register Blackout Date Custom Post Type
     */
    public function register_blackout_cpt()
    {
        $labels = array(
            'name' => 'Blackout Dates',
            'singular_name' => 'Blackout Date',
            'menu_name' => 'Blackout Dates',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Blackout Date',
            'edit_item' => 'Edit Blackout Date',
            'new_item' => 'New Blackout Date',
            'view_item' => 'View Blackout Date',
            'search_items' => 'Search Blackout Dates',
            'not_found' => 'No blackout dates found',
            'not_found_in_trash' => 'No blackout dates found in Trash',
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'antigravity-booking',
            'menu_icon' => 'dashicons-calendar-alt',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        );

        register_post_type('blackout_date', $args);
    }

    /**
     * Add meta boxes for blackout date details
     */
    public function add_blackout_meta_boxes()
    {
        add_meta_box(
            'blackout_details',
            'Blackout Date Details',
            array($this, 'render_blackout_details_meta_box'),
            'blackout_date',
            'normal',
            'high'
        );
    }

    /**
     * Render blackout details meta box
     */
    public function render_blackout_details_meta_box($post)
    {
        wp_nonce_field('blackout_meta_box', 'blackout_meta_box_nonce');

        $start_date = get_post_meta($post->ID, '_blackout_start_date', true);
        $end_date = get_post_meta($post->ID, '_blackout_end_date', true);
        $reason = get_post_meta($post->ID, '_blackout_reason', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="blackout_start_date">Start Date</label></th>
                <td>
                    <input type="date" id="blackout_start_date" name="blackout_start_date" 
                           value="<?php echo esc_attr($start_date); ?>" required style="width: 100%;" />
                    <p class="description">First date of the blackout period</p>
                </td>
            </tr>
            <tr>
                <th><label for="blackout_end_date">End Date</label></th>
                <td>
                    <input type="date" id="blackout_end_date" name="blackout_end_date" 
                           value="<?php echo esc_attr($end_date ?: $start_date); ?>" required style="width: 100%;" />
                    <p class="description">Last date of the blackout period (same as start for single day)</p>
                </td>
            </tr>
            <tr>
                <th><label for="blackout_reason">Reason (Optional)</label></th>
                <td>
                    <input type="text" id="blackout_reason" name="blackout_reason" 
                           value="<?php echo esc_attr($reason); ?>" style="width: 100%;" 
                           placeholder="e.g., Holiday, Maintenance, Private Event" />
                    <p class="description">Optional description for this blackout period</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save blackout meta data
     */
    public function save_blackout_meta($post_id, $post)
    {
        // Verify nonce
        if (!isset($_POST['blackout_meta_box_nonce']) || !wp_verify_nonce($_POST['blackout_meta_box_nonce'], 'blackout_meta_box')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save meta fields
        if (isset($_POST['blackout_start_date'])) {
            $start_date = sanitize_text_field($_POST['blackout_start_date']);
            update_post_meta($post_id, '_blackout_start_date', $start_date);
        }

        if (isset($_POST['blackout_end_date'])) {
            $end_date = sanitize_text_field($_POST['blackout_end_date']);
            update_post_meta($post_id, '_blackout_end_date', $end_date);
        } else {
            // If no end date, use start date
            $end_date = sanitize_text_field($_POST['blackout_start_date']);
            update_post_meta($post_id, '_blackout_end_date', $end_date);
        }

        if (isset($_POST['blackout_reason'])) {
            update_post_meta($post_id, '_blackout_reason', sanitize_text_field($_POST['blackout_reason']));
        }

        // Update post title to reflect date range
        $title = 'Blackout: ' . date('M j, Y', strtotime($start_date));
        if ($end_date && $end_date !== $start_date) {
            $title .= ' - ' . date('M j, Y', strtotime($end_date));
        }
        
        // Prevent infinite loop
        remove_action('save_post_blackout_date', array($this, 'save_blackout_meta'), 10);
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title,
        ));
        add_action('save_post_blackout_date', array($this, 'save_blackout_meta'), 10, 2);
    }

    /**
     * AJAX: Add blackout date
     */
    public function ajax_add_blackout_date()
    {
        check_ajax_referer('antigravity_blackout_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : $start_date;
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

        if (empty($start_date)) {
            wp_send_json_error('Start date is required');
        }

        // Create blackout date post
        $title = 'Blackout: ' . date('M j, Y', strtotime($start_date));
        if ($end_date && $end_date !== $start_date) {
            $title .= ' - ' . date('M j, Y', strtotime($end_date));
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'blackout_date',
            'post_title' => $title,
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create blackout date');
        }

        update_post_meta($post_id, '_blackout_start_date', $start_date);
        update_post_meta($post_id, '_blackout_end_date', $end_date);
        if ($reason) {
            update_post_meta($post_id, '_blackout_reason', $reason);
        }

        wp_send_json_success(array(
            'id' => $post_id,
            'title' => $title,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reason' => $reason,
        ));
    }

    /**
     * AJAX: Remove blackout date
     */
    public function ajax_remove_blackout_date()
    {
        check_ajax_referer('antigravity_blackout_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'blackout_date') {
            wp_send_json_error('Invalid blackout date ID');
        }

        $result = wp_delete_post($post_id, true);

        if ($result) {
            wp_send_json_success('Blackout date removed');
        } else {
            wp_send_json_error('Failed to remove blackout date');
        }
    }

    /**
     * AJAX: Get all blackout dates
     */
    public function ajax_get_blackout_dates()
    {
        check_ajax_referer('antigravity_blackout_nonce', 'nonce');

        $blackouts = new WP_Query(array(
            'post_type' => 'blackout_date',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));

        $dates = array();

        if ($blackouts->have_posts()) {
            while ($blackouts->have_posts()) {
                $blackouts->the_post();
                $post_id = get_the_ID();
                
                $dates[] = array(
                    'id' => $post_id,
                    'start_date' => get_post_meta($post_id, '_blackout_start_date', true),
                    'end_date' => get_post_meta($post_id, '_blackout_end_date', true),
                    'reason' => get_post_meta($post_id, '_blackout_reason', true),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success($dates);
    }

    /**
     * Check if a date falls within any blackout period
     *
     * @param string $date Date to check (Y-m-d format)
     * @return bool True if date is blacked out
     */
    public static function is_date_blacked_out($date)
    {
        $blackouts = new WP_Query(array(
            'post_type' => 'blackout_date',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_blackout_start_date',
                    'value' => $date,
                    'compare' => '<=',
                    'type' => 'DATE',
                ),
                array(
                    'key' => '_blackout_end_date',
                    'value' => $date,
                    'compare' => '>=',
                    'type' => 'DATE',
                ),
            ),
        ));

        return $blackouts->have_posts();
    }

    /**
     * Get all blackout dates as array
     *
     * @return array Array of blackout date ranges
     */
    public static function get_all_blackout_dates()
    {
        $blackouts = new WP_Query(array(
            'post_type' => 'blackout_date',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));

        $dates = array();

        if ($blackouts->have_posts()) {
            while ($blackouts->have_posts()) {
                $blackouts->the_post();
                $post_id = get_the_ID();
                
                $dates[] = array(
                    'id' => $post_id,
                    'start_date' => get_post_meta($post_id, '_blackout_start_date', true),
                    'end_date' => get_post_meta($post_id, '_blackout_end_date', true),
                    'reason' => get_post_meta($post_id, '_blackout_reason', true),
                );
            }
            wp_reset_postdata();
        }

        return $dates;
    }
}
