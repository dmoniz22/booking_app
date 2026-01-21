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
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20;

        // Date range filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        // Query bookings
        $args = array(
            'post_type' => 'booking',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => $status_filter === 'all' ? array('pending_review', 'approved', 'expired', 'trash') : $status_filter
        );

        if ($status_filter !== 'all') {
            $args['post_status'] = $status_filter;
        } else {
            $args['post_status'] = array('pending_review', 'approved', 'draft', 'expired');
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

            <!-- Export Button -->
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline; float: right;">
                <input type="hidden" name="action" value="export_bookings_csv">
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                <?php wp_nonce_field('export_bookings_csv'); ?>
                <input type="submit" class="button" value="Export to CSV">
            </form>

            <hr class="wp-header-end">

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
                    </a>
                </li>
            </ul>

            <!-- Search & Date Filters (Separate form from Bulk Actions) -->
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
                            <option value="delete">Delete</option>
                        </select>
                        <input type="submit" class="button action" value="Apply">
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all-bookings"></th>
                            <th style="width: 50px;">ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <p style="color: #666;">No bookings found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $customer_name = get_post_meta($booking->ID, '_customer_name', true);
                                $customer_email = get_post_meta($booking->ID, '_customer_email', true);
                                $start = get_post_meta($booking->ID, '_booking_start_datetime', true);
                                $end = get_post_meta($booking->ID, '_booking_end_datetime', true);
                                $cost = get_post_meta($booking->ID, '_estimated_cost', true);
                                $status = get_post_status($booking->ID);

                                $status_labels = array(
                                    'pending_review' => '<span style="color: #d63638;">Pending Review</span>',
                                    'approved' => '<span style="color: #00a32a;">Approved</span>',
                                    'expired' => '<span style="color: #999;">Expired</span>',
                                    'draft' => '<span style="color: #999;">Draft</span>',
                                );
                                ?>
                                <tr>
                                    <th scope="row"><input type="checkbox" name="booking_ids[]" value="<?php echo $booking->ID; ?>"
                                            class="booking-checkbox"></th>
                                    <td><?php echo $booking->ID; ?></td>
                                    <td><strong><?php echo esc_html($customer_name ?: 'N/A'); ?></strong></td>
                                    <td><?php echo esc_html($customer_email ?: 'N/A'); ?></td>
                                    <td><?php echo $start ? date('M j, Y g:i A', strtotime($start)) : 'N/A'; ?></td>
                                    <td><?php echo $end ? date('M j, Y g:i A', strtotime($end)) : 'N/A'; ?></td>
                                    <td><strong>$<?php echo number_format($cost, 2); ?></strong></td>
                                    <td><?php echo $status_labels[$status] ?? $status; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url("post.php?post={$booking->ID}&action=edit"); ?>"
                                            class="button button-small">Edit</a>

                                        <?php if ($status === 'pending_review'): ?>
                                            <button type="button" class="button button-small button-primary"
                                                onclick="quickApprove(<?php echo $booking->ID; ?>, '<?php echo wp_create_nonce('change_booking_status_' . $booking->ID); ?>')">Approve</button>
                                        <?php endif; ?>

                                        <?php if ($status === 'approved'): ?>
                                            <span style="color: #00a32a;">✓ Confirmed</span>
                                        <?php endif; ?>
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
                            <option value="delete">Delete</option>
                        </select>
                        <input type="submit" class="button action" value="Apply">
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo $query->found_posts; ?> items</span>
                            <?php
                            $base_url = admin_url('admin.php?page=antigravity-booking');
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

                // Sync bulk action selectors
                document.getElementById('bulk-action-selector-top')?.addEventListener('change', function () {
                    document.getElementById('bulk-action-selector-bottom').value = this.value;
                });
                document.getElementById('bulk-action-selector-bottom')?.addEventListener('change', function () {
                    document.getElementById('bulk-action-selector-top').value = this.value;
                });
            </script>
        </div>
        <?php
    }

    /**
     * Handle single status change
     */
    public function handle_status_change()
    {
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if (!wp_verify_nonce($_POST['_wpnonce'], 'change_booking_status_' . $booking_id)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('edit_post', $booking_id)) {
            wp_die('You do not have permission to edit this booking');
        }

        wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => $new_status,
        ));

        wp_redirect(admin_url('admin.php?page=antigravity-booking&updated=1'));
        exit;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_action()
    {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_booking_action')) {
            wp_die('Security check failed');
        }

        $booking_ids = isset($_POST['booking_ids']) ? array_map('intval', $_POST['booking_ids']) : array();
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

        // Check bottom selector too
        if (empty($bulk_action) || $bulk_action === '-1') {
            $bulk_action = isset($_POST['bulk_action_bottom']) ? sanitize_text_field($_POST['bulk_action_bottom']) : '';
        }

        if (empty($booking_ids) || $bulk_action === '-1') {
            wp_redirect(admin_url('admin.php?page=antigravity-bookings-dashboard&error=no_selection'));
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
                case 'delete':
                    wp_delete_post($booking_id, true);
                    break;
            }
        }

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
}
