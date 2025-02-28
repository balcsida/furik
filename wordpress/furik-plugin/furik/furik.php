<?php
/**
 * Plugin Name: Furik Donation Plugin
 * Text Domain: furik
 * Domain Path: /lang
 */
include_once "config.php";

include_once "furik_helper.php";
include_once "furik_localization.php";

include_once "furik_database.php";

include_once "furik_shortcode_back_to_campaign_url.php";
include_once "furik_shortcode_campaign.php";
include_once "furik_shortcode_campaigns.php";
include_once "furik_shortcode_donate_form.php";
include_once "furik_shortcode_donate_link.php";
include_once "furik_shortcode_donation_sum.php";
include_once "furik_shortcode_donations.php";
include_once "furik_shortcode_order_ref.php";
include_once "furik_shortcode_payment_info.php";
include_once "furik_shortcode_progress.php";
include_once "furik_shortcode_register_user.php";


include_once "furik_payment_processing.php";

include_once "furik_admin_batch_tools.php";
include_once "furik_admin_donations.php";
include_once "furik_admin_recurring.php";
include_once "furik_admin_recurring_log.php";
include_once "furik_campaigns.php";
include_once "furik_own_donations.php";

register_activation_hook( __FILE__, 'furik_install' );
