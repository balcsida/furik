<?php
if (!class_exists('WP_List_Table')) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Recurring_List extends WP_List_Table {

	public function __construct() {
		parent::__construct([
			'singular' => __('Donation', 'furik'),
			'plural'   => __('Donations', 'furik'),
			'ajax'     => false
		]);
	}

	/**
	 * Get columns for the table
	 */
	public function get_columns() {
		$columns = [
			'cb'              => '<input type="checkbox" />',
			'transaction_id'  => __('ID', 'furik'),
			'time'            => __('Registration time', 'furik'),
			'name'            => __('Name', 'furik'),
			'email'           => __('E-mail', 'furik'),
			'transaction_type' => __('Type', 'furik')
		];
		
		if (furik_extra_field_enabled('phone_number')) {
			$columns['phone_number'] = __('Phone Number', 'furik');
		}
		
		$columns = array_merge($columns, [
			'amount'            => __('Amount', 'furik'),
			'full_amount'       => __('Full amount', 'furik'),
			'campaign_name'     => __('Campaign', 'furik'),
			'message'           => __('Message', 'furik'),
			'anon'              => __('Anonymity', 'furik'),
			'newsletter_status' => __('Newsletter Status', 'furik'),
			'transaction_status'=> __('Registration status', 'furik'),
			'future_count'      => __('Status', 'furik')
		]);
		
		return $columns;
	}

	/**
	 * Default column rendering
	 */
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'amount':
			case 'full_amount':
				return number_format($item[$column_name], 0, ',', ' ') . ' HUF';
			case 'anon':
				return $item[$column_name] ? __('Yes', 'furik') : __('No', 'furik');
			case 'newsletter_status':
				return $item[$column_name] ? __('Subscribed', 'furik') : __('Not subscribed', 'furik');
			default:
				return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
		}
	}

	/**
	 * Checkbox column
	 */
	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="donation_ids[]" value="%s" />',
			$item['id']
		);
	}

	/**
	 * Transaction ID column with action links
	 */
	public function column_transaction_id($item) {
		$id = $item['id'];
		$transaction_id = $item['transaction_id'];
		
		// Build row actions
		$actions = [
			'view' => sprintf(
				'<a href="#" class="view-details" data-id="%s">%s</a>',
				esc_attr($id),
				__('View Details', 'furik')
			),
			'cancel' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				wp_nonce_url(admin_url('admin.php?page=furik-recurring-donations&action=cancel&id=' . $id), 'cancel_recurring_' . $id),
				__('Are you sure you want to cancel this recurring donation? This action cannot be undone.', 'furik'),
				__('Cancel Recurring', 'furik')
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				wp_nonce_url(admin_url('admin.php?page=furik-recurring-donations&action=delete&id=' . $id), 'delete_recurring_' . $id),
				__('Are you sure you want to completely delete this recurring donation? This will remove ALL related records from the database.', 'furik'),
				__('Delete Completely', 'furik')
			),
			'history' => sprintf(
				'<a href="%s" class="thickbox">%s</a>',
				admin_url('admin.php?page=furik-donations&filter_by_parent=' . $id . '&TB_iframe=true&width=800&height=600'),
				__('View All Transactions', 'furik')
			)
		];
		
		return sprintf(
			'<a href="#" class="view-details" data-id="%s">%s</a><div class="hidden donation-details-%s">%s</div> %s',
			esc_attr($id),
			esc_html($transaction_id),
			esc_attr($id),
			$this->get_donation_details_html($item),
			$this->row_actions($actions)
		);
	}

	/**
	 * Column for campaign name
	 */
	public function column_campaign_name($item) {
		if (!$item['campaign_name']) {
			return __('General donation', 'furik');
		}
		if (!$item['parent_campaign_name']) {
			return $item['campaign_name'];
		}
		return $item['campaign_name'] . " (" . $item['parent_campaign_name'] .")";
	}

	/**
	 * Column for transaction status
	 */
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
				$actions = [
					'approve' => sprintf(
						'<a href="%s">%s</a>',
						admin_url('admin.php?page=furik-recurring-donations&action=approve&id=' . $item['id']),
						__('Approve', 'furik')
					)
				];
				return sprintf('%1$s %2$s', __('Waiting for confirmation', 'furik'), $this->row_actions($actions));
			case FURIK_STATUS_IPN_SUCCESSFUL:
				return __('Successful and confirmed', 'furik');
			default:
				return __('Unknown', 'furik');
		}
	}

	/**
	 * Column for transaction type
	 */
	public function column_transaction_type($item) {
		if ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_REG) {
			return __('SimplePay Card', 'furik');
		} elseif ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
			return __('Bank transfer', 'furik');
		} else {
			return __('Unknown', 'furik');
		}
	}

	/**
	 * Column for future count/status
	 */
	public function column_future_count($item) {
		if ($item['future_count']) {
			return sprintf(
				'%d %s',
				$item['future_count'],
				__('future donations', 'furik')
			);
		} elseif ($item['transaction_type'] == FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG) {
			$days = isset($item['last_transaction']) ? 
				round((time() - strtotime($item['last_transaction'])) / (60 * 60 * 24)) : 
				0;
			
			return sprintf(
				__('Last payment was recorded %d day(s) ago.', 'furik'),
				$days
			);
		} else {
			return __('Expired or cancelled.', 'furik');
		}
	}

	/**
	 * Generate HTML for donation details modal
	 */
	private function get_donation_details_html($item) {
		global $wpdb;
		
		// Get all related transactions
		$related_transactions = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}furik_transactions 
			WHERE parent = %d 
			ORDER BY time ASC",
			$item['id']
		));
		
		ob_start();
		?>
		<div class="donation-details">
			<h2><?php echo sprintf(__('Recurring Donation Details: %s', 'furik'), $item['transaction_id']); ?></h2>
			
			<div class="donation-info">
				<h3><?php _e('Donor Information', 'furik'); ?></h3>
				<table class="widefat fixed striped">
					<tr>
						<th><?php _e('Name', 'furik'); ?></th>
						<td><?php echo esc_html($item['name']); ?></td>
					</tr>
					<tr>
						<th><?php _e('Email', 'furik'); ?></th>
						<td><?php echo esc_html($item['email']); ?></td>
					</tr>
					<?php if (isset($item['phone_number']) && !empty($item['phone_number'])): ?>
					<tr>
						<th><?php _e('Phone Number', 'furik'); ?></th>
						<td><?php echo esc_html($item['phone_number']); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th><?php _e('Anonymous', 'furik'); ?></th>
						<td><?php echo $item['anon'] ? __('Yes', 'furik') : __('No', 'furik'); ?></td>
					</tr>
					<?php if (isset($item['message']) && !empty($item['message'])): ?>
					<tr>
						<th><?php _e('Message', 'furik'); ?></th>
						<td><?php echo esc_html($item['message']); ?></td>
					</tr>
					<?php endif; ?>
				</table>
				
				<h3><?php _e('Donation Information', 'furik'); ?></h3>
				<table class="widefat fixed striped">
					<tr>
						<th><?php _e('Transaction ID', 'furik'); ?></th>
						<td><?php echo esc_html($item['transaction_id']); ?></td>
					</tr>
					<tr>
						<th><?php _e('Registration Date', 'furik'); ?></th>
						<td><?php echo esc_html($item['time']); ?></td>
					</tr>
					<tr>
						<th><?php _e('Amount', 'furik'); ?></th>
						<td><?php echo number_format($item['amount'], 0, ',', ' '); ?> HUF</td>
					</tr>
					<tr>
						<th><?php _e('Campaign', 'furik'); ?></th>
						<td><?php echo $this->column_campaign_name($item); ?></td>
					</tr>
					<tr>
						<th><?php _e('Type', 'furik'); ?></th>
						<td><?php echo $this->column_transaction_type($item); ?></td>
					</tr>
					<tr>
						<th><?php _e('Status', 'furik'); ?></th>
						<td><?php echo str_replace($this->row_actions([]), '', $this->column_transaction_status($item)); ?></td>
					</tr>
					<?php if (!empty($item['vendor_ref'])): ?>
					<tr>
						<th><?php _e('Vendor Reference', 'furik'); ?></th>
						<td><?php echo esc_html($item['vendor_ref']); ?></td>
					</tr>
					<?php endif; ?>
				</table>
				
				<?php if (!empty($related_transactions)): ?>
				<h3><?php _e('Related Transactions', 'furik'); ?></h3>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e('Transaction ID', 'furik'); ?></th>
							<th><?php _e('Date', 'furik'); ?></th>
							<th><?php _e('Amount', 'furik'); ?></th>
							<th><?php _e('Status', 'furik'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($related_transactions as $transaction): ?>
						<tr>
							<td><?php echo esc_html($transaction->transaction_id); ?></td>
							<td><?php echo esc_html($transaction->time); ?></td>
							<td><?php echo number_format($transaction->amount, 0, ',', ' '); ?> HUF</td>
							<td>
								<?php 
								switch ($transaction->transaction_status) {
									case FURIK_STATUS_SUCCESSFUL:
										echo __('Successful, waiting for confirmation', 'furik');
										break;
									case FURIK_STATUS_UNSUCCESSFUL:
										echo __('Unsuccessful card payment', 'furik');
										break;
									case FURIK_STATUS_IPN_SUCCESSFUL:
										echo __('Successful and confirmed', 'furik');
										break;
									case FURIK_STATUS_FUTURE:
										echo __('Future donation', 'furik');
										break;
									case FURIK_STATUS_RECURRING_FAILED:
										echo __('Recurring transaction failed', 'furik');
										break;
									case FURIK_STATUS_RECURRING_PAST_FAILED:
										echo __('Past recurring transaction failed', 'furik');
										break;
									default:
										echo __('Unknown', 'furik');
								}
								?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		return [
			'transaction_id' => ['transaction_id', false],
			'time' => ['time', true],
			'name' => ['name', false],
			'email' => ['email', false],
			'amount' => ['amount', false],
			'full_amount' => ['full_amount', false],
			'campaign_name' => ['campaign', false],
			'transaction_status' => ['transaction_status', false],
		];
	}

	/**
	 * Get bulk actions
	 */
	public function get_bulk_actions() {
		$actions = [
			'cancel' => __('Cancel Recurring', 'furik'),
			'delete' => __('Delete Completely', 'furik')
		];
		
		return $actions;
	}

	/**
	 * Process bulk actions
	 */
	protected function process_bulk_action() {
		if ('delete' === $this->current_action()) {
			$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
			
			if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
				return; // Silent fail to avoid breaking page flow
			}
			
			$donation_ids = isset($_POST['donation_ids']) ? $_POST['donation_ids'] : [];
			
			if (!empty($donation_ids)) {
				foreach ($donation_ids as $donation_id) {
					self::delete_recurring_donation($donation_id);
				}
				
				// Add admin notice
				add_action('admin_notices', function() use ($donation_ids) {
					$count = count($donation_ids);
					echo '<div class="notice notice-success is-dismissible"><p>' . 
					sprintf(_n('%d recurring donation completely deleted.', '%d recurring donations completely deleted.', $count, 'furik'), $count) . 
					'</p></div>';
				});
			}
		}
		
		if ('cancel' === $this->current_action()) {
			$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
			
			if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
				return; // Silent fail to avoid breaking page flow
			}
			
			$donation_ids = isset($_POST['donation_ids']) ? $_POST['donation_ids'] : [];
			
			if (!empty($donation_ids)) {
				foreach ($donation_ids as $donation_id) {
					self::cancel_recurring_donation($donation_id);
				}
				
				// Add admin notice
				add_action('admin_notices', function() use ($donation_ids) {
					$count = count($donation_ids);
					echo '<div class="notice notice-success is-dismissible"><p>' . 
					sprintf(_n('%d recurring donation cancelled.', '%d recurring donations cancelled.', $count, 'furik'), $count) . 
					'</p></div>';
				});
			}
		}
	}

	/**
	 * Delete a recurring donation completely
	 */
	public static function delete_recurring_donation($id) {
		global $wpdb;
		
		// Get vendor_ref for card-based recurring donations
		$donation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}furik_transactions WHERE id = %d",
			$id
		));
		
		if (!$donation) {
			return false;
		}
		
		// If this is a card-based recurring, cancel it with SimplePay
		if ($donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && !empty($donation->vendor_ref)) {
			try {
				furik_cancel_recurring($donation->vendor_ref);
			} catch (Exception $e) {
				// Log error but continue to delete from database
				error_log('Error cancelling SimplePay recurring payment: ' . $e->getMessage());
			}
		}
		
		// Delete all child transactions (future payments and past completed payments)
		$wpdb->delete(
			"{$wpdb->prefix}furik_transactions",
			array('parent' => $id)
		);
		
		// Delete the main transaction record
		$wpdb->delete(
			"{$wpdb->prefix}furik_transactions",
			array('id' => $id)
		);
		
		return true;
	}
	
	/**
	 * Cancel a recurring donation (keep records but remove future payments)
	 */
	public static function cancel_recurring_donation($id) {
		global $wpdb;
		
		// Get vendor_ref for card-based recurring donations
		$donation = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}furik_transactions WHERE id = %d",
			$id
		));
		
		if (!$donation) {
			return false;
		}
		
		// If this is a card-based recurring, cancel it with SimplePay
		if ($donation->transaction_type == FURIK_TRANSACTION_TYPE_RECURRING_REG && !empty($donation->vendor_ref)) {
			try {
				furik_cancel_recurring($donation->vendor_ref);
			} catch (Exception $e) {
				// Log error but continue to delete future transactions
				error_log('Error cancelling SimplePay recurring payment: ' . $e->getMessage());
			}
		}
		
		// Delete only future transactions
		$wpdb->delete(
			"{$wpdb->prefix}furik_transactions",
			array(
				'parent' => $id,
				'transaction_status' => FURIK_STATUS_FUTURE
			)
		);
		
		return true;
	}
	
	/**
	 * Extra table navigation
	 */
	public function extra_tablenav($which) {
		if ($which === 'top') {
			?>
			<div class="alignleft actions">
				<label class="screen-reader-text" for="filter-by-type"><?php _e('Filter by type', 'furik'); ?></label>
				<select name="filter_type" id="filter-by-type">
					<option value=""><?php _e('All types', 'furik'); ?></option>
					<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_REG; ?>" <?php selected(isset($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : '', FURIK_TRANSACTION_TYPE_RECURRING_REG); ?>><?php _e('Card', 'furik'); ?></option>
					<option value="<?php echo FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG; ?>" <?php selected(isset($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : '', FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG); ?>><?php _e('Bank Transfer', 'furik'); ?></option>
				</select>
				
				<label class="screen-reader-text" for="filter-by-status"><?php _e('Filter by status', 'furik'); ?></label>
				<select name="filter_status" id="filter-by-status">
					<option value=""><?php _e('All statuses', 'furik'); ?></option>
					<option value="active" <?php selected(isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : '', 'active'); ?>><?php _e('Active (has future donations)', 'furik'); ?></option>
					<option value="expired" <?php selected(isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : '', 'expired'); ?>><?php _e('Expired/Cancelled', 'furik'); ?></option>
				</select>
				
				<?php submit_button(__('Filter', 'furik'), '', 'filter_action', false); ?>
			</div>
			<?php
		}
	}

	/**
	 * Count total recurring donations with current filters
	 */
	public static function record_count() {
		global $wpdb;
		
		$where = "transaction_type in (" . FURIK_TRANSACTION_TYPE_RECURRING_REG . ", " . FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG . ")";
		
		// Apply filters if set
		if (isset($_REQUEST['filter_type']) && !empty($_REQUEST['filter_type'])) {
			$type = intval($_REQUEST['filter_type']);
			$where .= $wpdb->prepare(" AND transaction_type = %d", $type);
		}
		
		if (isset($_REQUEST['filter_status']) && !empty($_REQUEST['filter_status'])) {
			if ($_REQUEST['filter_status'] === 'active') {
				$where .= " AND (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=tr.id AND transaction_status=" . FURIK_STATUS_FUTURE . ") > 0";
			} elseif ($_REQUEST['filter_status'] === 'expired') {
				$where .= " AND (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=tr.id AND transaction_status=" . FURIK_STATUS_FUTURE . ") = 0";
			}
		}
		
		// Add search query if present
		if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
			$search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
			$where .= $wpdb->prepare(
				" AND (tr.transaction_id LIKE %s OR tr.name LIKE %s OR tr.email LIKE %s)",
				$search,
				$search,
				$search
			);
		}
		
		$sql = "SELECT COUNT(tr.id) FROM {$wpdb->prefix}furik_transactions as tr WHERE $where";
		
		return $wpdb->get_var($sql);
	}

	/**
	 * Get recurring donations data
	 */
	public static function get_recurring_donations($per_page = 20, $page_number = 1) {
		global $wpdb;
		
		$where = "transaction_type in (" . FURIK_TRANSACTION_TYPE_RECURRING_REG . ", " . FURIK_TRANSACTION_TYPE_RECURRING_TRANSFER_REG . ")";
		
		// Apply filters if set
		if (isset($_REQUEST['filter_type']) && !empty($_REQUEST['filter_type'])) {
			$type = intval($_REQUEST['filter_type']);
			$where .= $wpdb->prepare(" AND transaction_type = %d", $type);
		}
		
		if (isset($_REQUEST['filter_status']) && !empty($_REQUEST['filter_status'])) {
			if ($_REQUEST['filter_status'] === 'active') {
				$where .= " AND (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=tr.id AND transaction_status=" . FURIK_STATUS_FUTURE . ") > 0";
			} elseif ($_REQUEST['filter_status'] === 'expired') {
				$where .= " AND (SELECT COUNT(*) FROM {$wpdb->prefix}furik_transactions WHERE parent=tr.id AND transaction_status=" . FURIK_STATUS_FUTURE . ") = 0";
			}
		}
		
		// Add search query if present
		if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
			$search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
			$where .= $wpdb->prepare(
				" AND (tr.transaction_id LIKE %s OR tr.name LIKE %s OR tr.email LIKE %s)",
				$search,
				$search,
				$search
			);
		}
		
		$orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'time';
		$order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
		
		$sql = "SELECT
			tr.*,
			campaigns.post_title AS campaign_name,
			parentcampaigns.post_title AS parent_campaign_name,
			(SELECT sum(amount) FROM {$wpdb->prefix}furik_transactions WHERE (parent=tr.id OR id=tr.id) AND transaction_status=" . FURIK_STATUS_IPN_SUCCESSFUL . ") as full_amount,
			(SELECT count(*) FROM {$wpdb->prefix}furik_transactions as ctr WHERE ctr.parent=tr.id AND ctr.transaction_status=" . FURIK_STATUS_FUTURE . ") as future_count,
			(SELECT transaction_time FROM {$wpdb->prefix}furik_transactions as ttr WHERE (ttr.parent=tr.id OR ttr.id=tr.id) ORDER BY id DESC LIMIT 1) as last_transaction
		FROM
			{$wpdb->prefix}furik_transactions as tr
			LEFT OUTER JOIN {$wpdb->prefix}posts campaigns ON (tr.campaign=campaigns.ID)
			LEFT OUTER JOIN {$wpdb->prefix}posts parentcampaigns ON (campaigns.post_parent=parentcampaigns.ID)
		WHERE $where";
		
		if (!empty($orderby)) {
			$sql .= " ORDER BY $orderby $order";
		}
		
		$sql .= " LIMIT $per_page";
		$sql .= " OFFSET " . ($page_number - 1) * $per_page;
		
		$result = $wpdb->get_results($sql, 'ARRAY_A');
		return $result;
	}

	/**
	 * No items found text
	 */
	public function no_items() {
		_e('No recurring donations are available.', 'furik');
	}

	/**
	 * Prepare the items for the table
	 */
	public function prepare_items() {
		$this->process_bulk_action();
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$per_page = $this->get_items_per_page('recurring_donations_per_page', 20);
		$current_page = $this->get_pagenum();
		$total_items = self::record_count();
		
		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items / $per_page)
		]);
		
		$this->items = self::get_recurring_donations($per_page, $current_page);
	}
}

