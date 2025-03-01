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

### [furik_back_to_campaign_url]
Provides an URL back to the campaign which received the payment. It requires the campaign_id variable set in the request.

### [furik_campaigns]
Lists all the child campaigns. It can be configured with the `show` parameter which lists the type of data we should list and the order, comma separated. Available data types: `image`, `title`, `excerpt`, `progress_bar`, `completed`, `goal`. The `image` URL is taken from the `IMAGE` custom field of the campaign. Default value: `image,title,excerpt,progress_bar,completed,goal`.

### [furik_donate_form]
Parameters:
 - `amount` (default: 5000): number (HUF), the default amount displayed on the form
 - `enable_cash` (default: false): boolean, enables cash donation
 - `enable_monthly` (default: false): boolean, enables monthly recurring donations. When this option is selected, there's an extra checkbox with the statement pop up, be careful with the design
 - `enable_newsletter` (default: false): boolean, enables newsletter registration. The values are tracked under the `newsletter_status` field. If it's set to 1, the donor chose to sign up. It can be set to any higher value when registering the users for the real newsletter system.

If the `AMOUNT_CONTENT` custom field is set for the campaign or the parent campaign, it replaces the amount box. This field should contain a form field with the name `furik_form_amount`. If the `furik_form_amount` value is `other`, it will use the value of the `furik_form_amount_other` POST variable.

### [furik_donate_link amount=5000]
Prepares a link to the donations page and sets the default amount to the `amount` value. If this is put on a campaign page, the campaign information is included in the donation.

### [furik_donations]
Lists the donations.

### [furik_order_ref]
Displays the order reference if it's valid. Used on the bank transfer thank you pages.

### [furik_payment_info]
Provides information about the payment (date, referece ids), it's used on return page.

### [furik_progress]
Shows the percentage of the collected amount. The full amount can be specified with the "amount" variable, if it's not set, the full amount is shown. The goal of the campaign can be set in the `GOAL` custom field. CSS is required to show the progress bar, recommended CSS for a small red progress bar:

```css
.furik-progress-bar {
    background-color: #aaaaaaa;
    height: 20px;
    padding: 5px;
    width: 200px;
    margin: 5px 0;
    border-radius: 5px;
    box-shadow: 0 1px 1px #444 inset, 0 1px 0 #888;
    }
 
.furik-progress-bar span {
    display: inline-block;
    float: left;
    height: 100%;
    border-radius: 3px;
    box-shadow: 0 1px 0 rgba(255, 255, 255, .5) inset;
    transition: width .4s ease-in-out;
    overflow: hidden;
    background-color: #D44236;
    }
```

### [furik_donation_sum]
Displays the total amount donated to a campaign.

Example usage:
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
