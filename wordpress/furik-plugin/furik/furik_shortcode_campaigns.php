<?php
/**
 * WordPress shortcode: [furik_campaigns]: lists all child campaigns
 */
function furik_shortcode_campaigns($atts) {
    $post = get_post();
    $campaigns = get_posts(['post_parent' => $post->ID, 'post_type' => 'campaign', 'numberposts' => 100]);

    foreach ($campaigns as $campaign) {
		$r .= "<a href=\"".$campaign->guid."\">".esc_html($campaign->post_title)."</a><br />";
    }

    return $r;
}

add_shortcode('furik_campaigns', 'furik_shortcode_campaigns');