<?php
if (!class_exists('WP_List_Table') ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Recurring_List extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => __('Donation', 'furik'),
			'plural'   => __('Donations', 'furik'),
			'ajax'     => false
		] );
	}

	public function column_default($item, $column_name) {
		return esc_html($item[$column_name]);
	}

	public function column_campaign_name($item) {
		if (!$item['campaign_name']) {
			return __('General donation', 'furik');
		}
		if (!$item['parent_campaign_name']) {
			return $item['campaign_name'];
		}
		return $item['campaign_name'] . " (" . $item['parent_campaign_name'] .")";
	}

	public function column_future_count($item) {
		if ($item['future_count']) {
			return $item['future_count'] . ' ' . __('future donations', 'furik');
		}
		elseif ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
			$days = (time() - strtotime($item['last_transaction']))/60/60/24;

			return sprintf(__('Last payment was recorded %d day(s) ago.', 'furik'), $days);
		}
		else {
			return __('Expired or cancelled.', 'furik');
		}
	}

	public function column_transaction_id($item) {
		return "<a href=\"admin.php?page=wp_list_table_class&filter_by_parent=" . $item['id'] . "\">". $item['transaction_id'] . "</a>";
	}

	public function column_transaction_status($item) {
		switch ($item['transaction_status']) {
			case "":
				return __('Pending', 'furik');
			case FURIK_STATUS_SUCCESSFUL:
				return __('Successful, waiting for confirmation', 'furik');
			case FURIK_STATUS_UNSUCCESSFUL:
				return __('Unsuccessful card payment', 'furik');
			case FURIK_STATUS_TRANSFER_ADDED:
			case FURIK_STATUS_CASH_ADDED:
				$actions = array(
					'approve' => sprintf('<br /><a href="?action=%s&campaign=%s">' . __('Approve', 'furik') . '</a>',
						'approve',
						$item['id'],
					)
				);
				return sprintf('%1$s %2$s', __('Waiting for confirmation', 'furik'), $actions['approve'] );
			case FURIK_STATUS_IPN_SUCCESSFUL:
				return __('Successful and confirmed', 'furik');
			default:
				return __('Unknown', 'furik');
		}
	}

	public function column_transaction_type($item) {
		if ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_REG) {
			return __('SimplePay Card', 'furik');
		}
		elseif ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
			return __('Bank transfer', 'furik');
		}
		else {
			return __('Unknown', 'furik');
		}
	}

	public static function get_donations() {
		global $wpdb;

		$sql = "SELECT
				tr.*,
				campaigns.post_title AS campaign_name,
				parentcampaigns.post_title AS parent_campaign_name,
				(SELECT sum(amount) FROM {$wpdb->prefix}furik_transactions WHERE (parent=tr.id OR id=tr.id) AND transaction_status=".FURIK_STATUS_IPN_SUCCESSFUL.") as full_amount,
				(SELECT count(*) FROM {$wpdb->prefix}furik_transactions as ctr WHERE ctr.parent=tr.id AND ctr.transaction_status=".FURIK_STATUS_FUTURE.") as future_count,
				(SELECT transaction_time FROM {$wpdb->prefix}furik_transactions as ttr WHERE (ttr.parent=tr.id OR ttr.id=tr.id) ORDER BY id DESC LIMIT 1) as last_transaction
			FROM
				{$wpdb->prefix}furik_transactions as tr
				LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (tr.campaign=campaigns.ID)
				LEFT OUTER JOIN {$wpdb->prefix}posts parentcampaigns ON (campaigns.post_parent=parentcampaigns.ID)
			WHERE transaction_type in (". FURIK_TRANSACTION_TYPE_RECURRING_REG . ", ". FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG .")";

		$result = $wpdb->get_results($sql, 'ARRAY_A');
		return $result;
	}

	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE transaction_type = ". FURIK_TRANSACTION_TYPE_RECURRING_REG;

		return $wpdb->get_var($sql);
	}

	public function no_items() {
		_e('No recurring donations are avaliable.', 'furik');
	}

	function get_columns() {
		$columns = [
			'transaction_id' => __('ID', 'furik'),
			'time' => __('Registration time', 'furik'),
			'name' => __('Name', 'furik'),
			'email' => __('E-mail', 'furik'),
			'transaction_type' => __('Type', 'furik')
		];
		if (furik_extra_field_enabled('phone_number')) {
			$columns += ['phone_number' => __('Phone Number', 'furik')];
		}
		$columns += [
			'amount' => __('Amount', 'furik'),
			'full_amount' => __('Full amount', 'furik'),
			'campaign_name' => __('Campaign', 'furik'),
			'message' => __('Message', 'furik'),
			'anon' => __('Anonymity', 'furik'),
			'newsletter_status' => __('Newsletter Status', 'furik'),
			'transaction_status' => __('Registration status', 'furik'),
			'future_count' => __('Status', 'furik')
		];

		return $columns;
	}

	public function prepare_items() {
		$this->items = self::get_donations();
	}
}

class Recurring_List_Plugin {

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
		$hook = add_menu_page(
			__('Recurring donations', 'furik'),
			__('Recurring donations', 'furik'),
			'manage_options',
			'recurring_donations',
			[$this, 'donations_list_page'],
			'dashicons-chart-line'
		);
		add_action("load-$hook", [$this, 'screen_option']);
	}

	public function screen_option() {
		$this->donations_obj = new Recurring_List();
	}

	public function donations_list_page() {
		global $wpdb;
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
			<h1 class="wp-heading-inline"><?php _e('Recurring donations', 'furik') ?></h1>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->donations_obj->prepare_items();
								$this->donations_obj->display(); ?>
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
			jQuery('.tmogatsok').DataTable({
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
				},
				initComplete: function () {
					this.api().columns([4,8,10,11,12]).every( function () {
						var column = this;
						var select = jQuery('<select><option value=""></option></select>')
							.appendTo( jQuery(column.footer()).empty() )
							.on( 'change', function () {
								var val = jQuery.fn.dataTable.util.escapeRegex(
									jQuery(this).val()
								);
								column
									.search( val ? '^'+val+'$' : '', true, false )
									.draw();
							} );
						column.data().unique().sort().each( function ( d, j ) {
							select.append( '<option value="'+d+'">'+d+'</option>' )
						} );
					} );
				}
			});
		} );
		</script>
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
	Recurring_List_Plugin::get_instance();
} );
