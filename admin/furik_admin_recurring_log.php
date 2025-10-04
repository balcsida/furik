<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Recurring_Log_List extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Transaction', 'furik' ),
				'plural'   => __( 'Transactions', 'furik' ),
				'ajax'     => false,
			)
		);
	}

	public function column_default( $item, $column_name ) {
		if ( ! isset( $item[ $column_name ] ) || '' === $item[ $column_name ] ) {
			return '';
		}

		return esc_html( $item[ $column_name ] );
	}

	public function column_donor_email( $item ) {
		if ( empty( $item['donor_email'] ) ) {
			return '';
		}

		$email = sanitize_email( $item['donor_email'] );
		if ( empty( $email ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( 'mailto:' . $email ),
			esc_html( $email )
		);
	}

	public function column_status( $item ) {
		$message = unserialize( $item['message'] );

		if ( $message['total'] > 0 ) {
			return 'OK, ' . $message['total'] . ' HUF';
		} else {
			return '<b>Error code: ' . $message['errorCodes'][0] . ' </b>';
		}
	}

	public static function get_transaction_log( $per_page = 5, $page_number = 1 ) {
		global $wpdb;

		$per_page    = max( 1, absint( $per_page ) );
		$page_number = max( 1, absint( $page_number ) );
		$offset      = ( $page_number - 1 ) * $per_page;

		$log_table          = $wpdb->prefix . 'furik_transaction_log';
		$transactions_table = $wpdb->prefix . 'furik_transactions';

		$sql = "SELECT
				log.*,
				trx.email AS donor_email
			FROM {$log_table} AS log
			LEFT JOIN {$transactions_table} AS trx ON trx.transaction_id = log.transaction_id";

		$orderby_whitelist = array(
			'transaction_id' => 'log.transaction_id',
			'time'           => 'log.time',
			'status'         => 'log.message',
			'donor_email'    => 'trx.email',
		);

		if ( ! empty( $_REQUEST['orderby'] ) && isset( $orderby_whitelist[ $_REQUEST['orderby'] ] ) ) {
			$direction = ( ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
			$sql      .= ' ORDER BY ' . $orderby_whitelist[ $_REQUEST['orderby'] ] . ' ' . $direction;
		} else {
			$sql .= ' ORDER BY log.time DESC';
		}

		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );
		return $result;
	}

	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transaction_log";

		return $wpdb->get_var( $sql );
	}

	public function no_items() {
		_e( 'No transactions are avaliable.', 'furik' );
	}

	function get_columns() {
		$columns = array(
			'transaction_id' => __( 'ID', 'furik' ),
			'time'           => __( 'Time', 'furik' ),
			'status'         => __( 'Status', 'furik' ),
			'donor_email'    => __( 'Email', 'furik' ),
		);

		return $columns;
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'transaction_id' => array( 'ID', 'furik' ),
			'time'           => array( 'time', true ),
			'status'         => array( 'status', false ),
			'donor_email'    => array( 'donor_email', false ),
		);

		return $sortable_columns;
	}

	public function prepare_items() {
		$per_page     = $this->get_items_per_page( 'transactions_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->items = self::get_transaction_log( $per_page, $current_page );
	}
}

class Recurring_Log_List_Plugin {

	static $instance;
	public $donations_obj;

	public function __construct() {
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen' ), 11, 3 );
		add_action( 'admin_menu', array( $this, 'plugin_menu' ) );
	}

	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {
		$hook = add_submenu_page(
			'furik-dashboard',    // Changed: Parent menu slug
			__( 'Recurring log', 'furik' ),
			__( 'Recurring log', 'furik' ),
			'manage_options',
			'furik-recurring-log',    // Changed: Page slug
			array( $this, 'transaction_log_list_page' )
		);
		add_action( "load-$hook", array( $this, 'screen_option' ) );
	}

	public function screen_option() {
		$option = 'per_page';
		$args   = array(
			'label'   => 'Recurring log',
			'default' => 20,
			'option'  => 'transactions_per_page',
		);

		add_screen_option( $option, $args );

		$this->transactions_obj = new Recurring_Log_List();
	}

	public function transaction_log_list_page() {
		global $wpdb;
		?>
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
			<h1 class="wp-heading-inline"><?php _e( 'Recurring log', 'furik' ); ?></h1>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->transactions_obj->prepare_items();
								$this->transactions_obj->display();
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
		Recurring_Log_List_Plugin::get_instance();
	}
);
