<?php

/**
 * Callback Handler
 * 
 * Handles M-Pesa payment callbacks and webhooks
 *
 * @package WooMpesa\Gateway
 */

declare(strict_types=1);

namespace WooMpesa\Gateway;

use WooMpesa\Services\MpesaApiService;
use WooMpesa\Services\LoggerService;
use WooMpesa\Core\Config;

/**
 * Callback Handler class
 */
final class CallbackHandler
{
    /**
     * API Service
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
     * Constructor
     *
     * @param MpesaApiService $apiService API service
     * @param LoggerService $logger Logger service
     */
    public function __construct(MpesaApiService $apiService, LoggerService $logger)
    {
        $this->apiService = $apiService;
        $this->logger = $logger;
    }

    /**
     * Handle STK Push reconciliation callback
     *
     * @return void
     */
    public function handleReconciliation(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $this->logger->info('STK Reconciliation callback received', ['data' => $data]);

        if (!isset($data['Body']['stkCallback'])) {
            wp_send_json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data'], 400);
            return;
        }

        $callback = $data['Body']['stkCallback'];
        $merchantRequestId = $callback['MerchantRequestID'] ?? '';
        $resultCode = (int) ($callback['ResultCode'] ?? 1);

        $order = $this->findOrderByMerchantRequestId($merchantRequestId);

        if (!$order) {
            $this->logger->warning('Order not found for merchant request', [
                'merchant_request_id' => $merchantRequestId
            ]);
            wp_send_json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            return;
        }

        // Payment successful
        if ($resultCode === 0 && isset($callback['CallbackMetadata'])) {
            $this->processSuccessfulPayment($order, $callback['CallbackMetadata']);
        } else {
            // Payment failed
            $resultDesc = $callback['ResultDesc'] ?? Config::getResultCodeDescription($resultCode);
            $this->processFailedPayment($order, $resultCode, $resultDesc);
        }

        wp_send_json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Handle C2B confirmation callback
     *
     * @return void
     */
    public function handleC2BConfirmation(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $this->logger->info('C2B Confirmation callback received', ['data' => $data]);

        if (empty($data)) {
            wp_send_json(['ResultCode' => 1, 'ResultDesc' => 'Invalid data']);
            return;
        }

        $transactionId = $data['TransID'] ?? '';
        $amount = (float) ($data['TransAmount'] ?? 0);
        $phone = $data['MSISDN'] ?? '';
        $reference = $data['BillRefNumber'] ?? '';

        $order = wc_get_order((int) $reference);

        if (!$order) {
            $this->logger->warning('Order not found for C2B payment', ['reference' => $reference]);
            wp_send_json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            return;
        }

        $orderTotal = (float) $order->get_total();
        $difference = $orderTotal - $amount;

        if (abs($difference) < 0.01) {
            // Exact payment
            $order->payment_complete($transactionId);
            $order->add_order_note(
                sprintf(
                    /* translators: 1: Transaction ID, 2: Phone number */
                    __('M-Pesa payment received. Transaction ID: %1$s, Phone: %2$s', 'woocommerce-mpesa-payment-gateway'),
                    $transactionId,
                    $phone
                )
            );
            $this->logger->logPaymentSuccess((int) $order->get_id(), $transactionId);
        } elseif ($difference > 0) {
            // Underpayment
            $order->update_status('on-hold');
            $order->add_order_note(
                sprintf(
                    /* translators: 1: Expected amount, 2: Received amount, 3: Shortage amount, 4: Transaction ID */
                    __('Partial M-Pesa payment received. Expected: %1$s, Received: %2$s, Shortage: %3$s. Transaction ID: %4$s', 'woocommerce-mpesa-payment-gateway'),
                    $orderTotal,
                    $amount,
                    $difference,
                    $transactionId
                )
            );
        } else {
            // Overpayment
            $order->payment_complete($transactionId);
            $order->add_order_note(
                sprintf(
                    /* translators: 1: Expected amount, 2: Received amount, 3: Excess amount, 4: Transaction ID */
                    __('M-Pesa payment received with overpayment. Expected: %1$s, Received: %2$s, Excess: %3$s. Transaction ID: %4$s', 'woocommerce-mpesa-payment-gateway'),
                    $orderTotal,
                    $amount,
                    abs($difference),
                    $transactionId
                )
            );
        }

        wp_send_json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Handle C2B validation callback
     *
     * @return void
     */
    public function handleC2BValidation(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $this->logger->info('C2B Validation callback received', ['data' => $data]);

        // Always accept for now - you can add custom validation logic here
        wp_send_json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Process successful payment
     *
     * @param \WC_Order $order Order object
     * @param array $metadata Callback metadata
     * @return void
     */
    private function processSuccessfulPayment($order, array $metadata): void
    {
        $items = $metadata['Item'] ?? [];
        $paymentData = [];

        foreach ($items as $item) {
            $paymentData[$item['Name']] = $item['Value'] ?? '';
        }

        $transactionId = $paymentData['MpesaReceiptNumber'] ?? '';
        $phone = $paymentData['PhoneNumber'] ?? '';
        $amount = (float) ($paymentData['Amount'] ?? 0);

        if (empty($transactionId)) {
            $this->logger->error('Transaction ID missing in callback', ['data' => $paymentData]);
            return;
        }

        // Check if already processed
        if ($order->get_transaction_id() === $transactionId) {
            $this->logger->info('Payment already processed', ['transaction_id' => $transactionId]);
            return;
        }

        $settings = get_option('woocommerce_mpesa_settings', []);
        $completionStatus = $settings['completion_status'] ?? 'completed';

        $order->payment_complete($transactionId);
        $order->update_status($completionStatus);
        $order->add_order_note(
            sprintf(
                /* translators: 1: Transaction ID, 2: Amount, 3: Phone number */
                __('M-Pesa payment completed. Transaction ID: %1$s, Amount: %2$s, Phone: %3$s', 'woocommerce-mpesa-payment-gateway'),
                $transactionId,
                $amount,
                $phone
            )
        );

        $this->logger->logPaymentSuccess((int) $order->get_id(), $transactionId);

        do_action('woo_mpesa_payment_complete', $order, $paymentData);
    }

    /**
     * Process failed payment
     *
     * @param \WC_Order $order Order object
     * @param int $resultCode Result code
     * @param string $resultDesc Result description
     * @return void
     */
    private function processFailedPayment($order, int $resultCode, string $resultDesc): void
    {
        $order->update_status('failed');
        $order->add_order_note(
            sprintf(
                /* translators: 1: Error code, 2: Error message */
                __('M-Pesa payment failed. Code: %1$d, Message: %2$s', 'woocommerce-mpesa-payment-gateway'),
                $resultCode,
                $resultDesc
            )
        );

        $this->logger->logPaymentFailure((int) $order->get_id(), $resultDesc);

        do_action('woo_mpesa_payment_failed', $order, $resultCode, $resultDesc);
    }

    /**
     * Find order by merchant request ID
     *
     * @param string $merchantRequestId Merchant request ID
     * @return \WC_Order|null
     */
    private function findOrderByMerchantRequestId(string $merchantRequestId): ?\WC_Order
    {
        $orders = wc_get_orders([
            'meta_key' => '_mpesa_merchant_request_id',
            'meta_value' => $merchantRequestId,
            'limit' => 1,
        ]);

        return !empty($orders) ? $orders[0] : null;
    }
}
