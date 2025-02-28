# Furik - WordPress Donation & Fundraising Plugin

Furik is a comprehensive donation management system for WordPress that helps organizations collect and process donations through various payment methods, manage campaigns, and track recurring contributions.

## Features

- **Multiple Payment Methods**: Support for SimplePay card payments, bank transfers, and cash donations
- **Campaign Management**: Create and manage multiple donation campaigns with hierarchical organization
- **Recurring Donations**: Enable monthly recurring donations with automatic payment processing
- **Donor Management**: Track donor information and donation history
- **Admin Dashboard**: Comprehensive admin interface for managing donations, campaigns, and recurring payments
- **Batch Tools**: Process recurring payments and manage donation statuses in bulk
- **Shortcodes**: Multiple shortcodes to display donation forms, campaigns, progress bars, and more
- **Multilingual**: Full localization support with Hungarian translation included

## Installation

1. Upload the `furik-plugin` directory to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings by creating a `config_local.php` file based on the configuration in `config.php`

## Configuration

The main configuration is managed in `config.php`. Create a `config_local.php` file in the same directory to override the default settings without modifying the original file.

### Payment Settings

For SimplePay integration, you need to configure:

```php
$furik_production_system = false; // Set to true for production
$furik_payment_merchant = "YOUR_MERCHANT_ID";
$furik_payment_secret_key = "YOUR_SECRET_KEY";
```

The IPN URL should point to your WordPress site with the parameter:
```
https://yoursite.com/?furik_process_ipn=1
```

## Shortcodes

Furik provides several shortcodes to display donation-related content:

- `[furik_donate_form]`: Displays a donation form with customizable parameters
- `[furik_progress]`: Shows a progress bar for campaign fundraising goals
- `[furik_campaigns]`: Lists all child campaigns
- `[furik_donations]`: Lists all donations to the current campaign
- `[furik_donation_sum]`: Displays the total amount donated to a campaign
- `[furik_payment_info]`: Shows payment information after a successful transaction

Example:
```
[furik_donate_form amount=5000 enable_monthly=true enable_newsletter=true]
```

## Campaign Management

Furik adds a "Campaign" post type to WordPress. Campaigns can be organized hierarchically with parent and child campaigns. Each campaign can have its own:

- Fundraising goal
- Custom donation form
- Progress tracking
- Associated donations

## Recurring Donations

The plugin supports monthly recurring donations with automated payment processing. Key features:

- Card registration for future payments
- Scheduled processing of recurring payments
- Management interface for recurring donations
- Tools for canceling recurring payments
