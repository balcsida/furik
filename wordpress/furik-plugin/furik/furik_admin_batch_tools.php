<?php
/**
 * Add Batch Tools submenu to the Furik menu
 */
function furik_batch_tools_menu() {
    add_submenu_page(
        'furik-dashboard', // Parent slug (main Furik menu)
        __('Batch Tools', 'furik'),
        __('Batch Tools', 'furik'),
        'manage_options',
        'furik-batch-tools', // Page slug - this is important
        'furik_batch_tools_page'
    );
}
add_action('admin_menu', 'furik_batch_tools_menu', 30); // Higher priority to ensure it appears after other items

/**
 * Register scripts and styles for batch tools
 */
function furik_batch_tools_scripts($hook) {
    // Only load on our page
    if ($hook != 'furik_page_furik-batch-tools') {
        return;
    }
    
    // Register and enqueue CSS
    wp_register_style(
        'furik-batch-tools', 
        plugins_url('css/furik-batch-tools.css', dirname(__FILE__))
    );
    wp_enqueue_style('furik-batch-tools');
    
    // Register and enqueue JS
    wp_register_script(
        'furik-batch-tools', 
        plugins_url('js/furik-batch-tools.js', dirname(__FILE__)), 
        array('jquery'), 
        '1.0', 
        true
    );
    
    // Add localizations for JS
    wp_localize_script('furik-batch-tools', 'furikBatchTools', array(
        'confirmMessage' => __('WARNING: You are about to make changes to the database. This cannot be undone. Are you sure you want to continue?', 'furik'),
    ));
    
    wp_enqueue_script('furik-batch-tools');
}
add_action('admin_enqueue_scripts', 'furik_batch_tools_scripts');

/**
 * The Batch Tools admin page
 */
