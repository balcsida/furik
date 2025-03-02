<?php
function furik_cancel_recurring( $vendor_ref ) {
	global $furik_payment_merchant;

	require_once '../payments/SimplePayV21.php';
	require_once '../payments/SimplePayV21CardStorage.php';

	$trx = new SimplePayCardCancel();
	$trx->addConfig( furik_get_simple_config() );
	$trx->addConfigData( 'merchantAccount', $furik_payment_merchant );

	$trx->runCardCancel();
	$trx->addData( 'cardId', $vendor_ref );
	$trx->runCardCancel();
}
/**
 * Processes IPN messages from Simple
 */
function furik_process_ipn() {
	require_once '../payments/SimplePayV21.php';

	$trx = new SimplePayIpn();
	$trx->addConfig( furik_get_simple_config() );

	$json = file_get_contents( 'php://input' );

	if ( $trx->isIpnSignatureCheck( $json ) ) {
		$content = json_decode( $trx->checkOrSetToJson( $json ), true );
		furik_update_transaction_status( $content['orderRef'], FURIK_STATUS_IPN_SUCCESSFUL, $content['transactionId'] );
		$trx->runIpnConfirm();
		die();
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

	require_once '../payments/SimplePayV21.php';

	$trx = new SimplePayBack();
	$trx->addConfig( furik_get_simple_config() );

	$result = array();
	if ( isset( $_REQUEST['r'] ) && isset( $_REQUEST['s'] ) ) {
		if ( $trx->isBackSignatureCheck( $_REQUEST['r'], $_REQUEST['s'] ) ) {
			$result = $trx->getRawNotification();
		}
	}

	$order_ref = $result['o'];

	$campaign_id = furik_get_post_id_from_order_ref( $order_ref );
	$url_config  = array(
		'campaign_id'     => $campaign_id,
		'furik_order_ref' => $order_ref,
		'furik_check'     => furik_order_sign( $order_ref ),
	);

	$vendor_ref = $result['t'];

	if ( $result['e'] == 'SUCCESS' ) {
		furik_update_transaction_status( $order_ref, FURIK_STATUS_SUCCESSFUL, $vendor_ref );
		furik_send_email_for_order( $order_ref );
		header( 'Location: ' . furik_url( $furik_payment_successful_url, $url_config ) );
	} elseif ( $result['e'] == 'CANCEL' ) {
		furik_update_transaction_status( $order_ref, FURIK_STATUS_CANCELLED, $vendor_ref );
		header( 'Location: ' . furik_url( $furik_payment_timeout_url, $url_config ) );
	} else { // FAIL
		furik_update_transaction_status( $order_ref, FURIK_STATUS_UNSUCCESSFUL, $vendor_ref );
		header( 'Location: ' . furik_url( $furik_payment_unsuccessful_url, $url_config ) );
	}

	die();
}

/**
 * Prepares an automatic redirect link to SimplePay with the posted data
 */
function furik_process_payment_form() {
	global $wpdb, $furik_name_order_eastern, $furik_production_system;

	if ( ! $_POST['furik_form_accept'] ) {
		_e( 'Please accept the data transmission agreement.', 'furik' );
		die();
	}

	$amount_field = 'furik_form_amount';
	if ( $_POST['furik_form_amount'] == 'other' ) {
		$amount_field = 'furik_form_amount_other';
	}

	$amount = is_numeric( $_POST[ $amount_field ] ) && $_POST[ $amount_field ] > 0 ? $_POST[ $amount_field ] : die( 'Error: amount is not a number.' );
	if ( ! furik_extra_field_enabled( 'name_separation' ) ) {
		$name = $_POST['furik_form_name'];
	} else {
		$first_name = $_POST['furik_form_first_name'];
		$last_name  = $_POST['furik_form_last_name'];

		$name = $furik_name_order_eastern ? "$last_name $first_name" : "$first_name $last_name";
	}
	$anon  = $_POST['furik_form_anon'] ? 1 : 0;
	$email = $_POST['furik_form_email'];

	if ( furik_extra_field_enabled( 'phone_number' ) ) {
		$phone_number = $_POST['furik_form_phone_number'];
	}

	$message     = $_POST['furik_form_message'];
	$campaign_id = is_numeric( $_POST['furik_campaign'] ) ? $_POST['furik_campaign'] : 0;
	$campaign    = $campaign_id > 0 ? get_post( $campaign_id ) : null;
	$type        = furik_numr( 'furik_form_type' );
	$recurring   = furik_numr( 'furik_form_recurring' );
	$newsletter  = $_POST['furik_form_newsletter'] ? 1 : 0;

	if ( $recurring ) {
		if ( $type == 0 ) {
			$type = FURIK_TRANSACTION_TYPE_RECURRING_REG;
		} elseif ( $type == 1 ) {
			$type = FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG;
		}
	}

	$wpdb->insert(
		"{$wpdb->prefix}furik_transactions",
		array(
			'time'              => current_time( 'mysql' ),
			'transaction_time'  => current_time( 'mysql' ),
			'transaction_type'  => $type,
			'production_system' => $furik_production_system ? 1 : 0,
			'name'              => $name,
			'first_name'        => $first_name,
			'last_name'         => $last_name,
			'anon'              => $anon,
			'email'             => $email,
			'phone_number'      => $phone_number,
			'message'           => $message,
			'amount'            => $amount,
			'campaign'          => $campaign_id,
			'recurring'         => $recurring,
			'newsletter_status' => $newsletter,
		)
	);

	$local_id = $wpdb->insert_id;

	$transactionId = furik_transaction_id( $local_id );

	$wpdb->update(
		"{$wpdb->prefix}furik_transactions",
		array( 'transaction_id' => $transactionId ),
		array( 'id' => $local_id )
	);

	$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}furik_transactions WHERE transaction_id = %s", $transactionId ), OBJECT );

	if ( count( $results ) != 1 ) {
		die( __( 'Database error. Please contact the site administrator.', 'furik' ) );
	}

	if ( ( $type == FURIK_TRANSACTION_TYPE_SIMPLEPAY ) || ( $type == FURIK_TRANSACTION_TYPE_RECURRING_REG ) ) {
		furik_prepare_simplepay_redirect( $local_id, $transactionId, $campaign, $amount, $email, $type == FURIK_TRANSACTION_TYPE_RECURRING_REG, $name );
	} elseif ( ( $type == FURIK_TRANSACTION_TYPE_TRANSFER ) || ( $type == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG ) ) {
		furik_redirect_to_transfer_page( $transactionId );
	} elseif ( $type == FURIK_TRANSACTION_TYPE_CASH ) {
		furik_redirect_to_thank_you_cash( $transactionId );
	}
}

