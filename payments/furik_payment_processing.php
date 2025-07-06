<?php
/**
 * Verify reCAPTCHA v3 token
 *
 * @param string $token The reCAPTCHA token from the client
 * @return array|false Returns array with success and score, or false on error
 */
function furik_verify_recaptcha( $token ) {
	global $furik_recaptcha_secret_key;

	if ( empty( $furik_recaptcha_secret_key ) || empty( $token ) ) {
		return false;
	}

	$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

	$response = wp_remote_post( $verify_url, array(
		'body' => array(
			'secret' => $furik_recaptcha_secret_key,
			'response' => $token,
			'remoteip' => $_SERVER['REMOTE_ADDR']
		)
	));

	if ( is_wp_error( $response ) ) {
		error_log( 'Furik reCAPTCHA error: ' . $response->get_error_message() );
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$result = json_decode( $body, true );

	if ( ! isset( $result['success'] ) || ! $result['success'] ) {
		error_log( 'Furik reCAPTCHA verification failed: ' . print_r( $result, true ) );
		return false;
	}

	return array(
		'success' => true,
		'score' => isset( $result['score'] ) ? floatval( $result['score'] ) : 0.0,
		'action' => isset( $result['action'] ) ? $result['action'] : '',
		'challenge_ts' => isset( $result['challenge_ts'] ) ? $result['challenge_ts'] : ''
	);
}

function furik_cancel_recurring( $vendor_ref ) {
	global $furik_payment_merchant;

	require_once 'SimplePayV21.php';
	require_once 'SimplePayV21CardStorage.php';

	$trx = new SimplePayCardCancel();
	$trx->addConfig( furik_get_simple_config() );
	$trx->addConfigData( 'merchantAccount', $furik_payment_merchant );

	$trx->runCardCancel();
	$trx->addData( 'cardId', $vendor_ref );
	$trx->runCardCancel();
}
/**
 * Processes IPN messages from SimplePay
 * 
 * This function handles various payment statuses from SimplePay IPN:
 * - FINISHED: Successful charge
 * - AUTHORISED: Two-step payment authorization  
 * - REFUND: Refund of charged amount
 * - REVERSED: Two-step payment release
 * - CANCELLED: Interrupted payment
 * - TIMEOUT: Timeout case
 */
function furik_process_ipn() {
	require_once 'SimplePayV21.php';

	$trx = new SimplePayIpn();
	$trx->addConfig( furik_get_simple_config() );

	$json = file_get_contents( 'php://input' );

	if ( $trx->isIpnSignatureCheck( $json ) ) {
		$content = json_decode( $trx->checkOrSetToJson( $json ), true );
		
		// Map SimplePay IPN status to Furik status
		$furik_status = FURIK_STATUS_UNKNOWN; // Default to unknown
		
		if ( isset( $content['status'] ) ) {
			switch ( $content['status'] ) {
				case 'FINISHED':
					// Only update to successful if not already in a final state
					$current_transaction = furik_get_transaction( $content['orderRef'] );
					if ( $current_transaction && 
					     $current_transaction->transaction_status != FURIK_STATUS_SUCCESSFUL &&
					     $current_transaction->transaction_status != FURIK_STATUS_IPN_SUCCESSFUL ) {
						$furik_status = FURIK_STATUS_IPN_SUCCESSFUL;
						// Send confirmation email for successful payment
						furik_send_email_for_order( $content['orderRef'] );
					}
					break;
					
				case 'AUTHORISED':
					// For two-step payments - could be treated as successful or pending
					// Using IPN_SUCCESSFUL as it indicates the authorization was successful
					$furik_status = FURIK_STATUS_IPN_SUCCESSFUL;
					break;
					
				case 'CANCELLED':
					$furik_status = FURIK_STATUS_CANCELLED;
					break;
					
				case 'TIMEOUT':
					// Timeout is similar to cancelled - payment didn't complete
					$furik_status = FURIK_STATUS_CANCELLED;
					break;
					
				case 'REFUND':
					// Log the refund but don't change the original transaction status
					// You might want to add a separate refund tracking mechanism
					furik_transaction_log( $content['orderRef'], 'IPN Refund notification received' );
					// Don't update the main transaction status for refunds
					$furik_status = null;
					break;
					
				case 'REVERSED':
					// For reversed two-step payments
					$furik_status = FURIK_STATUS_UNSUCCESSFUL;
					break;
					
				default:
					// Unknown status - log it but don't update
					furik_transaction_log( $content['orderRef'], 'Unknown IPN status received: ' . $content['status'] );
					$furik_status = null;
					break;
			}
			
			// Update transaction status if we have a valid status to set
			if ( $furik_status !== null ) {
				furik_update_transaction_status( 
					$content['orderRef'], 
					$furik_status, 
					isset( $content['transactionId'] ) ? $content['transactionId'] : ''
				);
			}
			
			// Log the IPN message for debugging
			furik_transaction_log( 
				$content['orderRef'], 
				'IPN received with status: ' . $content['status'] . ' - Full content: ' . $json 
			);
		}
		
		// Always confirm IPN receipt to SimplePay
		$trx->runIpnConfirm();
		die();
	}
	
	// If signature check fails, log it
	if ( isset( $content['orderRef'] ) ) {
		furik_transaction_log( $content['orderRef'], 'IPN signature verification failed' );
	}
	
	// Return 400 Bad Request for invalid IPN
	http_response_code( 400 );
	die( 'Invalid IPN' );
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

	require_once 'SimplePayV21.php';

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
	global $wpdb, $furik_name_order_eastern, $furik_production_system,
	       $furik_recaptcha_enabled, $furik_recaptcha_threshold;

	if ( ! isset( $_POST['furik_form_accept'] ) || ! $_POST['furik_form_accept'] ) {
		_e( 'Please accept the data transmission agreement.', 'furik' );
		die();
	}

	// Verify reCAPTCHA if enabled
	$recaptcha_score = null;
	if ( $furik_recaptcha_enabled ) {
		$recaptcha_token = isset( $_POST['furik_recaptcha_token'] ) ? sanitize_text_field( $_POST['furik_recaptcha_token'] ) : '';

		if ( empty( $recaptcha_token ) ) {
			wp_die(
				__( 'Security verification failed. Please enable JavaScript and try again.', 'furik' ),
				__( 'Verification Failed', 'furik' ),
				array( 'response' => 400 )
			);
		}

		$recaptcha_result = furik_verify_recaptcha( $recaptcha_token );

		if ( ! $recaptcha_result ) {
			wp_die(
				__( 'Security verification failed. Please try again later.', 'furik' ),
				__( 'Verification Failed', 'furik' ),
				array( 'response' => 400 )
			);
		}

		$recaptcha_score = $recaptcha_result['score'];

		// Check if score is below threshold
		if ( $recaptcha_score < $furik_recaptcha_threshold ) {
			// Log suspicious activity
			error_log( sprintf(
				'Furik: Low reCAPTCHA score detected. Score: %f, Threshold: %f, IP: %s, Email: %s',
				$recaptcha_score,
				$furik_recaptcha_threshold,
				$_SERVER['REMOTE_ADDR'],
				isset( $_POST['furik_form_email'] ) ? $_POST['furik_form_email'] : 'unknown'
			));

			// Challenge the user - show a friendly message
			wp_die(
				sprintf(
					__( 'We need to verify that you are human. Your verification score (%s) is below our threshold (%s). This might happen if you are using a VPN, privacy tools, or automated software. Please try again, or contact us directly if you continue to have issues.', 'furik' ),
					number_format( $recaptcha_score, 2 ),
					number_format( $furik_recaptcha_threshold, 2 )
				),
				__( 'Additional Verification Required', 'furik' ),
				array(
					'response' => 403,
					'back_link' => true
				)
			);
		}
	}

	$amount_field = 'furik_form_amount';
	if ( isset( $_POST['furik_form_amount'] ) && $_POST['furik_form_amount'] == 'other' ) {
		$amount_field = 'furik_form_amount_other';
	}

	$amount = ( isset( $_POST[ $amount_field ] ) && is_numeric( $_POST[ $amount_field ] ) && $_POST[ $amount_field ] > 0 ) ? $_POST[ $amount_field ] : die( 'Error: amount is not a number.' );

	if ( ! furik_extra_field_enabled( 'name_separation' ) ) {
		$name = isset( $_POST['furik_form_name'] ) ? $_POST['furik_form_name'] : '';
		$first_name = '';
		$last_name = '';
	} else {
		$first_name = isset( $_POST['furik_form_first_name'] ) ? $_POST['furik_form_first_name'] : '';
		$last_name  = isset( $_POST['furik_form_last_name'] ) ? $_POST['furik_form_last_name'] : '';

		$name = $furik_name_order_eastern ? "$last_name $first_name" : "$first_name $last_name";
	}

	$anon  = isset( $_POST['furik_form_anon'] ) && $_POST['furik_form_anon'] ? 1 : 0;
	$email = isset( $_POST['furik_form_email'] ) ? $_POST['furik_form_email'] : '';

	$phone_number = '';
	if ( furik_extra_field_enabled( 'phone_number' ) ) {
		$phone_number = isset( $_POST['furik_form_phone_number'] ) ? $_POST['furik_form_phone_number'] : '';
	}

	$message     = isset( $_POST['furik_form_message'] ) ? $_POST['furik_form_message'] : '';
	$campaign_id = ( isset( $_POST['furik_campaign'] ) && is_numeric( $_POST['furik_campaign'] ) ) ? $_POST['furik_campaign'] : 0;
	$campaign    = $campaign_id > 0 ? get_post( $campaign_id ) : null;
	$type        = furik_numr( 'furik_form_type' );
	$recurring   = furik_numr( 'furik_form_recurring' );
	$newsletter  = isset( $_POST['furik_form_newsletter'] ) && $_POST['furik_form_newsletter'] ? 1 : 0;

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
			'recaptcha_score'   => $recaptcha_score,
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

	require_once 'SimplePayV21.php';
	require_once 'SimplePayV21CardStorage.php';

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

	require_once 'SimplePayV21.php';

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
		// Get the parent transaction's reCAPTCHA score to copy to future transactions
		$parent_recaptcha_score = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT recaptcha_score FROM {$wpdb->prefix}furik_transactions WHERE id = %d",
				$parent_id
			)
		);

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
					'campaign'           => $campaign ? $campaign->ID : 0,
					'parent'             => $parent_id,
					'token'              => $token,
					'transaction_status' => FURIK_STATUS_FUTURE,
					'token_validity'     => date( 'Y-m-d H:i:s', $token_validity ),
					'recaptcha_score'    => $parent_recaptcha_score, // Copy parent's reCAPTCHA score
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

	$campaign_id = furik_get_post_id_from_order_ref( $transactionId );
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

	$campaign_id = furik_get_post_id_from_order_ref( $transactionId );
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

