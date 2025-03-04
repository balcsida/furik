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
    if (!wp_next_scheduled('furik_process_recurring_payments')) {
        // Schedule it to run once daily
        wp_schedule_event(time(), 'daily', 'furik_process_recurring_payments');
    }
}

/**
 * Cleanup cron events on plugin deactivation
 */
function furik_deactivate_cron_events() {
    wp_clear_scheduled_hook('furik_process_recurring_payments');
}

/**
 * Hook our cron registration function to WordPress init
 */
add_action('init', 'furik_register_cron_events');
register_deactivation_hook(dirname(__FILE__) . '/furik.php', 'furik_deactivate_cron_events');

/**
 * The function that will be called by the cron event to process recurring payments
 */
function furik_cron_process_recurring_payments() {
    global $wpdb;
    
    // Log the start of cron processing
    error_log('Furik: Starting scheduled recurring payment processing');
    
    // Get recurring payments that are due to be processed
    $sql = "SELECT rec.*
        FROM
            {$wpdb->prefix}furik_transactions AS rec
            JOIN {$wpdb->prefix}furik_transactions AS ptr ON (rec.parent=ptr.id)
        WHERE rec.time <= NOW()
            AND rec.transaction_status = " . FURIK_STATUS_FUTURE . "
            AND ptr.transaction_status IN (1, 10)
            AND NOT EXISTS (
                -- Check for any failed recurring payments in the last 2 months
                SELECT 1 FROM {$wpdb->prefix}furik_transactions AS failed
                WHERE failed.parent = ptr.id
                AND failed.transaction_status = " . FURIK_STATUS_RECURRING_FAILED . "
                AND failed.time > DATE_SUB(NOW(), INTERVAL 2 MONTH)
            )
        ORDER BY time ASC
        LIMIT 20"; // Process in batches for safety
    
    $payments = $wpdb->get_results($sql);
    
    if (empty($payments)) {
        error_log('Furik: No recurring payments due for processing.');
        return;
    }
    
    error_log('Furik: Found ' . count($payments) . ' recurring payments to process');
    
    require_once "../payments/SimplePayV21.php";
    require_once "../payments/SimplePayV21CardStorage.php";
    
    $processed_count = 0;
    $successful_count = 0;
    $failed_count = 0;
    
    foreach ($payments as $payment) {
        // Check the previous payment's date
        $previous_date = $wpdb->get_var($wpdb->prepare(
            "SELECT transaction_time
                FROM {$wpdb->prefix}furik_transactions
                WHERE transaction_time IS NOT NULL
                    AND (id = %d OR parent = %d)
                ORDER BY id DESC
                LIMIT 1",
            $payment->parent,
            $payment->parent
        ));
        
        if (!$previous_date) {
            error_log("Furik: Skipping payment ID {$payment->id} - no previous transaction date found");
            continue;
        }
        
        // Only process if previous payment was at least 25 days ago
        $time_diff = time() - strtotime($previous_date);
        if ($time_diff < 60 * 60 * 24 * 25) {
            error_log("Furik: Skipping payment ID {$payment->id} - previous payment was less than 25 days ago");
            continue;
        }
        
        // Process the payment
        try {
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
                $successful_count++;
                error_log("Furik: Successfully processed recurring payment ID {$payment->id}");
            } else {
                $failed_count++;
                $failureCode = isset($returnData['errorCodes']) ? $returnData['errorCodes'][0] : 'unknown';
                
                // Error codes 2063 and 2072 indicate card issues that will cause future payments to fail
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
                    
                    error_log("Furik: Recurring payment failed with code {$failureCode}. Cancelling all future payments for payment ID {$payment->id}");
                }
            }
            
            $processed_count++;
            
        } catch (Exception $e) {
            error_log("Furik: Error processing recurring payment ID {$payment->id}: " . $e->getMessage());
        }
        
        // Small delay between API calls to avoid overwhelming the payment provider
        sleep(2);
    }
    
    error_log("Furik: Completed scheduled recurring payment processing. Processed: {$processed_count}, Successful: {$successful_count}, Failed: {$failed_count}");
}
add_action('furik_process_recurring_payments', 'furik_cron_process_recurring_payments');

/**
 * Function to get upcoming recurring payments in the next X days
 */
function furik_get_upcoming_recurring_payments($days = 5) {
    global $wpdb;
    
    $date_limit = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    
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
                AND rec.transaction_status = " . FURIK_STATUS_FUTURE . "
                AND ptr.transaction_status IN (1, 10)
            ORDER BY rec.time ASC";
    
    return $wpdb->get_results($wpdb->prepare($sql, $date_limit));
}
