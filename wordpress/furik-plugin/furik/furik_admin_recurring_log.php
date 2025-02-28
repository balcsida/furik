<?php
if (!class_exists('WP_List_Table') ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Recurring_Log_List extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => __('Transaction', 'furik'),
			'plural'   => __('Transactions', 'furik'),
			'ajax'     => false
		] );
	}

	public function column_default($item, $column_name) {
		return esc_html($item[$column_name]);
	}

	public function column_status($item) {
		$message = unserialize($item['message']);

		if ($message['total'] > 0) {
			return "OK, " . $message['total'] . " HUF";
		}
		else {
			return "<b>Error code: " . $message['errorCodes'][0] ." </b>";
		}
	}

	public static function get_transaction_log($per_page = 5, $page_number = 1) {
		global $wpdb;

		$sql = "SELECT
				*
				FROM {$wpdb->prefix}furik_transaction_log";
		if (!empty($_REQUEST['orderby'])) {
			$sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
			$sql .= ! empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
		} else {
			$sql .= ' ORDER BY time DESC';
		}
		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

		$result = $wpdb->get_results($sql, 'ARRAY_A');
		return $result;
	}

	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transaction_log";

		return $wpdb->get_var($sql);
	}

	public function no_items() {
		_e('No transactions are avaliable.', 'furik');
	}

	function get_columns() {
		$columns = [
			'transaction_id' => __('ID', 'furik'),
			'time' => __('Time', 'furik'),
			'status' => __('Status', 'furik'),
		];

		return $columns;
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'transaction_id' => array('ID', 'furik'),
			'time' => array('time', true),
			'status' => array('status', false)
		);

		return $sortable_columns;
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page('transactions_per_page', 20);
		$current_page = $this->get_pagenum();
		$total_items = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page
		] );

		$this->items = self::get_transaction_log($per_page, $current_page);
	}
}

class Recurring_Log_List_Plugin {

	static $instance;
	public $donations_obj;

	public function __construct() {
		add_filter('set-screen-option', [ __CLASS__, 'set_screen' ], 11, 3);
		add_action('admin_menu', [$this, 'plugin_menu']);
	}

	public static function set_screen($status, $option, $value) {
		return $value;
	}

	public function plugin_menu() {
		$hook = add_submenu_page(
			'furik-dashboard',    // Changed: Parent menu slug
			__('Recurring log', 'furik'),
			__('Recurring log', 'furik'),
			'manage_options',
			'furik-recurring-log',    // Changed: Page slug
			[$this, 'transaction_log_list_page']
		);
		add_action("load-$hook", [$this, 'screen_option']);
	}

	public function screen_option() {
		$option = 'per_page';
		$args   = [
			'label' => 'Recurring log',
			'default' => 20,
			'option' => 'transactions_per_page'
		];

		add_screen_option($option, $args);

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
			<h1 class="wp-heading-inline"><?php _e('Recurring log', 'furik') ?></h1>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->transactions_obj->prepare_items();
								$this->transactions_obj->display(); ?>
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
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

add_action( 'plugins_loaded', function () {
	Recurring_Log_List_Plugin::get_instance();
} );