class Recurring_List_Plugin {

	static $instance;
	public $recurring_list;

	public function __construct() {
		add_filter('set-screen-option', [ __CLASS__, 'set_screen' ], 11, 3);
		add_action('admin_menu', [$this, 'plugin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
	}

	public static function set_screen($status, $option, $value) {
		return $value;
	}

	public function plugin_menu() {
		$hook = add_submenu_page(
			'furik-dashboard',
			__('Recurring donations', 'furik'),
			__('Recurring donations', 'furik'),
			'manage_options',
			'furik-recurring-donations',
			[$this, 'recurring_list_page']
		);
		add_action("load-$hook", [$this, 'screen_option']);
	}

	public function screen_option() {
		$option = 'per_page';
		$args = [
			'label'   => __('Recurring Donations', 'furik'),
			'default' => 20,
			'option'  => 'recurring_donations_per_page'
		];
		
		add_screen_option($option, $args);
		
		$this->recurring_list = new Recurring_List();
	}
	
	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts($hook) {
		if ('furik_page_furik-recurring-donations' !== $hook) {
			return;
		}
		
		// Enqueue ThickBox
		wp_enqueue_script('thickbox');
		wp_enqueue_style('thickbox');
		
		// Add custom scripts and styles
		wp_enqueue_script(
			'furik-recurring-admin',
			plugins_url('/furik/js/furik-recurring-admin.js', dirname(__FILE__)),
			['jquery', 'thickbox'],
			'1.0.0',
			true
		);
		
		wp_enqueue_style(
			'furik-recurring-admin',
			plugins_url('/furik/css/furik-recurring-admin.css', dirname(__FILE__)),
			[],
			'1.0.0'
		);
	}
	
	/**
	 * The recurring donations list page
	 */
	public function recurring_list_page() {
		global $wpdb;
		
		// Handle single donation actions
		if (isset($_GET['action']) && isset($_GET['id'])) {
			$id = intval($_GET['id']);
			
			if ($_GET['action'] === 'delete') {
				$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
				if (wp_verify_nonce($nonce, 'delete_recurring_' . $id)) {
					if (Recurring_List::delete_recurring_donation($id)) {
						echo '<div class="notice notice-success is-dismissible"><p>' . 
						__('Recurring donation completely deleted.', 'furik') . 
						'</p></div>';
					}
				}
			}
			
			if ($_GET['action'] === 'cancel') {
				$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
				if (wp_verify_nonce($nonce, 'cancel_recurring_' . $id)) {
					if (Recurring_List::cancel_recurring_donation($id)) {
						echo '<div class="notice notice-success is-dismissible"><p>' . 
						__('Recurring donation cancelled.', 'furik') . 
						'</p></div>';
					}
				}
			}
			
			if ($_GET['action'] === 'approve') {
				$wpdb->update(
					"{$wpdb->prefix}furik_transactions",
					["transaction_status" => FURIK_STATUS_IPN_SUCCESSFUL],
					["id" => $id]
				);
				echo '<div class="notice notice-success is-dismissible"><p>' . 
				__('Recurring donation approved.', 'furik') . 
				'</p></div>';
			}
		}
		
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e('Recurring donations', 'furik') ?></h1>
			<hr class="wp-header-end">
			
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								// Display search box
								$this->recurring_list->search_box(__('Search Donations', 'furik'), 'search_id');
								
								// Prepare and display the list
								$this->recurring_list->prepare_items();
								$this->recurring_list->display();
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
			
			<!-- Modal for donation details -->
			<div id="donation-details-modal" style="display:none;">
				<div id="donation-details-container"></div>
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

add_action('plugins_loaded', function() {
	Recurring_List_Plugin::get_instance();
});
