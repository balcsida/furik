<?php
/**
 * Processes IPN messages from Simple
 */
function furik_process_ipn() {
	require_once 'patched_SimplePayment.class.php';
	$ipn = new SimpleIpn(furik_get_simple_config(), "HUF");

	if ($ipn->validateReceived()) {
		furik_update_transaction_status($_POST['REFNOEXT'], FURIK_STATUS_IPN_SUCCESSFUL);
		$ipn->confirmReceived();
	}
}

/**
 * Processes payment information which is provided right after the visitor filled the SimplePay form.
 */
function furik_process_payment() {
	global
		$furik_homepage_https,
		$furik_homepage_url,
		$furik_payment_successful_url,
		$furik_payment_timeout_url,
		$furik_payment_unsuccessful_url;

	require_once 'patched_SimplePayment.class.php';

	$backref = new SimpleBackRef(furik_get_simple_config(), "HUF");
	$backref->order_ref = (isset($_REQUEST['order_ref'])) ? $_REQUEST['order_ref'] : 'N/A';

	$campaign_id = furik_get_post_id_from_order_ref($backref->order_ref);
	$url_config = [
		'campaign_id' => $campaign_id,
		'furik_order_ref' => $backref->order_ref,
		'furik_check' => furik_order_sign($backref->order_ref)
	];

	$vendor_ref = $backref->backStatusArray['PAYREFNO'];

	if ($backref->checkResponse()){
		furik_update_transaction_status($backref->order_ref, FURIK_STATUS_SUCCESSFUL, $vendor_ref);
		header("Location: " . furik_url($furik_payment_successful_url, $url_config));
	}
	elseif ($_REQUEST['furik_timeout']) {
		furik_update_transaction_status($backref->order_ref, FURIK_STATUS_CANCELLED, $vendor_ref);
		header("Location: " . furik_url($furik_payment_timeout_url, $url_config));
	}
	else {
		furik_update_transaction_status($backref->order_ref, FURIK_STATUS_UNSUCCESSFUL, $vendor_ref);
		header("Location: " . furik_url($furik_payment_unsuccessful_url, $url_config));
	}
	die();
}

/**
 * Prepares an automatic redirect link to SimplePay with the posted data
 */
function furik_process_payment_form() {
	global $wpdb;

	if (!$_POST['furik_form_accept']) {
		_e('Please accept the data transmission agreement.', 'furik');
		die();
	}

	$amount = is_numeric($_POST['furik_form_amount']) && $_POST['furik_form_amount'] > 0 ? $_POST['furik_form_amount'] : die("Error: amount is not a number.");
	$name = $_POST['furik_form_name'];
	$anon = $_POST['furik_form_anon'] ? 1 : 0;
	$email = $_POST['furik_form_email'];
	$message = $_POST['furik_form_message'];
	$campaign_id = is_numeric($_POST['furik_campaign']) ? $_POST['furik_campaign'] : 0;
	$campaign = get_post($campaign_id);
	$type = furik_numr("furik_form_type");

	$wpdb->insert(
		"{$wpdb->prefix}furik_transactions",
		array(
			'time' => current_time( 'mysql' ),
			'transaction_type' => $type,
			'name' => $name,
			'anon' => $anon,
			'email' => $email,
			'message' => $message,
			'amount' => $amount,
			'campaign' => $campaign_id
		)
	);

	$local_id = $wpdb->insert_id;

	$transactionId = substr(md5($_SERVER['SERVER_ADDR']), 0, 4) . '-' . $local_id;

	$wpdb->update(
		"{$wpdb->prefix}furik_transactions",
		array("transaction_id" => $transactionId),
		array("id" => $local_id)
	);

	$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}furik_transactions WHERE transaction_id = %s", $transactionId), OBJECT);

	if (count($results) != 1) {
		die(__('Database error. Please contact the site administrator.', 'furik'));
	}

	if ($type == 0) {
		furik_prepare_simplepay_redirect($transactionId, $campaign, $amount, $email);
	}
	elseif ($type == 1) {
		furik_redirect_to_transfer_page($transactionId);
	}
	elseif ($type == 2) {
		furik_redirect_to_thank_you_cash($transactionId);
	}
}

