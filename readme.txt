=== M-Pesa Payment Gateway for WooCommerce ===
Contributors: davisonpro
Tags: mpesa, payment, gateway, woocommerce, kenya
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamless M-Pesa payment gateway for WooCommerce with STK Push, C2B payments, and WooCommerce Blocks support.

== Description ==

Accept M-Pesa mobile money payments directly in your WooCommerce store. This plugin integrates Safaricom's M-Pesa payment service, allowing customers in Kenya to pay using their mobile phones.

= Key Features =

* **STK Push (Lipa Na M-Pesa Online)** - Automatic payment prompts sent directly to customer's phone
* **C2B Payments** - Accept manual M-Pesa payments
* **WooCommerce Blocks** - Full support for the modern Gutenberg checkout
* **HPOS Compatible** - Works with High-Performance Order Storage
* **Transaction Reversals** - Automatic refund processing
* **Real-time Callbacks** - Instant payment confirmation
* **Comprehensive Logging** - Debug mode for troubleshooting
* **Secure** - Encrypted credentials, webhook validation, and phone number masking

= Perfect For =

* E-commerce stores in Kenya
* Businesses accepting mobile money
* WooCommerce shops wanting local payment methods
* Stores using the new WooCommerce Blocks checkout

= Modern Architecture =

Built with modern PHP practices including:

* PSR-4 autoloading and namespacing
* Dependency injection via service container
* Type-safe PHP 7.4+ with strict typing
* SOLID principles implementation
* Comprehensive error handling

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.3 or higher
* PHP 7.4 or higher (8.0+ recommended)
* Safaricom Daraja API credentials
* SSL certificate (HTTPS)

= Getting Started =

1. Install and activate the plugin
2. Register at [Safaricom Daraja Portal](https://developer.safaricom.co.ke)
3. Create an app and get your credentials
4. Go to **WooCommerce → Settings → Payments → M-Pesa**
5. Enter your credentials and configure settings
6. Test with sandbox mode before going live

= Documentation =

For detailed documentation, configuration guides, and developer resources, visit the [GitHub repository](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway).

= Support =

* [GitHub Issues](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/issues)
* [Documentation](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/wiki)
* Email: davis@davisonpro.dev

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New**
3. Search for "M-Pesa Payment Gateway"
4. Click **Install Now** and then **Activate**

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded file and click **Install Now**
4. Activate the plugin

= After Installation =

1. Navigate to **WooCommerce → Settings → Payments**
2. Enable **Lipa Na M-Pesa** and click **Manage**
3. Enter your Safaricom Daraja API credentials:
   - Consumer Key
   - Consumer Secret
   - Business Shortcode
   - Passkey
4. Select environment (Sandbox for testing, Live for production)
5. Configure optional settings as needed
6. Save changes

= Testing in Sandbox =

Use these sandbox credentials for testing:

* Shortcode: 174379
* Passkey: bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
* Test phone: 254708374149
* Test PIN: 1234

== Frequently Asked Questions ==

= Do I need a Safaricom Daraja account? =

Yes, you need to register at [developer.safaricom.co.ke](https://developer.safaricom.co.ke) and create an app to get API credentials.

= Does this work with WooCommerce Blocks? =

Yes! The plugin fully supports the modern WooCommerce Blocks checkout introduced in WooCommerce 8.0+.

= Is HPOS (High-Performance Order Storage) supported? =

Yes, the plugin is fully compatible with WooCommerce's HPOS feature.

= How do I test before going live? =

Set the environment to "Sandbox" in the plugin settings and use Safaricom's test credentials. Test phone: 254708374149, PIN: 1234.

= What phone number format is required? =

Phone numbers should be in Kenyan format: 254XXXXXXXXX (e.g., 254712345678). The plugin automatically formats numbers entered by customers.

= How do I handle callbacks? =

The plugin automatically handles payment callbacks from Safaricom. Ensure your site is accessible via HTTPS and not blocked by a firewall.

= Can I process refunds? =

Yes, enable "Transaction Reversals" in settings. Refunds initiated from WooCommerce will be automatically processed via M-Pesa.

= Where can I view transaction logs? =

Enable debug mode in the plugin settings, then go to **WooCommerce → Status → Logs** and select the `mpesa-payment-gateway-*` log file.

= What about security? =

The plugin implements multiple security measures:
* API credentials are never logged
* Phone numbers are masked in logs
* Webhook signature validation
* Input sanitization and output escaping
* Secure token caching

= Can I customize the payment gateway? =

Yes, the plugin provides WordPress hooks and filters for developers. Check the documentation for available hooks.

== Screenshots ==

1. Payment gateway settings page
2. M-Pesa option at checkout
3. Phone number input field
4. STK Push notification on customer's phone
5. Payment confirmation
6. Order details with M-Pesa transaction info
7. WooCommerce Blocks checkout integration
8. Transaction logs

== Changelog ==

= 2.0.0 - 2024-11-13 =
* Complete plugin refactor with modern architecture
* Added: PSR-4 autoloading and namespacing
* Added: Dependency injection via ServiceContainer
* Added: Full WooCommerce Blocks checkout support
* Added: HPOS (High-Performance Order Storage) compatibility
* Added: Comprehensive logging system
* Added: Type-safe PHP 7.4+ with strict typing
* Added: Enhanced security features
* Added: Automatic token caching
* Added: Phone number validation
* Added: Real-time payment status updates
* Changed: Improved code organization with modern architecture
* Changed: Better error handling and user feedback
* Fixed: PHP 8.2+ compatibility warnings
* Fixed: Label overlapping in Blocks checkout
* Security: Credentials never logged
* Security: Phone numbers masked in logs
* Security: Webhook signature validation
* Performance: Token caching with 55-minute TTL
* Performance: Optimized database queries

= 1.0.0 =
* Initial release
* STK Push functionality
* C2B payments
* Transaction reversals
* Classic WooCommerce checkout support

== Upgrade Notice ==

= 2.0.0 =
Major update with complete refactor. Adds WooCommerce Blocks support, HPOS compatibility, and enhanced security. Fully backward compatible with existing installations.

== Developer Documentation ==

= Hooks =

**Actions:**

`woo_mpesa_payment_complete` - Fired when payment succeeds
`woo_mpesa_payment_failed` - Fired when payment fails

**Filters:**

`woo_mpesa_api_config` - Modify API configuration

= Example Usage =

`
add_action('woo_mpesa_payment_complete', function($order, $paymentData) {
    $transactionId = $paymentData['MpesaReceiptNumber'];
    // Your custom code here
}, 10, 2);
`

= GitHub =

Contribute or report issues: [github.com/Davisonpro/woocommerce-mpesa-payment-gateway](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway)

== Privacy Policy ==

This plugin:

* Does not collect or store personal data beyond what WooCommerce collects
* Sends phone numbers to Safaricom M-Pesa API for payment processing
* Stores M-Pesa transaction IDs with orders for reference
* Masks phone numbers in debug logs for privacy
* Does not share data with third parties except Safaricom for payment processing

== Credits ==

Developed by [Davison Pro](https://davisonpro.dev)

M-Pesa and Safaricom are trademarks of Safaricom Limited.