/**
 * Core function to process recurring payments
 *
 * This function handles the actual processing logic and can be used by both
 * the batch tools interface and the cron job
 *
 * @param int    $limit          Maximum number of payments to process
 * @param int    $days_threshold Minimum days since last payment
 * @param bool   $dry_run        If true, only simulate processing without actual API calls
 * @param string $return_type    'html' for HTML output, 'array' for data return
 *
 * @return array|string          Processing results (HTML or array depending on return_type)
 */
function furik_process_recurring_payments($limit = 10, $days_threshold = 25, $dry_run = false, $return_type = 'array') {
    global $wpdb;

    require_once FURIK_PLUGIN_DIR . 'payments/SimplePayV21.php';
    require_once FURIK_PLUGIN_DIR . 'payments/SimplePayV21CardStorage.php';

    $limit = min(100, max(1, intval($limit)));
    $days_threshold = max(1, intval($days_threshold));

    // Get future recurring payments that are due
    $sql = "SELECT rec.*
        FROM
            {$wpdb->prefix}furik_transactions AS rec
            JOIN {$wpdb->prefix}furik_transactions AS ptr ON (rec.parent=ptr.id)
        WHERE rec.time <= now()
            AND rec.transaction_status in (" . FURIK_STATUS_FUTURE . ")
            AND ptr.transaction_status in (1, 10)
        ORDER BY time ASC
        LIMIT " . $limit;

    $payments = $wpdb->get_results($sql);

    // Initialize counters and results
    $results = array(
        'payments' => array(),
        'totals' => array(
            'total' => count($payments),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        )
    );

    // Process each payment
    foreach ($payments as $payment) {
        $payment_result = array(
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'scheduled_date' => $payment->time,
            'email' => $payment->email
        );

        // Get previous transaction date
        $previous_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT transaction_time
                FROM {$wpdb->prefix}furik_transactions
                WHERE transaction_time is not null
                    AND (id = %d OR parent = %d)
                ORDER BY id DESC
                LIMIT 1",
                $payment->parent,
                $payment->parent
            )
        );

        $payment_result['last_payment'] = $previous_date ?: 'N/A';

        if ($previous_date) {
            $time_diff = time() - strtotime($previous_date);
            $days_since_last = round($time_diff / (60 * 60 * 24));
            $payment_result['days_since_last'] = $days_since_last;

            if ($days_since_last < $days_threshold) {
                $payment_result['status'] = 'skipped';
                $payment_result['result'] = sprintf('Last payment was only %1$d days ago (threshold: %2$d days)',
                                                  $days_since_last, $days_threshold);
                $results['totals']['skipped']++;
            } else {
                $payment_result['status'] = $dry_run ? 'dry_run' : 'processing';

                if (!$dry_run) {
                    try {
                        // Process the payment through SimplePay
                        $trx = new SimplePayDorecurring();
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

                        // Update transaction status
                        $wpdb->update(
                            "{$wpdb->prefix}furik_transactions",
                            array(
                                'transaction_status' => $newStatus,
                                'transaction_time' => date('Y-m-d H:i:s'),
                            ),
                            array('id' => $payment->id)
                        );

                        if ($newStatus == FURIK_STATUS_SUCCESSFUL) {
                            $payment_result['status'] = 'success';
                            $payment_result['result'] = sprintf('Payment successful: %d HUF', $payment->amount);
                            $results['totals']['successful']++;
                        } else {
                            $payment_result['status'] = 'failed';
                            $payment_result['result'] = isset($returnData['errorCodes']) && !empty($returnData['errorCodes']) ?
                                    sprintf('Error code: %s', $returnData['errorCodes'][0]) :
                                    'Unknown error';
                            $results['totals']['failed']++;

                            // Handle failure types that require cancelling future transactions
                            if (isset($returnData['errorCodes']) &&
                                (in_array(2063, $returnData['errorCodes']) || in_array(2072, $returnData['errorCodes']))) {

                                $wpdb->update(
                                    "{$wpdb->prefix}furik_transactions",
                                    array(
                                        'transaction_status' => FURIK_STATUS_RECURRING_PAST_FAILED,
                                        'transaction_time' => date('Y-m-d H:i:s'),
                                    ),
                                    array(
                                        'parent' => $payment->parent,
                                        'transaction_status' => FURIK_STATUS_FUTURE,
                                    )
                                );

                                $payment_result['result'] .= ' All future recurring payments cancelled due to payment failure.';
                            }
                        }

                        $results['totals']['processed']++;
                    } catch (Exception $e) {
                        $payment_result['status'] = 'error';
                        $payment_result['result'] = 'Exception: ' . $e->getMessage();
                        $results['totals']['failed']++;
                    }
                } else {
                    $payment_result['result'] = 'Would process this payment (dry run)';
                }
            }
        } else {
            $payment_result['status'] = 'error';
            $payment_result['result'] = 'No previous transaction found';
            $payment_result['days_since_last'] = 'N/A';
            $results['totals']['skipped']++;
        }

        $results['payments'][] = $payment_result;
    }

    // Return data in requested format
    if ($return_type == 'html') {
        return furik_generate_recurring_html_output($results, $limit, $days_threshold, $dry_run);
    }

    return $results;
}

