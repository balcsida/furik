<?php
/**
 * WordPress shortcode: [furik_donations]: lists all donations to the campaign
 */
function furik_shortcode_donations( $atts ) {
	global $wpdb;

	$default_layout = <<<EOT
<div class="furik-campaign-donation">
	<div class="date">{relative_date}</div>
	<div class="name">{name}</div>
	<div class="campaign">{campaign}</div>
	<div class="amount">{amount}</div>
	<div class="message">{message}</div>
</div>
EOT;

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
	$ids       = array();
	$ids[]     = $post->ID;
	$a         = shortcode_atts(
		array(
			'layout' => $default_layout,
		),
		$atts
	);

	foreach ( $campaigns as $campaign ) {
		$ids[] = $campaign->ID;
	}
	// Fixed: implode() parameters were in wrong order
	$id_list = implode( ',', $ids );

	$sql = "SELECT
			transaction.*,
			campaigns.post_title AS campaign_name,
			campaigns.ID AS campaign_id
		FROM
			{$wpdb->prefix}furik_transactions AS transaction
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (transaction.campaign=campaigns.ID)
		WHERE campaigns.ID in ($id_list)
			AND transaction.transaction_status in (" . FURIK_STATUS_DISPLAYABLE . ')
		ORDER BY time DESC';

	$result = $wpdb->get_results( $sql );

	// Initialize $r variable
	$r = '';

	if ( ! count( $result ) ) {
		$r .= __( 'No donations yet. Be the first one!', 'furik' );
	} else {
		foreach ( $result as $donation ) {
			$r .= strtr(
				$a['layout'],
				array(
					'{url}'           => '/?post_type=campaign&p=' . $donation->campaign_id,
					'{datetime}'      => $donation->time,
					'{date_ymd}'      => date( 'Y-m-d', strtotime( $donation->time ) ),
					'{relative_date}' => time2str( $donation->time . ' ' . get_option( 'timezone_string' ) ),
					'{name}'          => ( $donation->anon ? __( 'Anonymous donation', 'furik' ) : esc_html( $donation->name ) ),
					'{amount}'        => number_format( $donation->amount, 0, ',', ' ' ),
					'{campaign}'      => ( ! $post->post_parent && $post->ID != $donation->campaign_id ? esc_html( $donation->campaign_name ) : '' ),
					'{message}'       => ( ! empty( $donation->message ) ? esc_html( $donation->message ) : '&nbsp;' ),
				)
			);
		}
	}

	return $r;
}

function time2str( $ts ) {
	if ( ! ctype_digit( $ts ) ) {
		$ts = strtotime( $ts );
	}
	$diff = time() - $ts;
	if ( $diff == 0 ) {
		return __( 'now', 'furik' );
	} elseif ( $diff > 0 ) {
		$day_diff = floor( $diff / 86400 );
		if ( $day_diff == 0 ) {
			if ( $diff < 60 ) {
				return __( 'now', 'furik' );
			}
			if ( $diff < 120 ) {
				return __( '1 minute ago', 'furik' );
			}
			if ( $diff < 3600 ) {
				return sprintf( __( '%d minutes ago', 'furik' ), floor( $diff / 60 ) );
			}
			if ( $diff < 7200 ) {
				return __( '1 hour ago', 'furik' );
			}
			if ( $diff < 86400 ) {
				return sprintf( __( '%d hours ago', 'furik' ), floor( $diff / 3600 ) );
			}
		}
		if ( $day_diff == 1 ) {
			return __( 'Yesterday', 'furik' ); }
		if ( $day_diff < 7 ) {
			return sprintf( __( '%d days ago', 'furik' ), $day_diff ); }
		if ( $day_diff < 31 ) {
			return sprintf( __( '%d weeks ago', 'furik' ), ceil( $day_diff / 7 ) ); }
		if ( $day_diff < 60 ) {
			return __( 'last month', 'furik' ); }
		return date( 'F Y', $ts );
	} else {
		$diff     = abs( $diff );
		$day_diff = floor( $diff / 86400 );
		if ( $day_diff == 0 ) {
			if ( $diff < 120 ) {
				return __( 'in a minute', 'furik' ); }
			if ( $diff < 3600 ) {
				return sprintf( __( 'in %d minutes', 'furik' ), floor( $diff / 60 ) ); }
			if ( $diff < 7200 ) {
				return __( 'in an hour', 'furik' ); }
			if ( $diff < 86400 ) {
				return sprintf( __( 'in %d hours', 'furik' ), floor( $diff / 3600 ) ); }
		}
		if ( $day_diff == 1 ) {
			return __( 'Tomorrow', 'furik' ); }
		if ( $day_diff < 4 ) {
			return date( 'l', $ts ); }
		if ( $day_diff < 7 + ( 7 - date( 'w' ) ) ) {
			return __( 'next week', 'furik' ); }
		if ( ceil( $day_diff / 7 ) < 4 ) {
			return sprintf( __( 'in %d weeks', 'furik' ), ceil( $day_diff / 7 ) ); }
		if ( date( 'n', $ts ) == date( 'n' ) + 1 ) {
			return __( 'next month', 'furik' ); }
		return date( 'F Y', $ts );
	}
}

add_shortcode( 'furik_donations', 'furik_shortcode_donations' );
