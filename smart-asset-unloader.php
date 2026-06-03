<?php
/**
 * Plugin Name: Smart WooCommerce Asset Unloader
 * Plugin URI:  https://example.com/smart-asset-unloader
 * Description: Conditionally dequeues WooCommerce scripts and styles (Slick, PhotoSwipe, etc.) on pages unrelated to shop, product, cart, or checkout — improving page speed sitewide.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: smart-asset-unloader
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

/**
 * -------------------------------------------------------------------------
 * CONFIGURATION
 * -------------------------------------------------------------------------
 * You can extend these lists as needed.
 * -------------------------------------------------------------------------
 */

/**
 * Scripts (handles) to dequeue on non-WooCommerce pages.
 */
$sau_scripts_to_unload = [
    // Slick Carousel (used by some WooCommerce themes for product galleries)
    'slick',
    'slick-carousel',
    'wc-slick',

    // PhotoSwipe (WooCommerce product gallery lightbox)
    'photoswipe',
    'photoswipe-ui-default',
    'wc-photoswipe',          // older WooCommerce alias

    // WooCommerce core frontend scripts
    'woocommerce',
    'wc-add-to-cart',
    'wc-add-to-cart-variation',
    'wc-cart-fragments',
    'wc-single-product',
    'jquery-cookie',           // used by WC cart
    'jquery-blockui',          // used by WC checkout/cart

    // WooCommerce payment scripts (only needed on checkout)
    'wc-checkout',
    'select2',                 // address autocomplete on checkout
    'selectWoo',               // WooCommerce fork of Select2

    // Review / rating scripts
    'wc-country-select',
    'wc-address-i18n',
];

/**
 * Styles (handles) to dequeue on non-WooCommerce pages.
 */
$sau_styles_to_unload = [
    'woocommerce-general',
    'woocommerce-layout',
    'woocommerce-smallscreen',
    'woocommerce',
    'wc-blocks-style',
    'photoswipe',
    'photoswipe-default-skin',
    'slick',
    'slick-theme',
    'select2',
    'selectWoo',
];

/**
 * -------------------------------------------------------------------------
 * CORE LOGIC
 * -------------------------------------------------------------------------
 */

/**
 * Determine whether the current page is WooCommerce-related.
 * Returns true if we're on a shop, product, cart, checkout, account,
 * order-confirmation (thank-you), or any WooCommerce taxonomy page.
 *
 * @return bool
 */
function sau_is_woocommerce_page(): bool {
    if ( ! function_exists( 'is_woocommerce' ) ) {
        return false;
    }

    return (
        is_woocommerce()   // shop, product, product category, product tag
        || is_cart()
        || is_checkout()
        || is_account_page()
        || is_order_received_page()
        || is_wc_endpoint_url()
    );
}

/**
 * Dequeue WooCommerce assets on non-WooCommerce pages.
 */
function sau_dequeue_woocommerce_assets(): void {
    // Only run on the frontend
    if ( is_admin() ) {
        return;
    }

    // If this IS a WooCommerce page, do nothing — keep all assets loaded
    if ( sau_is_woocommerce_page() ) {
        return;
    }

    global $sau_scripts_to_unload, $sau_styles_to_unload;

    // --- Dequeue & deregister scripts ---
    foreach ( $sau_scripts_to_unload as $handle ) {
        wp_dequeue_script( $handle );
        wp_deregister_script( $handle );
    }

    // --- Dequeue & deregister styles ---
    foreach ( $sau_styles_to_unload as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }
}

// Hook in late (priority 99) so WooCommerce has already registered its assets
add_action( 'wp_enqueue_scripts', 'sau_dequeue_woocommerce_assets', 99 );


/**
 * -------------------------------------------------------------------------
 * OPTIONAL: Also remove WooCommerce generator meta tag on non-shop pages
 * -------------------------------------------------------------------------
 */
function sau_remove_wc_generator_tag(): void {
    if ( ! sau_is_woocommerce_page() ) {
        remove_action( 'wp_head', [ $GLOBALS['woocommerce'], 'generator' ] );
    }
}
add_action( 'wp_head', 'sau_remove_wc_generator_tag', 1 );


/**
 * -------------------------------------------------------------------------
 * OPTIONAL: Prevent WooCommerce from loading its session handler & cookies
 * on pages that don't need it (reduces database queries too).
 * NOTE: Disable this if you display a mini-cart widget sitewide.
 * -------------------------------------------------------------------------
 */
// Uncomment the block below to enable:
/*
add_filter( 'woocommerce_session_handler', function( $handler ) {
    if ( ! sau_is_woocommerce_page() && ! is_admin() ) {
        // Return a no-op session handler to skip DB session queries
        return 'WC_Session'; // abstract base — no actual session started
    }
    return $handler;
} );
*/


/**
 * -------------------------------------------------------------------------
 * ADMIN NOTICE: Remind admins that the plugin is active
 * -------------------------------------------------------------------------
 */
function sau_admin_notice(): void {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'plugins' ) {
        echo '<div class="notice notice-info is-dismissible">
            <p><strong>Smart WooCommerce Asset Unloader</strong> is active.
            WooCommerce scripts &amp; styles (Slick, PhotoSwipe, etc.) will only
            load on shop, product, cart, checkout, and account pages.</p>
        </div>';
    }
}
add_action( 'admin_notices', 'sau_admin_notice' );
