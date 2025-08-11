<?php
/**
 * Plugin Name: WooCommerce Progress Bar
 * Description: Displays a progress bar showing how close the customer is to free shipping.
 * Version: 1.0.0
 * Author: AI Assistant
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Amount required for free shipping.
 *
 * @return float
 */
function wpb_free_shipping_threshold() {
    return (float) apply_filters('wpb_free_shipping_threshold', 100);
}

/**
 * Render the progress bar on cart and checkout pages.
 */
function wpb_render_progress_bar() {
    if (is_admin() || !function_exists('WC') || !WC()->cart) {
        return;
    }

    $threshold = wpb_free_shipping_threshold();
    $cart_total = (float) WC()->cart->get_cart_contents_total();

    if ($cart_total <= 0) {
        $cart_total = (float) WC()->cart->subtotal;
    }

    $progress = $threshold > 0 ? min(100, ($cart_total / $threshold) * 100) : 0;
    $remaining = max(0, $threshold - $cart_total);

    $message = $remaining > 0
        ? sprintf(__('Add %s more to get free shipping!', 'wpb'), wc_price($remaining))
        : __('You have unlocked free shipping!', 'wpb');

    echo '<div class="wpb-progress-container">'
        . '<div class="wpb-progress-bar" style="width:' . esc_attr($progress) . '%;"></div>'
        . '</div>'
        . '<p class="wpb-progress-message">' . esc_html($message) . '</p>';
}

/**
 * Enqueue styles for the progress bar.
 */
function wpb_enqueue_assets() {
    wp_enqueue_style(
        'wpb-progress',
        plugins_url('assets/progress-bar.css', __FILE__),
        array(),
        '1.0.0'
    );
}

/**
 * Register hooks once WooCommerce is loaded.
 */
function wpb_init() {
    if (class_exists('WooCommerce')) {
        add_action('woocommerce_before_cart', 'wpb_render_progress_bar');
        add_action('woocommerce_before_checkout_form', 'wpb_render_progress_bar', 5);
        add_action('wp_enqueue_scripts', 'wpb_enqueue_assets');
    }
}
add_action('plugins_loaded', 'wpb_init');

?>