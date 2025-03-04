<?php
/**
 * Furik Recurring Payment Cron Implementation
 *
 * This file contains functionality to make the recurring payment processing
 * more robust through WordPress cron scheduling.
 */

/**
 * Register the cron event on plugin activation
 */
function furik_register_cron_events() {
	// Schedule the event if it's not already scheduled
	if ( ! wp_next_scheduled( 'furik_process_recurring_payments' ) ) {
		// Schedule it to run once daily
		wp_schedule_event( time(), 'daily', 'furik_process_recurring_payments' );
	}
}

/**
 * Cleanup cron events on plugin deactivation
 */
function furik_deactivate_cron_events() {
	wp_clear_scheduled_hook( 'furik_process_recurring_payments' );
}

/**
 * Hook our cron registration function to WordPress init
 */
add_action( 'init', 'furik_register_cron_events' );
register_deactivation_hook( __DIR__ . '/furik.php', 'furik_deactivate_cron_events' );

/**
 * The function that will be called by the cron event to process recurring payments
 */
function furik_cron_process_recurring_payments() {
    // Log the start of cron processing
    error_log('Furik: Starting scheduled recurring payment processing');

    // Use the unified processing function with default settings
    // Process maximum 20 payments, with 25 days threshold, no dry run
    $results = furik_process_recurring_payments(20, 25, false, 'array');

    // Log results
    error_log(sprintf(
        'Furik: Completed scheduled recurring payment processing. Found: %d, Processed: %d, Successful: %d, Failed: %d, Skipped: %d',
        $results['totals']['total'],
        $results['totals']['processed'],
        $results['totals']['successful'],
        $results['totals']['failed'],
        $results['totals']['skipped']
    ));
    
    return $results;
}
add_action( 'furik_process_recurring_payments', 'furik_cron_process_recurring_payments' );

/**
 * Function to get upcoming recurring payments in the next X days
 */
function furik_get_upcoming_recurring_payments( $days = 5 ) {
	global $wpdb;

	$date_limit = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );

	$sql = "SELECT rec.*, 
                ptr.name AS donor_name,
                ptr.email AS donor_email,
                campaigns.post_title AS campaign_name
            FROM
                {$wpdb->prefix}furik_transactions AS rec
                JOIN {$wpdb->prefix}furik_transactions AS ptr ON (rec.parent=ptr.id)
                LEFT JOIN {$wpdb->prefix}posts AS campaigns ON (ptr.campaign=campaigns.ID)
            WHERE rec.time <= %s
                AND rec.time >= NOW()
                AND rec.transaction_status = " . FURIK_STATUS_FUTURE . '
                AND ptr.transaction_status IN (1, 10)
            ORDER BY rec.time ASC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $date_limit ) );
}
