<?php
/**
 * M-Pesa Payment Gateway
 * 
 * Main payment gateway class for WooCommerce
 *
 * @package WooMpesa\Gateway
 */

declare(strict_types=1);

namespace WooMpesa\Gateway;

use WC_Payment_Gateway;
use WC_Order;
use WooMpesa\Services\MpesaApiService;
use WooMpesa\Services\LoggerService;
use WooMpesa\Services\CurrencyConverter;
use WooMpesa\Core\Config;

/**
 * M-Pesa Gateway class
 */
final class MpesaGateway extends WC_Payment_Gateway
{
    /**
     * M-Pesa API Service
     *
     * @var MpesaApiService
     */
    private MpesaApiService $apiService;

    /**
     * Logger Service
     *
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * Currency Converter Service
     *
     * @var CurrencyConverter
     */
    private CurrencyConverter $converter;

    /**
     * Constructor
     *
     * @param MpesaApiService|null $apiService API service
     * @param LoggerService|null $logger Logger service
     * @param CurrencyConverter|null $converter Currency converter
     */
    public function __construct(
        ?MpesaApiService $apiService = null,
        ?LoggerService $logger = null,
        ?CurrencyConverter $converter = null
    ) {
        // Get dependencies from service container if not provided
        $container = \WooMpesa\Core\ServiceContainer::getInstance();
        
        $this->apiService = $apiService ?? $container->get(MpesaApiService::class);
        $this->logger = $logger ?? $container->get(LoggerService::class);
        $this->converter = $converter ?? $container->get(CurrencyConverter::class);

        $this->id = 'mpesa';
        $this->icon = Config::getUrl('assets/images/mpesa-logo.png');
        $this->method_title = __('Lipa Na M-Pesa', 'mpesa-payment-gateway');
        $this->method_description = __('Accept M-Pesa payments via Safaricom Daraja API', 'mpesa-payment-gateway');
        $this->has_fields = true;
        $this->supports = ['products'];

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Set user-configurable properties
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Initialize hooks
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function initHooks(): void
    {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
            $this, 
            'process_admin_options'
        ]);
        
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankYouPage'], 10, 1);
        add_action('woocommerce_email_before_order_table', [$this, 'emailInstructions'], 10, 4);
        add_action('woocommerce_api_mpesa-payment-gateway', [$this, 'handleCallback']);
        
