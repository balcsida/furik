<?php
/**
 * Furik Admin Menu Setup
 *
 * This file creates the parent "Furik" menu and dashboard.
 */
function furik_create_parent_menu() {
	add_menu_page(
		__( 'Furik', 'furik' ),
		__( 'Furik', 'furik' ),
		'manage_options',
		'furik-dashboard',
		'furik_dashboard_page',
		'dashicons-money-alt',
		25
	);
}
add_action( 'admin_menu', 'furik_create_parent_menu', 9 ); // Lower priority to ensure it runs first

/**
 * Dashboard page content
 */
function furik_dashboard_page() {
	?>
	<div class="wrap">
		<h1><?php _e( 'Furik Donation Plugin Dashboard', 'furik' ); ?></h1>
		
		<div class="card">
			<h2><?php _e( 'Welcome to Furik', 'furik' ); ?></h2>
			<p><?php _e( 'Furik is a donations management plugin that helps you collect and manage donations through various payment methods.', 'furik' ); ?></p>
		</div>
		
		<div class="card">
			<h2><?php _e( 'Quick Links', 'furik' ); ?></h2>
			<ul>
				<li><a href="<?php echo admin_url( 'admin.php?page=furik-donations' ); ?>"><?php _e( 'View All Donations', 'furik' ); ?></a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=furik-recurring-donations' ); ?>"><?php _e( 'Manage Recurring Donations', 'furik' ); ?></a></li>
				<li><a href="<?php echo admin_url( 'admin.php?page=furik-batch-tools' ); ?>"><?php _e( 'Batch Tools', 'furik' ); ?></a></li>
				<li><a href="<?php echo admin_url( 'edit.php?post_type=campaign' ); ?>"><?php _e( 'Manage Campaigns', 'furik' ); ?></a></li>
			</ul>
		</div>
		
		<?php
		// Display donation statistics if available
		global $wpdb;
		$total_donations = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE transaction_status IN (1, 10)" );
		$total_amount    = $wpdb->get_var( "SELECT SUM(amount) FROM {$wpdb->prefix}furik_transactions WHERE transaction_status IN (1, 10)" );
		$recurring_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE transaction_type IN (3, 5)" );

		if ( $total_donations ) {
			?>
			<div class="card">
				<h2><?php _e( 'Statistics', 'furik' ); ?></h2>
				<p><?php printf( __( 'Total Donations: %d', 'furik' ), $total_donations ); ?></p>
				<p><?php printf( __( 'Total Amount: %s HUF', 'furik' ), number_format( $total_amount, 0, ',', ' ' ) ); ?></p>
				<p><?php printf( __( 'Active Recurring Donations: %d', 'furik' ), $recurring_count ); ?></p>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
