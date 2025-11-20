<?php
/**
 * Plugin Name: M-Pesa Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/Davisonpro/mpesa-payment-gateway
 * Description: Seamless M-Pesa payment gateway integration for WooCommerce. Supports STK Push, C2B, and WooCommerce Blocks checkout.
 * Version: 2.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Davison Pro
 * Author URI: https://davisonpro.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-mpesa-payment-gateway
 * Domain Path: /languages
 * WC requires at least: 5.3
 * WC tested up to: 9.4
 * Requires Plugins: woocommerce
 *
 * @package WooMpesa
 */

declare(strict_types=1);

namespace WooMpesa;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_MPESA_VERSION', '2.0.0');
define('WOO_MPESA_FILE', __FILE__);
define('WOO_MPESA_PATH', plugin_dir_path(__FILE__));
define('WOO_MPESA_URL', plugin_dir_url(__FILE__));
define('WOO_MPESA_BASENAME', plugin_basename(__FILE__));

// Require Composer autoloader
if (file_exists(WOO_MPESA_PATH . 'vendor/autoload.php')) {
    require_once WOO_MPESA_PATH . 'vendor/autoload.php';
}

/**
 * Main plugin class initialization
 * Follows singleton pattern for single instance
 */
final class Plugin
{
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    private const MIN_PHP_VERSION = '7.4';

    /**
     * Minimum WordPress version required
     *
     * @var string
     */
    private const MIN_WP_VERSION = '5.8';

    /**
     * Minimum WooCommerce version required
     *
     * @var string
     */
    private const MIN_WC_VERSION = '5.3';

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->checkRequirements();
        $this->initHooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Check system requirements
     *
     * @return void
     */
    private function checkRequirements(): void
    {
        add_action('admin_init', [$this, 'checkVersionRequirements']);
    }

    /**
     * Check PHP and WordPress version requirements
     *
     * @return void
     */
    public function checkVersionRequirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function () {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        /* translators: 1: Required PHP version, 2: Current PHP version */
                        esc_html__('M-Pesa Payment Gateway requires PHP version %1$s or higher. You are running version %2$s.', 'mpesa-payment-gateway-for-woocommerce'),
                        esc_html(self::MIN_PHP_VERSION),
                        esc_html(PHP_VERSION)
                    )
                );
            });
            return;
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function () use ($wp_version) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        /* translators: 1: Required WordPress version, 2: Current WordPress version */
                        esc_html__('M-Pesa Payment Gateway requires WordPress version %1$s or higher. You are running version %2$s.', 'mpesa-payment-gateway-for-woocommerce'),
                        esc_html(self::MIN_WP_VERSION),
                        esc_html($wp_version)
                    )
                );
            });
            return;
        }

        // Check WooCommerce
        if (!$this->isWooCommerceActive()) {
            add_action('admin_notices', function () {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__('M-Pesa Payment Gateway requires WooCommerce to be installed and activated.', 'mpesa-payment-gateway-for-woocommerce')
                );
            });
            return;
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            add_action('admin_notices', function () {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        /* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
                        esc_html__('M-Pesa Payment Gateway requires WooCommerce version %1$s or higher. You are running version %2$s.', 'mpesa-payment-gateway-for-woocommerce'),
                        esc_html(self::MIN_WC_VERSION),
                        esc_html(WC_VERSION)
                    )
                );
            });
        }
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function isWooCommerceActive(): bool
    {
        // Check if WooCommerce class exists
        if (class_exists('WooCommerce')) {
            return true;
        }
        
        // Check active plugins list
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core filter
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', []));
        
        return in_array('woocommerce/woocommerce.php', $active_plugins, true);
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function initHooks(): void
    {
        add_action('plugins_loaded', [$this, 'init'], 11);
        add_action('before_woocommerce_init', [$this, 'declareCompatibility']);
        
        register_activation_hook(WOO_MPESA_FILE, [$this, 'activate']);
        register_deactivation_hook(WOO_MPESA_FILE, [$this, 'deactivate']);
    }

    /**
     * Declare compatibility with WooCommerce features
     *
     * @return void
     */
    public function declareCompatibility(): void
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        // Declare HPOS compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            WOO_MPESA_FILE,
            true
        );

        // Declare Cart and Checkout Blocks compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            WOO_MPESA_FILE,
            true
        );
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    public function init(): void
    {
        if (!$this->isWooCommerceActive()) {
            return;
        }

        // Initialize core components
        Core\ServiceContainer::getInstance()->boot();
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate(): void
    {
        if (!$this->isWooCommerceActive()) {
            deactivate_plugins(WOO_MPESA_BASENAME);
            wp_die(
                esc_html__('Please install and activate WooCommerce before activating M-Pesa Payment Gateway.', 'mpesa-payment-gateway-for-woocommerce'),
                esc_html__('Plugin Activation Error', 'mpesa-payment-gateway-for-woocommerce'),
                ['back_link' => true]
            );
        }

        set_transient('woo_mpesa_activation_notice', true, 5);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize plugin
Plugin::getInstance();

