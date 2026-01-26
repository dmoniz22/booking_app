<?php
/**
 * Custom Post Type: Booking
 */
class Antigravity_Booking_CPT
{

	public function __construct()
	{
		add_action('init', array($this, 'register_booking_cpt'));
		add_action('init', array($this, 'register_booking_statuses'));
		add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
		add_action('save_post_booking', array($this, 'save_booking_meta'), 10, 2);
		add_action('admin_notices', array($this, 'display_booking_notices'));
		add_filter('post_updated_messages', array($this, 'booking_updated_messages'));
	}

	/**
	 * Register Booking Custom Post Type
	 */
	public function register_booking_cpt()
	{
		$labels = array(
			'name' => 'Bookings',
			'singular_name' => 'Booking',
			'menu_name' => 'Bookings',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New Booking',
			'edit_item' => 'Edit Booking',
			'new_item' => 'New Booking',
			'view_item' => 'View Booking',
			'search_items' => 'Search Bookings',
			'not_found' => 'No bookings found',
			'not_found_in_trash' => 'No bookings found in Trash',
		);

		$args = array(
			'labels' => $labels,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false, // Hidden, replaced by Dashboard
			'menu_icon' => 'dashicons-calendar-alt',
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => array('title'),
			'has_archive' => false,
			'rewrite' => false,
			'show_in_rest' => false,
		);

		register_post_type('booking', $args);
	}

	/**
	 * Register custom post statuses for bookings
	 */
	public function register_booking_statuses()
	{
		register_post_status('pending_review', array(
			'label' => 'Pending Review',
			'public' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>'),
		));

		register_post_status('approved', array(
			'label' => 'Approved',
			'public' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
		));

		register_post_status('expired', array(
			'label' => 'Expired',
			'public' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>'),
		));

		register_post_status('cancelled', array(
			'label' => 'Cancelled',
			'public' => false,
			'show_in_admin_all_list' => true,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
		));
	}

	/**
	 * Add meta boxes for booking details
	 */
	public function add_booking_meta_boxes()
	{
		add_meta_box(
			'booking_details',
			'Booking Details',
			array($this, 'render_booking_details_meta_box'),
			'booking',
			'normal',
			'high'
		);

		add_meta_box(
			'customer_details',
			'Customer Details',
			array($this, 'render_customer_details_meta_box'),
			'booking',
			'side',
			'default'
		);

		add_meta_box(
			'booking_checklist',
			'Booking Approval Checklist',
			array($this, 'render_booking_checklist_meta_box'),
			'booking',
			'side',
			'default'
		);
	}

	/**
	 * Render booking details meta box
	 */
	public function render_booking_details_meta_box($post)
	{
		wp_nonce_field('booking_meta_box', 'booking_meta_box_nonce');

		$start = get_post_meta($post->ID, '_booking_start_datetime', true);
		$end = get_post_meta($post->ID, '_booking_end_datetime', true);
		$cost = get_post_meta($post->ID, '_estimated_cost', true);
		$guest_count = get_post_meta($post->ID, '_guest_count', true);
		$description = get_post_meta($post->ID, '_event_description', true);
		$is_overnight = get_post_meta($post->ID, '_is_overnight', true);
		$hourly_rate = get_option('antigravity_booking_hourly_rate', 100);

		?>
		<table class="form-table">
			<tr>
				<th><label for="booking_start">Start Date/Time</label></th>
				<td><input type="datetime-local" id="booking_start" name="booking_start" value="<?php echo esc_attr($start); ?>"
						style="width: 100%;" /></td>
			</tr>
			<tr>
				<th><label for="booking_end">End Date/Time</label></th>
				<td><input type="datetime-local" id="booking_end" name="booking_end" value="<?php echo esc_attr($end); ?>"
						style="width: 100%;" /></td>
			</tr>
			<tr>
				<th>Estimated Cost</th>
				<td>
					<input type="text" id="estimated_cost" name="estimated_cost" value="<?php echo esc_attr($cost); ?>" readonly
						style="width: 100%;" />
					<p class="description">Hourly Rate: $
						<?php echo esc_html($hourly_rate); ?>/hr
						<?php echo $is_overnight ? '(Includes overnight billing 10PM-10AM)' : ''; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="guest_count">Guest Count</label></th>
				<td><input type="number" id="guest_count" name="guest_count" value="<?php echo esc_attr($guest_count); ?>"
						style="width: 100%;" min="1" /></td>
			</tr>
			<tr>
				<th><label for="event_description">Description</label></th>
				<td><textarea id="event_description" name="event_description" style="width: 100%;"
						rows="3"><?php echo esc_textarea($description); ?></textarea></td>
			</tr>
		</table>
		<script>
			jQuery(document).ready(function ($) {
				function calculateCost() {
					var start = $('#booking_start').val();
					var end = $('#booking_end').val();

					if (!start || !end) return;

					$.post(ajaxurl, {
						action: 'calculate_booking_cost',
						start: start,
						end: end
					}, function (response) {
						if (response.success) {
							$('#estimated_cost').val('$' + response.data.cost);
						}
					});
				}

				$('#booking_start, #booking_end').on('change', calculateCost);
			});
		</script>
		<?php
	}

