<?php
/**
 * WordPress shortcode: [furik_payment_info]: provides the necessary information after a transation, depending on what information is available
 */
function furik_shortcode_payment_info($atts) {
	$s = "";
	$s .= __('Date', 'furik') . ': ' . date("Y-m-d H:i:s");
	return $s;
}

add_shortcode('furik_payment_info', 'furik_shortcode_payment_info');