function furik_process_recurring() {
	global $wpdb;

	require_once '../payments/SimplePayV21.php';
	require_once '../payments/SimplePayV21CardStorage.php';

	$sql = "SELECT rec.*
		FROM
			{$wpdb->prefix}furik_transactions AS rec
			JOIN {$wpdb->prefix}furik_transactions AS ptr ON (rec.parent=ptr.id)
		WHERE rec.time <= now()
			AND rec.transaction_status in (" . FURIK_STATUS_FUTURE . ')
			AND ptr.transaction_status in (1, 10)
		ORDER BY time DESC';

	$result = $wpdb->get_results( $sql );

	$count = 0;

	echo '<pre>';

	foreach ( $result as $payment ) {
		++$count;

		if ( isset( $_GET['n'] ) && $count > $_GET['n'] ) {
			echo "Skipping other payments due to reaching the limit.\n";

			break;
		}
		echo $payment->id . ' ' . $payment->amount . ' ' . $payment->time;

		$previous_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT transaction_time
				FROM {$wpdb->prefix}furik_transactions
				WHERE transaction_time is not null
					AND (id = $payment->parent OR parent = $payment->parent)
				ORDER BY id DESC
				LIMIT 1
			"
			)
		);

		if ( ! $previous_date ) {
			echo " &lt;- There was no previous transaction date, skipping the previous item on the list.\n";

			continue;
		}

		$time_diff = time() - strtotime( $previous_date );

		if ( $time_diff < 60 * 60 * 24 * 25 ) {
			echo " &lt;- is skipped as the previous payment was not done more than 25 days ago.\n";

			continue;
		}

		if ( isset( $_GET['runpayments'] ) ) {
			$trx = new SimplePayDorecurring();

			$trx->addConfig( furik_get_simple_config() );
			$trx->addData( 'orderRef', $payment->transaction_id );
			$trx->addData( 'methods', array( 'CARD' ) );
			$trx->addData( 'currency', 'HUF' );
			$trx->addData( 'total', $payment->amount );
			$trx->addData( 'customerEmail', $payment->email );
			$trx->addData( 'token', $payment->token );
			$trx->runDorecurring();

			$returnData = $trx->getReturnData();

			furik_transaction_log( $payment->transaction_id, serialize( $returnData ) );

			$newStatus = $returnData['total'] > 0 ? FURIK_STATUS_SUCCESSFUL : FURIK_STATUS_RECURRING_FAILED;

			echo ' new status: ' . $newStatus;

			$wpdb->update(
				"{$wpdb->prefix}furik_transactions",
				array(
					'transaction_status' => $newStatus,
					'transaction_time'   => date( 'Y-m-d H:i:s' ),
				),
				array( 'id' => $payment->id )
			);

			if ( $newStatus == FURIK_STATUS_RECURRING_FAILED ) {
				$failureCode = $returnData['errorCodes'][0];
				if ( $failureCode == 2063 || $failureCode == 2072 ) {
					$wpdb->update(
						"{$wpdb->prefix}furik_transactions",
						array(
							'transaction_status' => FURIK_STATUS_RECURRING_PAST_FAILED,
							'transaction_time'   => date( 'Y-m-d H:i:s' ),
						),
						array(
							'parent'             => $payment->parent,
							'transaction_status' => FURIK_STATUS_FUTURE,
						)
					);
				}
			}
		}

		echo "\n";
	}

	die( 'Query finished.' );
}

