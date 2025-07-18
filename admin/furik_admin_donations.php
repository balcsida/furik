<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Donations_List extends WP_List_Table {

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

	public function column_recaptcha_score( $item ) {
		if ( is_null( $item['recaptcha_score'] ) ) {
			return '<span style="color: #999;">N/A</span>';
		}
		
		$score = floatval( $item['recaptcha_score'] );
		$color = '#46b450'; // Green by default
		
		if ( $score < 0.3 ) {
			$color = '#dc3232'; // Red for very low scores
		} elseif ( $score < 0.5 ) {
			$color = '#ffb900'; // Yellow for medium scores
		}
		
		return '<span style="color: ' . $color . '; font-weight: bold;">' . number_format( $score, 2 ) . '</span>';
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
			case FURIK_STATUS_FUTURE:
				return __( 'Future donation', 'furik' );
			case FURIK_STATUS_RECURRING_FAILED:
				return __( 'Recurring transaction failed', 'furik' );
			case FURIK_STATUS_RECURRING_PAST_FAILED:
				return __( 'Past recurring transaction failed', 'furik' );

			default:
				return __( 'Unknown', 'furik' );
		}
	}

	public function column_transaction_type( $item ) {
		switch ( $item['transaction_type'] ) {
			case FURIK_TRANSACTION_TYPE_SIMPLEPAY:
				return __( 'SimplePay Card', 'furik' );
			case FURIK_TRANSACTION_TYPE_TRANSFER:
				return __( 'Bank transfer', 'furik' );
			case FURIK_TRANSACTION_TYPE_CASH:
				return __( 'Cash payment', 'furik' );
			case FURIK_TRANSACTION_TYPE_RECURRING_REG:
				return __( 'Recurring (registration)', 'furik' );
			case FURIK_TRANSACTION_TYPE_RECURRING_AUTO:
				return __( 'Recurring (automatic)', 'furik' );
			case FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG:
				return __( 'Recurring transfer (registration)', 'furik' );
			case FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_AUTO:
				return __( 'Recurring transfer (automatic)', 'furik' );

			default:
				return __( 'Unknown', 'furik' );
		}
	}

	public static function get_donations( $per_page = 5, $page_number = 1 ) {
		global $wpdb;

		$sql = "SELECT
				tr.*,
				campaigns.post_title AS campaign_name,
				parentcampaigns.post_title AS parent_campaign_name
			FROM
				{$wpdb->prefix}furik_transactions as tr
				LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (tr.campaign=campaigns.ID)
				LEFT OUTER JOIN {$wpdb->prefix}posts parentcampaigns ON (campaigns.post_parent=parentcampaigns.ID)
			WHERE " . self::get_filter_query();
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			$sql .= ' ORDER BY TIME DESC';
		}
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}

	public static function get_filter_query() {
		if ( isset( $_GET['filter_by_parent'] ) && is_numeric( $_GET['filter_by_parent'] ) ) {
			$parent_id = $_GET['filter_by_parent'];
			return "(tr.id = $parent_id OR tr.parent=$parent_id)";
		} else {
			return '((transaction_status not in (' .
					FURIK_STATUS_FUTURE . ', ' .
					FURIK_STATUS_RECURRING_PAST_FAILED . ')) or
				 (transaction_status is null))';
		}
	}

	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions as tr WHERE " . self::get_filter_query();

		return $wpdb->get_var( $sql );
	}

	public function no_items() {
		_e( 'No donations are avaliable.', 'furik' );
	}

	function get_columns() {
		global $furik_recaptcha_enabled;
		
		$columns = array(
			'transaction_id' => __( 'ID', 'furik' ),
			'time'           => __( 'Time', 'furik' ),
			'name'           => __( 'Name', 'furik' ),
			'email'          => __( 'E-mail', 'furik' ),
		);
		if ( furik_extra_field_enabled( 'phone_number' ) ) {
			$columns += array( 'phone_number' => __( 'Phone Number', 'furik' ) );
		}
		$columns += array(
			'amount'             => __( 'Amount', 'furik' ),
			'transaction_type'   => __( 'Type', 'furik' ),
			'campaign_name'      => __( 'Campaign', 'furik' ),
			'message'            => __( 'Message', 'furik' ),
			'anon'               => __( 'Anonymity', 'furik' ),
			'newsletter_status'  => __( 'Newsletter Status', 'furik' ),
		);
		
		// Add reCAPTCHA score column if enabled
		if ( $furik_recaptcha_enabled ) {
			$columns += array( 'recaptcha_score' => __( 'reCAPTCHA Score', 'furik' ) );
		}
		
		$columns += array(
			'transaction_status' => __( 'Status', 'furik' ),
		);

		return $columns;
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'transaction_id'     => array( 'transaction_id', false ),
			'time'               => array( 'time', true ),
			'name'               => array( 'name', false ),
			'email'              => array( 'email', false ),
			'amount'             => array( 'amount', false ),
			'transaction_type'   => array( 'transaction_type', false ),
			'campaign_name'      => array( 'campaign', false ),
			'recaptcha_score'    => array( 'recaptcha_score', false ),
			'transaction_status' => array( 'transaction_status', false ),
		);

		return $sortable_columns;
	}

	public function prepare_items() {
		$total_items = self::record_count();

		$this->items = self::get_donations();
	}
}

