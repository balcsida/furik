<?php
$furik_production_system = false;

$furik_payment_merchant   = '';
$furik_payment_secret_key = '';

// Do NOT add the protocol here (only indahousehungary.hu)
$furik_homepage_url   = $_SERVER['HTTP_HOST'];
$furik_homepage_https = false;

// Paths of prepared pages, relative to $furik_homepage_url
$furik_payment_successful_url   = 'payment-successful';
$furik_payment_unsuccessful_url = 'payment-unsuccessful';
$furik_payment_timeout_url      = 'payment-unsuccessful';
$furik_donations_url            = 'tamogatas';
$furik_payment_transfer_url     = 'bank-transfer-donation';
$furik_payment_cash_url         = 'cash-donation';

$furik_card_registration_statement_url   = 'card-registration-statement';
$furik_data_transmission_declaration_url = 'data-transmission-declaration';
$furik_monthly_explanation_url           = 'monthly-donation';

$furik_simplepay_ask_for_invoice_information = true;

$furik_processing_recurring_secret = 'aekah2Qu';

$furik_enable_extra_fields = array( 'phone_number', 'name_separation' );

$furik_name_order_eastern = false;

$furik_email_thanks_enabled = false;

/**
* If furik_email_send_recurring_only is set to true, only the recurring email is sent when a recurring donation was registered. If it's false, two emails are sent on recurring donations. Does not change the one time donation setup.
*/
$furik_email_send_recurring_only = false;

/**
* Set $furik_change_sender to true if you would like to change the from address in the outgoing e-mails. The address needs to be set in the email templates (tempates/furik_email_*).
*/
$furik_email_change_sender = false;

/**
* Google reCAPTCHA v3 settings
* Get your keys from: https://www.google.com/recaptcha/admin
*/
$furik_recaptcha_enabled = false;
$furik_recaptcha_site_key = 'YOUR_V3_SITE_KEY_HERE';
$furik_recaptcha_secret_key = 'YOUR_V3_SECRET_KEY_HERE';
$furik_recaptcha_threshold = 0.5; // Score threshold (0.0-1.0), lower values are more permissive

/**
* Google reCAPTCHA v2 settings (for fallback)
*/
$furik_recaptcha_v2_site_key = 'YOUR_V2_SITE_KEY_HERE';
$furik_recaptcha_v2_secret_key = 'YOUR_V2_SECRET_KEY_HERE';

@require_once 'config_local.php';
