# M-Pesa Payment Gateway for WooCommerce

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-brightgreen.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.3%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0-red.svg)](LICENSE)

M-Pesa payment gateway for WooCommerce. Accept mobile money payments directly in your WordPress store with support for STK Push, C2B payments, and automatic transaction processing.

**Developed by [Davison Pro](https://davisonpro.dev)**

---

## Overview

This plugin integrates Safaricom's M-Pesa mobile payment service with WooCommerce, allowing customers to pay using their M-Pesa accounts. It supports both the classic WooCommerce checkout and the modern Blocks-based checkout.

### What's Included

- STK Push (Lipa Na M-Pesa Online) for automatic payment prompts
- C2B (Customer to Business) for manual payments
- Transaction reversals and refunds
- Real-time payment callbacks
- WooCommerce Blocks compatibility
- Comprehensive logging and debugging tools

## Features

### Payment Methods
- STK Push (Lipa Na M-Pesa Online) - Automatic payment prompts
- C2B (Customer to Business) - Manual payment processing
- Transaction reversals and refunds
- Real-time payment callbacks
- Transaction history and order meta

### WooCommerce Integration
- Classic WooCommerce checkout support
- WooCommerce Blocks (Gutenberg) checkout support
- HPOS (High-Performance Order Storage) compatible
- Order status automation
- Payment method customization

### Technical Features
- PSR-4 autoloading with namespaces
- Dependency injection via service container
- Type-safe PHP 7.4+ with strict typing
- SOLID principles implementation
- Comprehensive error handling
- Detailed logging system

### Security
- Webhook signature validation
- Encrypted API credentials
- Input sanitization and validation
- Secure token caching
- Phone number masking in logs

### Developer Tools
- WordPress hooks and filters
- Extensible service architecture
- Modern JavaScript (ES6+)
- PHPDoc documentation
- Debug mode with detailed logs

## Requirements

- WordPress 5.8+
- WooCommerce 5.3+
- PHP 7.4+ (8.0+ recommended)
- Safaricom Daraja API credentials

## Installation

### Via Composer

```bash
composer require davisonpro/mpesa-payment-gateway
```

### Manual Installation

1. Download the plugin from [GitHub](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway)
2. Upload to `/wp-content/plugins/mpesa-payment-gateway`
3. Navigate to the plugin directory:
   ```bash
   cd wp-content/plugins/mpesa-payment-gateway
   composer install --no-dev --optimize-autoloader
   ```
4. Activate the plugin in WordPress admin
5. Go to **WooCommerce → Settings → Payments → M-Pesa** to configure

## Configuration

### Get API Credentials

1. Register at [Safaricom Developer Portal](https://developer.safaricom.co.ke)
2. Create a new app (select "Lipa Na M-Pesa Online")
3. Note down:
   - Consumer Key
   - Consumer Secret
   - Business Shortcode
   - Passkey

### Plugin Settings

Navigate to **WooCommerce → Settings → Payments → M-Pesa**

#### Required Settings
- **Environment**: Choose `Sandbox` for testing or `Live` for production
- **Business Shortcode**: Your M-Pesa paybill or till number
- **Consumer Key**: From Daraja portal
- **Consumer Secret**: From Daraja portal
- **Passkey**: Lipa Na M-Pesa Online passkey

#### Optional Settings
- **Order Status**: Order status after successful payment (default: Completed)
- **Enable C2B**: Allow manual M-Pesa payments
- **Enable Reversals**: Automatic transaction reversals on refunds
- **Debug Mode**: Enable detailed logging

### Testing

Sandbox credentials for testing:
```
Shortcode: 174379
Consumer Key: (your sandbox key)
Consumer Secret: (your sandbox secret)
Passkey: bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919

Test phone: 254708374149
Test PIN: 1234
```

## Usage

### For Customers

1. Customer adds products to cart and proceeds to checkout
2. Selects "Lipa Na M-Pesa" as payment method
3. Enters M-Pesa phone number
4. Receives STK Push prompt on phone
5. Enters M-Pesa PIN to complete payment
6. Order is automatically updated upon payment confirmation

### For Developers

#### Initiate Payment Programmatically

```php
use WooMpesa\Core\ServiceContainer;
use WooMpesa\Services\MpesaApiService;

$container = ServiceContainer::getInstance();
$apiService = $container->get(MpesaApiService::class);

$result = $apiService->stkPush(
    phone: '254712345678',
    amount: 1000.00,
    reference: 'ORDER123',
    description: 'Payment for Order #123'
);

if (is_wp_error($result)) {
    // Handle error
    $error = $result->get_error_message();
} else {
    // Payment initiated
    $merchantRequestId = $result['MerchantRequestID'];
}
```

#### Query Transaction Status

```php
$result = $apiService->stkQuery('MERCHANT_REQUEST_ID');
```

## Hooks

### Actions

#### `woo_mpesa_payment_complete`
Fired when a payment is successfully completed.

```php
add_action('woo_mpesa_payment_complete', function($order, $paymentData) {
    // $order: WC_Order object
    // $paymentData: Array with transaction details
    
    $transactionId = $paymentData['MpesaReceiptNumber'];
    $phone = $paymentData['PhoneNumber'];
    $amount = $paymentData['Amount'];
    
    // Your custom code here
}, 10, 2);
```

#### `woo_mpesa_payment_failed`
Fired when a payment fails.

```php
add_action('woo_mpesa_payment_failed', function($order, $resultCode, $resultDesc) {
    // $order: WC_Order object
    // $resultCode: M-Pesa result code
    // $resultDesc: Error description
    
    // Your custom code here
}, 10, 3);
```

### Filters

#### `woo_mpesa_api_config`
Modify API configuration before requests.

```php
add_filter('woo_mpesa_api_config', function($config) {
    // $config: Array of API settings
    // Modify and return
    return $config;
});
```

## Architecture

### Directory Structure

```
woocommerce-mpesa-payment-gateway/
├── assets/
│   ├── css/                    # Stylesheets
│   ├── images/                 # Images and logos
│   └── js/
│       └── blocks/             # WooCommerce Blocks integration
├── includes/
│   ├── Admin/                  # Admin interface
│   │   └── AdminInterface.php
│   ├── Blocks/                 # Blocks payment method
│   │   └── BlocksIntegration.php
│   ├── Core/                   # Core classes
│   │   ├── Config.php         # Configuration management
│   │   └── ServiceContainer.php # Dependency injection
│   ├── Gateway/                # Payment gateway
│   │   ├── CallbackHandler.php # Webhook processing
│   │   └── MpesaGateway.php   # Main gateway class
│   ├── Hooks/                  # WordPress hooks
│   │   └── HookManager.php
│   └── Services/               # Business logic
│       ├── LoggerService.php  # Logging
│       └── MpesaApiService.php # M-Pesa API wrapper
├── languages/                  # Translation files
├── templates/                  # Template files
├── composer.json
└── woocommerce-mpesa-payment-gateway.php              # Main plugin file
```

### Code Standards

- PSR-4 autoloading
- PSR-12 coding style
- WordPress coding standards
- Type declarations (PHP 7.4+)
- PHPDoc comments
- SOLID principles

## Development

### Local Setup

```bash
git clone https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway.git
cd mpesa-payment-gateway
composer install
npm install
```

### Building a Release Package

```bash
# Build distributable zip file
npm run build

# Output: dist/mpesa-payment-gateway-2.0.0.zip
```

See [BUILD.md](BUILD.md) for detailed build instructions.

### Code Quality

```bash
# Code sniffer
composer phpcs

# Static analysis  
composer phpstan

# Auto-fix formatting
composer format
```

### Testing

1. Configure sandbox environment
2. Test STK Push flow
3. Test C2B payments
4. Test callback processing
5. Verify both checkout types

## Security

- API credentials encrypted and never logged
- Phone numbers masked in debug logs
- Webhook signature validation
- Input sanitization and output escaping
- SQL injection prevention with prepared statements
- CSRF protection via nonces

## Troubleshooting

### Payment Not Working

1. Check API credentials are correct
2. Verify environment setting (sandbox/live)
3. Enable debug mode and check logs
4. Ensure phone number format is correct (254XXXXXXXXX)
5. Check SSL certificate is valid

### Callback Not Received

1. Verify callback URL is publicly accessible
2. Check firewall is not blocking requests
3. Review webhook signature setting
4. Check M-Pesa transaction in Daraja portal

### Debug Logs

Enable debug mode in settings, then view logs at:
**WooCommerce → Status → Logs** (select `mpesa-payment-gateway-*` log file)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines.

## License

This plugin is open source software licensed under [GPL-3.0-or-later](LICENSE).

## Support

- **Issues**: [GitHub Issues](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/issues)
- **Email**: davis@davisonpro.dev
- **Documentation**: [GitHub Wiki](https://github.com/Davisonpro/woocommerce-mpesa-payment-gateway/wiki)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

---

**Developed by [Davison Pro](https://davisonpro.dev)**

