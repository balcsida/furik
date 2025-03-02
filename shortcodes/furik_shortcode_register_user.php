<?php
/**
 * WordPress shortcode: [furik_register_user] - registers the users if the transaction was successful and recurring.
 */
function furik_shortcode_register_user( $atts ) {
	if ($_REQUEST['furik_order_ref'] && furik_order_sign($_REQUEST['furik_order_ref']) == $_REQUEST['furik_check']) {
		$order_ref = $_REQUEST['furik_order_ref'];
		$transaction = furik_get_transaction($order_ref);

		if ($transaction->recurring && !email_exists($transaction->email)) {
			$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
			$user_id = wp_create_user( $transaction->email, $random_password, $transaction->email );

			return __("If you'd like to make changes to the recurring payment, please log in with your e-mail address. Your password is: ", 'furik') . $random_password;
		}
	}
}

add_shortcode( 'furik_register_user', 'furik_shortcode_register_user' );