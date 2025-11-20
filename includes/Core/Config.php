<?php
/**
 * Configuration Management
 * 
 * Centralized configuration management for the plugin
 *
 * @package WooMpesa\Core
 */

declare(strict_types=1);

namespace WooMpesa\Core;

/**
 * Config class
 */
final class Config
{
    /**
     * M-Pesa API endpoints
     */
    public const API_ENDPOINTS = [
        'sandbox' => [
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'oauth' => '/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => '/mpesa/stkpush/v1/processrequest',
            'stk_query' => '/mpesa/stkpushquery/v1/query',
            'c2b_register' => '/mpesa/c2b/v1/registerurl',
            'reversal' => '/mpesa/reversal/v1/request',
        ],
        'live' => [
            'base_url' => 'https://api.safaricom.co.ke',
            'oauth' => '/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => '/mpesa/stkpush/v1/processrequest',
            'stk_query' => '/mpesa/stkpushquery/v1/query',
            'c2b_register' => '/mpesa/c2b/v1/registerurl',
            'reversal' => '/mpesa/reversal/v1/request',
        ],
    ];

    /**
     * Transaction types
     */
    public const TRANSACTION_TYPES = [
        'PAYBILL' => 4,
        'TILL' => 2,
        'MSISDN' => 1,
    ];

    /**
     * Result codes
     */
    public const RESULT_CODES = [
        0 => 'Success',
        1 => 'Insufficient Funds',
        2 => 'Less Than Minimum Transaction Value',
        3 => 'More Than Maximum Transaction Value',
        4 => 'Would Exceed Daily Transfer Limit',
        5 => 'Would Exceed Minimum Balance',
        6 => 'Unresolved Primary Party',
        7 => 'Unresolved Receiver Party',
        8 => 'Would Exceed Maximum Balance',
        11 => 'Debit Account Invalid',
        12 => 'Credit Account Invalid',
        13 => 'Unresolved Debit Account',
        14 => 'Unresolved Credit Account',
        15 => 'Duplicate Detected',
        17 => 'Internal Failure',
        20 => 'Unresolved Initiator',
        26 => 'Traffic blocking condition in place',
    ];

    /**
     * Phone number regex patterns
     */
    public const PHONE_PATTERNS = [
        'kenyan' => '/^(254|0)[17]\d{8}$/',
        'international' => '/^\+?254[17]\d{8}$/',
    ];

    /**
     * Default settings
     */
    public const DEFAULT_SETTINGS = [
        'title' => 'Lipa Na M-Pesa',
        'description' => 'Pay securely using your M-Pesa mobile money account.',
        'env' => 'sandbox',
        'idtype' => self::TRANSACTION_TYPES['PAYBILL'],
        'enable_c2b' => 'no',
        'enable_bonga' => 'no',
        'enable_reversal' => 'no',
        'debug' => 'no',
        'completion_status' => 'completed',
    ];

    /**
     * Cache keys
     */
    public const CACHE_KEYS = [
        'access_token' => 'woo_mpesa_access_token',
        'api_response' => 'woo_mpesa_api_response',
    ];

    /**
     * Cache expiration times (in seconds)
     */
    public const CACHE_EXPIRATION = [
        'access_token' => 3300, // 55 minutes (token expires in 60 minutes)
        'api_response' => 300, // 5 minutes
    ];

    /**
     * Get API endpoint URL
     *
     * @param string $environment Environment (sandbox|live)
     * @param string $endpoint Endpoint name
     * @return string
     */
    public static function getApiEndpoint(string $environment, string $endpoint): string
    {
        $env = self::API_ENDPOINTS[$environment] ?? self::API_ENDPOINTS['sandbox'];
        return $env['base_url'] . ($env[$endpoint] ?? '');
    }

    /**
     * Get result code description
     *
     * @param int $code Result code
     * @return string
     */
    public static function getResultCodeDescription(int $code): string
    {
        return self::RESULT_CODES[$code] ?? __('Unknown Error', 'woocommerce-mpesa-payment-gateway');
    }

    /**
     * Validate phone number
     *
     * @param string $phone Phone number
     * @param string $pattern Pattern name
     * @return bool
     */
    public static function validatePhone(string $phone, string $pattern = 'kenyan'): bool
    {
        $regex = self::PHONE_PATTERNS[$pattern] ?? self::PHONE_PATTERNS['kenyan'];
        return (bool) preg_match($regex, $phone);
    }

    /**
     * Format phone number to M-Pesa format (254XXXXXXXXX)
     *
     * @param string $phone Phone number
     * @return string
     */
    public static function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // Remove leading zeros or plus
        $phone = ltrim($phone, '0+');

        // Add country code if not present
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return WOO_MPESA_VERSION;
    }

    /**
     * Get plugin path
     *
     * @param string $path Relative path
     * @return string
     */
    public static function getPath(string $path = ''): string
    {
        return WOO_MPESA_PATH . $path;
    }

    /**
     * Get plugin URL
     *
     * @param string $path Relative path
     * @return string
     */
    public static function getUrl(string $path = ''): string
    {
        return WOO_MPESA_URL . $path;
    }
}

