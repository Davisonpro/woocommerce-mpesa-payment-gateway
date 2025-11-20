<?php
/**
 * Logger Service
 * 
 * Handles logging for debugging and monitoring
 *
 * @package WooMpesa\Services
 */

declare(strict_types=1);

namespace WooMpesa\Services;

use WC_Logger;
use WC_Logger_Interface;

/**
 * Logger Service class
 */
final class LoggerService
{
    /**
     * Logger instance
     *
     * @var WC_Logger_Interface|null
     */
    private ?WC_Logger_Interface $logger = null;

    /**
     * Log source identifier
     *
     * @var string
     */
    private const LOG_SOURCE = 'woocommerce-mpesa-payment-gateway';

    /**
     * Constructor
     */
    public function __construct()
    {
        if (function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log critical message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log API request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return void
     */
    public function logApiRequest(string $endpoint, array $data): void
    {
        $this->info(
            sprintf('API Request to %s', $endpoint),
            [
                'endpoint' => $endpoint,
                'data' => $this->sanitizeData($data),
            ]
        );
    }

    /**
     * Log API response
     *
     * @param string $endpoint API endpoint
     * @param mixed $response Response data
     * @return void
     */
    public function logApiResponse(string $endpoint, $response): void
    {
        $this->info(
            sprintf('API Response from %s', $endpoint),
            [
                'endpoint' => $endpoint,
                'response' => $this->sanitizeData($response),
            ]
        );
    }

    /**
     * Log API error
     *
     * @param string $endpoint API endpoint
     * @param string $error Error message
     * @param array $context Additional context
     * @return void
     */
    public function logApiError(string $endpoint, string $error, array $context = []): void
    {
        $this->error(
            sprintf('API Error at %s: %s', $endpoint, $error),
            array_merge(['endpoint' => $endpoint], $context)
        );
    }

    /**
     * Log payment attempt
     *
     * @param int $orderId Order ID
     * @param string $phone Phone number
     * @param float $amount Amount
     * @return void
     */
    public function logPaymentAttempt(int $orderId, string $phone, float $amount): void
    {
        $this->info(
            'Payment attempt initiated',
            [
                'order_id' => $orderId,
                'phone' => $this->maskPhone($phone),
                'amount' => $amount,
            ]
        );
    }

    /**
     * Log payment success
     *
     * @param int $orderId Order ID
     * @param string $transactionId Transaction ID
     * @return void
     */
    public function logPaymentSuccess(int $orderId, string $transactionId): void
    {
        $this->info(
            'Payment successful',
            [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]
        );
    }

    /**
     * Log payment failure
     *
     * @param int $orderId Order ID
     * @param string $reason Failure reason
     * @return void
     */
    public function logPaymentFailure(int $orderId, string $reason): void
    {
        $this->error(
            'Payment failed',
            [
                'order_id' => $orderId,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Generic log method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $contextString = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';
        $this->logger->log($level, $message . $contextString, ['source' => self::LOG_SOURCE]);
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     *
     * @param mixed $data Data to sanitize
     * @return mixed
     */
    private function sanitizeData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = [
            'Password',
            'password',
            'SecurityCredential',
            'appkey',
            'appsecret',
            'consumer_key',
            'consumer_secret',
        ];

        foreach ($data as $key => &$value) {
            if (in_array($key, $sensitiveKeys, true)) {
                $value = '***REDACTED***';
            } elseif (is_array($value)) {
                $value = $this->sanitizeData($value);
            }
        }

        return $data;
    }

    /**
     * Mask phone number for privacy
     *
     * @param string $phone Phone number
     * @return string
     */
    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length < 4) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3) . str_repeat('*', $length - 6) . substr($phone, -3);
    }
}

