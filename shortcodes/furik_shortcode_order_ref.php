<?php
/**
 * WordPress shortcode: [furik_order_ref]: displays the order_ref of a donation, bank transfer page needs it
 */
function furik_shortcode_order_ref( $atts ) {
	$s         = '';
	$order_ref = 'unknown';

	if ( $_REQUEST['furik_order_ref'] && furik_order_sign( $_REQUEST['furik_order_ref'] ) == $_REQUEST['furik_check'] ) {
		$order_ref = $_REQUEST['furik_order_ref'];
		$s        .= $order_ref;
	}

	return $s;
}

add_shortcode( 'furik_order_ref', 'furik_shortcode_order_ref' );
