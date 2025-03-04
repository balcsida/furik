<?php
/**
 * Add Batch Tools submenu to the Furik menu
 */
function furik_batch_tools_menu() {
	add_submenu_page(
		'furik-dashboard',    // Changed: Parent menu slug
		__( 'Batch Tools', 'furik' ),
		__( 'Batch Tools', 'furik' ),
		'manage_options',
		'furik-batch-tools',    // Page slug
		'furik_batch_tools_page'
	);
}
add_action( 'admin_menu', 'furik_batch_tools_menu', 30 ); // Higher priority to ensure it appears after other items

/**
 * Register scripts and styles for batch tools
 */
function furik_batch_tools_scripts( $hook ) {
	// Only load on our page
	if ( $hook != 'furik_page_furik-batch-tools' ) {
		return;
	}

	// Register and enqueue CSS
	wp_register_style(
		'furik-batch-tools',
		plugins_url( 'assets/css/furik-batch-tools.css', __DIR__ )
	);
	wp_enqueue_style( 'furik-batch-tools' );

	// Register and enqueue JS
	wp_register_script(
		'furik-batch-tools',
		plugins_url( 'assets/js/furik-batch-tools.js', __DIR__ ),
		array( 'jquery' ),
		'1.0',
		true
	);

	// Add localizations for JS
	wp_localize_script(
		'furik-batch-tools',
		'furikBatchTools',
		array(
			'confirmMessage' => __( 'WARNING: You are about to make changes to the database. This cannot be undone. Are you sure you want to continue?', 'furik' ),
		)
	);

	wp_enqueue_script( 'furik-batch-tools' );
}
add_action( 'admin_enqueue_scripts', 'furik_batch_tools_scripts' );

/**
 * The Batch Tools admin page
 */
