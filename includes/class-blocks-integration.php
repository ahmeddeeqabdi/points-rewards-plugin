<?php
if (!defined('ABSPATH')) exit;

class PR_Blocks_Integration {
    public function __construct() {
        // Core WooCommerce Blocks data filters
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        
        // Filter cart items before they're sent to the frontend
        add_filter('woocommerce_store_api_product_quantity_minimum', array($this, 'filter_product_data'), 10, 3);
        add_filter('woocommerce_blocks_cart_item_data', array($this, 'filter_blocks_cart_item'), 10, 3);
        
        // Most important: Filter the actual cart calculation
        add_action('woocommerce_before_calculate_totals', array($this, 'zero_points_only_prices_in_cart'), 999);
    }

    /**
     * Register our integration when Blocks are loaded
     */
    public function register_blocks_integration() {
        if (function_exists('woocommerce_store_api_register_endpoint_data')) {
            woocommerce_store_api_register_endpoint_data(array(
                'endpoint' => 'cart-items',
                'namespace' => 'points-rewards',
                'schema_callback' => array($this, 'extend_cart_schema'),
                'data_callback' => array($this, 'extend_cart_data'),
            ));
        }
    }

    /**
     * Extend cart item schema
     */
    public function extend_cart_schema() {
        return array(
            'pr_is_points_only' => array(
                'description' => 'Whether this item is points-only',
                'type' => 'boolean',
                'readonly' => true,
            ),
            'pr_points_cost' => array(
                'description' => 'Points cost for this item',
                'type' => 'integer',
                'readonly' => true,
            ),
        );
    }

    /**
     * Add custom data to cart items
     */
    public function extend_cart_data($cart_item) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return array();
        }

        $product = $cart_item['data'];
        
        if ($this->is_points_only_product($product)) {
            $product_id = $product->get_id();
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            return array(
                'pr_is_points_only' => true,
                'pr_points_cost' => $points_cost,
            );
        }

        return array(
            'pr_is_points_only' => false,
            'pr_points_cost' => 0,
        );
    }

    /**
     * Filter Blocks cart item data
     */
    public function filter_blocks_cart_item($item_data, $cart_item, $request) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $item_data;
        }

        $product = $cart_item['data'];
        
        if ($this->is_points_only_product($product)) {
            // Add points-only metadata
            $product_id = $product->get_id();
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            $item_data['pr_is_points_only'] = true;
            $item_data['pr_points_cost'] = $points_cost;
        }

        return $item_data;
    }

    /**
     * Filter product data for Blocks
     */
    public function filter_product_data($minimum, $product, $request) {
        // This hook is just to ensure we're in the loop
        return $minimum;
    }

    /**
     * CRITICAL: Zero out prices in cart BEFORE totals are calculated
     * This runs at priority 999 to ensure it's the last thing that runs
     */
    public function zero_points_only_prices_in_cart($cart) {
        if (!$cart || !is_a($cart, 'WC_Cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $product = $cart_item['data'];
            
            if ($this->is_points_only_product($product)) {
                // Force price to 0 - this affects ALL displays (cart, checkout, blocks, etc.)
                $product->set_price(0);
                
                // Also mark in cart item data
                $cart->cart_contents[$cart_item_key]['pr_points_only'] = 'yes';
            }
        }
    }

    /**
     * Check if product is in points-only mode
     */
    private function is_points_only_product($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        $points_only = get_option('pr_points_only_categories', 'no');
        if ($points_only !== 'yes') {
            return false;
        }

        return $this->can_purchase_with_points($product);
    }

    /**
     * Check if product can be purchased with points
     */
    private function can_purchase_with_points($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        $restrict_categories = get_option('pr_restrict_categories', 'no');
        
        if ($restrict_categories !== 'yes') {
            return true;
        }
        
        $allowed_categories = get_option('pr_allowed_categories', array());
        
        if (empty($allowed_categories)) {
            return true;
        }
        
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        
        return !empty(array_intersect($product_categories, (array)$allowed_categories));
    }
}
?>
