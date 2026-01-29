<?php
/**
 * Backend Bookings Dashboard - Production Ready
 * Provides admin interface for viewing and managing all bookings
 */
class Antigravity_Booking_Dashboard
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_dashboard_page'), 9);
        add_action('admin_post_change_booking_status', array($this, 'handle_status_change'));
        add_action('admin_post_bulk_booking_action', array($this, 'handle_bulk_action'));
        add_action('admin_post_export_bookings_csv', array($this, 'export_csv'));
        
        // AJAX handlers for inline editing
        add_action('wp_ajax_update_booking_inline', array($this, 'ajax_update_booking_inline'));
        add_action('wp_ajax_update_checklist_item', array($this, 'ajax_update_checklist_item'));
    }

    /**
     * Add dashboard page to admin menu
     */
    public function add_dashboard_page()
    {
        // Top Level Menu
        add_menu_page(
            'Simplified Booking',
            'Simplified Booking',
            'edit_posts',
            'antigravity-booking',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            26
        );

        // Dashboard Submenu (Explicit)
        add_submenu_page(
            'antigravity-booking',
            'Bookings Dashboard',
            'Dashboard',
            'edit_posts',
            'antigravity-booking',
            array($this, 'render_dashboard')
        );
    }

    /**
     * Render the dashboard page
     */
    public function render_dashboard()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Get filter parameters
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20;

        // Sorting parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'start_date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        // Date range filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        // Month/Year for calendar view
        $cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int) date('n');
        $cal_year = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : (int) date('Y');

        // Query bookings
        $args = array(
            'post_type' => 'booking',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => $status_filter === 'all' ? array('pending_review', 'approved', 'expired', 'trash') : $status_filter
        );

        // Apply sorting
        if ($orderby === 'start_date') {
            $args['meta_key'] = '_booking_start_datetime';
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;
        } elseif ($orderby === 'customer_name') {
            $args['meta_key'] = '_customer_name';
            $args['orderby'] = 'meta_value';
            $args['order'] = $order;
        } elseif ($orderby === 'cost') {
            $args['meta_key'] = '_estimated_cost';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = $order;
        } elseif ($orderby === 'status') {
            $args['orderby'] = 'post_status';
            $args['order'] = $order;
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }

        if ($status_filter !== 'all') {
            $args['post_status'] = $status_filter;
        } else {
            $args['post_status'] = array('pending_review', 'approved', 'draft', 'expired', 'publish');
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Date range meta query
        if ($date_from || $date_to) {
            $args['meta_query'] = array('relation' => 'AND');

            if ($date_from) {
                $args['meta_query'][] = array(
                    'key' => '_booking_start_datetime',
                    'value' => $date_from . ' 00:00:00',
                    'compare' => '>=',
                    'type' => 'DATETIME',
                );
            }

            if ($date_to) {
                $args['meta_query'][] = array(
                    'key' => '_booking_start_datetime',
                    'value' => $date_to . ' 23:59:59',
                    'compare' => '<=',
                    'type' => 'DATETIME',
                );
            }
        }

        $query = new WP_Query($args);
        $bookings = $query->posts;
        $total_pages = $query->max_num_pages;

        // Get counts for filters
        $counts = array(
            'all' => 0,
            'pending_review' => 0,
            'approved' => 0,
            'expired' => 0,
        );

        foreach (array('pending_review', 'approved', 'expired') as $status) {
            $count_query = new WP_Query(array(
                'post_type' => 'booking',
                'post_status' => $status,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ));
            $counts[$status] = $count_query->found_posts;
            $counts['all'] += $count_query->found_posts;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Bookings Dashboard</h1>
            <a href="<?php echo admin_url('post-new.php?post_type=booking'); ?>" class="page-title-action">Add New Booking</a>

            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&view=list'); ?>"
                    class="nav-tab <?php echo $view === 'list' ? 'nav-tab-active' : ''; ?>">List View</a>
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&view=calendar'); ?>"
                    class="nav-tab <?php echo $view === 'calendar' ? 'nav-tab-active' : ''; ?>">Calendar View</a>
            </nav>

            <?php if ($view === 'list'): ?>
                <!-- Export Button -->
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline; float: right;">
                    <input type="hidden" name="action" value="export_bookings_csv">
                    <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                    <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                    <?php wp_nonce_field('export_bookings_csv'); ?>
                    <input type="submit" class="button" value="Export to CSV">
                </form>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php
            if ($view === 'calendar') {
                $this->render_calendar_view($cal_month, $cal_year);
            } else {
                $this->render_list_view($bookings, $query, $status_filter, $search, $date_from, $date_to, $paged, $total_pages, $counts, $orderby, $order);
            }
            ?>

            <style>
                .wp-list-table th {
                    font-weight: 600;
                }

                .search-box {
                    float: right;
                    margin: 10px 0;
                }

                .search-box form {
                    display: flex;
                    gap: 5px;
                }

                .search-box input[type="search"] {
                    width: 250px;
                }

                .tablenav {
                    clear: both;
                    height: 50px;
                }
            </style>

            <script>
                // Select all checkboxes
                document.getElementById('select-all-bookings')?.addEventListener('change', function () {
                    const checkboxes = document.querySelectorAll('.booking-checkbox');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });

                // Quick approve function
                function quickApprove(bookingId, nonce) {
                    if (!confirm('Approve this booking?')) return;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo admin_url('admin-post.php'); ?>';

                    const fields = [
                        { name: 'action', value: 'change_booking_status' },
                        { name: 'booking_id', value: bookingId },
                        { name: 'new_status', value: 'approved' },
                        { name: '_wpnonce', value: nonce }
                    ];

                    fields.forEach(field => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = field.name;
                        input.value = field.value;
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                }

                // Quick cancel function
                function quickCancel(bookingId, nonce) {
                    if (!confirm('Cancel this booking? This will also remove it from Google Calendar if approved.')) return;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo admin_url('admin-post.php'); ?>';

                    const fields = [
                        { name: 'action', value: 'change_booking_status' },
                        { name: 'booking_id', value: bookingId },
                        { name: 'new_status', value: 'cancelled' },
                        { name: '_wpnonce', value: nonce }
                    ];

                    fields.forEach(field => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = field.name;
                        input.value = field.value;
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                }

                // Sync bulk action selectors
                document.getElementById('bulk-action-selector-top')?.addEventListener('change', function () {
                    document.getElementById('bulk-action-selector-bottom').value = this.value;
                });
                document.getElementById('bulk-action-selector-bottom')?.addEventListener('change', function () {
                    document.getElementById('bulk-action-selector-top').value = this.value;
                });

                // Expandable dashboard functionality
                jQuery(document).ready(function($) {
                    // Toggle booking details
                    $(document).on('click', '.toggle-details', function() {
                        const bookingId = $(this).data('booking-id');
                        const detailsRow = $('#details-' + bookingId);
                        const icon = $(this).find('.dashicons');
                        
                        if (detailsRow.is(':visible')) {
                            detailsRow.hide();
                            icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
                        } else {
                            // Hide all other expanded rows
                            $('.booking-details').hide();
                            $('.toggle-details .dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
                            
                            // Show this row
                            detailsRow.show();
                            icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
                        }
                    });

                    // Update booking via AJAX
                    $(document).on('click', '.update-booking', function() {
                        const bookingId = $(this).data('booking-id');
                        const statusSpan = $('#update-status-' + bookingId);
                        const button = $(this);
                        
                        // Gather form data
                        const data = {
                            action: 'update_booking_inline',
                            booking_id: bookingId,
                            customer_name: $('#customer_name_' + bookingId).val(),
                            customer_email: $('#customer_email_' + bookingId).val(),
                            customer_phone: $('#customer_phone_' + bookingId).val(),
                            booking_start: $('#booking_start_' + bookingId).val(),
                            booking_end: $('#booking_end_' + bookingId).val(),
                            guest_count: $('#guest_count_' + bookingId).val(),
                            event_description: $('#event_description_' + bookingId).val(),
                            booking_status: $('#booking_status_' + bookingId).val(),
                            nonce: '<?php echo wp_create_nonce("update_booking_inline"); ?>'
                        };
                        
                        // Disable button
                        button.prop('disabled', true).text('Updating...');
                        statusSpan.text('Saving...').css('color', 'orange');
                        
                        // Send AJAX request
                        $.post(ajaxurl, data, function(response) {
                            button.prop('disabled', false).text('Update Booking');
                            
                            if (response.success) {
                                statusSpan.text('✓ Saved successfully!').css('color', 'green');
                                
                                // Update the collapsed row using class selectors
                                const row = $('tr[data-booking-id="' + bookingId + '"]');
                                row.find('.customer-name-cell').html('<strong>' + data.customer_name + '</strong>');
                                row.find('.status-cell').html('<span style="color: ' + response.data.status_color + ';">' + response.data.status_label + '</span>');
                                
                                // Update dates if provided in response
                                if (response.data.start_date_formatted) {
                                    row.find('.start-date-cell').text(response.data.start_date_formatted);
                                }
                                if (response.data.end_date_formatted) {
                                    row.find('.end-date-cell').text(response.data.end_date_formatted);
                                }
                                
                                // Clear message after 3 seconds
                                setTimeout(function() {
                                    statusSpan.text('');
                                }, 3000);
                            } else {
                                statusSpan.text('✗ Error: ' + response.data).css('color', 'red');
                            }
                        }).fail(function() {
                            button.prop('disabled', false).text('Update Booking');
                            statusSpan.text('✗ Update failed').css('color', 'red');
                        });
                    });

                    // Checklist item change handler
                    $(document).on('change', '.checklist-item', function() {
                        const bookingId = $(this).data('booking-id');
                        const item = $(this).data('item');
                        const checked = $(this).is(':checked');
                        
                        // Update checklist via AJAX
                        $.post(ajaxurl, {
                            action: 'update_checklist_item',
                            booking_id: bookingId,
                            item: item,
                            checked: checked ? 1 : 0,
                            nonce: '<?php echo wp_create_nonce("update_checklist_item"); ?>'
                        }, function(response) {
                            if (response.success) {
                                // Update progress bar in expanded view
                                const progress = response.data.progress;
                                $('#progress-bar-' + bookingId).css('width', progress + '%');
                                $('#progress-text-' + bookingId).text(progress + '%');
                                
                                // Update progress bar in collapsed row
                                const row = $('tr[data-booking-id="' + bookingId + '"]');
                                row.find('.progress-bar-mini').css('width', progress + '%');
                                row.find('.progress-text-mini').text(progress + '%');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    /**
     * Render the list view (Standard table)
     */
    private function render_list_view($bookings, $query, $status_filter, $search, $date_from, $date_to, $paged, $total_pages, $counts, $orderby = 'start_date', $order = 'DESC')
    {
        // Helper function to generate sortable column URL
        $get_sort_url = function($column) use ($status_filter, $search, $date_from, $date_to, $orderby, $order) {
            $base_url = admin_url('admin.php?page=antigravity-booking&view=list');
            if ($status_filter !== 'all') $base_url .= '&status=' . $status_filter;
            if ($search) $base_url .= '&s=' . urlencode($search);
            if ($date_from) $base_url .= '&date_from=' . $date_from;
            if ($date_to) $base_url .= '&date_to=' . $date_to;
            
            $new_order = ($orderby === $column && $order === 'ASC') ? 'DESC' : 'ASC';
            return $base_url . '&orderby=' . $column . '&order=' . $new_order;
        };
        
        // Helper function to get sort indicator
        $get_sort_indicator = function($column) use ($orderby, $order) {
            if ($orderby !== $column) return ' ↕';
            return $order === 'ASC' ? ' ↑' : ' ↓';
        };
        ?>
        <!-- Status Filters -->
        <ul class="subsubsub">
            <li class="all">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking'); ?>"
                    class="<?php echo $status_filter === 'all' ? 'current' : ''; ?>">
                    All <span class="count">(<?php echo $counts['all']; ?>)</span>
                </a> |
            </li>
            <li class="pending">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&status=pending_review'); ?>"
                    class="<?php echo $status_filter === 'pending_review' ? 'current' : ''; ?>">
                    Pending <span class="count">(<?php echo $counts['pending_review']; ?>)</span>
                </a> |
            </li>
            <li class="approved">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&status=approved'); ?>"
                    class="<?php echo $status_filter === 'approved' ? 'current' : ''; ?>">
                    Approved <span class="count">(<?php echo $counts['approved']; ?>)</span>
                </a> |
            </li>
            <li class="expired">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&status=expired'); ?>"
                    class="<?php echo $status_filter === 'expired' ? 'current' : ''; ?>">
                    Expired <span class="count">(<?php echo $counts['expired']; ?>)</span>
                </a> |
            </li>
            <li class="cancelled">
                <a href="<?php echo admin_url('admin.php?page=antigravity-booking&status=cancelled'); ?>"
                    class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
                    Cancelled <span class="count">(<?php echo $counts['cancelled'] ?? 0; ?>)</span>
                </a>
            </li>
        </ul>

        <!-- Search & Date Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" action="" style="display: inline-flex; gap: 5px;">
                    <input type="hidden" name="page" value="antigravity-booking">
                    <?php if ($status_filter !== 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                    <?php endif; ?>

                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From date">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To date">
                    <input type="submit" class="button" value="Filter by Date">

                    <?php if ($date_from || $date_to): ?>
                        <a href="<?php echo admin_url('admin.php?page=antigravity-booking' . ($status_filter !== 'all' ? '&status=' . $status_filter : '')); ?>"
                            class="button">Clear Dates</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="search-box">
                <form method="get" action="">
                    <input type="hidden" name="page" value="antigravity-booking">
                    <?php if ($status_filter !== 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                    <?php endif; ?>
                    <?php if ($date_from): ?>
                        <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    <?php endif; ?>
                    <?php if ($date_to): ?>
                        <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search bookings...">
                    <input type="submit" class="button" value="Search">
                </form>
            </div>
        </div>

        <!-- Bulk Action Form -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="bookings-filter">
            <input type="hidden" name="action" value="bulk_booking_action">
            <?php wp_nonce_field('bulk_booking_action'); ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="expire">Mark as Expired</option>
                        <option value="cancelled">Cancel</option>
                        <option value="delete">Delete</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped" id="bookings-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th>
                        <th style="width: 40px;"><input type="checkbox" id="select-all-bookings"></th>
                        <th style="width: 50px;">ID</th>
                        <th class="sortable" style="cursor: pointer;">
                            <a href="<?php echo $get_sort_url('customer_name'); ?>" style="text-decoration: none; color: inherit;">
                                Customer<?php echo $get_sort_indicator('customer_name'); ?>
                            </a>
                        </th>
                        <th class="sortable" style="cursor: pointer;">
                            <a href="<?php echo $get_sort_url('start_date'); ?>" style="text-decoration: none; color: inherit;">
                                Date<?php echo $get_sort_indicator('start_date'); ?>
                            </a>
                        </th>
                        <th class="sortable" style="cursor: pointer;">
                            <a href="<?php echo $get_sort_url('status'); ?>" style="text-decoration: none; color: inherit;">
                                Status<?php echo $get_sort_indicator('status'); ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <p style="color: #666;">No bookings found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $customer_name = get_post_meta($booking->ID, '_customer_name', true);
                            $customer_email = get_post_meta($booking->ID, '_customer_email', true);
                            $customer_phone = get_post_meta($booking->ID, '_customer_phone', true);
                            $start = get_post_meta($booking->ID, '_booking_start_datetime', true);
                            $end = get_post_meta($booking->ID, '_booking_end_datetime', true);
                            $cost = get_post_meta($booking->ID, '_estimated_cost', true);
                            $guest_count = get_post_meta($booking->ID, '_guest_count', true);
                            $description = get_post_meta($booking->ID, '_event_description', true);
                            $status = get_post_status($booking->ID);
                            $progress = get_post_meta($booking->ID, '_checklist_progress', true) ?: 0;
                            
                            // Checklist items
                            $checklist = array(
                                'rental_agreement' => get_post_meta($booking->ID, '_checklist_rental_agreement', true),
                                'deposit' => get_post_meta($booking->ID, '_checklist_deposit', true),
                                'insurance' => get_post_meta($booking->ID, '_checklist_insurance', true),
                                'key_arrangement' => get_post_meta($booking->ID, '_checklist_key_arrangement', true),
                                'deposit_returned' => get_post_meta($booking->ID, '_checklist_deposit_returned', true),
                            );

                            $status_labels = array(
                                'pending_review' => 'Pending Review',
                                'approved' => 'Approved',
                                'expired' => 'Expired',
                                'cancelled' => 'Cancelled',
                                'draft' => 'Draft',
                                'publish' => 'Published',
                            );
                            
                            $status_colors = array(
                                'pending_review' => '#d63638',
                                'approved' => '#00a32a',
                                'expired' => '#999',
                                'cancelled' => '#d63638',
                                'draft' => '#999',
                                'publish' => '#00a32a',
                            );
                            ?>
                            <!-- Collapsed Row -->
                            <tr class="booking-row" data-booking-id="<?php echo $booking->ID; ?>">
                                <td>
                                    <button type="button" class="toggle-details" data-booking-id="<?php echo $booking->ID; ?>"
                                            style="background: none; border: none; cursor: pointer; font-size: 16px;">
                                        <span class="dashicons dashicons-arrow-right"></span>
                                    </button>
                                </td>
                                <th scope="row"><input type="checkbox" name="booking_ids[]" value="<?php echo $booking->ID; ?>"
                                        class="booking-checkbox"></th>
                                <td><strong><?php echo $booking->ID; ?></strong></td>
                                <td class="customer-name-cell"><strong><?php echo esc_html($customer_name ?: 'N/A'); ?></strong></td>
                                <td class="start-date-cell"><?php echo $start ? date('M j, Y g:i A', strtotime($start)) : 'N/A'; ?></td>
                                <td class="end-date-cell"><?php echo $end ? date('M j, Y g:i A', strtotime($end)) : 'N/A'; ?></td>
                                <td class="status-cell">
                                    <span style="color: <?php echo $status_colors[$status] ?? '#666'; ?>;">
                                        <?php echo $status_labels[$status] ?? $status; ?>
                                    </span>
                                </td>
                                <td class="progress-cell">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <div style="background: #f0f0f1; border-radius: 3px; height: 12px; width: 60px; overflow: hidden;">
                                            <div class="progress-bar-mini" style="background: #2271b1; height: 100%; width: <?php echo $progress; ?>%;"></div>
                                        </div>
                                        <span class="progress-text-mini" style="font-size: 11px; color: #666;"><?php echo $progress; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="button button-small toggle-details" data-booking-id="<?php echo $booking->ID; ?>">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Expanded Details Row (Hidden by default) -->
                            <tr class="booking-details" id="details-<?php echo $booking->ID; ?>" style="display: none;">
                                <td colspan="7" style="padding: 20px; background: #f9f9f9;">
                                    <div class="booking-details-container">
                                        <h3 style="margin-top: 0;">Booking #<?php echo $booking->ID; ?> - <?php echo esc_html($customer_name); ?></h3>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                            <!-- Left Column: Booking Details -->
                                            <div>
                                                <h4>Booking Information</h4>
                                                <table class="form-table">
                                                    <tr>
                                                        <th>Customer Name:</th>
                                                        <td>
                                                            <input type="text" class="regular-text"
                                                                   id="customer_name_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($customer_name); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Email:</th>
                                                        <td>
                                                            <input type="email" class="regular-text"
                                                                   id="customer_email_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($customer_email); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Phone:</th>
                                                        <td>
                                                            <input type="tel" class="regular-text"
                                                                   id="customer_phone_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($customer_phone); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Start Date/Time:</th>
                                                        <td>
                                                            <input type="datetime-local" class="regular-text"
                                                                   id="booking_start_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($start); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>End Date/Time:</th>
                                                        <td>
                                                            <input type="datetime-local" class="regular-text"
                                                                   id="booking_end_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($end); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Guest Count:</th>
                                                        <td>
                                                            <input type="number" class="small-text" min="1"
                                                                   id="guest_count_<?php echo $booking->ID; ?>"
                                                                   value="<?php echo esc_attr($guest_count); ?>">
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Description:</th>
                                                        <td>
                                                            <textarea class="large-text" rows="3"
                                                                      id="event_description_<?php echo $booking->ID; ?>"><?php echo esc_textarea($description); ?></textarea>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Estimated Cost:</th>
                                                        <td><strong>$<?php echo number_format($cost, 2); ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Status:</th>
                                                        <td>
                                                            <select id="booking_status_<?php echo $booking->ID; ?>" class="regular-text">
                                                                <option value="pending_review" <?php selected($status, 'pending_review'); ?>>Pending Review</option>
                                                                <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                                                                <option value="expired" <?php selected($status, 'expired'); ?>>Expired</option>
                                                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            
                                            <!-- Right Column: Checklist -->
                                            <div>
                                                <h4>Approval Checklist</h4>
                                                <div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                                    <div class="checklist-progress" style="margin-bottom: 15px;">
                                                        <div style="background: #f0f0f1; border-radius: 3px; height: 20px; overflow: hidden;">
                                                            <div id="progress-bar-<?php echo $booking->ID; ?>"
                                                                 style="background: #2271b1; height: 100%; width: <?php echo $progress; ?>%; transition: width 0.3s;"></div>
                                                        </div>
                                                        <p style="margin: 5px 0; text-align: center; font-weight: bold;">
                                                            Progress: <span id="progress-text-<?php echo $booking->ID; ?>"><?php echo $progress; ?>%</span>
                                                        </p>
                                                    </div>
                                                    
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" class="checklist-item"
                                                                   data-booking-id="<?php echo $booking->ID; ?>"
                                                                   data-item="rental_agreement"
                                                                   <?php checked($checklist['rental_agreement'], '1'); ?>>
                                                            Rental Agreement
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" class="checklist-item"
                                                                   data-booking-id="<?php echo $booking->ID; ?>"
                                                                   data-item="deposit"
                                                                   <?php checked($checklist['deposit'], '1'); ?>>
                                                            Deposit Received
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" class="checklist-item"
                                                                   data-booking-id="<?php echo $booking->ID; ?>"
                                                                   data-item="insurance"
                                                                   <?php checked($checklist['insurance'], '1'); ?>>
                                                            Certificate of Insurance
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" class="checklist-item"
                                                                   data-booking-id="<?php echo $booking->ID; ?>"
                                                                   data-item="key_arrangement"
                                                                   <?php checked($checklist['key_arrangement'], '1'); ?>>
                                                            Key Arrangement
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label>
                                                            <input type="checkbox" class="checklist-item"
                                                                   data-booking-id="<?php echo $booking->ID; ?>"
                                                                   data-item="deposit_returned"
                                                                   <?php checked($checklist['deposit_returned'], '1'); ?>>
                                                            Deposit Returned
                                                        </label>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                            <button type="button" class="button button-primary button-large update-booking"
                                                    data-booking-id="<?php echo $booking->ID; ?>">
                                                Update Booking
                                            </button>
                                            <button type="button" class="button button-large toggle-details"
                                                    data-booking-id="<?php echo $booking->ID; ?>">
                                                Close
                                            </button>
                                            <span class="update-status" id="update-status-<?php echo $booking->ID; ?>"
                                                  style="margin-left: 15px; font-weight: bold;"></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Bottom Bulk Actions -->
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action_bottom" id="bulk-action-selector-bottom">
                        <option value="-1">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="expire">Mark as Expired</option>
                        <option value="cancelled">Cancel</option>
                        <option value="delete">Delete</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $query->found_posts; ?> items</span>
                        <?php
                        $base_url = admin_url('admin.php?page=antigravity-booking&view=list');
                        if ($status_filter !== 'all')
                            $base_url .= '&status=' . $status_filter;
                        if ($search)
                            $base_url .= '&s=' . urlencode($search);
                        if ($date_from)
                            $base_url .= '&date_from=' . $date_from;
                        if ($date_to)
                            $base_url .= '&date_to=' . $date_to;

                        echo paginate_links(array(
                            'base' => $base_url . '%_%',
                            'format' => '&paged=%#%',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ));
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render the calendar view
     */
    private function render_calendar_view($month, $year)
    {
        // Calculate month start/end
        $first_day = strtotime("$year-$month-01");
        $last_day = strtotime("last day of " . date('F Y', $first_day));

        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        $next_month = $month == 12 ? 1 : $month + 1;
        $next_year = $month == 12 ? $year + 1 : $year;

        // Query all bookings for this month
        $bookings_query = new WP_Query(array(
            'post_type' => 'booking',
            'posts_per_page' => -1,
            'post_status' => array('pending_review', 'approved'),
            'meta_query' => array(
                array(
                    'key' => '_booking_start_datetime',
                    'value' => array(date('Y-m-d 00:00:00', $first_day), date('Y-m-d 23:59:59', $last_day)),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                )
            )
        ));

        // Group bookings by date
        $calendar_data = array();
        foreach ($bookings_query->posts as $booking) {
            $start = get_post_meta($booking->ID, '_booking_start_datetime', true);
            if ($start) {
                $date_key = date('Y-m-d', strtotime($start));
                if (!isset($calendar_data[$date_key])) {
                    $calendar_data[$date_key] = array();
                }
                $calendar_data[$date_key][] = $booking;
            }
        }

        ?>
        <div class="antigravity-calendar-view">
            <div class="calendar-header"
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h2 style="margin: 0;"><?php echo date('F Y', $first_day); ?></h2>
                <div class="calendar-nav">
                    <a href="<?php echo admin_url("admin.php?page=antigravity-booking&view=calendar&cal_month=$prev_month&cal_year=$prev_year"); ?>"
                        class="button">&laquo; Prev Month</a>
                    <a href="<?php echo admin_url("admin.php?page=antigravity-booking&view=calendar&cal_month=" . date('n') . "&cal_year=" . date('Y')); ?>"
                        class="button">Today</a>
                    <a href="<?php echo admin_url("admin.php?page=antigravity-booking&view=calendar&cal_month=$next_month&cal_year=$next_year"); ?>"
                        class="button">Next Month &raquo;</a>
                </div>
            </div>

            <div class="calendar-grid"
                style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #ccd0d4; border: 1px solid #ccd0d4; border-radius: 8px; overflow: hidden;">
                <!-- Week Headers -->
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                    <div class="calendar-day-header"
                        style="background: #f8f9fa; padding: 10px; text-align: center; font-weight: bold; font-size: 0.8em; color: #666;">
                        <?php echo $day; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Calendar Days -->
                <?php
                $start_index = date('w', $first_day);
                $days_in_month = (int) date('t', $first_day);

                // Pad start
                for ($i = 0; $i < $start_index; $i++) {
                    echo '<div class="calendar-day empty" style="background: #fafafa; min-height: 120px;"></div>';
                }

                // Days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $is_today = $current_date === date('Y-m-d');
                    ?>
                    <div class="calendar-day"
                        style="background: #fff; min-height: 120px; padding: 5px; position: relative; <?php echo $is_today ? 'background: #f0f7ff;' : ''; ?>">
                        <div class="day-number"
                            style="font-weight: bold; margin-bottom: 5px; font-size: 0.9em; <?php echo $is_today ? 'color: #2271b1;' : ''; ?>">
                            <?php echo $day; ?>
                        </div>
                        <div class="day-bookings">
                            <?php if (isset($calendar_data[$current_date])): ?>
                                <?php foreach ($calendar_data[$current_date] as $booking): ?>
                                    <?php
                                    $status = get_post_status($booking->ID);
                                    $start_time = date('g:i a', strtotime(get_post_meta($booking->ID, '_booking_start_datetime', true)));
                                    $name = get_post_meta($booking->ID, '_customer_name', true);
                                    $color = $status === 'approved' ? '#00a32a' : '#d63638';
                                    $bg = $status === 'approved' ? '#e5f6e8' : '#fbeaea';
                                    ?>
                                    <a href="<?php echo admin_url("post.php?post={$booking->ID}&action=edit"); ?>"
                                        title="<?php echo esc_attr($name . ' (' . $status . ')'); ?>"
                                        style="display: block; font-size: 10px; padding: 2px 4px; margin-bottom: 2px; border-radius: 3px; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; text-decoration: none; border-left: 3px solid <?php echo $color; ?>; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                        <strong><?php echo $start_time; ?></strong> <?php echo esc_html($name); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }

                // Pad end
                $total_slots = $start_index + $days_in_month;
                $pad_end = (7 - ($total_slots % 7)) % 7;
                for ($i = 0; $i < $pad_end; $i++) {
                    echo '<div class="calendar-day empty" style="background: #fafafa; min-height: 120px;"></div>';
                }
                ?>
            </div>

            <div class="calendar-legend" style="margin-top: 20px; display: flex; gap: 20px; font-size: 0.9em;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span
                        style="display: inline-block; width: 12px; height: 12px; background: #00a32a; border-radius: 2px;"></span>
                    Approved
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span
                        style="display: inline-block; width: 12px; height: 12px; background: #d63638; border-radius: 2px;"></span>
                    Pending Review
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle single status change
     */
    public function handle_status_change()
    {
        // Start output buffering to prevent any output before redirect
        // This prevents "headers already sent" errors from Google Calendar sync
        ob_start();
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if (!wp_verify_nonce($_POST['_wpnonce'], 'change_booking_status_' . $booking_id)) {
            ob_end_clean();
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_post', $booking_id)) {
            ob_end_clean();
            wp_die('You do not have permission to edit this booking');
        }

        // Update post status (this triggers transition_post_status hook which may output errors)
        wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => $new_status,
        ));

        // Clean any output that may have occurred during hooks
        ob_end_clean();
        
        // Now safe to redirect
        wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
        exit;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_action()
    {
        // Start output buffering to prevent any output before redirect
        ob_start();
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_booking_action')) {
            ob_end_clean();
            wp_die('Security check failed');
        }

        $booking_ids = isset($_POST['booking_ids']) ? array_map('intval', $_POST['booking_ids']) : array();
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

        // Check bottom selector too
        if (empty($bulk_action) || $bulk_action === '-1') {
            $bulk_action = isset($_POST['bulk_action_bottom']) ? sanitize_text_field($_POST['bulk_action_bottom']) : '';
        }

        if (empty($booking_ids) || empty($bulk_action) || $bulk_action === '-1') {
            ob_end_clean();
            wp_redirect(admin_url('admin.php?page=antigravity-booking&error=no_selection'));
            exit;
        }

        foreach ($booking_ids as $booking_id) {
            if (!current_user_can('edit_post', $booking_id)) {
                continue;
            }

            switch ($bulk_action) {
                case 'approve':
                    wp_update_post(array('ID' => $booking_id, 'post_status' => 'approved'));
                    break;
                case 'expire':
                    wp_update_post(array('ID' => $booking_id, 'post_status' => 'expired'));
                    break;
                case 'cancelled':
                    wp_update_post(array('ID' => $booking_id, 'post_status' => 'cancelled'));
                    break;
                case 'delete':
                    wp_delete_post($booking_id, true);
                    break;
            }
        }

        // Clean any output that may have occurred during hooks
        ob_end_clean();
        
        wp_redirect(admin_url('admin.php?page=antigravity-booking&bulk_updated=' . count($booking_ids)));
        exit;
    }

    /**
     * Export bookings to CSV
     */
    public function export_csv()
    {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'export_bookings_csv')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Query bookings
        $args = array(
            'post_type' => 'booking',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ($status_filter !== 'all') {
            $args['post_status'] = $status_filter;
        } else {
            $args['post_status'] = array('pending_review', 'approved', 'draft', 'expired');
        }

        // Date range
        if ($date_from || $date_to) {
            $args['meta_query'] = array('relation' => 'AND');
            if ($date_from) {
                $args['meta_query'][] = array(
                    'key' => '_booking_start_datetime',
                    'value' => $date_from . ' 00:00:00',
                    'compare' => '>=',
                    'type' => 'DATETIME',
                );
            }
            if ($date_to) {
                $args['meta_query'][] = array(
                    'key' => '_booking_start_datetime',
                    'value' => $date_to . ' 23:59:59',
                    'compare' => '<=',
                    'type' => 'DATETIME',
                );
            }
        }

        $bookings = get_posts($args);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bookings-export-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array('ID', 'Customer Name', 'Email', 'Phone', 'Start Date', 'End Date', 'Cost', 'Status', 'Created Date'));

        // CSV Data
        foreach ($bookings as $booking) {
            $row = array(
                $booking->ID,
                get_post_meta($booking->ID, '_customer_name', true),
                get_post_meta($booking->ID, '_customer_email', true),
                get_post_meta($booking->ID, '_customer_phone', true),
                get_post_meta($booking->ID, '_booking_start_datetime', true),
                get_post_meta($booking->ID, '_booking_end_datetime', true),
                get_post_meta($booking->ID, '_estimated_cost', true),
                get_post_status($booking->ID),
                get_the_date('Y-m-d H:i:s', $booking->ID),
            );
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: Update booking inline
     */
    public function ajax_update_booking_inline()
    {
        check_ajax_referer('update_booking_inline', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (!$booking_id || get_post_type($booking_id) !== 'booking') {
            wp_send_json_error('Invalid booking ID');
        }

        // Update post meta
        if (isset($_POST['customer_name'])) {
            update_post_meta($booking_id, '_customer_name', sanitize_text_field($_POST['customer_name']));
        }

        if (isset($_POST['customer_email'])) {
            update_post_meta($booking_id, '_customer_email', sanitize_email($_POST['customer_email']));
        }

        if (isset($_POST['customer_phone'])) {
            update_post_meta($booking_id, '_customer_phone', sanitize_text_field($_POST['customer_phone']));
        }

        if (isset($_POST['booking_start'])) {
            update_post_meta($booking_id, '_booking_start_datetime', sanitize_text_field($_POST['booking_start']));
        }

        if (isset($_POST['booking_end'])) {
            $end = sanitize_text_field($_POST['booking_end']);
            $start = sanitize_text_field($_POST['booking_start']);

            // Check if overnight
            $is_overnight = Antigravity_Booking_Availability::is_overnight_booking($start);
            if ($is_overnight) {
                $end = Antigravity_Booking_Availability::get_overnight_end($start);
            }

            update_post_meta($booking_id, '_booking_end_datetime', $end);
            update_post_meta($booking_id, '_is_overnight', $is_overnight);
        }

        if (isset($_POST['guest_count'])) {
            update_post_meta($booking_id, '_guest_count', intval($_POST['guest_count']));
        }

        if (isset($_POST['event_description'])) {
            update_post_meta($booking_id, '_event_description', sanitize_textarea_field($_POST['event_description']));
        }

        // Update booking status
        if (isset($_POST['booking_status'])) {
            $new_status = sanitize_text_field($_POST['booking_status']);
            wp_update_post(array(
                'ID' => $booking_id,
                'post_status' => $new_status,
            ));
        }

        // Recalculate cost
        $start_dt = new DateTime(get_post_meta($booking_id, '_booking_start_datetime', true));
        $end_dt = new DateTime(get_post_meta($booking_id, '_booking_end_datetime', true));
        $diff = $start_dt->diff($end_dt);
        $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        $hourly_rate = get_option('antigravity_booking_hourly_rate', 100);
        $cost = round($hours * $hourly_rate, 2);
        update_post_meta($booking_id, '_estimated_cost', $cost);

        // Get status info for response
        $status = get_post_status($booking_id);
        $status_labels = array(
            'pending_review' => 'Pending Review',
            'approved' => 'Approved',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        );
        $status_colors = array(
            'pending_review' => '#d63638',
            'approved' => '#00a32a',
            'expired' => '#999',
            'cancelled' => '#d63638',
        );

        // Get updated dates for display
        $start_formatted = get_post_meta($booking_id, '_booking_start_datetime', true);
        $end_formatted = get_post_meta($booking_id, '_booking_end_datetime', true);

        wp_send_json_success(array(
            'message' => 'Booking updated successfully',
            'status_label' => $status_labels[$status] ?? $status,
            'status_color' => $status_colors[$status] ?? '#666',
            'start_date_formatted' => $start_formatted ? date('M j, Y g:i A', strtotime($start_formatted)) : 'N/A',
            'end_date_formatted' => $end_formatted ? date('M j, Y g:i A', strtotime($end_formatted)) : 'N/A',
        ));
    }

    /**
     * AJAX: Update single checklist item
     */
    public function ajax_update_checklist_item()
    {
        check_ajax_referer('update_checklist_item', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $item = isset($_POST['item']) ? sanitize_text_field($_POST['item']) : '';
        $checked = isset($_POST['checked']) ? intval($_POST['checked']) : 0;

        if (!$booking_id || get_post_type($booking_id) !== 'booking') {
            wp_send_json_error('Invalid booking ID');
        }

        // Update checklist item
        update_post_meta($booking_id, '_checklist_' . $item, $checked);

        // Recalculate progress
        $items = array(
            '_checklist_rental_agreement',
            '_checklist_deposit',
            '_checklist_insurance',
            '_checklist_key_arrangement',
            '_checklist_deposit_returned'
        );
        
        $completed = 0;
        foreach ($items as $meta_key) {
            if (get_post_meta($booking_id, $meta_key, true)) {
                $completed++;
            }
        }
        
        $progress = round(($completed / count($items)) * 100);
        update_post_meta($booking_id, '_checklist_progress', $progress);

        wp_send_json_success(array(
            'progress' => $progress,
            'message' => 'Checklist updated',
        ));
    }
}