function furik_prepare_simplepay_redirect( $local_id, $transactionId, $campaign, $amount, $email, $recurring = false, $name ) {
	global $wpdb, $furik_simplepay_ask_for_invoice_information, $furik_production_system;

	require_once '../payments/SimplePayV21.php';

	$lu = new SimplePayStart();

	$config = furik_get_simple_config();
	$lu->addConfig( $config );

	$lu->addData( 'orderRef', $transactionId );
	$lu->addData( 'language', 'HU' );
	$lu->addData( 'currency', 'HUF' );
	$lu->addData( 'customerEmail', $email );
	$lu->addData( 'maySelectInvoice', $furik_simplepay_ask_for_invoice_information );
	$lu->addData( 'methods', array( 'CARD' ) );
	$lu->addData( 'total', $amount );
	$lu->addData( 'url', $config['URL'] );

	$token_validity = null;
	if ( $recurring ) {
		$token_validity = strtotime( '+729 days' );
		$until          = date( 'Y-m-d\TH:i:s+02:00', $token_validity );
		$lu->addGroupData( 'recurring', 'times', 24 );
		$lu->addGroupData( 'recurring', 'until', $until );
		$lu->addGroupData( 'recurring', 'maxAmount', $amount );
	}

	if ( $campaign ) {
		$lu->addItems(
			array(
				'title'       => "$campaign->post_title",
				'ref'         => "$campaign->ID",
				'description' => "$campaign->post_title",
				'price'       => $amount,
				'vat'         => 0,
				'amount'      => 1,
			)
		);
	} else {
		$lu->addItems(
			array(
				'title'       => __( 'General donation', 'furik' ),
				'ref'         => 0,
				'description' => __( 'General donation', 'furik' ),
				'price'       => $amount,
				'tax'         => 0,
				'amount'      => 1,
			)
		);
	}

	$lu->formDetails['element'] = 'auto';
	$lu->runStart();
	$lu->getHtmlForm();
	$returnData = $lu->getReturnData();

	$time      = time();
	$parent_id = $local_id;

	if ( isset( $returnData['tokens'] ) ) {
		foreach ( $returnData['tokens'] as $token ) {
			$time = strtotime( '+1 month', $time );

			$wpdb->insert(
				"{$wpdb->prefix}furik_transactions",
				array(
					'time'               => date( 'Y-m-d H:i:s', $time ),
					'transaction_type'   => FURIK_TRANSACTION_TYPE_RECURRING_AUTO,
					'production_system'  => $furik_production_system ? 1 : 0,
					'name'               => $name,
					'email'              => $email,
					'amount'             => $amount,
					'campaign'           => $campaign,
					'parent'             => $parent_id,
					'token'              => $token,
					'transaction_status' => FURIK_STATUS_FUTURE,
					'token_validity'     => date( 'Y-m-d H:i:s', $token_validity ),
				)
			);

			$local_id = $wpdb->insert_id;

			$transactionId = furik_transaction_id( $local_id );

			$wpdb->update(
				"{$wpdb->prefix}furik_transactions",
				array( 'transaction_id' => $transactionId ),
				array( 'id' => $local_id )
			);
		}
	}

	echo $lu->returnData['form'];

	die( __( 'Redirecting to the payment partner page', 'furik' ) );
}

function furik_redirect_to_transfer_page( $transactionId ) {
	global $furik_payment_transfer_url;
	$url_config = array(
		'campaign_id'     => $campaign_id,
		'furik_order_ref' => $transactionId,
		'furik_check'     => furik_order_sign( $transactionId ),
	);

	furik_update_transaction_status( $transactionId, FURIK_STATUS_TRANSFER_ADDED );
	header( 'Location: ' . furik_url( $furik_payment_transfer_url, $url_config ) );
	die();
}