/**
 * Generate HTML output for the recurring payment processing results
 *
 * @param array  $results        The processing results
 * @param int    $limit          Maximum payments limit
 * @param int    $days_threshold Minimum days threshold
 * @param bool   $dry_run        Whether this was a dry run
 *
 * @return string HTML output
 */
function furik_generate_recurring_html_output($results, $limit, $days_threshold, $dry_run) {
    $html = '<div class="wrap">';
    $html .= '<h2>' . __('Process Recurring Payments Results', 'furik') . '</h2>';
    $html .= '<p>' . sprintf(__('Processing up to %d recurring payments', 'furik'), $limit) . '</p>';

    $html .= '<table class="wp-list-table widefat fixed striped results-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . __('ID', 'furik') . '</th>';
    $html .= '<th>' . __('Transaction ID', 'furik') . '</th>';
    $html .= '<th>' . __('Amount', 'furik') . '</th>';
    $html .= '<th>' . __('Scheduled Date', 'furik') . '</th>';
    $html .= '<th>' . __('Last Payment', 'furik') . '</th>';
    $html .= '<th>' . __('Days Since Last', 'furik') . '</th>';
    $html .= '<th>' . __('Status', 'furik') . '</th>';
    $html .= '<th>' . __('Result', 'furik') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($results['payments'] as $payment) {
        $status_class = '';
        $status_text = '';

        switch ($payment['status']) {
            case 'success':
                $status_class = 'status-success';
                $status_text = __('Success', 'furik');
                break;
            case 'failed':
                $status_class = 'status-error';
                $status_text = __('Failed', 'furik');
                break;
            case 'skipped':
                $status_class = 'status-skipped';
                $status_text = __('Skipped', 'furik');
                break;
            case 'dry_run':
                $status_class = 'status-warning';
                $status_text = __('Ready (Dry Run)', 'furik');
                break;
            case 'processing':
                $status_class = 'status-warning';
                $status_text = __('Processing', 'furik');
                break;
            case 'error':
            default:
                $status_class = 'status-error';
                $status_text = __('Error', 'furik');
                break;
        }

        $html .= '<tr data-status="' . esc_attr($status_class) . '">';
        $html .= '<td>' . esc_html($payment['id']) . '</td>';
        $html .= '<td>' . esc_html($payment['transaction_id']) . '</td>';
        $html .= '<td>' . esc_html(number_format($payment['amount'], 0, ',', ' ')) . ' HUF</td>';
        $html .= '<td class="date-display">' . esc_html($payment['scheduled_date']) . '</td>';
        $html .= '<td class="date-display">' . esc_html($payment['last_payment']) . '</td>';
        $html .= '<td>' . esc_html($payment['days_since_last']) . '</td>';
        $html .= '<td class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</td>';
        $html .= '<td>' . esc_html($payment['result']) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    // Add a filter dropdown for the results table
    if (count($results['payments']) > 0) {
        $html .= '<div class="tablenav bottom">';
        $html .= '<div class="alignleft actions">';
        $html .= '<label for="filter-status" class="screen-reader-text">' . __('Filter by status', 'furik') . '</label>';
        $html .= '<select id="filter-status">';
        $html .= '<option value="all">' . __('All statuses', 'furik') . '</option>';
        $html .= '<option value="status-success">' . __('Success', 'furik') . '</option>';
        $html .= '<option value="status-error">' . __('Error', 'furik') . '</option>';
        $html .= '<option value="status-warning">' . __('Warning', 'furik') . '</option>';
        $html .= '<option value="status-skipped">' . __('Skipped', 'furik') . '</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '<div class="results-summary notice notice-' . ($dry_run ? 'info' : 'success') . '"><p>';
    if ($dry_run) {
        $html .= sprintf(
            __('DRY RUN ONLY - Found %1$d total payments (%2$d would be skipped, %3$d would be processed).', 'furik'),
            $results['totals']['total'],
            $results['totals']['skipped'],
            $results['totals']['total'] - $results['totals']['skipped']
        );
    } else {
        $html .= sprintf(
            __('Processed %1$d payments: %2$d successful, %3$d failed, %4$d skipped.', 'furik'),
            $results['totals']['processed'],
            $results['totals']['successful'],
            $results['totals']['failed'],
            $results['totals']['skipped']
        );
    }
    $html .= '</p></div>';

    $html .= '<p><a href="' . admin_url('admin.php?page=furik-batch-tools&tab=process_recurring') . '" class="button">' . __('Back to Processing Tool', 'furik') . '</a></p>';

    $html .= '</div>';

    return $html;
}

// Fix: Check if furik_action exists in $_POST before accessing it
if ( isset( $_POST['furik_action'] ) && $_POST['furik_action'] == 'process_payment_form' ) {
	furik_process_payment_form();
}

if ( isset( $_GET['furik_process_recurring'] ) && isset( $furik_processing_recurring_secret ) && ( $_GET['furik_process_recurring'] == $furik_processing_recurring_secret ) ) {
	furik_process_recurring();
}

if ( isset( $_GET['furik_process_payment'] ) ) {
	add_action( 'init', 'furik_process_payment' );
}

if ( isset( $_GET['furik_process_ipn'] ) ) {
	furik_process_ipn();
}
