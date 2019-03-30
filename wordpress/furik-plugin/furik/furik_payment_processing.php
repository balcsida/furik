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
	global $furik_payment_successful_url, $furik_payment_unsuccessful_url;
	require_once 'patched_SimplePayment.class.php';

	$backref = new SimpleBackRef(furik_get_simple_config(), "HUF");
	$backref->order_ref = (isset($_REQUEST['order_ref'])) ? $_REQUEST['order_ref'] : 'N/A';

	if ($backref->checkResponse()){
		furik_update_transaction_status($backref->order_ref, FURIK_STATUS_SUCCESSFUL);
		header("Location: $furik_payment_successful_url");
	}
	else {
		furik_update_transaction_status($backref->order_ref, FURIK_STATUS_UNSUCCESSFUL);
		header("Location: $furik_payment_unsuccessful_url");
	}
	die();
}

/**
 * Prepares an automatic redirect link to SimplePay with the posted data
 */
function furik_redirect() {
	global $wpdb;

	require_once 'patched_SimplePayment.class.php';

	$amount = is_numeric($_POST['furik_form_amount']) && $_POST['furik_form_amount'] > 0 ? $_POST['furik_form_amount'] : die("Error: amount is not a number.");
	$email = $_POST['furik_form_email'];

	$orderCurrency = 'HUF';
	$transactionId = str_replace(array('.', ':'), "", $_SERVER['SERVER_ADDR']) . @date("U", time()) . rand(1000, 9999);

	$lu = new SimpleLiveUpdate(furik_get_simple_config(), $orderCurrency);
	$lu->setField("ORDER_REF", $transactionId);
	$lu->setField("LANGUAGE", "HU");
	$lu->addProduct(array(
	    'name' => 'Adomány',
	    'code' => 'sku0001',
	    'info' => 'Az alapítvány támogatása',
	    'price' => $amount,
	    'vat' => 0,
	    'qty' => 1
	));
	$lu->setField("BILL_EMAIL", "sdk_test@otpmobil.com"); 
	$display = $lu->createHtmlForm('SimplePayForm', 'auto', "Átirányítás a SimplePay oldalára");
	echo $display;

	$table_name = $wpdb->prefix . 'furik_transactions';

	$wpdb->insert(
		$table_name,
		array(
			'time' => current_time( 'mysql' ),
			'transaction_id' => $transactionId,
			'email' => $email,
			'amount' => $amount
		)
	);
	die("Redirecting to Simple Pay");
}

/**
 * Prepares SimplePay SDK configuration based on plugin configuration
 */
function furik_get_simple_config() {
	global
		$furik_payment_merchant,
		$furik_payment_secret_key,
		$furik_production_system;

	$config = array(
	    'HUF_MERCHANT' => $furik_payment_merchant,
	    'HUF_SECRET_KEY' => $furik_payment_secret_key,
	    'CURL' => true,
	    'SANDBOX' => !$furik_production_system,
	    'PROTOCOL' => 'http',			//http or https

	    'BACK_REF' => $_SERVER['HTTP_HOST'] . '/wordpress/',		   //url of payment backref page
	    'TIMEOUT_URL' => $_SERVER['HTTP_HOST'] . '/timeout.php',     //url of payment timeout page
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

if ($_POST['furik_action'] == "redirect") {
	furik_redirect();
}

if (isset($_GET['order_ref'])) {
	furik_process_payment();
}

if (isset($_GET['furik_process_ipn'])) {
	furik_process_ipn();
}