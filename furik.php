<?php
/**
 * Plugin Name: Furik Donation Plugin
 * Text Domain: furik
 * Domain Path: /lang
 * Version: 1.0.0
 * Author: Zsolt Balogh
 * License: MIT
 */

// Define plugin constants
define( 'FURIK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FURIK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load configuration
require_once 'config.php';

// Load core files
require_once FURIK_PLUGIN_DIR . 'includes/furik_helper.php';
require_once FURIK_PLUGIN_DIR . 'includes/furik_localization.php';
require_once FURIK_PLUGIN_DIR . 'includes/furik_database.php';
require_once FURIK_PLUGIN_DIR . 'includes/furik_page_installation.php';
require_once FURIK_PLUGIN_DIR . 'includes/furik_campaigns.php';
require_once FURIK_PLUGIN_DIR . 'includes/furik_cron.php';

// Load shortcodes
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_back_to_campaign_url.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_campaign.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_campaigns.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_donate_form.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_donate_link.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_donation_sum.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_donations.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_donor_feed.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_order_ref.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_payment_info.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_progress.php';
require_once FURIK_PLUGIN_DIR . 'shortcodes/furik_shortcode_register_user.php';

// Load payment processing
require_once FURIK_PLUGIN_DIR . 'payments/furik_payment_processing.php';

// Load admin files
require_once FURIK_PLUGIN_DIR . 'admin/furik_admin_menu.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_admin_batch_tools.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_admin_donations.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_admin_recurring.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_admin_recurring_log.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_dashboard_widget.php';
require_once FURIK_PLUGIN_DIR . 'admin/furik_own_donations.php';

register_activation_hook( __FILE__, 'furik_install' );
