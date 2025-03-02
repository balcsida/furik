<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Own_Donations_List extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Donation', 'furik' ),
				'plural'   => __( 'Donations', 'furik' ),
				'ajax'     => false,
			)
		);
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] );
	}

	public function column_campaign_name( $item ) {
		if ( ! $item['campaign_name'] ) {
			return __( 'General donation', 'furik' );
		}
		if ( ! $item['parent_campaign_name'] ) {
			return $item['campaign_name'];
		}
		return $item['campaign_name'] . ' (' . $item['parent_campaign_name'] . ')';
	}

	public function column_transaction_status( $item ) {
		switch ( $item['transaction_status'] ) {
			case '':
				return __( 'Pending', 'furik' );
			case FURIK_STATUS_SUCCESSFUL:
				return __( 'Successful, waiting for confirmation', 'furik' );
			case FURIK_STATUS_UNSUCCESSFUL:
				return __( 'Unsuccessful card payment', 'furik' );
			case FURIK_STATUS_TRANSFER_ADDED:
			case FURIK_STATUS_CASH_ADDED:
				$actions = array(
					'approve' => sprintf(
						'<br /><a href="?page=%s&action=%s&campaign=%s&orderby=%s&order=%s&paged=%s">' . __( 'Approve', 'furik' ) . '</a>',
						$_REQUEST['page'],
						'approve',
						$item['id'],
						@$_GET['orderby'],
						@$_GET['order'],
						@$_GET['paged']
					),
				);
				return sprintf( '%1$s %2$s', __( 'Waiting for confirmation', 'furik' ), $actions['approve'] );
			case FURIK_STATUS_IPN_SUCCESSFUL:
				return __( 'Successful and confirmed', 'furik' );
			default:
				return __( 'Unknown', 'furik' );
		}
	}

	public function column_transaction_type( $item ) {
		global $wpdb;

		if ( $item['transaction_type'] == 3 ) {
			$line = __( 'Recurring monthly donation', 'furik' ) . '<br />';
			$sql  = "SELECT time FROM {$wpdb->prefix}furik_transactions WHERE transaction_status=" . FURIK_STATUS_FUTURE . ' AND parent=' . $item['id'] . ' ORDER BY time';
			$next = $wpdb->get_var( $sql );
			if ( $next ) {
				$line .= __( 'Next', 'furik' ) . ': ' . $next . '<br />';
				$line .= '<a href="?page=' . $_REQUEST['page'] . '&cancelRecurring=' . $item['id'] . '&transactionId=' . $item['transaction_id'] . "\" onclick=\"return confirm('" . __( 'Are you sure you want to cancel this recurring donation? This action cannot be undone.', 'furik' ) . "');\">" . __( 'Cancel future donations', 'furik' ) . '</a>';
			} else {
				$line .= __( 'Expired or cancelled.', 'furik' ) . '<br />';
			}

			return $line;
		}
		switch ( $item['transaction_type'] ) {
			case 0:
				return __( 'SimplePay Card', 'furik' );
			case 1:
				return __( 'Bank transfer', 'furik' );
			case 2:
				return __( 'Cash payment', 'furik' );
			default:
				return __( 'Unknown', 'furik' );
		}
	}

	public static function get_donations( $per_page = 50, $page_number = 1 ) {
		global $wpdb;

		$user = wp_get_current_user();

		$sql = "SELECT
				{$wpdb->prefix}furik_transactions.*,
				campaigns.post_title AS campaign_name,
				parentcampaigns.post_title AS parent_campaign_name
			FROM
				{$wpdb->prefix}furik_transactions
				LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON ({$wpdb->prefix}furik_transactions.campaign=campaigns.ID)
				LEFT OUTER JOIN {$wpdb->prefix}posts parentcampaigns ON (campaigns.post_parent=parentcampaigns.ID)
			WHERE
				email='" . esc_sql( $user->user_email ) . "' AND
				parent IS NULL AND
					(recurring IS NULL OR (recurring=1 AND transaction_status=10))";
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}
		$sql   .= " LIMIT $per_page";
		$sql   .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $result;
	}

	public static function record_count() {
		global $wpdb;

		$user = wp_get_current_user();

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions
			WHERE
				email='" . esc_sql( $user->user_email ) . "' AND
				parent IS NULL";

		return $wpdb->get_var( $sql );
	}

	public function no_items() {
		_e( 'No donations avaliable.', 'furik' );
	}

	function get_columns() {
		$columns = array(
			'name'               => __( 'Name', 'furik' ),
			'email'              => __( 'E-mail', 'furik' ),
			'amount'             => __( 'Amount', 'furik' ),
			'transaction_type'   => __( 'Type', 'furik' ),
			'transaction_id'     => __( 'Transaction ID', 'furik' ),
			'campaign_name'      => __( 'Campaign', 'furik' ),
			'time'               => __( 'Time', 'furik' ),
			'transaction_status' => __( 'Status', 'furik' ),
		);

		return $columns;
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'name'               => array( 'name', false ),
			'email'              => array( 'email', false ),
			'amount'             => array( 'amount', false ),
			'transaction_type'   => array( 'transaction_type', false ),
			'campaign_name'      => array( 'campaign_name', false ),
			'time'               => array( 'time', true ),
			'transaction_status' => array( 'transaction_status', false ),
		);

		return $sortable_columns;
	}

	public function prepare_items() {
		$per_page     = $this->get_items_per_page( 'donations_per_page', 50 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->items = self::get_donations( $per_page, $current_page );
	}
}

