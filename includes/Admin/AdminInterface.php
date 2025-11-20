<?php
/**
 * Admin Interface
 * 
 * Handles admin-specific functionality
 *
 * @package WooMpesa\Admin
 */

declare(strict_types=1);

namespace WooMpesa\Admin;

/**
 * Admin Interface class
 */
final class AdminInterface
{
    /**
     * Initialize admin hooks
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu'], 99);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayOrderMeta']);
    }

    /**
     * Add admin menu items
     *
     * @return void
     */
    public function addAdminMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('M-Pesa Transactions', 'woocommerce-mpesa-payment-gateway'),
            __('M-Pesa', 'woocommerce-mpesa-payment-gateway'),
            'manage_woocommerce',
            'mpesa-payment-gateway-transactions',
            [$this, 'renderTransactionsPage']
        );
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function registerSettings(): void
    {
        register_setting(
            'mpesa-payment-gateway-settings',
            'woo_mpesa_options',
            [
                'sanitize_callback' => [$this, 'sanitizeSettings'],
            ]
        );
    }

    /**
     * Sanitize plugin settings
     *
     * @param array $input Raw input data
     * @return array Sanitized data
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeSettings($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Display M-Pesa transaction meta in order page
     *
     * @param \WC_Order $order Order object
     * @return void
     */
    public function displayOrderMeta($order): void
    {
        if ($order->get_payment_method() !== 'mpesa') {
            return;
        }

        $merchantRequestId = $order->get_meta('_mpesa_merchant_request_id');
        $checkoutRequestId = $order->get_meta('_mpesa_checkout_request_id');
        $phone = $order->get_meta('_mpesa_phone');

        ?>
        <div class="order_data_column">
            <h3><?php esc_html_e('M-Pesa Payment Details', 'woocommerce-mpesa-payment-gateway'); ?></h3>
            <?php if ($merchantRequestId): ?>
                <p><strong><?php esc_html_e('Merchant Request ID:', 'woocommerce-mpesa-payment-gateway'); ?></strong><br>
                <code><?php echo esc_html($merchantRequestId); ?></code></p>
            <?php endif; ?>
            
            <?php if ($checkoutRequestId): ?>
                <p><strong><?php esc_html_e('Checkout Request ID:', 'woocommerce-mpesa-payment-gateway'); ?></strong><br>
                <code><?php echo esc_html($checkoutRequestId); ?></code></p>
            <?php endif; ?>
            
            <?php if ($phone): ?>
                <p><strong><?php esc_html_e('Phone Number:', 'woocommerce-mpesa-payment-gateway'); ?></strong><br>
                <?php echo esc_html($phone); ?></p>
            <?php endif; ?>
            
            <?php if ($order->get_transaction_id()): ?>
                <p><strong><?php esc_html_e('Transaction ID:', 'woocommerce-mpesa-payment-gateway'); ?></strong><br>
                <code><?php echo esc_html($order->get_transaction_id()); ?></code></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render transactions page
     *
     * @return void
     */
    public function renderTransactionsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('M-Pesa Transactions', 'woocommerce-mpesa-payment-gateway'); ?></h1>
            <p><?php esc_html_e('View and manage M-Pesa transactions.', 'woocommerce-mpesa-payment-gateway'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order ID', 'woocommerce-mpesa-payment-gateway'); ?></th>
                        <th><?php esc_html_e('Transaction ID', 'woocommerce-mpesa-payment-gateway'); ?></th>
                        <th><?php esc_html_e('Phone', 'woocommerce-mpesa-payment-gateway'); ?></th>
                        <th><?php esc_html_e('Amount', 'woocommerce-mpesa-payment-gateway'); ?></th>
                        <th><?php esc_html_e('Status', 'woocommerce-mpesa-payment-gateway'); ?></th>
                        <th><?php esc_html_e('Date', 'woocommerce-mpesa-payment-gateway'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $orders = wc_get_orders([
                        'payment_method' => 'mpesa',
                        'limit' => 50,
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ]);

                    if (empty($orders)) {
                        echo '<tr><td colspan="6">' . esc_html__('No transactions found.', 'woocommerce-mpesa-payment-gateway') . '</td></tr>';
                    } else {
                        foreach ($orders as $order) {
                            $phone = $order->get_meta('_mpesa_phone');
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                    #<?php echo esc_html($order->get_id()); ?>
                                </a></td>
                                <td><code><?php echo esc_html($order->get_transaction_id() ?: '-'); ?></code></td>
                                <td><?php echo esc_html($phone ?: '-'); ?></td>
                                <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                                <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

