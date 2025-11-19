<?php
/**
 * M-Pesa API Service
 * 
 * Handles all M-Pesa API interactions
 *
 * @package WooMpesa\Services
 */

declare(strict_types=1);

namespace WooMpesa\Services;

use WooMpesa\Core\Config;
use WP_Error;

/**
 * M-Pesa API Service class
 */
final class MpesaApiService
{
    /**
     * Logger service
     *
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * API configuration
     *
     * @var array
     */
    private array $config;

    /**
     * Constructor
     *
     * @param LoggerService $logger Logger service
     */
    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
        $this->loadConfig();
    }

    /**
     * Load configuration from settings
     *
     * @return void
     */
    private function loadConfig(): void
    {
        $settings = get_option('woocommerce_mpesa_settings', []);

        $this->config = [
            'env' => $settings['env'] ?? 'sandbox',
            'consumer_key' => $settings['key'] ?? '',
            'consumer_secret' => $settings['secret'] ?? '',
            'shortcode' => $settings['shortcode'] ?? '',
            'passkey' => $settings['passkey'] ?? '',
            'initiator' => $settings['initiator'] ?? '',
            'password' => $settings['password'] ?? '',
            'type' => (int) ($settings['idtype'] ?? Config::TRANSACTION_TYPES['PAYBILL']),
        ];
    }

    /**
     * Get OAuth access token
     *
     * @return string|WP_Error
     */
    public function getAccessToken()
    {
        // Check cache first
        $cachedToken = get_transient(Config::CACHE_KEYS['access_token']);
        if ($cachedToken !== false) {
            return $cachedToken;
        }

        $url = Config::getApiEndpoint($this->config['env'], 'oauth');
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    $this->config['consumer_key'] . ':' . $this->config['consumer_secret']
                ),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->logApiError('oauth', $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $token = $body['access_token'];
            set_transient(
                Config::CACHE_KEYS['access_token'],
                $token,
                Config::CACHE_EXPIRATION['access_token']
            );
            return $token;
        }

        $error = new WP_Error(
            'token_error',
            __('Failed to retrieve access token', 'mpesa-payment-gateway'),
            $body
        );
        $this->logger->logApiError('oauth', 'Failed to retrieve access token', ['response' => $body]);

        return $error;
    }

    /**
     * Initiate STK Push request
     *
     * @param string $phone Phone number
     * @param float $amount Amount
     * @param string $reference Transaction reference
     * @param string $description Transaction description
     * @return array|WP_Error
     */
    public function stkPush(
        string $phone,
        float $amount,
        string $reference,
        string $description = ''
    ) {
        $token = $this->getAccessToken();
        
        if (is_wp_error($token)) {
            return $token;
        }

        $phone = Config::formatPhoneNumber($phone);
        $timestamp = gmdate('YmdHis');
        $password = base64_encode(
            $this->config['shortcode'] . $this->config['passkey'] . $timestamp
        );

        $callbackUrl = add_query_arg(
            ['action' => 'reconcile', 'order' => $reference],
            home_url('/wc-api/mpesa-payment-gateway')
        );

        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $this->config['type'] === Config::TRANSACTION_TYPES['TILL'] 
                ? 'CustomerBuyGoodsOnline' 
                : 'CustomerPayBillOnline',
            'Amount' => (int) ceil($amount),
            'PartyA' => $phone,
            'PartyB' => $this->config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => $description ?: sprintf(
                /* translators: %s: Site name */
                __('Payment for %s', 'mpesa-payment-gateway'),
                get_bloginfo('name')
            ),
        ];

        $url = Config::getApiEndpoint($this->config['env'], 'stk_push');

        $this->logger->logApiRequest('stk_push', $payload);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->logApiError('stk_push', $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->logger->logApiResponse('stk_push', $body);

        return $body;
    }

    /**
     * Query STK Push transaction status
     *
     * @param string $checkoutRequestId Checkout request ID
     * @return array|WP_Error
     */
    public function stkQuery(string $checkoutRequestId)
    {
        $token = $this->getAccessToken();
        
        if (is_wp_error($token)) {
            return $token;
        }

        $timestamp = gmdate('YmdHis');
        $password = base64_encode(
            $this->config['shortcode'] . $this->config['passkey'] . $timestamp
        );

        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $url = Config::getApiEndpoint($this->config['env'], 'stk_query');

        $this->logger->logApiRequest('stk_query', $payload);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->logApiError('stk_query', $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->logger->logApiResponse('stk_query', $body);

        return $body;
    }

    /**
     * Register C2B URLs
     *
     * @param string $validationUrl Validation URL
     * @param string $confirmationUrl Confirmation URL
     * @return array|WP_Error
     */
    public function registerC2BUrls(string $validationUrl, string $confirmationUrl)
    {
        $token = $this->getAccessToken();
        
        if (is_wp_error($token)) {
            return $token;
        }

        $payload = [
            'ShortCode' => $this->config['shortcode'],
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL' => $validationUrl,
        ];

        $url = Config::getApiEndpoint($this->config['env'], 'c2b_register');

        $this->logger->logApiRequest('c2b_register', $payload);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->logApiError('c2b_register', $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->logger->logApiResponse('c2b_register', $body);

        return $body;
    }

    /**
     * Reverse transaction
     *
     * @param string $transactionId Transaction ID to reverse
     * @param float $amount Amount to reverse
     * @param string $remarks Reversal remarks
     * @return array|WP_Error
     */
    public function reverseTransaction(
        string $transactionId,
        float $amount,
        string $remarks = ''
    ) {
        $token = $this->getAccessToken();
        
        if (is_wp_error($token)) {
            return $token;
        }

        // Get security credential (encrypted password)
        $securityCredential = $this->getSecurityCredential();

        $payload = [
            'Initiator' => $this->config['initiator'],
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'TransactionReversal',
            'TransactionID' => $transactionId,
            'Amount' => (int) ceil($amount),
            'ReceiverParty' => $this->config['shortcode'],
            'RecieverIdentifierType' => $this->config['type'],
            'ResultURL' => home_url('/wc-api/mpesa-payment-gateway?action=reversal_result'),
            'QueueTimeOutURL' => home_url('/wc-api/mpesa-payment-gateway?action=reversal_timeout'),
            'Remarks' => $remarks ?: __('Transaction reversal', 'mpesa-payment-gateway'),
            'Occasion' => '',
        ];

        $url = Config::getApiEndpoint($this->config['env'], 'reversal');

        $this->logger->logApiRequest('reversal', $payload);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->logger->logApiError('reversal', $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->logger->logApiResponse('reversal', $body);

        return $body;
    }

    /**
     * Get security credential (encrypted password)
     *
     * @return string
     */
    private function getSecurityCredential(): string
    {
        $certPath = Config::getPath(
            sprintf('includes/certificates/%s/cert.cer', $this->config['env'])
        );

        if (!file_exists($certPath)) {
            $this->logger->warning('Certificate file not found', ['path' => $certPath]);
            return base64_encode($this->config['password']);
        }

        $publicKey = file_get_contents($certPath);
        openssl_public_encrypt($this->config['password'], $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Validate callback signature
     *
     * @param array $data Callback data
     * @param string $signature Expected signature
     * @return bool
     */
    public function validateCallback(array $data, string $signature): bool
    {
        $settings = get_option('woocommerce_mpesa_settings', []);
        $secret = $settings['signature'] ?? '';

        if (empty($secret)) {
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', wp_json_encode($data), $secret);

        return hash_equals($calculatedSignature, $signature);
    }
}