class Donations_List_Plugin {

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
		$hook = add_submenu_page(
			'furik-dashboard',    // Changed: Parent menu slug
			__( 'Furik Donations', 'furik' ),
			__( 'Donations', 'furik' ),
			'manage_options',
			'furik-donations',    // Changed: Page slug
			array( $this, 'donations_list_page' )
		);
		add_action( "load-$hook", array( $this, 'screen_option' ) );
	}

	public function screen_option() {
		$option = 'per_page';
		$args   = array(
			'label'   => 'Donations',
			'default' => 20,
			'option'  => 'donations_per_page',
		);

		add_screen_option( $option, $args );

		$this->donations_obj = new Donations_List();
	}

	public function donations_list_page() {
		global $wpdb, $furik_recaptcha_enabled;

		if ( isset( $_GET['action'] ) && $_GET['action'] == 'approve' && isset( $_GET['campaign'] ) ) {
			$wpdb->update(
				"{$wpdb->prefix}furik_transactions",
				array( 'transaction_status' => FURIK_STATUS_IPN_SUCCESSFUL ),
				array( 'id' => esc_sql( $_GET['campaign'] ) )
			);
		}

		?>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.23/b-1.6.5/b-html5-1.6.5/datatables.min.css"/>
		<style>
			td.message.column-message {
				white-space: nowrap;
				overflow:hidden;
				text-overflow:ellipsis;
			}
			td.message.column-message:hover {
				white-space: initial;
				overflow: initial;
			}
		</style>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Donations', 'furik' ); ?></h1>
			
			<?php if ( $furik_recaptcha_enabled ) : ?>
			<div class="notice notice-info" style="margin-top: 15px;">
				<p><?php _e( 'reCAPTCHA v3 Score: 0.0 (likely bot) to 1.0 (likely human). Scores below 0.3 are highlighted in red, 0.3-0.5 in yellow, and above 0.5 in green.', 'furik' ); ?></p>
			</div>
			<?php endif; ?>
			
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
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
		<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.23/b-1.6.5/b-html5-1.6.5/datatables.min.js"></script>
		<script>
		jQuery(document).ready( function () {
			jQuery('.wp-list-table').DataTable({
				"order": [[ 1, "desc" ]],
				dom: 'Bfrtip',
				buttons: [
					'copyHtml5',
					'excelHtml5',
					'csvHtml5',
					'pdfHtml5'
				],
				"lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
				"language": {
					"url": "//cdn.datatables.net/plug-ins/1.10.22/i18n/Hungarian.json"
				}
			});
		} );
		</script>
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
		Donations_List_Plugin::get_instance();
	}
);
