<?php
/**
 * Uninstall Script
 * 
 * Fired when the plugin is uninstalled
 *
 * @package WooMpesa
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall plugins
if (!current_user_can('activate_plugins')) {
    exit;
}

/**
 * Clean up plugin data
 * 
 * Note: This only runs if the user explicitly uninstalls the plugin.
 * Deactivation does not trigger this file.
 */

// Delete plugin options
delete_option('woocommerce_mpesa_settings');
delete_option('woo_mpesa_version');

// Delete transients
delete_transient('woo_mpesa_access_token');
delete_transient('woo_mpesa_api_response');
delete_transient('woo_mpesa_activation_notice');

// Clean up cached data for all sites in multisite
if (is_multisite()) {
    global $wpdb;
    
    // Direct database query is acceptable in uninstall context for cleanup
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $woo_mpesa_blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    
    foreach ($woo_mpesa_blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Delete options for this site
        delete_option('woocommerce_mpesa_settings');
        delete_option('woo_mpesa_version');
        
        // Delete transients for this site
        delete_transient('woo_mpesa_access_token');
        delete_transient('woo_mpesa_api_response');
        delete_transient('woo_mpesa_activation_notice');
        
        restore_current_blog();
    }
}

// Optional: Clean up order meta data
// Uncomment the following if you want to remove all M-Pesa related order meta
// Note: This will permanently delete transaction history from orders

/*
global $wpdb;

// Delete order meta data
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
    WHERE meta_key LIKE '_mpesa_%'"
);

// For HPOS (WooCommerce 8.0+)
if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}wc_orders_meta 
        WHERE meta_key LIKE '_mpesa_%'"
    );
}
*/

// Optional: Delete log files
// Uncomment to remove M-Pesa log files

/*
if (function_exists('wc_get_log_file_path')) {
    $log_files = glob(wc_get_log_file_path(' woocommerce-mpesa-payment-gateway') . '*');
    if ($log_files) {
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                @unlink($log_file);
            }
        }
    }
}
*/

// Clear any scheduled cron jobs (if any)
wp_clear_scheduled_hook('woo_mpesa_cleanup_logs');

// Flush rewrite rules
flush_rewrite_rules();

// That's all! Plugin data has been cleaned up.