class Own_Donations_List_Plugin {

	static $instance;
	public $donations_obj;

	public function __construct() {
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
	}

	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {
		$hook = add_menu_page(
			__( 'Own Furik Donations', 'furik' ),
			__( 'Own Donations', 'furik' ),
			'read',
			'own_donations',
			array( $this, 'own_donations_list_page' ),
			'dashicons-heart'
		);
		add_action( "load-$hook", array( $this, 'screen_option' ) );
	}

	public function screen_option() {
		$option = 'per_page';
		$args   = array(
			'label'   => 'Own Donations',
			'default' => 20,
			'option'  => 'donations_per_page',
		);

		add_screen_option( $option, $args );

		$this->donations_obj = new Own_Donations_List();
	}

	public function own_donations_list_page() {
		global $wpdb;

		if ( isset( $_GET['cancelRecurring'] ) && is_numeric( $_GET['cancelRecurring'] ) ) {
			$parent_id      = intval( $_GET['cancelRecurring'] );
			$transaction_id = isset( $_GET['transactionId'] ) ? sanitize_text_field( $_GET['transactionId'] ) : '';
			$user           = wp_get_current_user();

			// First, verify that this is actually a recurring donation owned by the current user
			$is_recurring = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions 
				WHERE id = %d AND transaction_id = %s AND email = %s AND 
				transaction_type IN (" . FURIK_TRANSACTION_TYPE_RECURRING_REG . ', ' . FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG . ')',
					$parent_id,
					$transaction_id,
					$user->user_email
				)
			);

			if ( $is_recurring ) {
				// Check for vendor_ref for SimplePay card-based recurring donations
				$vendor_ref = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT vendor_ref FROM {$wpdb->prefix}furik_transactions 
					WHERE id = %d AND transaction_id = %s AND email = %s",
						$parent_id,
						$transaction_id,
						$user->user_email
					)
				);

				$card_cancellation_successful = false;

				// If this is a card-based recurring with vendor_ref, cancel it with SimplePay
				if ( ! empty( $vendor_ref ) ) {
					try {
						furik_cancel_recurring( $vendor_ref );
						$card_cancellation_successful = true;
					} catch ( Exception $e ) {
						// Log error but continue to delete future transactions
						error_log( 'Error cancelling SimplePay recurring payment: ' . $e->getMessage() );
					}
				}

				// Delete future transactions regardless of card cancellation result
				$deleted_count = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}furik_transactions 
					WHERE parent = %d AND transaction_status = %d",
						$parent_id,
						FURIK_STATUS_FUTURE
					)
				);

				// Set notification message based on what happened
				if ( ! empty( $vendor_ref ) && $card_cancellation_successful ) {
					echo '<div class="notice notice-success is-dismissible"><p>' .
						__( 'Your recurring donation has been cancelled. All future donations have been removed.', 'furik' ) .
						'</p></div>';
				} elseif ( ! empty( $vendor_ref ) && ! $card_cancellation_successful ) {
					echo '<div class="notice notice-warning is-dismissible"><p>' .
						__( 'Warning: There was an issue cancelling your payment card registration, but all future donations have been removed. If you see unexpected charges, please contact support.', 'furik' ) .
						'</p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>' .
						__( 'Your recurring donation has been cancelled. All future donations have been removed.', 'furik' ) .
						'</p></div>';
				}
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' .
					__( 'Error: The selected donation could not be cancelled. Please contact support if you need assistance.', 'furik' ) .
					'</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Own Donations', 'furik' ); ?></h1>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->donations_obj->prepare_items();
								$this->donations_obj->display();
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
		<?php
	}

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

add_action(
	'plugins_loaded',
	function () {
		Own_Donations_List_Plugin::get_instance();
	}
);
