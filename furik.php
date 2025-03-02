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
define('FURIK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FURIK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load configuration
include_once "config.php";

// Load core files
include_once FURIK_PLUGIN_DIR . "includes/furik_helper.php";
include_once FURIK_PLUGIN_DIR . "includes/furik_localization.php";
include_once FURIK_PLUGIN_DIR . "includes/furik_database.php";
include_once FURIK_PLUGIN_DIR . "includes/furik_page_installation.php";
include_once FURIK_PLUGIN_DIR . "includes/furik_campaigns.php";
include_once FURIK_PLUGIN_DIR . "includes/furik_cron.php";

// Load shortcodes
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_back_to_campaign_url.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_campaign.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_campaigns.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_donate_form.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_donate_link.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_donation_sum.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_donations.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_order_ref.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_payment_info.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_progress.php";
include_once FURIK_PLUGIN_DIR . "shortcodes/furik_shortcode_register_user.php";

// Load payment processing
include_once FURIK_PLUGIN_DIR . "payments/furik_payment_processing.php";

// Load admin files
include_once FURIK_PLUGIN_DIR . "admin/furik_admin_menu.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_admin_batch_tools.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_admin_donations.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_admin_recurring.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_admin_recurring_log.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_dashboard_widget.php";
include_once FURIK_PLUGIN_DIR . "admin/furik_own_donations.php";

register_activation_hook( __FILE__, 'furik_install' );
