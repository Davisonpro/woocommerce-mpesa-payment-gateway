<?php
/**
 * Thank You Page Template
 * 
 * @package WooMpesa
 * @var WC_Order $order
 */

if (!defined('ABSPATH')) {
    exit;
}

$woo_mpesa_merchant_request_id = $order->get_meta('_mpesa_merchant_request_id');
$woo_mpesa_phone = $order->get_meta('_mpesa_phone');
?>

<div class="mpesa-payment-gateway-thank-you">
    <h3><?php esc_html_e('M-Pesa Payment Pending', 'mpesa-payment-gateway-for-woocommerce'); ?></h3>
    
    <p><?php esc_html_e('A payment request has been sent to your phone. Please check your phone and enter your M-Pesa PIN to complete the payment.', 'mpesa-payment-gateway-for-woocommerce'); ?></p>
    
    <?php if ($woo_mpesa_phone): ?>
        <p><strong><?php esc_html_e('Phone Number:', 'mpesa-payment-gateway-for-woocommerce'); ?></strong> <?php echo esc_html($woo_mpesa_phone); ?></p>
    <?php endif; ?>
    
    <?php if ($woo_mpesa_merchant_request_id): ?>
        <p><strong><?php esc_html_e('Request ID:', 'mpesa-payment-gateway-for-woocommerce'); ?></strong> <code><?php echo esc_html($woo_mpesa_merchant_request_id); ?></code></p>
    <?php endif; ?>
    
    <p><?php esc_html_e('Once you complete the payment, this page will update automatically. If you did not receive the prompt, please wait a moment and refresh this page.', 'mpesa-payment-gateway-for-woocommerce'); ?></p>
</div>

<script>
(function() {
    'use strict';
    
    // Poll for payment status
    let attempts = 0;
    const maxAttempts = 60; // 5 minutes (60 * 5 seconds)
    const orderId = <?php echo (int) $order->get_id(); ?>;
    
    const checkPaymentStatus = () => {
        if (attempts >= maxAttempts) {
            console.log('Max attempts reached');
            return;
        }
        
        attempts++;
        
        fetch('<?php echo esc_url(wc_get_endpoint_url('order-received', $order->get_id(), wc_get_page_permalink('checkout'))); ?>', {
            credentials: 'same-origin'
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const thankYouSection = doc.querySelector('.mpesa-payment-gateway-thank-you');
            
            if (!thankYouSection) {
                // Payment completed, reload page
                window.location.reload();
            } else {
                // Still pending, check again
                setTimeout(checkPaymentStatus, 5000);
            }
        })
        .catch(error => {
            console.error('Error checking payment status:', error);
            setTimeout(checkPaymentStatus, 5000);
        });
    };
    
    // Start polling after 5 seconds
    setTimeout(checkPaymentStatus, 5000);
})();
</script>