function furik_redirect_to_thank_you_cash( $transactionId ) {
	global $furik_payment_cash_url;

	$url_config = array(
		'campaign_id'     => $campaign_id,
		'furik_order_ref' => $transactionId,
		'furik_check'     => furik_order_sign( $transactionId ),
	);

	furik_update_transaction_status( $transactionId, FURIK_STATUS_CASH_ADDED );
	header( 'Location: ' . furik_url( $furik_payment_cash_url, $url_config ) );
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
		'HUF_MERCHANT'   => $furik_payment_merchant,
		'HUF_SECRET_KEY' => $furik_payment_secret_key,
		'CURL'           => true,
		'SANDBOX'        => ! $furik_production_system,
		'PROTOCOL'       => $furik_homepage_https ? 'https' : 'http',         // http or https

		'URL'            => furik_url( '', array( 'furik_process_payment' => 1 ) ),
		'URLS_SUCCESS'   => furik_url( '', array( 'furik_process_payment' => 1 ) ),
		'URLS_FAILED'    => furik_url( '', array( 'furik_process_payment' => 1 ) ),
		'URLS_FAILED'    => furik_url( '', array( 'furik_process_payment' => 1 ) ),
		'URLS_CANCEL'    => furik_url(
			$furik_payment_timeout_url,
			array(
				'furik_process_payment' => 1,
				'furik_timeout'         => 1,
			)
		),
		'ORDER_TIMEOUT'  => 30,
		'IRN_BACK_URL'   => $_SERVER['HTTP_HOST'] . '/irn.php',        // url of payment irn page
		'IDN_BACK_URL'   => $_SERVER['HTTP_HOST'] . '/idn.php',        // url of payment idn page
		'IOS_BACK_URL'   => $_SERVER['HTTP_HOST'] . '/ios.php',        // url of payment idn page

		'GET_DATA'       => $_GET,
		'POST_DATA'      => $_POST,
		'SERVER_DATA'    => $_SERVER,

		'LOGGER'         => false,
	);

	return $config;
}

function furik_send_email_for_order( $order_ref ) {
	global $furik_email_thanks_enabled, $furik_email_send_recurring_only;

	if ( ! $furik_email_thanks_enabled ) {
		return;
	}

	$transaction = furik_get_transaction( $order_ref );

	if ( ( $transaction->transaction_type != FURIK_TRANSACTION_TYPE_SIMPLEPAY ) &&
		( $transaction->transaction_type != FURIK_TRANSACTION_TYPE_RECURRING_REG ) ) {

		return;
	}

	ob_start();

	if ( file_exists( __DIR__ . '/templates/custom_furik_email_regular_thanks.php' ) ) {
		include_once __DIR__ . '/templates/custom_furik_email_regular_thanks.php';
	} else {
		include_once __DIR__ . '/templates/furik_email_regular_thanks.php';
	}

	$body = ob_get_clean();

	if ( ! $furik_email_send_recurring_only || ! $transaction->recurring ) {
		furik_send_email( $furik_sender_address, $furik_sender_name, $transaction->email, $furik_email_subject, $body );
	}

	if ( $transaction->recurring ) {
		$random_password = furik_register_user( $transaction->email );

		if ( ! $random_password ) {
			$already_registered = true;
		}

		ob_start();

		if ( file_exists( __DIR__ . '/templates/custom_furik_email_recurring_information.php' ) ) {
			include_once __DIR__ . '/templates/custom_furik_email_recurring_information.php';
		} else {
			include_once __DIR__ . '/templates/furik_email_recurring_information.php';
		}

		$body = ob_get_clean();

		furik_send_email( $furik_sender_address, $furik_sender_name, $transaction->email, $furik_email_subject, $body );
	}
}

// Fix: Check if furik_action exists in $_POST before accessing it
if ( isset( $_POST['furik_action'] ) && $_POST['furik_action'] == 'process_payment_form' ) {
	furik_process_payment_form();
}

if ( isset( $_GET['furik_process_recurring'] ) && ( $_GET['furik_process_recurring'] == $furik_processing_recurring_secret ) ) {
	furik_process_recurring();
}

if ( isset( $_GET['furik_process_payment'] ) ) {
	add_action( 'init', 'furik_process_payment' );
}

if ( isset( $_GET['furik_process_ipn'] ) ) {
	furik_process_ipn();
}