function furik_batch_tools_page() {
    // Process batch cancel form submission
    if (isset($_POST['furik_action']) && $_POST['furik_action'] === 'batch_cancel') {
        furik_process_batch_cancel();
    }
    
    // Process recurring payments form submission
    if (isset($_POST['furik_action']) && $_POST['furik_action'] === 'process_recurring') {
        furik_process_batch_recurring();
    }
    
    // Determine active tab
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'cancel_recurring';
    ?>
    <div class="wrap">
        <h1><?php _e('Furik Batch Tools', 'furik'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=furik-batch-tools&tab=cancel_recurring" class="nav-tab <?php echo $active_tab == 'cancel_recurring' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Cancel Recurring Donations', 'furik'); ?>
            </a>
            <a href="?page=furik-batch-tools&tab=process_recurring" class="nav-tab <?php echo $active_tab == 'process_recurring' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Process Recurring Payments', 'furik'); ?>
            </a>
        </h2>
        
        <div class="tab-content">
            <?php 
            if ($active_tab == 'cancel_recurring') {
                furik_render_batch_cancel_tab();
            } else if ($active_tab == 'process_recurring') {
                furik_render_process_recurring_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Render the Cancel Recurring Donations tab with additional filters
 */
function furik_render_batch_cancel_tab() {
    ?>
    <div class="notice notice-warning">
        <p><?php _e('This tool allows you to cancel recurring donations created before a specific date. This operation cannot be undone!', 'furik'); ?></p>
    </div>
    
    <form method="post" action="" class="furik-batch-form">
        <?php wp_nonce_field('furik_batch_cancel_nonce'); ?>
        <input type="hidden" name="furik_action" value="batch_cancel">
        <input type="hidden" name="tab" value="cancel_recurring">
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="cutoff_date"><?php _e('Cancel donations before', 'furik'); ?></label></th>
                <td>
                    <input type="date" id="cutoff_date" name="cutoff_date" value="2025-02-01" required>
                    <p class="description"><?php _e('All recurring donations created before this date will be cancelled.', 'furik'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="transaction_type"><?php _e('Transaction Type', 'furik'); ?></label></th>
                <td>
                    <select id="transaction_type" name="transaction_type">
                        <option value="all"><?php _e('All Types', 'furik'); ?></option>
                        <option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_REG; ?>"><?php _e('Card Only', 'furik'); ?></option>
                        <option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; ?>"><?php _e('Bank Transfer Only', 'furik'); ?></option>
                    </select>
                    <p class="description"><?php _e('Filter by transaction type to target specific recurring donation methods.', 'furik'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Email Filter', 'furik'); ?></th>
                <td>
                    <input type="text" id="email_filter" name="email_filter" placeholder="<?php _e('Optional email address filter', 'furik'); ?>">
                    <p class="description"><?php _e('Filter by email address (leave blank for all emails).', 'furik'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Dry run', 'furik'); ?></th>
                <td>
                    <input type="checkbox" id="dry_run" name="dry_run" value="1" checked>
                    <label for="dry_run"><?php _e('Perform a dry run (no actual changes will be made)', 'furik'); ?></label>
                    <p class="description"><?php _e('Uncheck this box when you are ready to perform the actual cancellation.', 'furik'); ?></p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Process Cancellations', 'furik'); ?>">
        </p>
    </form>
    <?php
}

/**
 * Process the batch cancellation of recurring donations with enhanced filtering and details
 */
function furik_process_batch_cancel() {
    global $wpdb;
    
    check_admin_referer('furik_batch_cancel_nonce');
    
    $cutoff_date = sanitize_text_field($_POST['cutoff_date']);
    $transaction_type = isset($_POST['transaction_type']) ? sanitize_text_field($_POST['transaction_type']) : 'all';
    $email_filter = isset($_POST['email_filter']) ? sanitize_email($_POST['email_filter']) : '';
    $dry_run = isset($_POST['dry_run']) ? true : false;
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff_date)) {
        echo '<div class="notice notice-error"><p>' . __('Invalid date format. Please use YYYY-MM-DD format.', 'furik') . '</p></div>';
        return;
    }
    
    // Build the SQL query with filters
    $sql = "SELECT t.*, 
        campaigns.post_title AS campaign_name,
        (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=t.id AND transaction_status=".FURIK_STATUS_FUTURE.") as future_count
        FROM {$wpdb->prefix}furik_transactions t
        LEFT JOIN {$wpdb->prefix}posts campaigns ON (t.campaign = campaigns.ID)
        WHERE t.time < %s";
    
    $params = array($cutoff_date);
    
    // Add transaction type filter if not "all"
    if ($transaction_type !== 'all') {
        $sql .= " AND t.transaction_type = %d";
        $params[] = intval($transaction_type);
    } else {
        $sql .= " AND t.transaction_type IN (".FURIK_TRANSACTION_TYPE_RECURRING_REG.", ".FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG.")";
    }
    
    // Add email filter if provided
    if (!empty($email_filter)) {
        $sql .= " AND t.email = %s";
        $params[] = $email_filter;
    }
    
    // Prepare and execute the query
    $recurring_donations = $wpdb->get_results($wpdb->prepare($sql, $params));
    
    $cancelled_cards = 0;
    $cancelled_transfers = 0;
    $deleted_future = 0;
    $errors = 0;
    
    echo '<div class="wrap">';
    echo '<h2>' . __('Batch Cancel Results', 'furik') . '</h2>';
    
    // Show filter information
    echo '<div class="filter-summary">';
    echo '<p><strong>' . __('Applied Filters', 'furik') . ':</strong> ';
    echo __('Date', 'furik') . ': <code>' . esc_html($cutoff_date) . '</code> | ';
    
    if ($transaction_type === 'all') {
        echo __('Type', 'furik') . ': <code>' . __('All Types', 'furik') . '</code>';
    } else if ($transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG) {
        echo __('Type', 'furik') . ': <code>' . __('Card Only', 'furik') . '</code>';
    } else if ($transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
        echo __('Type', 'furik') . ': <code>' . __('Bank Transfer Only', 'furik') . '</code>';
    }
    
    if (!empty($email_filter)) {
        echo ' | ' . __('Email', 'furik') . ': <code>' . esc_html($email_filter) . '</code>';
    }
    
    echo '</p></div>';
    
    if (empty($recurring_donations)) {
        echo '<div class="notice notice-warning"><p>' . __('No recurring donations found matching your criteria.', 'furik') . '</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=furik-batch-tools&tab=cancel_recurring') . '" class="button">' . __('Back to Cancellation Tool', 'furik') . '</a></p>';
        echo '</div>';
        return;
    }
    
    echo '<p>' . sprintf(__('Found %d recurring donations matching your criteria', 'furik'), count($recurring_donations)) . '</p>';
    
    // Add export buttons
    echo '<div class="export-buttons" style="margin-bottom: 10px;">';
    echo '<button type="button" class="button" id="export-csv" onclick="exportTableToCSV(\'recurring-cancellations.csv\')">' . __('Export to CSV', 'furik') . '</button>';
    echo '</div>';
    
    echo '<table class="wp-list-table widefat fixed striped results-table" id="cancellation-results">';
    echo '<thead><tr>';
    echo '<th>' . __('ID', 'furik') . '</th>';
    echo '<th>' . __('Transaction ID', 'furik') . '</th>';
    echo '<th>' . __('Type', 'furik') . '</th>';
    echo '<th>' . __('Name', 'furik') . '</th>';
    echo '<th>' . __('Email', 'furik') . '</th>';
    echo '<th>' . __('Amount', 'furik') . '</th>';
    echo '<th>' . __('Campaign', 'furik') . '</th>';
    echo '<th>' . __('Registration Date', 'furik') . '</th>';
    echo '<th>' . __('Future Transactions', 'furik') . '</th>';
    echo '<th>' . __('Card Cancelled', 'furik') . '</th>';
    echo '<th>' . __('Result', 'furik') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    // Process each recurring registration
    foreach ($recurring_donations as $donation) {
        $card_cancelled = 'N/A';
        $result = 'No action taken (dry run)';
        $status_class = 'status-skipped';
        
        if (!$dry_run) {
            // For card-based recurring with vendor_ref, cancel with SimplePay
            if ($donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && !empty($donation->vendor_ref)) {
                try {
                    furik_cancel_recurring($donation->vendor_ref);
                    $card_cancelled = 'Yes';
                    $cancelled_cards++;
                    $status_class = 'status-success';
                } catch (Exception $e) {
                    $card_cancelled = 'Error';
                    $errors++;
                    $result = 'Error cancelling card: ' . $e->getMessage();
                    $status_class = 'status-error';
                    continue;
                }
            } else if ($donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
                $cancelled_transfers++;
                $status_class = 'status-success';
            }
            
            // Delete future transactions
            $deleted_count = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}furik_transactions 
                WHERE parent = %d AND transaction_status = %d",
                $donation->id,
                FURIK_STATUS_FUTURE
            ));
            
            $deleted_future += $deleted_count;
            $result = sprintf(__('Successfully cancelled. %d future transactions deleted.', 'furik'), $deleted_count);
        }
        
        $type = $donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG ? 
            __('Card', 'furik') : __('Transfer', 'furik');
        
        $name = !empty($donation->name) ? $donation->name : 
                (!empty($donation->first_name) ? $donation->first_name . ' ' . $donation->last_name : '—');
        
        echo '<tr data-status="' . esc_attr($status_class) . '">';
        echo '<td>' . esc_html($donation->id) . '</td>';
        echo '<td>' . esc_html($donation->transaction_id) . '</td>';
        echo '<td>' . esc_html($type) . '</td>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td>' . esc_html($donation->email) . '</td>';
        echo '<td>' . esc_html(number_format($donation->amount, 0, ',', ' ')) . ' HUF</td>';
        echo '<td>' . esc_html($donation->campaign_name ?: __('General donation', 'furik')) . '</td>';
        echo '<td class="date-display">' . esc_html($donation->time) . '</td>';
        echo '<td>' . esc_html($donation->future_count) . '</td>';
        echo '<td class="' . esc_attr($status_class) . '">' . esc_html($card_cancelled) . '</td>';
        echo '<td>' . esc_html($result) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    echo '<div class="results-summary notice notice-' . ($dry_run ? 'warning' : 'success') . '"><p>';
    if ($dry_run) {
        echo sprintf(
            __('DRY RUN ONLY - No actual changes made. Would have cancelled: %d card registrations, %d transfer registrations, and deleted %d future transactions.', 'furik'),
            count(array_filter($recurring_donations, function($d) { return $d->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && !empty($d->vendor_ref); })),
            count(array_filter($recurring_donations, function($d) { return $d->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; })),
            array_sum(array_column($recurring_donations, 'future_count'))
        );
    } else {
        echo sprintf(
            __('Successfully cancelled %d card registrations, %d transfer registrations, and deleted %d future transactions. Encountered %d errors.', 'furik'),
            $cancelled_cards,
            $cancelled_transfers,
            $deleted_future,
            $errors
        );
    }
    echo '</p></div>';
    
    echo '<p><a href="' . admin_url('admin.php?page=furik-batch-tools&tab=cancel_recurring') . '" class="button">' . __('Back to Cancellation Tool', 'furik') . '</a></p>';
    
    // Add CSV export script
    ?>
    <script>
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll('#cancellation-results tr');
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (var j = 0; j < cols.length; j++) {
                // Replace HTML entities and clean up text
                var text = cols[j].innerText.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        downloadCSV(csv.join('\n'), filename);
    }
    
    function downloadCSV(csv, filename) {
        var csvFile = new Blob([csv], {type: 'text/csv'});
        var downloadLink = document.createElement('a');
        
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
    </script>
    <?php
    
    echo '</div>';
}

/**
 * Process recurring payments manually
 */
function furik_process_batch_recurring() {
    global $wpdb;
    
    check_admin_referer('furik_process_recurring_nonce');
    
    $process_limit = min(100, max(1, intval($_POST['process_limit'])));
    $days_threshold = max(1, intval($_POST['days_threshold']));
    $dry_run = isset($_POST['dry_run']) ? true : false;
    
    require_once "SimplePayV21.php";
    require_once "SimplePayV21CardStorage.php";
    
    // Get future recurring payments that are due
    $sql = "SELECT rec.*
        FROM
            {$wpdb->prefix}furik_transactions AS rec
            JOIN {$wpdb->prefix}furik_transactions AS ptr ON (rec.parent=ptr.id)
        WHERE rec.time <= now()
            AND rec.transaction_status in (".FURIK_STATUS_FUTURE.")
            AND ptr.transaction_status in (1, 10)
        ORDER BY time ASC
        LIMIT " . $process_limit;
    
    $payments = $wpdb->get_results($sql);
    
    echo '<div class="wrap">';
    echo '<h2>' . __('Process Recurring Payments Results', 'furik') . '</h2>';
    echo '<p>' . sprintf(__('Processing up to %d recurring payments', 'furik'), $process_limit) . '</p>';
    
    echo '<table class="wp-list-table widefat fixed striped results-table">';
    echo '<thead><tr>';
    echo '<th>' . __('ID', 'furik') . '</th>';
    echo '<th>' . __('Transaction ID', 'furik') . '</th>';
    echo '<th>' . __('Amount', 'furik') . '</th>';
    echo '<th>' . __('Scheduled Date', 'furik') . '</th>';
    echo '<th>' . __('Last Payment', 'furik') . '</th>';
    echo '<th>' . __('Days Since Last', 'furik') . '</th>';
    echo '<th>' . __('Status', 'furik') . '</th>';
    echo '<th>' . __('Result', 'furik') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    $processed_count = 0;
    $successful_count = 0;
    $failed_count = 0;
    $skipped_count = 0;
    
    foreach ($payments as $payment) {
        // Get previous transaction date
        $previous_date = $wpdb->get_var($wpdb->prepare(
            "SELECT transaction_time
                FROM {$wpdb->prefix}furik_transactions
                WHERE transaction_time is not null
                    AND (id = %d OR parent = %d)
                ORDER BY id DESC
                LIMIT 1",
            $payment->parent,
            $payment->parent
        ));
        
        $days_since_last = 'N/A';
        $time_diff = 0;
        $status = '';
        $result = '';
        $status_class = '';
        
        if ($previous_date) {
            $time_diff = time() - strtotime($previous_date);
            $days_since_last = round($time_diff / (60 * 60 * 24));
            
            if ($days_since_last < $days_threshold) {
                $status = __('Skipped', 'furik');
                $result = sprintf(__('Last payment was only %d days ago (threshold: %d days)', 'furik'), $days_since_last, $days_threshold);
                $skipped_count++;
                $status_class = 'status-skipped';
            } else {
                $status = $dry_run ? __('Ready (Dry Run)', 'furik') : __('Processing', 'furik');
                $status_class = 'status-warning';
                
                if (!$dry_run) {
                    $trx = new SimplePayDorecurring;
                    $trx->addConfig(furik_get_simple_config());
                    $trx->addData('orderRef', $payment->transaction_id);
                    $trx->addData('methods', array('CARD'));
                    $trx->addData('currency', 'HUF');
                    $trx->addData('total', $payment->amount);
                    $trx->addData('customerEmail', $payment->email);
                    $trx->addData('token', $payment->token);
                    $trx->runDorecurring();
                    
                    $returnData = $trx->getReturnData();
                    furik_transaction_log($payment->transaction_id, serialize($returnData));
                    
                    $newStatus = $returnData['total'] > 0 ? FURIK_STATUS_SUCCESSFUL : FURIK_STATUS_RECURRING_FAILED;
                    
                    $wpdb->update(
                        "{$wpdb->prefix}furik_transactions",
                        array(
                            "transaction_status" => $newStatus,
                            "transaction_time" => date("Y-m-d H:i:s")
                        ),
                        array("id" => $payment->id)
                    );
                    
                    if ($newStatus == FURIK_STATUS_SUCCESSFUL) {
                        $status = __('Success', 'furik');
                        $result = sprintf(__('Payment successful: %d HUF', 'furik'), $payment->amount);
                        $successful_count++;
                        $status_class = 'status-success';
                    } else {
                        $status = __('Failed', 'furik');
                        $result = isset($returnData['errorCodes']) && !empty($returnData['errorCodes']) ? 
                                 sprintf(__('Error code: %s', 'furik'), $returnData['errorCodes'][0]) : 
                                 __('Unknown error', 'furik');
                        $failed_count++;
                        $status_class = 'status-error';
                        
                        // Handle failure types that require cancelling future transactions
                        if (isset($returnData['errorCodes']) && 
                            (in_array(2063, $returnData['errorCodes']) || in_array(2072, $returnData['errorCodes']))) {
                            
                            $wpdb->update(
                                "{$wpdb->prefix}furik_transactions",
                                array(
                                    "transaction_status" => FURIK_STATUS_RECURRING_PAST_FAILED,
                                    "transaction_time" => date("Y-m-d H:i:s")
                                ),
                                array(
                                    "parent" => $payment->parent,
                                    "transaction_status" => FURIK_STATUS_FUTURE
                                )
                            );
                            
                            $result .= ' ' . __('All future recurring payments cancelled due to payment failure.', 'furik');
                        }
                    }
                    
                    $processed_count++;
                } else {
                    $result = __('Would process this payment (dry run)', 'furik');
                }
            }
        } else {
            $status = __('Error', 'furik');
            $result = __('No previous transaction found', 'furik');
            $skipped_count++;
            $status_class = 'status-error';
        }
        
        echo '<tr data-status="' . esc_attr($status_class) . '">';
        echo '<td>' . esc_html($payment->id) . '</td>';
        echo '<td>' . esc_html($payment->transaction_id) . '</td>';
        echo '<td>' . esc_html(number_format($payment->amount, 0, ',', ' ')) . ' HUF</td>';
        echo '<td class="date-display">' . esc_html($payment->time) . '</td>';
        echo '<td class="date-display">' . esc_html($previous_date ?: 'N/A') . '</td>';
        echo '<td>' . esc_html($days_since_last) . '</td>';
        echo '<td class="' . esc_attr($status_class) . '">' . esc_html($status) . '</td>';
        echo '<td>' . esc_html($result) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    // Add a filter dropdown for the results table
    if (count($payments) > 0) {
        echo '<div class="tablenav bottom">';
        echo '<div class="alignleft actions">';
        echo '<label for="filter-status" class="screen-reader-text">' . __('Filter by status', 'furik') . '</label>';
        echo '<select id="filter-status">';
        echo '<option value="all">' . __('All statuses', 'furik') . '</option>';
        echo '<option value="status-success">' . __('Success', 'furik') . '</option>';
        echo '<option value="status-error">' . __('Error', 'furik') . '</option>';
        echo '<option value="status-warning">' . __('Warning', 'furik') . '</option>';
        echo '<option value="status-skipped">' . __('Skipped', 'furik') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '<div class="results-summary notice notice-' . ($dry_run ? 'info' : 'success') . '"><p>';
    if ($dry_run) {
        echo sprintf(
            __('DRY RUN ONLY - Found %d total payments (%d would be skipped, %d would be processed).', 'furik'),
            count($payments),
            $skipped_count,
            count($payments) - $skipped_count
        );
    } else {
        echo sprintf(
            __('Processed %d payments: %d successful, %d failed, %d skipped.', 'furik'),
            $processed_count,
            $successful_count,
            $failed_count,
            $skipped_count
        );
    }
    echo '</p></div>';
    
    echo '<p><a href="' . admin_url('admin.php?page=furik-batch-tools&tab=process_recurring') . '" class="button">' . __('Back to Processing Tool', 'furik') . '</a></p>';
    
    echo '</div>';
}
