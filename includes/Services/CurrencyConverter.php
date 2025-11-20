<?php
/**
 * Currency Converter Service
 * 
 * Handles currency conversion to KES
 *
 * @package WooMpesa\Services
 */

declare(strict_types=1);

namespace WooMpesa\Services;

use WP_Error;

/**
 * Currency Converter class
 */
final class CurrencyConverter
{
    /**
     * Logger service
     *
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * Gateway settings
     *
     * @var array
     */
    private array $settings;

    /**
     * Constructor
     *
     * @param LoggerService $logger Logger service
     * @param array $settings Gateway settings
     */
    public function __construct(LoggerService $logger, array $settings = [])
    {
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Convert amount to KES
     *
     * @param float $amount Amount to convert
     * @param string $fromCurrency Source currency code
     * @return float|WP_Error Converted amount or error
     */
    public function convertToKES(float $amount, string $fromCurrency)
    {
        // Already in KES
        if ($fromCurrency === 'KES') {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency);

        if (is_wp_error($rate)) {
            return $rate;
        }

        if ($rate <= 0) {
            return new WP_Error(
                'invalid_rate',
                sprintf(
                    /* translators: %s: Currency code */
                    __('Invalid exchange rate for %s to KES', 'woocommerce-mpesa-payment-gateway'),
                    $fromCurrency
                )
            );
        }

        $kesAmount = $amount * $rate;

        // Allow filtering
        return (float) apply_filters(
            'woo_mpesa_converted_amount',
            $kesAmount,
            $amount,
            $fromCurrency,
            $rate
        );
    }

    /**
     * Get exchange rate from currency to KES
     *
     * @param string $fromCurrency Source currency
     * @return float|WP_Error Exchange rate or error
     */
    public function getExchangeRate(string $fromCurrency)
    {
        // 1. Custom filter (highest priority)
        $customRate = apply_filters('woo_mpesa_exchange_rate', null, $fromCurrency);
        if ($customRate !== null) {
            return (float) $customRate;
        }

        // 2. WooCommerce Multi-Currency plugins
        $wcRate = $this->getWCMultiCurrencyRate($fromCurrency);
        if ($wcRate !== null) {
            return $wcRate;
        }

        // 3. Auto-fetch from API
        if ($this->isAutoFetchEnabled()) {
            $apiRate = $this->fetchFromAPI($fromCurrency);
            if (!is_wp_error($apiRate)) {
                return $apiRate;
            }
        }

        // 4. Manual rates (fallback)
        $manualRates = $this->getManualRates();
        if (isset($manualRates[$fromCurrency])) {
            return $manualRates[$fromCurrency];
        }

        return new WP_Error(
            'no_exchange_rate',
            sprintf(
                /* translators: %s: Currency code */
                __('No exchange rate found for %s to KES. Please configure exchange rates in M-Pesa settings.', 'woocommerce-mpesa-payment-gateway'),
                $fromCurrency
            )
        );
    }

    /**
     * Check if auto-fetch is enabled
     *
     * @return bool
     */
    private function isAutoFetchEnabled(): bool
    {
        return ($this->settings['auto_exchange_rates'] ?? 'yes') === 'yes';
    }

    /**
     * Get exchange rate from WooCommerce Multi-Currency plugins
     *
     * @param string $fromCurrency Source currency
     * @return float|null Exchange rate or null
     */
    private function getWCMultiCurrencyRate(string $fromCurrency): ?float
    {
        // WooCommerce Currency Switcher (WOOCS)
        if (function_exists('woocs_exchange_value')) {
            $rate = woocs_exchange_value(1, $fromCurrency, 'KES');
            if ($rate > 0) {
                return $rate;
            }
        }

        // WooCommerce Multi-Currency by TIV.NET
        if (class_exists('WOOMULTI_CURRENCY_F_Data')) {
            $wcmc = \WOOMULTI_CURRENCY_F_Data::get_ins();
            $rate = $wcmc->get_exchange_rate($fromCurrency, 'KES');
            if ($rate > 0) {
                return $rate;
            }
        }

        // WPML Multi-Currency
        if (function_exists('wcml_get_currency_exchange_rate')) {
            $rate = wcml_get_currency_exchange_rate($fromCurrency, 'KES');
            if ($rate > 0) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * Fetch exchange rate from external API
     *
     * @param string $fromCurrency Source currency
     * @return float|WP_Error Exchange rate or error
     */
    private function fetchFromAPI(string $fromCurrency)
    {
        $cacheKey = 'mpesa_rate_' . strtolower($fromCurrency) . '_kes';
        $cachedRate = get_transient($cacheKey);

        if ($cachedRate !== false) {
            return (float) $cachedRate;
        }

        $apiUrl = sprintf(
            'https://api.exchangerate-api.com/v4/latest/%s',
            strtoupper($fromCurrency)
        );

        $response = wp_remote_get($apiUrl, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['rates']['KES'])) {
            return new WP_Error(
                'api_error',
                __('Failed to fetch exchange rate from API', 'woocommerce-mpesa-payment-gateway')
            );
        }

        $rate = (float) $body['rates']['KES'];
        
        // Cache for 6 hours
        set_transient($cacheKey, $rate, 6 * HOUR_IN_SECONDS);
        
        $this->logger->info('Fetched exchange rate from API', [
            'from' => $fromCurrency,
            'to' => 'KES',
            'rate' => $rate,
        ]);

        return $rate;
    }

    /**
     * Get manual exchange rates from settings
     *
     * @return array Parsed exchange rates
     */
    private function getManualRates(): array
    {
        $ratesText = $this->settings['exchange_rates'] ?? '';
        
        if (empty($ratesText)) {
            return [];
        }

        $rates = [];
        $lines = explode("\n", $ratesText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#' || strpos($line, '//') === 0) {
                continue;
            }
            
            // Parse: CURRENCY=RATE
            if (strpos($line, '=') === false) {
                continue;
            }

            list($currency, $rate) = explode('=', $line, 2);
            $currency = strtoupper(trim($currency));
            $rate = trim($rate);
            
            // Validate
            if (strlen($currency) === 3 && ctype_alpha($currency) && is_numeric($rate) && $rate > 0) {
                $rates[$currency] = (float) $rate;
            } else {
                $this->logger->warning('Invalid exchange rate entry', [
                    'line' => $line,
                    'currency' => $currency,
                    'rate' => $rate,
                ]);
            }
        }

        return $rates;
    }

    /**
     * Get conversion info for display
     *
     * @param float $amount Original amount
     * @param string $fromCurrency Source currency
     * @return array|WP_Error Conversion info or error
     */
    public function getConversionInfo(float $amount, string $fromCurrency)
    {
        // Handle zero amount
        if ($amount <= 0) {
            return new \WP_Error(
                'invalid_amount',
                __('Invalid amount for currency conversion', 'woocommerce-mpesa-payment-gateway')
            );
        }

        if ($fromCurrency === 'KES') {
            return [
                'amount' => $amount,
                'currency' => 'KES',
                'kes_amount' => $amount,
                'rate' => 1.0,
                'converted' => false,
            ];
        }

        $kesAmount = $this->convertToKES($amount, $fromCurrency);

        if (is_wp_error($kesAmount)) {
            return $kesAmount;
        }

        return [
            'amount' => $amount,
            'currency' => $fromCurrency,
            'kes_amount' => $kesAmount,
            'rate' => $kesAmount / $amount,
            'converted' => true,
        ];
    }
}

