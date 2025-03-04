<?php
/**
 * WordPress shortcode: [furik_back_to_campaign_url]: provides a link to the campaign based on the campaign_id back parameter
 */
function furik_shortcode_back_to_campaign_url( $atts ) {
	$post = get_post( $_GET['campaign_id'] );
	return $post->guid;
}

add_shortcode( 'furik_back_to_campaign_url', 'furik_shortcode_back_to_campaign_url' );
