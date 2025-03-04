<?php
/**
 * WordPress shortcode: [furik_payment_info]: provides the necessary information after a transation, depending on what information is available
 */
function furik_shortcode_payment_info($atts) {
	$s = "";
	$order_ref = "unknown";

	if ($_REQUEST['furik_order_ref'] && furik_order_sign($_REQUEST['furik_order_ref']) == $_REQUEST['furik_check']) {
		$order_ref = $_REQUEST['furik_order_ref'];
		$transaction = furik_get_transaction($order_ref);
		$s .= __('SimplePay reference id', 'furik') . ': ' . $transaction->vendor_ref . "<br />";
	}

	$s .= __('Order reference', 'furik') . ': ' . $order_ref . '<br />';
	$s .= __('Date', 'furik') . ': ' . date("Y-m-d H:i:s");
	return $s;
}

add_shortcode('furik_payment_info', 'furik_shortcode_payment_info');