        // Blocks checkout support
        add_action('woocommerce_store_api_checkout_update_order_from_request', [
            $this, 
            'updateOrderMetaFromBlocks'
        ], 10, 2);
    }

    /**
     * Initialize gateway form fields
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'mpesa-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable M-Pesa Payment Gateway', 'mpesa-payment-gateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'mpesa-payment-gateway'),
                'type' => 'text',
                'description' => __('Payment method title shown to customers during checkout.', 'mpesa-payment-gateway'),
                'default' => __('Lipa Na M-Pesa', 'mpesa-payment-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'mpesa-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Payment method description shown to customers during checkout.', 'mpesa-payment-gateway'),
                'default' => __('Pay securely using your M-Pesa mobile money account.', 'mpesa-payment-gateway'),
                'desc_tip' => true,
            ],
            'env_section' => [
                'title' => __('Environment Settings', 'mpesa-payment-gateway'),
                'type' => 'title',
                'description' => __('Configure your M-Pesa environment settings.', 'mpesa-payment-gateway'),
            ],
            'env' => [
                'title' => __('Environment', 'mpesa-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'sandbox' => __('Sandbox', 'mpesa-payment-gateway'),
                    'live' => __('Live', 'mpesa-payment-gateway'),
                ],
                'default' => 'sandbox',
                'desc_tip' => true,
                'description' => __('Select sandbox for testing or live for production.', 'mpesa-payment-gateway'),
            ],
            'idtype' => [
                'title' => __('Business Type', 'mpesa-payment-gateway'),
                'type' => 'select',
                'options' => [
                    Config::TRANSACTION_TYPES['PAYBILL'] => __('Paybill', 'mpesa-payment-gateway'),
                    Config::TRANSACTION_TYPES['TILL'] => __('Till Number', 'mpesa-payment-gateway'),
                ],
                'default' => Config::TRANSACTION_TYPES['PAYBILL'],
                'desc_tip' => true,
                'description' => __('Select your M-Pesa business type.', 'mpesa-payment-gateway'),
            ],
            'api_section' => [
                'title' => __('API Credentials', 'mpesa-payment-gateway'),
                'type' => 'title',
                'description' => __('Enter your Daraja API credentials. Get them from <a href="https://developer.safaricom.co.ke" target="_blank">Safaricom Developer Portal</a>.', 'mpesa-payment-gateway'),
            ],
            'shortcode' => [
                'title' => __('Business Shortcode', 'mpesa-payment-gateway'),
                'type' => 'text',
                'description' => __('Your M-Pesa business shortcode (Paybill or Till number).', 'mpesa-payment-gateway'),
                'default' => '174379', // Sandbox default
                'desc_tip' => true,
            ],
            'key' => [
                'title' => __('Consumer Key', 'mpesa-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Daraja API consumer key.', 'mpesa-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'secret' => [
                'title' => __('Consumer Secret', 'mpesa-payment-gateway'),
                'type' => 'password',
                'description' => __('Your Daraja API consumer secret.', 'mpesa-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ],
            'passkey' => [
                'title' => __('Passkey', 'mpesa-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Lipa Na M-Pesa Online passkey.', 'mpesa-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
                'css' => 'width: 100%;',
            ],
            'signature' => [
                'title' => __('Webhook Signature', 'mpesa-payment-gateway'),
                'type' => 'password',
                'description' => __('Webhook security signature. Keep this secret.', 'mpesa-payment-gateway'),
                'default' => wp_generate_password(32, false),
                'desc_tip' => true,
            ],
            'advanced_section' => [
                'title' => __('Advanced Options', 'mpesa-payment-gateway'),
                'type' => 'title',
            ],
            'completion_status' => [
                'title' => __('Order Status on Payment', 'mpesa-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'completed' => __('Completed', 'mpesa-payment-gateway'),
                    'processing' => __('Processing', 'mpesa-payment-gateway'),
                    'on-hold' => __('On Hold', 'mpesa-payment-gateway'),
                ],
                'default' => 'completed',
                'description' => __('Order status after successful payment.', 'mpesa-payment-gateway'),
                'desc_tip' => true,
            ],
            'enable_c2b' => [
                'title' => __('Enable C2B', 'mpesa-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable manual M-Pesa payments (C2B)', 'mpesa-payment-gateway'),
                'default' => 'no',
                'description' => __('Allows customers to manually send money via M-Pesa.', 'mpesa-payment-gateway'),
            ],
            'enable_reversal' => [
                'title' => __('Enable Reversals', 'mpesa-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable automatic transaction reversals', 'mpesa-payment-gateway'),
                'default' => 'no',
            ],
            'debug' => [
                'title' => __('Debug Mode', 'mpesa-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable debug logging', 'mpesa-payment-gateway'),
                'default' => 'no',
                'description' => sprintf(
                    /* translators: %s: Log file path */
                    __('Log M-Pesa events. Logs can be found in %s', 'mpesa-payment-gateway'),
                    '<code>WooCommerce > Status > Logs</code>'
                ),
            ],
            'currency_section' => [
                'title' => __('Currency Conversion', 'mpesa-payment-gateway'),
                'type' => 'title',
                'description' => __('M-Pesa only accepts payments in KES. Configure how other currencies should be converted.', 'mpesa-payment-gateway'),
            ],
            'auto_exchange_rates' => [
                'title' => __('Auto Exchange Rates', 'mpesa-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Automatically fetch current exchange rates', 'mpesa-payment-gateway'),
                'default' => 'yes',
                'description' => __('Automatically fetch and update exchange rates every 6 hours. Recommended for accurate conversions.', 'mpesa-payment-gateway'),
            ],
            'exchange_rates' => [
                'title' => __('Exchange Rates', 'mpesa-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Enter exchange rates (one per line) in format: <strong>CURRENCY=RATE</strong><br>Example:<br><code>USD=130.50<br>EUR=140.25<br>GBP=165.80<br>TZS=0.055<br>UGX=0.035<br>ZAR=7.50</code><br>Rate = How many KES for 1 unit of currency. Used when auto rates are disabled or unavailable.', 'mpesa-payment-gateway'),
                'default' => "USD=130\nEUR=140\nGBP=165\nTZS=0.055\nUGX=0.035",
                'desc_tip' => false,
                'css' => 'min-height: 120px; font-family: monospace;',
                'placeholder' => "USD=130.50\nEUR=140.25\nGBP=165.80",
            ],
        ];
    }

    /**
     * Payment field (phone number input)
     *
     * @return void
     */
    public function payment_fields(): void
    {
        if ($this->description) {
            echo wp_kses_post(wpautop(wptexturize($this->description)));
        }

        // Show currency conversion info if applicable
        $this->displayCurrencyConversionNotice();

        echo '<div class="mpesa-payment-gateway-payment-fields">';
        woocommerce_form_field('billing_mpesa_phone', [
            'type' => 'tel',
            'required' => true,
            'label' => __('M-Pesa Phone Number', 'mpesa-payment-gateway'),
            'placeholder' => __('e.g. 254712345678', 'mpesa-payment-gateway'),
            'custom_attributes' => [
                'pattern' => '[0-9]*',
                'inputmode' => 'numeric',
                'maxlength' => '12',
            ],
        ]);
        echo '</div>';
    }

    /**
     * Display currency conversion notice to customer
     *
     * @return void
     */
    private function displayCurrencyConversionNotice(): void
    {
        if (!WC()->cart) {
            return;
        }

        $currency = get_woocommerce_currency();
        
        if ($currency === 'KES') {
            return;
        }

        $cartTotal = (float) WC()->cart->get_total('');
        
        // Don't show conversion for empty cart
        if ($cartTotal <= 0) {
            return;
        }

        $conversionInfo = $this->converter->getConversionInfo($cartTotal, $currency);

        if (is_wp_error($conversionInfo)) {
            echo '<div class="woocommerce-info" style="margin-bottom: 1em;">';
            echo '<strong>' . esc_html__('Currency Conversion Required', 'mpesa-payment-gateway') . ':</strong> ';
            echo esc_html($conversionInfo->get_error_message());
            echo '</div>';
            return;
        }

        echo '<div class="woocommerce-info" style="margin-bottom: 1em;">';
        echo '<strong>' . esc_html__('Currency Conversion', 'mpesa-payment-gateway') . ':</strong> ';
        echo wp_kses_post(
            sprintf(
                /* translators: 1: Original amount with currency, 2: KES amount, 3: Currency code, 4: Exchange rate */
                __('Your order total of %1$s will be charged as KES %2$s (Rate: 1 %3$s = %4$s KES)', 'mpesa-payment-gateway'),
                '<strong>' . wc_price($conversionInfo['amount'], ['currency' => $currency]) . '</strong>',
                '<strong>' . number_format($conversionInfo['kes_amount'], 2, '.', ',') . '</strong>',
                esc_html($currency),
                number_format($conversionInfo['rate'], 4, '.', ',')
            )
        );
        echo '</div>';
    }

    /**
     * Validate payment fields
     *
     * @return bool
     */
    public function validate_fields(): bool
    {
        // Nonce is verified by WooCommerce checkout process
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $phone = isset($_POST['billing_mpesa_phone']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field(wp_unslash($_POST['billing_mpesa_phone'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            : '';

        if (empty($phone)) {
            wc_add_notice(
                __('M-Pesa phone number is required.', 'mpesa-payment-gateway'),
                'error'
            );
            return false;
        }

        if (!Config::validatePhone($phone)) {
            wc_add_notice(
                __('Please enter a valid M-Pesa phone number (e.g. 254712345678).', 'mpesa-payment-gateway'),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Process payment
     *
     * @param int $orderId Order ID
     * @return array
     */
    public function process_payment($orderId): array
    {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return $this->paymentError(__('Invalid order.', 'mpesa-payment-gateway'));
        }

        $phone = $this->getPhoneNumber($order);
        $orderAmount = (float) $order->get_total();
        $orderCurrency = $order->get_currency();

        // Convert to KES (M-Pesa only accepts KES)
        $conversionInfo = $this->converter->getConversionInfo($orderAmount, $orderCurrency);
        
        if (is_wp_error($conversionInfo)) {
            return $this->paymentError($conversionInfo->get_error_message());
        }

        $kesAmount = $conversionInfo['kes_amount'];

        // Store conversion info
        if ($conversionInfo['converted']) {
            $order->update_meta_data('_mpesa_original_amount', $conversionInfo['amount']);
            $order->update_meta_data('_mpesa_original_currency', $conversionInfo['currency']);
            $order->update_meta_data('_mpesa_kes_amount', $kesAmount);
            $order->update_meta_data('_mpesa_exchange_rate', $conversionInfo['rate']);
            
            $order->add_order_note(
                sprintf(
                    /* translators: 1: Original amount, 2: Original currency, 3: KES amount, 4: Exchange rate */
                    __('Currency converted: %1$s %2$s → KES %3$s (Rate: %4$s)', 'mpesa-payment-gateway'),
                    number_format($conversionInfo['amount'], 2, '.', ','),
                    $conversionInfo['currency'],
                    number_format($kesAmount, 2, '.', ','),
                    number_format($conversionInfo['rate'], 4, '.', ',')
                )
            );
        }

        $this->logger->logPaymentAttempt($orderId, $phone, $kesAmount);

        $result = $this->apiService->stkPush(
            $phone,
            $kesAmount,
            (string) $orderId,
            sprintf(
                /* translators: %s: Order ID */
                __('Order #%s', 'mpesa-payment-gateway'),
                $orderId
            )
        );

        if (is_wp_error($result)) {
            $this->logger->logPaymentFailure($orderId, $result->get_error_message());
            return $this->paymentError($result->get_error_message());
        }

        if (isset($result['errorCode'])) {
            $message = sprintf(
                '%s: %s',
                $result['errorCode'],
                $result['errorMessage'] ?? __('Unknown error', 'mpesa-payment-gateway')
            );
            $this->logger->logPaymentFailure($orderId, $message);
            return $this->paymentError($message);
        }

        if (isset($result['MerchantRequestID'])) {
            $order->update_meta_data('_mpesa_merchant_request_id', $result['MerchantRequestID']);
            $order->update_meta_data('_mpesa_checkout_request_id', $result['CheckoutRequestID'] ?? '');
            $order->update_meta_data('_mpesa_phone', Config::formatPhoneNumber($phone));
            $order->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: Phone number, 2: Merchant Request ID */
                    __('M-Pesa STK push sent to %1$s. Merchant Request ID: %2$s', 'mpesa-payment-gateway'),
                    $phone,
                    $result['MerchantRequestID']
                )
            );

            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        return $this->paymentError(__('Failed to initiate payment.', 'mpesa-payment-gateway'));
    }

    /**
     * Get phone number from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function getPhoneNumber(WC_Order $order): string
    {
        // Check POST data (classic checkout)
        // Nonce is verified by WooCommerce checkout process
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['billing_mpesa_phone'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return sanitize_text_field(wp_unslash($_POST['billing_mpesa_phone']));
        }

        // Check order meta (Blocks checkout)
        $phone = $order->get_meta('_billing_mpesa_phone');
        if ($phone) {
            return sanitize_text_field($phone);
        }

        // Fallback to billing phone
        return $order->get_billing_phone();
    }

    /**
     * Return payment error response
     *
     * @param string $message Error message
     * @return array
     */
    private function paymentError(string $message): array
    {
        wc_add_notice(
            sprintf(
                /* translators: %s: Error message */
                __('Payment Error: %s', 'mpesa-payment-gateway'),
                $message
            ),
            'error'
        );

        return [
            'result' => 'failure',
            'redirect' => '',
        ];
    }

    /**
     * Thank you page content
     *
     * @param int $orderId Order ID
     * @return void
     */
    public function thankYouPage(int $orderId): void
    {
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return;
        }

        if ($order->get_status() === 'completed') {
            echo '<p>' . esc_html__('Your payment has been received. Thank you for your purchase!', 'mpesa-payment-gateway') . '</p>';
            return;
        }

        include Config::getPath('templates/thankyou-page.php');
    }

    /**
     * Add content to customer emails
     *
     * @param WC_Order $order Order object
     * @param bool $sentToAdmin Sent to admin
     * @param bool $plainText Plain text email
     * @param object $email Email object
     * @return void
     */
    public function emailInstructions($order, $sentToAdmin, $plainText, $email): void
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        if ($email->id === 'customer_completed_order' && $order->get_transaction_id()) {
            echo '<p><strong>' . esc_html__('M-Pesa Transaction ID:', 'mpesa-payment-gateway') . '</strong> ' .
                 esc_html($order->get_transaction_id()) . '</p>';
        }
    }

    /**
     * Update order meta from Blocks checkout
     *
     * @param WC_Order $order Order object
     * @param object $request Request object
     * @return void
     */
    public function updateOrderMetaFromBlocks($order, $request): void
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $paymentData = $request['payment_data'] ?? [];
        
        if (isset($paymentData['billing_mpesa_phone'])) {
            $phone = sanitize_text_field($paymentData['billing_mpesa_phone']);
            $order->update_meta_data('_billing_mpesa_phone', $phone);
            $order->save();
        }
    }

    /**
     * Handle M-Pesa callbacks
     *
     * @return void
     */
    public function handleCallback(): void
    {
        // Nonce verification not applicable for M-Pesa callbacks (external API)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        $handler = new CallbackHandler($this->apiService, $this->logger);

        switch ($action) {
            case 'reconcile':
                $handler->handleReconciliation();
                break;
            case 'confirm':
                $handler->handleC2BConfirmation();
                break;
            case 'validate':
                $handler->handleC2BValidation();
                break;
            default:
                wp_send_json(['error' => 'Invalid action'], 400);
        }
    }

}

