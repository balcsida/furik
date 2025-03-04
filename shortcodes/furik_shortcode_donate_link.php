<?php
/**
 * WordPress shortcode: [furik_donate_link], paramters: amount.
 */
function furik_shortcode_donate_link( $atts ) {
	global $furik_donations_url;
    $a = shortcode_atts( array(
		'amount' => '5000'
    ), $atts );

    $post = get_post();
    return furik_url($furik_donations_url,
		array(
			'furik_amount' => $atts['amount'],
			'furik_campaign' => $post->ID));
}

add_shortcode( 'furik_donate_link', 'furik_shortcode_donate_link' );