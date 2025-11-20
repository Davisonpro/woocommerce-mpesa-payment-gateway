<?php

/**
 * Service Container
 * 
 * Manages dependency injection and service registration
 * Follows SOLID principles and Dependency Injection pattern
 *
 * @package WooMpesa\Core
 */

declare(strict_types=1);

namespace WooMpesa\Core;

use WooMpesa\Gateway\MpesaGateway;
use WooMpesa\Admin\AdminInterface;
use WooMpesa\Blocks\BlocksIntegration;
use WooMpesa\Services\MpesaApiService;
use WooMpesa\Services\LoggerService;
use WooMpesa\Services\CurrencyConverter;
use WooMpesa\Hooks\HookManager;

/**
 * Service Container class
 */
final class ServiceContainer
{
    /**
     * Container instance
     *
     * @var ServiceContainer|null
     */
    private static ?ServiceContainer $instance = null;

    /**
     * Registered services
     *
     * @var array<string, object>
     */
    private array $services = [];

    /**
     * Service definitions
     *
     * @var array<string, callable>
     */
    private array $serviceDefinitions = [];

    /**
     * Get container instance
     *
     * @return ServiceContainer
     */
    public static function getInstance(): ServiceContainer
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->registerServices();
    }

    /**
     * Register all services
     *
     * @return void
     */
    private function registerServices(): void
    {
        // Register logger service
        $this->register(LoggerService::class, function () {
            return new LoggerService();
        });

        // Register API service
        $this->register(MpesaApiService::class, function () {
            return new MpesaApiService($this->get(LoggerService::class));
        });

        // Register currency converter
        $this->register(CurrencyConverter::class, function () {
            $settings = get_option('woocommerce_mpesa_settings', []);
            return new CurrencyConverter($this->get(LoggerService::class), $settings);
        });

        // Register hook manager
        $this->register(HookManager::class, function () {
            return new HookManager();
        });

        // Register admin interface
        $this->register(AdminInterface::class, function () {
            return new AdminInterface();
        });

        // Register blocks integration
        $this->register(BlocksIntegration::class, function () {
            return new BlocksIntegration();
        });
    }

    /**
     * Register a service
     *
     * @param string $id Service identifier
     * @param callable $definition Service definition
     * @return void
     */
    public function register(string $id, callable $definition): void
    {
        $this->serviceDefinitions[$id] = $definition;
    }

    /**
     * Get a service
     *
     * @param string $id Service identifier
     * @return object
     * @throws \RuntimeException If service not found
     */
    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (!isset($this->serviceDefinitions[$id])) {
            throw new \RuntimeException(
                sprintf('Service "%s" is not registered.', esc_html($id))
            );
        }

        $this->services[$id] = call_user_func($this->serviceDefinitions[$id]);

        return $this->services[$id];
    }

    /**
     * Check if service exists
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->serviceDefinitions[$id]) || isset($this->services[$id]);
    }

    /**
     * Boot all services
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot hook manager
        $this->get(HookManager::class)->init();

        // Boot admin interface
        if (is_admin()) {
            $this->get(AdminInterface::class)->init();
        }

        // Register payment gateway with WooCommerce
        add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = MpesaGateway::class;
            return $gateways;
        }, 10);

        // Register Blocks integration
        add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
            $registry->register($this->get(BlocksIntegration::class));
        });

        // Show activation notice
        add_action('admin_notices', [$this, 'showActivationNotice']);
    }

    /**
     * Show activation notice
     *
     * @return void
     */
    public function showActivationNotice(): void
    {
        if (!get_transient('woo_mpesa_activation_notice')) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            sprintf(
                /* translators: %s: Plugin documentation link */
                esc_html__('Thank you for installing M-Pesa Payment Gateway! %s', 'woocommerce-mpesa-payment-gateway'),
                sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')),
                    esc_html__('Configure settings', 'woocommerce-mpesa-payment-gateway')
                )
            )
        );

        delete_transient('woo_mpesa_activation_notice');
    }
}