function furik_prepare_simplepay_redirect($transactionId, $campaign, $amount, $email) {
	require_once 'patched_SimplePayment.class.php';

	$lu = new SimpleLiveUpdate(furik_get_simple_config(), 'HUF');
	$lu->setField("ORDER_REF", $transactionId);
	$lu->setField("LANGUAGE", "HU");
	$lu->addProduct(array(
	    'name' => "$campaign->post_title",
	    'code' => "$campaign->post_id",
	    'info' => "$campaign->post_title",
	    'price' => $amount,
	    'vat' => 0,
	    'qty' => 1
	));

	$lu->setField("BILL_EMAIL", $email);
	$display = $lu->createHtmlForm('SimplePayForm', 'auto', __('Redirecting to the payment partner page', 'furik'));

	echo $display;

	die(__('Redirecting to the payment partner page', 'furik'));
}

function furik_redirect_to_transfer_page($transactionId) {
	global $furik_payment_transfer_url;

	header("Location: " . furik_url($furik_payment_transfer_url, ['transactionId' => $transactionId]));
	die();
}

function furik_redirect_to_thank_you_cash($transactionId) {
	global $furik_payment_cash_url;

	header("Location: " . furik_url($furik_payment_cash_url, ['transactionId' => $transactionId]));
	die();
}

/**
 * Prepares SimplePay SDK configuration based on plugin configuration
 */
function furik_get_simple_config() {
	global
		$furik_homepage_https,
		$furik_homepage_url,
		$furik_payment_merchant,
		$furik_payment_secret_key,
		$furik_payment_timeout_url,
		$furik_production_system;

	$config = array(
	    'HUF_MERCHANT' => $furik_payment_merchant,
	    'HUF_SECRET_KEY' => $furik_payment_secret_key,
	    'CURL' => true,
	    'SANDBOX' => !$furik_production_system,
	    'PROTOCOL' => $furik_homepage_https ? 'https' : 'http',			//http or https

	    'BACK_REF' => furik_url('', ['furik_process_payment' => 1], false),
	    'TIMEOUT_URL' => furik_url($furik_payment_timeout_url, ['furik_process_payment' => 1, 'furik_timeout' => 1], false),
	    'ORDER_TIMEOUT' => 30,
	    'IRN_BACK_URL' => $_SERVER['HTTP_HOST'] . '/irn.php',        //url of payment irn page
	    'IDN_BACK_URL' => $_SERVER['HTTP_HOST'] . '/idn.php',        //url of payment idn page
	    'IOS_BACK_URL' => $_SERVER['HTTP_HOST'] . '/ios.php',        //url of payment idn page

	    'GET_DATA' => $_GET,
	    'POST_DATA' => $_POST,
	    'SERVER_DATA' => $_SERVER,

	    'LOGGER' => false,                                   //basic transaction log
	    'LOG_PATH' => 'log',  								//path of log file

		'DEBUG_LIVEUPDATE_PAGE' => false,					//Debug message on demo LiveUpdate page (only for development purpose)
		'DEBUG_LIVEUPDATE' => false,						//LiveUpdate debug into log file
		'DEBUG_BACKREF' => false,							//BackRef debug into log file
		'DEBUG_IPN' => false,								//IPN debug into log file
		'DEBUG_IRN' => false,								//IRN debug into log file
		'DEBUG_IDN' => false,								//IDN debug into log file
		'DEBUG_IOS' => false,								//IOS debug into log file
		'DEBUG_ONECLICK' => false,							//OneClick debug into log file
		'DEBUG_ALU' => false,								//ALU debug into log file
	);

	return $config;
}

if ($_POST['furik_action'] == "process_payment_form") {
	furik_process_payment_form();
}

if (isset($_GET['furik_process_payment'])) {
	furik_process_payment();
}

if (isset($_GET['furik_process_ipn'])) {
	furik_process_ipn();
}