	/**
	 * Render customer details meta box
	 */
	public function render_customer_details_meta_box($post)
	{
		$name = get_post_meta($post->ID, '_customer_name', true);
		$email = get_post_meta($post->ID, '_customer_email', true);
		?>
		<p>
			<label for="customer_name"><strong>Name</strong></label><br>
			<input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($name); ?>"
				style="width: 100%;" />
		</p>
		<p>
			<label for="customer_email"><strong>Email</strong></label><br>
			<input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($email); ?>"
				style="width: 100%;" />
		</p>
		<p>
			<label for="customer_phone"><strong>Phone</strong></label><br>
			<input type="tel" id="customer_phone" name="customer_phone"
				value="<?php echo esc_attr(get_post_meta($post->ID, '_customer_phone', true)); ?>" style="width: 100%;" />
		</p>
		<?php
	}

	/**
	 * Save booking meta data
	 */
	public function save_booking_meta($post_id, $post)
	{
		// Verify nonce
		if (!isset($_POST['booking_meta_box_nonce']) || !wp_verify_nonce($_POST['booking_meta_box_nonce'], 'booking_meta_box')) {
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
		if (isset($_POST['booking_start'])) {
			update_post_meta($post_id, '_booking_start_datetime', sanitize_text_field($_POST['booking_start']));
		}

		if (isset($_POST['booking_end'])) {
			$end = sanitize_text_field($_POST['booking_end']);
			$start = sanitize_text_field($_POST['booking_start']);

			// Use centralized availability checker
			$is_overnight = Antigravity_Booking_Availability::is_overnight_booking($start);

			if ($is_overnight) {
				$end = Antigravity_Booking_Availability::get_overnight_end($start);
			}

			update_post_meta($post_id, '_booking_end_datetime', $end);
			update_post_meta($post_id, '_is_overnight', $is_overnight);

			// Validate availability
			$availability = Antigravity_Booking_Availability::check_availability($start, $end);
			if (!$availability['available']) {
				// Store errors to display in admin notice
				set_transient('antigravity_booking_errors_' . $post_id, $availability['errors'], 45);
			} else {
				delete_transient('antigravity_booking_errors_' . $post_id);
			}
		}

		if (isset($_POST['customer_name'])) {
			update_post_meta($post_id, '_customer_name', sanitize_text_field($_POST['customer_name']));
		}

		if (isset($_POST['customer_email'])) {
			update_post_meta($post_id, '_customer_email', sanitize_email($_POST['customer_email']));
		}

		if (isset($_POST['customer_phone'])) {
			update_post_meta($post_id, '_customer_phone', sanitize_text_field($_POST['customer_phone']));
		}

		if (isset($_POST['guest_count'])) {
			update_post_meta($post_id, '_guest_count', intval($_POST['guest_count']));
		}

		if (isset($_POST['event_description'])) {
			update_post_meta($post_id, '_event_description', sanitize_textarea_field($_POST['event_description']));
		}

		// Save checklist items
		$checklist_items = array(
			'checklist_rental_agreement' => '_checklist_rental_agreement',
			'checklist_deposit' => '_checklist_deposit',
			'checklist_insurance' => '_checklist_insurance',
			'checklist_key_arrangement' => '_checklist_key_arrangement',
			'checklist_deposit_returned' => '_checklist_deposit_returned'
		);

		foreach ($checklist_items as $field_name => $meta_key) {
			$value = isset($_POST[$field_name]) ? '1' : '0';
			update_post_meta($post_id, $meta_key, $value);
		}

		// Update checklist progress
		$progress = $this->calculate_checklist_progress($post_id);
		update_post_meta($post_id, '_checklist_progress', $progress);

		// Recalculate cost
		$cost = $this->calculate_cost($post_id);
		update_post_meta($post_id, '_estimated_cost', $cost);
	}

	/**
	 * Display admin notices for availability errors
	 */
	public function display_booking_notices()
	{
		$screen = get_current_screen();
		if ($screen->post_type !== 'booking') {
			return;
		}

		global $post;
		if (!isset($post->ID)) {
			return;
		}

		$errors = get_transient('antigravity_booking_errors_' . $post->ID);
		if ($errors) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong>Warning:</strong> This booking conflicts with availability rules:</p>
				<ul>
					<?php foreach ($errors as $error): ?>
						<li>
							<?php echo esc_html($error); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			// Don't delete immediately so it persists on redirect, but maybe cleared on next save
		}
	}