function furik_batch_tools_page() {
	// Process batch cancel form submission
	if ( isset( $_POST['furik_action'] ) && $_POST['furik_action'] === 'batch_cancel' ) {
		furik_process_batch_cancel();
	}

	// Process recurring payments form submission
	if ( isset( $_POST['furik_action'] ) && $_POST['furik_action'] === 'process_recurring' ) {
		furik_process_batch_recurring();
	}

	// Process duplicate finder form submission
	if ( isset( $_POST['furik_action'] ) && $_POST['furik_action'] === 'find_duplicates' ) {
		furik_process_find_duplicates();
	}

	// Determine active tab
	$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'cancel_recurring';
	?>
	<div class="wrap">
		<h1><?php _e( 'Furik Batch Tools', 'furik' ); ?></h1>
		
		<h2 class="nav-tab-wrapper">
			<a href="?page=furik-batch-tools&tab=cancel_recurring" class="nav-tab <?php echo $active_tab == 'cancel_recurring' ? 'nav-tab-active' : ''; ?>">
				<?php _e( 'Cancel Recurring Donations', 'furik' ); ?>
			</a>
			<a href="?page=furik-batch-tools&tab=process_recurring" class="nav-tab <?php echo $active_tab == 'process_recurring' ? 'nav-tab-active' : ''; ?>">
				<?php _e( 'Process Recurring Payments', 'furik' ); ?>
			</a>
			<a href="?page=furik-batch-tools&tab=find_duplicates" class="nav-tab <?php echo $active_tab == 'find_duplicates' ? 'nav-tab-active' : ''; ?>">
				<?php _e( 'Find Duplicate Recurring Donations', 'furik' ); ?>
			</a>
		</h2>
		
		<div class="tab-content">
			<?php
			if ( $active_tab == 'cancel_recurring' ) {
				furik_render_batch_cancel_tab();
			} elseif ( $active_tab == 'process_recurring' ) {
				furik_render_process_recurring_tab();
			} elseif ( $active_tab == 'find_duplicates' ) {
				furik_render_find_duplicates_tab();
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
		<p><?php _e( 'This tool allows you to cancel recurring donations created before a specific date. This operation cannot be undone!', 'furik' ); ?></p>
	</div>
	
	<form method="post" action="" class="furik-batch-form">
		<?php wp_nonce_field( 'furik_batch_cancel_nonce' ); ?>
		<input type="hidden" name="furik_action" value="batch_cancel">
		<input type="hidden" name="tab" value="cancel_recurring">
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="cutoff_date"><?php _e( 'Cancel donations before', 'furik' ); ?></label></th>
				<td>
					<input type="date" id="cutoff_date" name="cutoff_date" value="2025-02-01" required>
					<p class="description"><?php _e( 'All recurring donations created before this date will be cancelled.', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="transaction_type"><?php _e( 'Transaction Type', 'furik' ); ?></label></th>
				<td>
					<select id="transaction_type" name="transaction_type">
						<option value="all"><?php _e( 'All Types', 'furik' ); ?></option>
						<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_REG; ?>"><?php _e( 'Card Only', 'furik' ); ?></option>
						<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; ?>"><?php _e( 'Bank Transfer Only', 'furik' ); ?></option>
					</select>
					<p class="description"><?php _e( 'Filter by transaction type to target specific recurring donation methods.', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Email Filter', 'furik' ); ?></th>
				<td>
					<input type="text" id="email_filter" name="email_filter" placeholder="<?php _e( 'Optional email address filter', 'furik' ); ?>">
					<p class="description"><?php _e( 'Filter by email address (leave blank for all emails).', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Dry run', 'furik' ); ?></th>
				<td>
					<input type="checkbox" id="dry_run" name="dry_run" value="1" checked>
					<label for="dry_run"><?php _e( 'Perform a dry run (no actual changes will be made)', 'furik' ); ?></label>
					<p class="description"><?php _e( 'Uncheck this box when you are ready to perform the actual cancellation.', 'furik' ); ?></p>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Process Cancellations', 'furik' ); ?>">
		</p>
	</form>
	<?php
}

/**
 * Process the batch cancellation of recurring donations with enhanced filtering and details
 */
function furik_process_batch_cancel() {
	global $wpdb;

	check_admin_referer( 'furik_batch_cancel_nonce' );

	$cutoff_date      = sanitize_text_field( $_POST['cutoff_date'] );
	$transaction_type = isset( $_POST['transaction_type'] ) ? sanitize_text_field( $_POST['transaction_type'] ) : 'all';
	$email_filter     = isset( $_POST['email_filter'] ) ? sanitize_email( $_POST['email_filter'] ) : '';
	$dry_run          = isset( $_POST['dry_run'] ) ? true : false;

	// Validate date format
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cutoff_date ) ) {
		echo '<div class="notice notice-error"><p>' . __( 'Invalid date format. Please use YYYY-MM-DD format.', 'furik' ) . '</p></div>';
		return;
	}

	// Build the SQL query with filters
	$sql = "SELECT t.*, 
        campaigns.post_title AS campaign_name,
        (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=t.id AND transaction_status=" . FURIK_STATUS_FUTURE . ") as future_count
        FROM {$wpdb->prefix}furik_transactions t
        LEFT JOIN {$wpdb->prefix}posts campaigns ON (t.campaign = campaigns.ID)
        WHERE t.time < %s";

	$params = array( $cutoff_date );

	// Add transaction type filter if not "all"
	if ( $transaction_type !== 'all' ) {
		$sql     .= ' AND t.transaction_type = %d';
		$params[] = intval( $transaction_type );
	} else {
		$sql .= ' AND t.transaction_type IN (' . FURIK_TRANSACTION_TYPE_RECURRING_REG . ', ' . FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG . ')';
	}

	// Add email filter if provided
	if ( ! empty( $email_filter ) ) {
		$sql     .= ' AND t.email = %s';
		$params[] = $email_filter;
	}

	// Prepare and execute the query
	$recurring_donations = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	$cancelled_cards     = 0;
	$cancelled_transfers = 0;
	$deleted_future      = 0;
	$errors              = 0;

	echo '<div class="wrap">';
	echo '<h2>' . __( 'Batch Cancel Results', 'furik' ) . '</h2>';

	// Show filter information
	echo '<div class="filter-summary">';
	echo '<p><strong>' . __( 'Applied Filters', 'furik' ) . ':</strong> ';
	echo __( 'Date', 'furik' ) . ': <code>' . esc_html( $cutoff_date ) . '</code> | ';

	if ( $transaction_type === 'all' ) {
		echo __( 'Type', 'furik' ) . ': <code>' . __( 'All Types', 'furik' ) . '</code>';
	} elseif ( $transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG ) {
		echo __( 'Type', 'furik' ) . ': <code>' . __( 'Card Only', 'furik' ) . '</code>';
	} elseif ( $transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG ) {
		echo __( 'Type', 'furik' ) . ': <code>' . __( 'Bank Transfer Only', 'furik' ) . '</code>';
	}

	if ( ! empty( $email_filter ) ) {
		echo ' | ' . __( 'Email', 'furik' ) . ': <code>' . esc_html( $email_filter ) . '</code>';
	}

	echo '</p></div>';

	if ( empty( $recurring_donations ) ) {
		echo '<div class="notice notice-warning"><p>' . __( 'No recurring donations found matching your criteria.', 'furik' ) . '</p></div>';
		echo '<p><a href="' . admin_url( 'admin.php?page=furik-batch-tools&tab=cancel_recurring' ) . '" class="button">' . __( 'Back to Cancellation Tool', 'furik' ) . '</a></p>';
		echo '</div>';
		return;
	}

	echo '<p>' . sprintf( __( 'Found %d recurring donations matching your criteria', 'furik' ), count( $recurring_donations ) ) . '</p>';

	// Add export buttons
	echo '<div class="export-buttons" style="margin-bottom: 10px;">';
	echo '<button type="button" class="button" id="export-csv" onclick="exportTableToCSV(\'recurring-cancellations.csv\')">' . __( 'Export to CSV', 'furik' ) . '</button>';
	echo '</div>';

	echo '<table class="wp-list-table widefat fixed striped results-table" id="cancellation-results">';
	echo '<thead><tr>';
	echo '<th>' . __( 'ID', 'furik' ) . '</th>';
	echo '<th>' . __( 'Transaction ID', 'furik' ) . '</th>';
	echo '<th>' . __( 'Type', 'furik' ) . '</th>';
	echo '<th>' . __( 'Name', 'furik' ) . '</th>';
	echo '<th>' . __( 'Email', 'furik' ) . '</th>';
	echo '<th>' . __( 'Amount', 'furik' ) . '</th>';
	echo '<th>' . __( 'Campaign', 'furik' ) . '</th>';
	echo '<th>' . __( 'Registration Date', 'furik' ) . '</th>';
	echo '<th>' . __( 'Future Transactions', 'furik' ) . '</th>';
	echo '<th>' . __( 'Card Cancelled', 'furik' ) . '</th>';
	echo '<th>' . __( 'Result', 'furik' ) . '</th>';
	echo '</tr></thead>';
	echo '<tbody>';

	// Process each recurring registration
	foreach ( $recurring_donations as $donation ) {
		$card_cancelled = 'N/A';
		$result         = 'No action taken (dry run)';
		$status_class   = 'status-skipped';

		if ( ! $dry_run ) {
			// For card-based recurring with vendor_ref, cancel with SimplePay
			if ( $donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && ! empty( $donation->vendor_ref ) ) {
				try {
					furik_cancel_recurring( $donation->vendor_ref );
					$card_cancelled = 'Yes';
					++$cancelled_cards;
					$status_class = 'status-success';
				} catch ( Exception $e ) {
					$card_cancelled = 'Error';
					++$errors;
					$result       = 'Error cancelling card: ' . $e->getMessage();
					$status_class = 'status-error';
					continue;
				}
			} elseif ( $donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG ) {
				++$cancelled_transfers;
				$status_class = 'status-success';
			}

			// Delete future transactions
			$deleted_count = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}furik_transactions 
                WHERE parent = %d AND transaction_status = %d",
					$donation->id,
					FURIK_STATUS_FUTURE
				)
			);

			$deleted_future += $deleted_count;
			$result          = sprintf( __( 'Successfully cancelled. %d future transactions deleted.', 'furik' ), $deleted_count );
		}

		$type = $donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG ?
			__( 'Card', 'furik' ) : __( 'Transfer', 'furik' );

		$name = ! empty( $donation->name ) ? $donation->name :
				( ! empty( $donation->first_name ) ? $donation->first_name . ' ' . $donation->last_name : '—' );

		echo '<tr data-status="' . esc_attr( $status_class ) . '">';
		echo '<td>' . esc_html( $donation->id ) . '</td>';
		echo '<td>' . esc_html( $donation->transaction_id ) . '</td>';
		echo '<td>' . esc_html( $type ) . '</td>';
		echo '<td>' . esc_html( $name ) . '</td>';
		echo '<td>' . esc_html( $donation->email ) . '</td>';
		echo '<td>' . esc_html( number_format( $donation->amount, 0, ',', ' ' ) ) . ' HUF</td>';
		echo '<td>' . esc_html( $donation->campaign_name ?: __( 'General donation', 'furik' ) ) . '</td>';
		echo '<td class="date-display">' . esc_html( $donation->time ) . '</td>';
		echo '<td>' . esc_html( $donation->future_count ) . '</td>';
		echo '<td class="' . esc_attr( $status_class ) . '">' . esc_html( $card_cancelled ) . '</td>';
		echo '<td>' . esc_html( $result ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';

	echo '<div class="results-summary notice notice-' . ( $dry_run ? 'warning' : 'success' ) . '"><p>';
	if ( $dry_run ) {
		printf(
			__( 'DRY RUN ONLY - No actual changes made. Would have cancelled: %1$d card registrations, %2$d transfer registrations, and deleted %3$d future transactions.', 'furik' ),
			count(
				array_filter(
					$recurring_donations,
					function ( $d ) {
						return $d->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && ! empty( $d->vendor_ref ); }
				)
			),
			count(
				array_filter(
					$recurring_donations,
					function ( $d ) {
						return $d->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; }
				)
			),
			array_sum( array_column( $recurring_donations, 'future_count' ) )
		);
	} else {
		printf(
			__( 'Successfully cancelled %1$d card registrations, %2$d transfer registrations, and deleted %3$d future transactions. Encountered %4$d errors.', 'furik' ),
			$cancelled_cards,
			$cancelled_transfers,
			$deleted_future,
			$errors
		);
	}
	echo '</p></div>';

	echo '<p><a href="' . admin_url( 'admin.php?page=furik-batch-tools&tab=cancel_recurring' ) . '" class="button">' . __( 'Back to Cancellation Tool', 'furik' ) . '</a></p>';

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
 * Render the Process Recurring Payments tab
 */
function furik_render_process_recurring_tab() {
	?>
	<div class="notice notice-info">
		<p><?php _e( 'This tool allows you to manually process recurring payments that are due. The system normally does this automatically via cron, but you can use this tool to trigger the process manually.', 'furik' ); ?></p>
	</div>
	
	<form method="post" action="" class="furik-batch-form">
		<?php wp_nonce_field( 'furik_process_recurring_nonce' ); ?>
		<input type="hidden" name="furik_action" value="process_recurring">
		<input type="hidden" name="tab" value="process_recurring">
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="process_limit"><?php _e( 'Number of payments to process', 'furik' ); ?></label></th>
				<td>
					<input type="number" id="process_limit" name="process_limit" value="10" min="1" max="5000" required>
					<p class="description"><?php _e( 'Limit the number of payments to process in this batch (1-5000).', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="days_threshold"><?php _e( 'Days since last payment', 'furik' ); ?></label></th>
				<td>
					<input type="number" id="days_threshold" name="days_threshold" value="25" min="1" required>
					<p class="description"><?php _e( 'Only process payments where the last successful payment was at least this many days ago.', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Dry run', 'furik' ); ?></th>
				<td>
					<input type="checkbox" id="dry_run_recurring" name="dry_run" value="1" checked>
					<label for="dry_run_recurring"><?php _e( 'Perform a dry run (show what would happen but make no actual charges)', 'furik' ); ?></label>
					<p class="description"><?php _e( 'Uncheck this box when you are ready to actually process payments.', 'furik' ); ?></p>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Process Payments', 'furik' ); ?>">
		</p>
	</form>
	<?php
}

/**
 * Process recurring payments manually from the Batch Tools page
 */
function furik_process_batch_recurring() {
    check_admin_referer('furik_process_recurring_nonce');

    $process_limit = isset($_POST['process_limit']) ? intval($_POST['process_limit']) : 10;
    $days_threshold = isset($_POST['days_threshold']) ? intval($_POST['days_threshold']) : 25;
    $dry_run = isset($_POST['dry_run']) ? true : false;

    // Process payments and generate HTML output
    echo furik_process_recurring_payments($process_limit, $days_threshold, $dry_run, 'html');
}

/**
 * Render the Find Duplicate Recurring Donations tab
 */
function furik_render_find_duplicates_tab() {
	?>
	<div class="notice notice-info">
		<p><?php _e( 'This tool helps you identify donors who have multiple active recurring donations with the same email address. This can help you find duplicates that may need to be consolidated or cancelled.', 'furik' ); ?></p>
	</div>
	
	<form method="post" action="" class="furik-batch-form">
		<?php wp_nonce_field( 'furik_find_duplicates_nonce' ); ?>
		<input type="hidden" name="furik_action" value="find_duplicates">
		<input type="hidden" name="tab" value="find_duplicates">
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="min_active_donations"><?php _e( 'Minimum active donations', 'furik' ); ?></label></th>
				<td>
					<input type="number" id="min_active_donations" name="min_active_donations" value="2" min="2" max="10" required>
					<p class="description"><?php _e( 'Find donors with at least this many active recurring donations.', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="transaction_type"><?php _e( 'Donation Type', 'furik' ); ?></label></th>
				<td>
					<select id="transaction_type" name="transaction_type">
						<option value="all"><?php _e( 'All Types', 'furik' ); ?></option>
						<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_REG; ?>"><?php _e( 'Card Only', 'furik' ); ?></option>
						<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; ?>"><?php _e( 'Bank Transfer Only', 'furik' ); ?></option>
					</select>
					<p class="description"><?php _e( 'Filter by transaction type.', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="days_range"><?php _e( 'Registration Period', 'furik' ); ?></label></th>
				<td>
					<input type="number" id="days_range" name="days_range" value="365" min="1">
					<p class="description"><?php _e( 'Look for duplicates registered within this many days from each other (0 for any timeframe).', 'furik' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="include_cancelled"><?php _e( 'Include cancelled donations', 'furik' ); ?></label></th>
				<td>
					<input type="checkbox" id="include_cancelled" name="include_cancelled" value="1">
					<p class="description"><?php _e( 'If checked, donations that have been cancelled (no future transactions) will also be counted.', 'furik' ); ?></p>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Find Duplicates', 'furik' ); ?>">
		</p>
	</form>
	<?php
}

/**
 * Process the duplicate finder tool
 */
function furik_process_find_duplicates() {
	global $wpdb;

	check_admin_referer( 'furik_find_duplicates_nonce' );

	$min_active_donations = intval( $_POST['min_active_donations'] );
	$transaction_type     = isset( $_POST['transaction_type'] ) ? sanitize_text_field( $_POST['transaction_type'] ) : 'all';
	$days_range           = isset( $_POST['days_range'] ) ? intval( $_POST['days_range'] ) : 365;
	$include_cancelled    = isset( $_POST['include_cancelled'] ) ? true : false;

	// Build SQL to find duplicate emails
	$sql_base = "
        SELECT 
            email, 
            COUNT(*) as count, 
            SUM(amount) as total_amount,
            GROUP_CONCAT(id ORDER BY time) as donation_ids,
            GROUP_CONCAT(transaction_id ORDER BY time) as transaction_ids,
            GROUP_CONCAT(time ORDER BY time) as registration_dates,
            GROUP_CONCAT(amount ORDER BY time) as amounts,
            GROUP_CONCAT(
                (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions 
                 WHERE parent = t.id AND transaction_status = " . FURIK_STATUS_FUTURE . ")
                ORDER BY time
            ) as future_counts,
            GROUP_CONCAT(
                (SELECT SUM(amount) FROM {$wpdb->prefix}furik_transactions 
                 WHERE (parent = t.id OR id = t.id) AND transaction_status IN (" . FURIK_STATUS_SUCCESSFUL . ', ' . FURIK_STATUS_IPN_SUCCESSFUL . "))
                ORDER BY time
            ) as collected_amounts,
            GROUP_CONCAT(
                (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions 
                 WHERE (parent = t.id OR id = t.id) AND transaction_status IN (" . FURIK_STATUS_SUCCESSFUL . ', ' . FURIK_STATUS_IPN_SUCCESSFUL . "))
                ORDER BY time
            ) as successful_counts
        FROM {$wpdb->prefix}furik_transactions as t
        WHERE transaction_type IN (";

	// Add transaction types
	if ( $transaction_type === 'all' ) {
		$sql_base .= FURIK_TRANSACTION_TYPE_RECURRING_REG . ',' . FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG;
	} else {
		$sql_base .= intval( $transaction_type );
	}

	$sql_base .= ')';

	// Add condition for active or cancelled donations
	if ( ! $include_cancelled ) {
		$sql_base .= " AND (
            SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions 
            WHERE parent = t.id AND transaction_status = " . FURIK_STATUS_FUTURE . '
        ) > 0';
	}

	// Group by email having multiple recurring donations
	$sql_base .= '
        GROUP BY email
        HAVING COUNT(*) >= %d
        ORDER BY COUNT(*) DESC, email ASC
    ';

	$sql              = $wpdb->prepare( $sql_base, $min_active_donations );
	$duplicate_emails = $wpdb->get_results( $sql );

	echo '<div class="wrap">';
	echo '<h2>' . __( 'Duplicate Recurring Donations Results', 'furik' ) . '</h2>';

	if ( empty( $duplicate_emails ) ) {
		echo '<div class="notice notice-info"><p>' .
			sprintf( __( 'No donors found with %d or more recurring donations.', 'furik' ), $min_active_donations ) .
			'</p></div>';
	} else {
		echo '<p>' . sprintf(
			__( 'Found %d donors with multiple recurring donations.', 'furik' ),
			count( $duplicate_emails )
		) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . __( 'Email', 'furik' ) . '</th>';
		echo '<th>' . __( 'Recurring Donations', 'furik' ) . '</th>';
		echo '<th>' . __( 'Total Monthly Amount', 'furik' ) . '</th>';
		echo '<th>' . __( 'Registration Dates', 'furik' ) . '</th>';
		echo '<th>' . __( 'Active Future Donations', 'furik' ) . '</th>';
		echo '<th>' . __( 'Amount Collected', 'furik' ) . '</th>';
		echo '<th>' . __( 'Collection Status', 'furik' ) . '</th>';
		echo '<th>' . __( 'Donation Details', 'furik' ) . '</th>';
		echo '<th>' . __( 'Actions', 'furik' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $duplicate_emails as $donor ) {
			$donation_ids       = explode( ',', $donor->donation_ids );
			$transaction_ids    = explode( ',', $donor->transaction_ids );
			$registration_dates = explode( ',', $donor->registration_dates );
			$amounts            = explode( ',', $donor->amounts );
			$future_counts      = explode( ',', $donor->future_counts );
			$collected_amounts  = explode( ',', $donor->collected_amounts );
			$successful_counts  = explode( ',', $donor->successful_counts );

			$row_class = '';
			// Flag rows with only one active recurring donation in yellow
			$active_count = array_sum( array_map( 'intval', $future_counts ) );
			if ( $active_count <= 1 ) {
				$row_class = 'status-warning';
			}
			// Flag rows with high combined amount in red
			elseif ( $donor->total_amount >= 10000 ) {
				$row_class = 'status-error';
			}

			echo '<tr class="' . $row_class . '">';
			echo '<td>' . esc_html( $donor->email ) . '</td>';
			echo '<td>' . esc_html( $donor->count ) . '</td>';
			echo '<td>' . number_format( $donor->total_amount, 0, ',', ' ' ) . ' HUF</td>';

			// Display registration dates with time intervals
			echo '<td>';
			$date_display = array();
			$prev_date    = null;
			foreach ( $registration_dates as $i => $date ) {
				$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $date ) );

				if ( $prev_date && $days_range > 0 ) {
					$days_diff = round( ( strtotime( $date ) - strtotime( $prev_date ) ) / ( 60 * 60 * 24 ) );
					if ( $days_diff <= $days_range ) {
						$date_display[] = '<span class="status-error">' . $formatted_date . ' (' . $days_diff . ' ' . __( 'days apart', 'furik' ) . ')</span>';
					} else {
						$date_display[] = $formatted_date;
					}
				} else {
					$date_display[] = $formatted_date;
				}
				$prev_date = $date;
			}
			echo implode( '<br>', $date_display );
			echo '</td>';

			// Display future donation counts
			echo '<td>';
			foreach ( $future_counts as $i => $count ) {
				$count = intval( $count );
				if ( $count > 0 ) {
					echo '<span class="status-success">' . $count . '</span><br>';
				} else {
					echo '<span class="status-skipped">' . $count . '</span><br>';
				}
			}
			echo '</td>';

			// Display collected amount for each donation
			echo '<td>';
			foreach ( $collected_amounts as $i => $collected ) {
				$collected = $collected ? intval( $collected ) : 0;
				echo number_format( $collected, 0, ',', ' ' ) . ' HUF';
				echo '<br>';
			}
			echo '</td>';

			// Display collection status - expected vs actual
			echo '<td>';
			foreach ( $registration_dates as $i => $date ) {
				$registration_timestamp    = strtotime( $date );
				$current_timestamp         = time();
				$months_since_registration = round( ( $current_timestamp - $registration_timestamp ) / ( 30 * 24 * 60 * 60 ) );
				$successful_count          = isset( $successful_counts[ $i ] ) ? intval( $successful_counts[ $i ] ) : 0;

				if ( $months_since_registration <= 1 ) {
					// New registration, not an issue
					echo '<span class="status-success">' . __( 'New registration', 'furik' ) . '</span>';
				} elseif ( $successful_count >= $months_since_registration ) {
					// All expected collections happened (or more)
					echo '<span class="status-success">' . sprintf(
						__( '%1$d/%2$d months collected', 'furik' ),
						$successful_count,
						$months_since_registration
					) . '</span>';
				} else {
					// Some collections are missing
					$missing_months = $months_since_registration - $successful_count;
					$missing_amount = $missing_months * intval( $amounts[ $i ] );

					echo '<span class="status-error">' . sprintf(
						__( '%1$d/%2$d months collected (%3$d months missing, ~%4$s HUF lost)', 'furik' ),
						$successful_count,
						$months_since_registration,
						$missing_months,
						number_format( $missing_amount, 0, ',', ' ' )
					) . '</span>';
				}
				echo '<br>';
			}
			echo '</td>';

			// Display donation details (ID, transaction ID, amount)
			echo '<td>';
			foreach ( $donation_ids as $i => $id ) {
				echo 'ID: ' . $id . ', ';
				echo 'Tx: ' . $transaction_ids[ $i ] . ', ';
				echo 'Amount: ' . number_format( $amounts[ $i ], 0, ',', ' ' ) . ' HUF';
				echo '<br>';
			}
			echo '</td>';

			// Actions column
			echo '<td>';
			foreach ( $donation_ids as $i => $id ) {
				$future_count = intval( $future_counts[ $i ] );
				if ( $future_count > 0 ) {
					echo '<a href="' . admin_url( 'admin.php?page=furik-recurring-donations&cancelRecurring=' . $id . '&transactionId=' . $transaction_ids[ $i ] ) . '" onclick="return confirm(\'' . __( 'Are you sure you want to cancel this recurring donation?', 'furik' ) . '\');">';
					echo __( 'Cancel Recurring', 'furik' );
					echo '</a>';
				} else {
					echo '<span class="status-skipped">' . __( 'Already Cancelled', 'furik' ) . '</span>';
				}
				echo '<br>';
			}
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '<div class="tablenav bottom">';
		echo '<div class="alignleft actions">';
		echo '<p>' . __( 'Color coding:', 'furik' ) . '</p>';
		echo '<ul>';
		echo '<li><span class="status-error" style="display:inline-block; padding:2px 5px;">' . __( 'Red', 'furik' ) . '</span>: ' . __( 'High combined amount (10,000+ HUF)', 'furik' ) . '</li>';
		echo '<li><span class="status-warning" style="display:inline-block; padding:2px 5px;">' . __( 'Yellow', 'furik' ) . '</span>: ' . __( 'Most donations already cancelled (only 0-1 active)', 'furik' ) . '</li>';
		echo '<li><span class="status-error" style="display:inline-block; padding:2px 5px;">' . __( 'Red dates', 'furik' ) . '</span>: ' . sprintf( __( 'Donations registered within %d days of each other', 'furik' ), $days_range ) . '</li>';
		echo '<li><span class="status-error" style="display:inline-block; padding:2px 5px;">' . __( 'Red collection status', 'furik' ) . '</span>: ' . __( 'Missing monthly collections (potential revenue loss)', 'furik' ) . '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	echo '<p><a href="' . admin_url( 'admin.php?page=furik-batch-tools&tab=find_duplicates' ) . '" class="button">' . __( 'Back to Duplicate Finder', 'furik' ) . '</a></p>';
	echo '</div>';
}

