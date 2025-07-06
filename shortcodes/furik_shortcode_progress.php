<?php
/**
 * WordPress shortcode: [furik_progress]: shows a status bar for the campaign
 */
function furik_shortcode_progress( $atts ) {
	$a = shortcode_atts(
		array(
			'amount' => 0,
		),
		$atts
	);

	global $wpdb;

	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$amount = $a['amount'];
	if ( ! $amount ) {
		$meta = get_post_custom( $post->ID );
		if ( isset( $meta['GOAL'][0] ) && is_numeric( $meta['GOAL'][0] ) ) {
			$amount = $meta['GOAL'][0];
		}
	}
	$campaigns = get_posts(
		array(
			'post_parent' => $post->ID,
			'post_type'   => 'campaign',
			'numberposts' => 100,
		)
	);
	$ids       = array();
	$ids[]     = $post->ID;

	foreach ( $campaigns as $campaign ) {
		$ids[] = $campaign->ID;
	}
	// Fixed: implode() parameters were in wrong order
	$id_list = implode( ',', $ids );

	$sql = "SELECT
			sum(amount)
		FROM
			{$wpdb->prefix}furik_transactions AS transaction
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (transaction.campaign=campaigns.ID)
		WHERE campaigns.ID in ($id_list)
			AND transaction.transaction_status in (" . FURIK_STATUS_DISPLAYABLE . ')
		ORDER BY time DESC';

	$result = $wpdb->get_var( $sql );
	$result = $result ? intval( $result ) : 0;

	// Initialize $r variable
	$r = '';
	$r .= '<p class="furik-collected">' . number_format( $result, 0, ',', ' ' ) . ' Ft</p>';

	if ( $amount > 0 ) {
		$percentage = round( 1.0 * $result / $amount * 100 );
		$r         .= '<style>
				.furik-progress-bar {
					background-color: #aaaaaaa;
					height: 20px;
					padding: 5px;
					width: 200px;
					margin: 5px 0;
					border-radius: 5px;
					box-shadow: 0 1px 1px #444 inset, 0 1px 0 #888;
					}
				.furik-progress-bar span {
					display: inline-block;
					float: left;
					height: 100%;
					border-radius: 3px;
					box-shadow: 0 1px 0 rgba(255, 255, 255, .5) inset;
					transition: width .4s ease-in-out;
					overflow: hidden;
					background-color: #D44236;
					}
				</style>';
		$r         .= '<div class="furik-progress-bar"><span style="width: ' . ( $percentage > 100 ? 100 : $percentage ) . '%"></span></div>';
		$r         .= '<p class="furik-percentage">' . $percentage . '% ' . __( 'completed', 'furik' ) . '</p>';
		$r         .= '<p class="furik-goal">' . __( 'Goal', 'furik' ) . ': ' . number_format( $amount, 0, ',', ' ' ) . ' Ft</p>';
	}

	return $r;
}

add_shortcode( 'furik_progress', 'furik_shortcode_progress' );
