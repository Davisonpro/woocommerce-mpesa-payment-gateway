<?php
/**
 * Hook Manager
 * 
 * Manages WordPress hooks and filters
 *
 * @package WooMpesa\Hooks
 */

declare(strict_types=1);

namespace WooMpesa\Hooks;

/**
 * Hook Manager class
 */
final class HookManager
{
    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init(): void
    {
        // Add custom query vars for order lookups
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [
            $this,
            'handleCustomOrderQuery'
        ], 10, 2);

        // Add action links to plugin page
        add_filter('plugin_action_links_' . WOO_MPESA_BASENAME, [
            $this,
            'addPluginActionLinks'
        ]);

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Handle custom order query vars
     *
     * @param array $query Query args
     * @param array $queryVars Query vars
     * @return array
     */
    public function handleCustomOrderQuery(array $query, array $queryVars): array
    {
        if (!empty($queryVars['mpesa_merchant_request_id'])) {
            $query['meta_query'][] = [
                'key' => '_mpesa_merchant_request_id',
                'value' => esc_attr($queryVars['mpesa_merchant_request_id']),
            ];
        }

        if (!empty($queryVars['mpesa_transaction_id'])) {
            $query['meta_query'][] = [
                'key' => '_transaction_id',
                'value' => esc_attr($queryVars['mpesa_transaction_id']),
            ];
        }

        return $query;
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array
     */
    public function addPluginActionLinks(array $links): array
    {
        $pluginLinks = [
            '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')) . '">' .
                esc_html__('Settings', 'woocommerce-mpesa-payment-gateway') .
            '</a>',
            '<a href="' . esc_url('https://github.com/Davisonpro/mpesa-payment-gateway') . '" target="_blank">' .
                esc_html__('Documentation', 'woocommerce-mpesa-payment-gateway') .
            '</a>',
        ];

        return array_merge($pluginLinks, $links);
    }

    /**
     * Enqueue public assets
     *
     * @return void
     */
    public function enqueuePublicAssets(): void
    {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'mpesa-payment-gateway-checkout',
            WOO_MPESA_URL . 'assets/css/checkout.css',
            [],
            WOO_MPESA_VERSION
        );

        wp_enqueue_script(
            'mpesa-payment-gateway-checkout',
            WOO_MPESA_URL . 'assets/js/checkout.js',
            ['jquery'],
            WOO_MPESA_VERSION,
            true
        );
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();

        if ($screen && strpos($screen->id, 'wc-settings') !== false) {
            wp_enqueue_style(
                'mpesa-payment-gateway-admin',
                WOO_MPESA_URL . 'assets/css/admin.css',
                [],
                WOO_MPESA_VERSION
            );
        }
    }
}

