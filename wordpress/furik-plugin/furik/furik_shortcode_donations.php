<?php
/**
 * WordPress shortcode: [furik_donations]: lists all donations to the campaign
 */
function furik_shortcode_donations($atts) {
	global $wpdb;

    $post = get_post();
    $campaigns = get_posts(['post_parent' => $post->ID, 'post_type' => 'campaign']);
    $ids = array();
    $ids[] = $post->ID;

    foreach ($campaigns as $campaign) {
		$ids[] = $campaign->ID;
    }
    $id_list = implode($ids, ",");

    $sql = "SELECT
			{$wpdb->prefix}furik_transactions.*,
			campaigns.post_title AS campaign_name,
			campaigns.ID AS campaign_id
		FROM
			{$wpdb->prefix}furik_transactions
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON ({$wpdb->prefix}furik_transactions.campaign=campaigns.ID)
		WHERE campaigns.ID in ($id_list)
		ORDER BY time DESC";

	$result = $wpdb->get_results($sql);

	if (count($result)) {
		$r .= "Donations so far: ";
	}

	$r .= "<table><tbody>";
	foreach ($result as $donation) {
		$r .= "<tr><td>".substr($donation->time, 0, 10)."</td>";

		if ($donation->anon) {
			$r .= "<td>".__('Anonymous donation', 'furik')."</td>";
		}
		else {
			$r .= "<td>{$donation->name}</td>";
		}

		$r .= "<td>{$donation->amount}</td>";
		if (!$post->parent_post) {
			if ($post->ID != $donation->campaign_id) {
				$r .= "<td>{$donation->campaign_name}</td>";
			}
			else {
				$r .= "<td></td>";
			}
		}
		$r .= "<td>{$donation->message}</td>";
		$r .= "</tr>";
	}
	$r .= "</tbody></table>";

    return $r;
}

add_shortcode('furik_donations', 'furik_shortcode_donations');