<?php
function furik_email_content_type() {
	return 'text/html';
}

function furik_email_from_address() {
	global $furik_email_from_address;

	return $furik_email_from_address;
}

function furik_email_from_name() {
	global $furik_email_from_name;

	return $furik_email_from_name;
}

function furik_extra_field_enabled( $name ) {
	global $furik_enable_extra_fields;

	return in_array( $name, $furik_enable_extra_fields );
}
/**
 * Load template.
 *
 * @param string $_template_file Filename with extension.
 * @param array  $args Array of arguments.
 */
function furik_load_template( $_template_file, $args = array() ) {
	$plugin              = basename( plugin_dir_path( __FILE__ ) );
	$overridden_template = locate_template( $plugin . '/' . $_template_file );

	ob_start();

	if ( $overridden_template ) {
		/*
		 * Method locate_template() returns path to file.
		 * If either the child theme or the parent theme have overridden the template.
		 */
		load_template( $overridden_template, true, $args );
	} else {
		/*
		 * If neither the child nor parent theme have overridden the template,
		 * we load the template from the 'templates' sub-directory of the directory this file is in.
		 */
		load_template( __DIR__ . '/templates/' . $_template_file, true, $args );
	}

	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}

function furik_numr( $name, $def = 0 ) {
	return is_numeric( $_REQUEST[ $name ] ) ? $_REQUEST[ $name ] : $def;
}

function furik_order_sign( $order_ref ) {
	global $furik_payment_secret_key;

	return md5( $order_ref . $furik_payment_secret_key . 'internal' );
}

function furik_register_user( $email ) {
	if ( email_exists( $email ) ) {
		return false;
	}

	$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
	wp_create_user( $email, $random_password, $email );

	return $random_password;
}

function furik_send_email( $from_address, $from_name, $to_address, $subject, $body ) {
	global $furik_email_from_address, $furik_email_from_name, $furik_email_change_sender;

	$furik_email_from_address = $from_address;
	$furik_email_from_name    = $from_name;

	add_filter( 'wp_mail_content_type', 'furik_email_content_type' );

	if ( $furik_email_change_sender ) {
		add_filter( 'wp_mail_from', 'furik_email_from_address' );
		add_filter( 'wp_mail_from_name', 'furik_email_from_name' );
	}

	wp_mail( $to_address, $subject, $body );

	remove_filter( 'wp_mail_content_type', 'furik_email_content_type' );

	if ( $furik_email_change_sender ) {
		remove_filter( 'wp_mail_from', 'furik_email_from_address' );
		remove_filter( 'wp_mail_from_name', 'furik_email_from_name' );
	}
}

function furik_transaction_id( $local_id ) {
	$transactionId = substr( md5( $_SERVER['SERVER_ADDR'] ), 0, 4 ) . '-' . $local_id;
	return $transactionId;
}

function furik_url( $uri, $parameters = array(), $add_proto = true ) {
	global $furik_homepage_https, $furik_homepage_url;
	$url = '';

	if ( $add_proto ) {
		$url .= ( $furik_homepage_https ? 'https' : 'http' ) . '://';
	}

	$url .= $furik_homepage_url . $uri;

	if ( count( $parameters ) ) {
		foreach ( $parameters as $key => $value ) {
			$url .= strpos( $url, '?' ) ? '&' : '?';
			$url .= urlencode( $key );
			$url .= '=';
			$url .= urlencode( $value );
		}
	}

	return $url;
}
