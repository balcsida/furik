<?php
define("FURIK_STATUS_UNKNOWN", 0);
define("FURIK_STATUS_SUCCESSFUL", 1);
define("FURIK_STATUS_UNSUCCESSFUL", 2);
define("FURIK_STATUS_CANCELLED", 3);
define("FURIK_STATUS_TRANSFER_ADDED", 4);
define("FURIK_STATUS_CASH_ADDED", 5);
define("FURIK_STATUS_FUTURE", 6);
define("FURIK_STATUS_IPN_SUCCESSFUL", 10);
define("FURIK_STATUS_RECURRING_FAILED", 11);
define("FURIK_STATUS_DISPLAYABLE", "1, 10");

define("FURIK_TRANSACTION_TYPE_SIMPLEPAY", 0);
define("FURIK_TRANSACTION_TYPE_TRANSFER", 1);
define("FURIK_TRANSACTION_TYPE_CASH", 2);
define("FURIK_TRANSACTION_TYPE_RECURRING", 3);

function furik_get_transaction($order_ref) {
	global $wpdb;

	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}furik_transactions WHERE transaction_id=%s", $order_ref));
}

function furik_get_post_id_from_order_ref($order_ref) {
	global $wpdb;

	return $wpdb->get_var($wpdb->prepare("SELECT campaign FROM {$wpdb->prefix}furik_transactions WHERE transaction_id=%s", $order_ref));
}

function furik_install() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$sql_transactions = "CREATE TABLE {$wpdb->prefix}furik_transactions (
		id int NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		transaction_id varchar(100) NOT NULL,
		transaction_type int DEFAULT 0,
		name varchar(255),
		anon int,
		email varchar(255),
		amount int,
		campaign int,
		message longtext,
		transaction_status int,
		vendor_ref varchar(255),
		recurring int,
		parent int,
		token varchar(255),
		token_validity datetime,
		PRIMARY KEY  (id)
	) $charset_collate;";

	$sql_transaction_log = "CREATE TABLE {$wpdb->prefix}furik_transaction_log (
		id int NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		transaction_id varchar(100) NOT NULL,
		message text,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_transactions);
	dbDelta($sql_transaction_log);

	add_option('furik_db_version', 1);
}

function furik_progress($campaign_id, $amount = 0) {
	global $wpdb;

	$return = array();

    $post = get_post($campaign_id);

    if (!$amount) {
		$meta = get_post_custom($post->ID);
		if (is_numeric($meta['GOAL'][0])) {
			$amount = $meta['GOAL'][0];
		}
    }
    $campaigns = get_posts(['post_parent' => $post->ID, 'post_type' => 'campaign', 'numberposts' => 100]);
    $ids = array();
    $ids[] = $post->ID;

    foreach ($campaigns as $campaign) {
		$ids[] = $campaign->ID;
    }
    $id_list = implode($ids, ",");

    $sql = "SELECT
			sum(amount)
		FROM
			{$wpdb->prefix}furik_transactions AS transaction
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (transaction.campaign=campaigns.ID)
		WHERE campaigns.ID in ($id_list)
			AND transaction.transaction_status in (".FURIK_STATUS_DISPLAYABLE.")
		ORDER BY time DESC";

	$result = $wpdb->get_var($sql);

	$return['collected'] = $result;

	if ($amount > 0) {
		$return['goal'] = $amount;
		$percentage = $return['percentage'] = round(1.0 * $result/$amount*100);
		$return['progress_bar'] = "<div class=\"furik-progress-bar\"><span style=\"width: " . ($percentage > 100 ? 100 : $percentage) . "%\"></span></div>";
	}

    return $return;
}

function furik_transaction_log($transaction_id, $message) {
	global $wpdb;

	$wpdb->insert(
		"{$wpdb->prefix}furik_transaction_log",
		array(
			'time' => current_time( 'mysql' ),
			'transaction_id' => $transaction_id,
			'message' => $message
		)
	);
}

function furik_update_transaction_status($order_ref, $status, $vendor_ref = "") {
	global $wpdb;

	$table_name = $wpdb->prefix . 'furik_transactions';
	$update = array("transaction_status" => $status);
	if ($vendor_ref) {
		$update["vendor_ref"] = $vendor_ref;
	}
	$wpdb->update(
		$table_name,
		$update,
		array("transaction_id" => $order_ref)
	);
}