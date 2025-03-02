<?php
/**
 * WordPress shortcode: [furik_donatation_sum]: shows the sum of campaign donations
 */
function furik_shortcode_donation_sum() {
	global $wpdb;

	$main_campaign = get_post();
	$sub_campaigns = get_posts(['post_parent' => $main_campaign->ID, 'post_type' => 'campaign', 'numberposts' => 100]);
	$campaign_ids = [$main_campaign->ID];
	if ($sub_campaigns) {
		$campaign_ids = array_merge($campaign_ids, array_map(function($item) { return $item->ID; }, $sub_campaigns));
	}

	$sql = "SELECT
			sum(amount)
		FROM
			{$wpdb->prefix}furik_transactions AS transaction
		WHERE transaction.campaign in (" . implode(',', $campaign_ids) . ")
			AND transaction.transaction_status in (" . FURIK_STATUS_DISPLAYABLE . ")";

	return number_format((int) $wpdb->get_var($sql), 0, ',', ' ');
}

add_shortcode('furik_donation_sum', 'furik_shortcode_donation_sum');
?>