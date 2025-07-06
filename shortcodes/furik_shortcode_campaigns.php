<?php
/**
 * WordPress shortcode: [furik_campaigns]: lists all child campaigns
 */
function furik_shortcode_campaigns( $atts ) {
	global $wpdb;

	$default_layout = <<<EOT
<div class="furik-sub-campaign-listing">
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
			'layout'          => $default_layout,
			'default_image'   => '',
			'parent_campaign' => null,
			'except'          => null,
		),
		$atts
	);

	if ( $a['parent_campaign'] === null ) {
		$post = get_post();
		if ( ! $post ) {
			return '';
		}
		$campaigns = get_posts(
			array(
				'post_parent' => $post->ID,
				'post_type'   => 'campaign',
				'numberposts' => 100,
			)
		);
	} else {
		$campaigns = get_posts(
			array(
				'post_parent' => $a['parent_campaign'],
				'post_type'   => 'campaign',
				'numberposts' => 100,
			)
		);
	}

	$r = '';

	foreach ( $campaigns as $campaign ) {

		if ( $a['except'] !== null ) {
			$except = explode( ',', $a['except'] );
			if ( in_array( $campaign->ID, $except ) ) {
				continue;
			}
		}

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
		$collected = $collected ? intval( $collected ) : 0;

		$r .= strtr(
			$a['layout'],
			array(
				'{url}'          => $campaign->guid,
				'{image}'        => ( isset( $meta['IMAGE'][0] ) ? $meta['IMAGE'][0] : $a['default_image'] ),
				'{title}'        => esc_html( $campaign->post_title ),
				'{excerpt}'      => esc_html( $campaign->post_excerpt ),
				'{progress_bar}' => isset( $progress['progress_bar'] ) ? $progress['progress_bar'] : '',
				'{percentage}'   => isset( $progress['percentage'] ) ? $progress['percentage'] : '0',
				'{goal}'         => isset( $progress['goal'] ) ? number_format( $progress['goal'], 0, ',', ' ' ) : '0',
				'{collected}'    => number_format( $collected, 0, ',', ' ' ),
			)
		);
	}

	return $r;
}

add_shortcode( 'furik_campaigns', 'furik_shortcode_campaigns' );
