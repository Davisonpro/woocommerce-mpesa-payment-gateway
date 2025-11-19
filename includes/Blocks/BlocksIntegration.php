<?php
/**
 * Blocks Integration
 * 
 * WooCommerce Blocks checkout integration
 *
 * @package WooMpesa\Blocks
 */

declare(strict_types=1);

namespace WooMpesa\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooMpesa\Core\Config;
use WooMpesa\Core\ServiceContainer;
use WooMpesa\Services\CurrencyConverter;

/**
 * Blocks Integration class
 */
final class BlocksIntegration extends AbstractPaymentMethodType
{
    /**
     * Payment method name
     *
     * @var string
     */
    protected $name = 'mpesa';

    /**
     * Gateway instance
     *
     * @var object|null
     */
    private $gateway;

    /**
     * Initialize the payment method
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_mpesa_settings', []);
        
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name] ?? null;
    }

    /**
     * Check if payment method is active
     *
     * @return bool
     */
    public function is_active(): bool
    {
        return $this->gateway && $this->gateway->is_available();
    }

    /**
     * Get payment method script handles
     *
     * @return array
     */
    public function get_payment_method_script_handles(): array
    {
        $scriptAssetPath = Config::getPath('assets/js/blocks/index.asset.php');
        $scriptAsset = file_exists($scriptAssetPath)
            ? require $scriptAssetPath
            : [
                'dependencies' => [],
                'version' => Config::getVersion(),
            ];

        wp_register_script(
            'mpesa-payment-gateway-blocks',
            Config::getUrl('assets/js/blocks/index.js'),
            $scriptAsset['dependencies'],
            $scriptAsset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'mpesa-payment-gateway-blocks',
                'mpesa-payment-gateway',
                Config::getPath('languages')
            );
        }

        return ['mpesa-payment-gateway-blocks'];
    }

    /**
     * Get payment method data for frontend
     *
     * @return array
     */
    public function get_payment_method_data(): array
    {
        $data = [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
            'icon' => Config::getUrl('assets/images/mpesa-logo.png'),
            'phoneLabel' => __('M-Pesa Phone Number', 'mpesa-payment-gateway'),
            'phonePlaceholder' => __('e.g. 254712345678', 'mpesa-payment-gateway'),
            'phoneRequired' => __('M-Pesa phone number is required.', 'mpesa-payment-gateway'),
            'phoneInvalid' => __('Please enter a valid M-Pesa phone number.', 'mpesa-payment-gateway'),
            'conversionInfo' => $this->getConversionInfo(),
        ];

        return $data;
    }

    /**
     * Get currency conversion info for cart
     *
     * @return array|null Conversion info or null
     */
    private function getConversionInfo(): ?array
    {
        if (!WC()->cart) {
            return null;
        }

        $currency = get_woocommerce_currency();
        
        if ($currency === 'KES') {
            return null;
        }

        $cartTotal = (float) WC()->cart->get_total('');
        
        // Don't show conversion for empty cart
        if ($cartTotal <= 0) {
            return null;
        }
        
        try {
            $container = ServiceContainer::getInstance();
            $converter = $container->get(CurrencyConverter::class);
            $conversionInfo = $converter->getConversionInfo($cartTotal, $currency);

            if (is_wp_error($conversionInfo)) {
                return [
                    'error' => true,
                    'message' => $conversionInfo->get_error_message(),
                ];
            }

            return [
                'error' => false,
                'amount' => $conversionInfo['amount'],
                'currency' => $conversionInfo['currency'],
                'kesAmount' => $conversionInfo['kes_amount'],
                'rate' => $conversionInfo['rate'],
                'converted' => $conversionInfo['converted'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get supported features
     *
     * @return array
     */
    public function get_supported_features(): array
    {
        $features = ['products'];
        
        if ($this->gateway && method_exists($this->gateway, 'supports')) {
            foreach ($this->gateway->supports as $feature) {
                if ($this->gateway->supports($feature)) {
                    $features[] = $feature;
                }
            }
        }

        return array_unique($features);
    }
}

