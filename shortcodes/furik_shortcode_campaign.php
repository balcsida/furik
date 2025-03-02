<?php
/**
 * WordPress shortcode: [furik_campaign]: summary of a child campaign
 */
function furik_shortcode_campaign( $atts ) {
	global $wpdb;

	$default_layout = <<<EOT
<div class="furik-sub-campaign-summary">
	<a href="{url}"><img src="{image}" alt="{title}" class="furik-sub-campaign-image" /></a>
	<div class="furik-sub-campaign-title"><a href="{url}">{title}</a></div>
	<div class="furik-sub-campaign-excerpt">{excerpt}</div>
	<div class="furik-sub-campaign-progress-bar">{progress_bar}</div>
	<div class="furik-sub-campaign-percentage">{percentage}%</div>
	<div class="furik-sub-campaign-collected">{collected}</div>
	<p class="furik-sub-campaign-goal">{goal}</p>
</div>
EOT;

	$a = shortcode_atts(
		array(
			'layout'        => $default_layout,
			'default_image' => '',
		),
		$atts
	);

	$campaign = get_post();

	if ( $campaign->post_parent == null ) {
		return '';
	}

	$r = '';

	$progress = furik_progress( $campaign->ID );
	$meta     = get_post_custom( $campaign->ID );
	$sql      = "SELECT
			sum(amount)
		FROM
			{$wpdb->prefix}furik_transactions AS transaction
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (transaction.campaign=campaigns.ID)
		WHERE campaigns.ID in ({$campaign->ID})
			AND transaction.transaction_status in (" . FURIK_STATUS_DISPLAYABLE . ')
		ORDER BY time DESC';

	$collected = $wpdb->get_var( $sql );

	$r .= strtr(
		$a['layout'],
		array(
			'{url}'          => $campaign->guid,
			'{image}'        => ( @$meta['IMAGE'][0] ?: $a['default_image'] ),
			'{title}'        => esc_html( $campaign->post_title ),
			'{excerpt}'      => esc_html( $campaign->post_excerpt ),
			'{progress_bar}' => $progress['progress_bar'],
			'{percentage}'   => $progress['percentage'],
			'{goal}'         => number_format( $progress['goal'], 0, ',', ' ' ),
			'{collected}'    => number_format( $collected, 0, ',', ' ' ),
		)
	);

	return $r;
}

add_shortcode( 'furik_campaign', 'furik_shortcode_campaign' );