	/**
	 * Calculate booking cost
	 */
	public function calculate_cost($post_id)
	{
		$start = get_post_meta($post_id, '_booking_start_datetime', true);
		$end = get_post_meta($post_id, '_booking_end_datetime', true);

		if (!$start || !$end) {
			return 0;
		}

		$start_dt = new DateTime($start);
		$end_dt = new DateTime($end);
		$diff = $start_dt->diff($end_dt);

		$hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
		$hourly_rate = get_option('antigravity_booking_hourly_rate', 100);

		return round($hours * $hourly_rate, 2);
	}

	/**
	 * Render booking checklist meta box
	 */
	public function render_booking_checklist_meta_box($post)
	{
		// Get checklist items
		$rental_agreement = get_post_meta($post->ID, '_checklist_rental_agreement', true);
		$deposit = get_post_meta($post->ID, '_checklist_deposit', true);
		$insurance = get_post_meta($post->ID, '_checklist_insurance', true);
		$key_arrangement = get_post_meta($post->ID, '_checklist_key_arrangement', true);
		$deposit_returned = get_post_meta($post->ID, '_checklist_deposit_returned', true);
		$progress = $this->calculate_checklist_progress($post->ID);

		?>
		<div class="booking-checklist">
			<div class="checklist-progress" style="margin-bottom: 15px;">
				<div style="background: #f0f0f1; border-radius: 3px; height: 20px; overflow: hidden;">
					<div style="background: #2271b1; height: 100%; width: <?php echo esc_attr($progress); ?>%; transition: width 0.3s;"></div>
				</div>
				<p style="margin: 5px 0; text-align: center; font-weight: bold;">Progress: <?php echo esc_html($progress); ?>%</p>
			</div>
			
			<p>
				<label>
					<input type="checkbox" name="checklist_rental_agreement" value="1" <?php checked($rental_agreement, '1'); ?> />
					Rental Agreement
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="checklist_deposit" value="1" <?php checked($deposit, '1'); ?> />
					Deposit Received
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="checklist_insurance" value="1" <?php checked($insurance, '1'); ?> />
					Certificate of Insurance
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="checklist_key_arrangement" value="1" <?php checked($key_arrangement, '1'); ?> />
					Key Arrangement
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="checklist_deposit_returned" value="1" <?php checked($deposit_returned, '1'); ?> />
					Deposit Returned
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Calculate checklist progress percentage
	 */
	public function calculate_checklist_progress($booking_id)
	{
		$items = array(
			'_checklist_rental_agreement',
			'_checklist_deposit',
			'_checklist_insurance',
			'_checklist_key_arrangement',
			'_checklist_deposit_returned'
		);
		
		$completed = 0;
		foreach ($items as $item) {
			if (get_post_meta($booking_id, $item, true)) {
				$completed++;
			}
		}
		
		return round(($completed / count($items)) * 100);
	}

	/**
	 * Custom update messages for bookings
	 */
	public function booking_updated_messages($messages)
	{
		$post = get_post();
		
		$messages['booking'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => 'Booking updated successfully.',
			2  => 'Custom field updated.',
			3  => 'Custom field deleted.',
			4  => 'Booking updated.',
			5  => isset($_GET['revision']) ? sprintf('Booking restored to revision from %s', wp_post_revision_title((int) $_GET['revision'], false)) : false,
			6  => 'Booking created successfully.',
			7  => 'Booking saved.',
			8  => 'Booking submitted.',
			9  => sprintf('Booking scheduled for: <strong>%1$s</strong>.', date_i18n('M j, Y @ g:i a', strtotime($post->post_date))),
			10 => 'Booking draft updated.'
		);
		
		return $messages;
	}
}
