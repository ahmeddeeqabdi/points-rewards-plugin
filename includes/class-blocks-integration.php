<?php
if (!defined('ABSPATH')) exit;

class PR_Blocks_Integration {
    public function __construct() {
        // Core WooCommerce Blocks data filters
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
        
        // Filter cart items before they're sent to the frontend
        add_filter('woocommerce_store_api_product_quantity_minimum', array($this, 'filter_product_data'), 10, 3);
        add_filter('woocommerce_blocks_cart_item_data', array($this, 'filter_blocks_cart_item'), 10, 3);
        
        // Add notice to cart response using the REST API filter
        add_filter('rest_request_after_callbacks', array($this, 'add_notice_to_rest_response'), 10, 3);
        
        // Most important: Filter the actual cart calculation
        add_action('woocommerce_before_calculate_totals', array($this, 'zero_points_only_prices_in_cart'), 999);

        // Add Kadence-specific checkout hooks for points notice
        add_action('kadence_woocommerce_checkout_before_order_review', array($this, 'add_kadence_checkout_notice'), 10);
        add_action('kadence_woocommerce_checkout_payment', array($this, 'add_kadence_checkout_notice'), 5);
        add_action('kadence_checkout_before_order_review', array($this, 'add_kadence_checkout_notice'), 10);
        add_action('kadence_checkout_payment_methods', array($this, 'add_kadence_checkout_notice'), 5);

        // Add direct checkout hooks as fallback
        add_action('woocommerce_checkout_before_customer_details', array($this, 'add_direct_checkout_notice'), 5);
        add_action('woocommerce_checkout_before_order_review', array($this, 'add_direct_checkout_notice'), 20);
        add_action('woocommerce_before_checkout_form', array($this, 'add_checkout_page_notice'), 5);

        // Add filter to modify checkout block content
        add_filter('render_block', array($this, 'modify_checkout_block_content'), 10, 2);
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

            // Add checkout notice for points purchases
            woocommerce_store_api_register_endpoint_data(array(
                'endpoint' => 'checkout',
                'namespace' => 'points-rewards',
                'schema_callback' => array($this, 'extend_checkout_schema'),
                'data_callback' => array($this, 'extend_checkout_data'),
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
     * Extend checkout schema for points notice
     */
    public function extend_checkout_schema() {
        return array(
            'pr_points_purchase_notice' => array(
                'description' => 'Notice to display when purchasing with points',
                'type' => 'string',
                'readonly' => true,
            ),
        );
    }

    /**
     * Add points purchase notice to checkout data
     */
    public function extend_checkout_data() {
        $cart = WC()->cart;
        if (!$cart) {
            return array('pr_points_purchase_notice' => '');
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            // Check if product has an explicit custom points cost (not just points-only products)
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
            }
        }

        if ($has_points_products) {
            $user_points = PR_Points_Manager::get_user_total_points(get_current_user_id());
            $notice = sprintf(
                __('Du køber produkter med point. I alt nødvendige point: %d. Din aktuelle saldo: %d point.', 'points-rewards'),
                $total_points_needed,
                $user_points
            );

            return array('pr_points_purchase_notice' => $notice);
        }

        return array('pr_points_purchase_notice' => '');
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
     * Add points purchase notice to REST API response (intercept after callbacks)
     */
    public function add_notice_to_rest_response($response, $server, $request) {
        // Only modify cart endpoint responses
        if (!$request || strpos($request->get_route(), '/wc/store/cart') === false) {
            return $response;
        }

        // Get the response data
        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        // Build the notice
        $notice_text = $this->get_points_notice();
        
        // Add to extensions
        if (!isset($data['extensions'])) {
            $data['extensions'] = array();
        }
        if (!isset($data['extensions']['points-rewards'])) {
            $data['extensions']['points-rewards'] = array();
        }

        $data['extensions']['points-rewards']['pr_points_purchase_notice'] = $notice_text;

        // Set the modified data back
        $response->set_data($data);
        return $response;
    }

    /**
     * Generate the points purchase notice text
     */
    private function get_points_notice() {
        $cart = WC()->cart;
        if (!$cart) {
            return '';
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $product = $cart_item['data'];
            // Check for any product with explicit points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
            }
        }

        if (!$has_points_products) {
            return '';
        }

        $user_id = get_current_user_id();
        $user_points = 0;
        if ($user_id) {
            // Use proper points calculation (same as product purchase class)
            global $wpdb;
            $table_name = $wpdb->prefix . 'user_points';
            $user_record = $wpdb->get_row($wpdb->prepare(
                "SELECT points, points_manually_set FROM $table_name WHERE user_id = %d",
                $user_id
            ));
            
            if (!$user_record) {
                $user_points = intval(get_option('pr_registration_points', 0));
            } elseif (intval($user_record->points_manually_set) === 1) {
                $user_points = intval($user_record->points);
            } else {
                $registration_bonus = intval(get_option('pr_registration_points', 0));
                $user_points = intval($user_record->points) + $registration_bonus;
            }
        }

        return sprintf(
            __('⭐ Du køber produkter med point. I alt nødvendige point: %d. Din aktuelle saldo: %d point.', 'points-rewards'),
            $total_points_needed,
            $user_points
        );
    }

    /**
     * Add points purchase notice to cart response data (OLD - for reference)
     */
    public function add_notice_to_cart_response($cart_data) {
        if (!is_array($cart_data)) {
            return $cart_data;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return $cart_data;
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $product = $cart_item['data'];
            if ($this->is_points_only_product($product)) {
                $has_points_products = true;
                $points_cost = PR_Product_Points_Cost::get_product_points_cost($product->get_id());
                $total_points_needed += $points_cost * $cart_item['quantity'];
            }
        }

        // Add notice to response
        if (!isset($cart_data['extensions'])) {
            $cart_data['extensions'] = array();
        }
        if (!isset($cart_data['extensions']['points-rewards'])) {
            $cart_data['extensions']['points-rewards'] = array();
        }

        if ($has_points_products) {
            $user_id = get_current_user_id();
            $user_points = 0;
            if ($user_id) {
                $user_points = get_user_meta($user_id, 'pr_user_points', true) ?: 0;
            }

            $notice = sprintf(
                __('⭐ Du køber produkter med point. I alt nødvendige point: %d. Din aktuelle saldo: %d point.', 'points-rewards'),
                $total_points_needed,
                $user_points
            );

            $cart_data['extensions']['points-rewards']['pr_points_purchase_notice'] = $notice;
        } else {
            $cart_data['extensions']['points-rewards']['pr_points_purchase_notice'] = '';
        }

        return $cart_data;
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
     * A product is points-only ONLY when:
     * 1) Points-only mode is enabled (pr_points_only_categories = yes)
     * 2) Category restrictions are enabled (pr_restrict_categories = yes)
     * 3) Product is in an allowed category
     */
    /**
     * CENTRALIZED HELPER: Check if product is in points-only mode
     * Used across all hooks to determine if a product is points-based
     * A product is points-only ONLY when:
     * 1) Points-only mode is enabled (pr_points_only_categories = yes)
     * 2) Category restrictions are enabled (pr_restrict_categories = yes)
     * 3) Product is in an allowed category
     * 4) Product has an EXPLICIT custom points cost set (not calculated)
     */
    private function is_points_only_product($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        // Must have points-only mode enabled
        $points_only = get_option('pr_points_only_categories', 'no');
        if ($points_only !== 'yes') {
            return false;
        }
        
        // Must have category restrictions enabled
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        if ($restrict_categories !== 'yes') {
            return false;
        }

        $allowed_categories = get_option('pr_allowed_categories', array());
        if (empty($allowed_categories)) {
            return false;
        }

        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        if (empty(array_intersect((array) $allowed_categories, $product_categories))) {
            return false;
        }

        // NEW: Must have an EXPLICIT custom points cost set (not calculated/default)
        $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
        return $explicit_cost !== false;
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

    /**
     * Add points notice directly to checkout page (fallback method)
     */
    public function add_direct_checkout_notice() {
        error_log('Points & Rewards: add_direct_checkout_notice called');

        $cart = WC()->cart;
        if (!$cart) {
            error_log('Points & Rewards: No cart found');
            return;
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            // Check if product has an explicit custom points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
                error_log('Points & Rewards: Found points product - cost: ' . $explicit_cost . ', quantity: ' . $cart_item['quantity']);
            }
        }

        if ($has_points_products) {
            $user_points = PR_Points_Manager::get_user_total_points(get_current_user_id());

            error_log('Points & Rewards: Displaying direct checkout notice - Points needed: ' . $total_points_needed . ', User balance: ' . $user_points);

            ?>
            <div class="pr-points-notice-wrapper" style="margin-bottom: 24px; width: 100%;">
                <div class="pr-points-notice" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    position: relative;
                    overflow: hidden;
                ">
                    <div style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
                        opacity: 0.5;
                    "></div>
                    <div style="position: relative; z-index: 1; display: flex; align-items: center; gap: 12px;">
                        <div style="flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" fill="white"/>
                            </svg>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px; opacity: 0.9;">
                                <?php _e('Points Purchase', 'points-rewards'); ?>
                            </div>
                            <div style="font-size: 13px; line-height: 1.4; opacity: 0.95;">
                                <?php printf(
                                    __('I alt nødvendige point: %d • Din saldo: %d point', 'points-rewards'),
                                    $total_points_needed,
                                    $user_points
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            error_log('Points & Rewards: No points products found in cart');
        }
    }

    /**
     * Add points notice at the very top of checkout page
     */
    public function add_checkout_page_notice() {
        error_log('Points & Rewards: add_checkout_page_notice called');

        $cart = WC()->cart;
        if (!$cart) {
            error_log('Points & Rewards: No cart found in page notice');
            return;
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            // Check if product has an explicit custom points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
                error_log('Points & Rewards: Page notice - Found points product - cost: ' . $explicit_cost . ', quantity: ' . $cart_item['quantity']);
            }
        }

        if ($has_points_products) {
            $user_points = PR_Points_Manager::get_user_total_points(get_current_user_id());

            error_log('Points & Rewards: Page notice - Displaying checkout notice - Points needed: ' . $total_points_needed . ', User balance: ' . $user_points);

            ?>
            <div class="pr-points-notice-wrapper" style="margin-bottom: 24px; width: 100%; max-width: 100%; box-sizing: border-box;">
                <div class="pr-points-notice" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    position: relative;
                    overflow: hidden;
                    margin: 0 auto;
                ">
                    <div style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
                        opacity: 0.5;
                    "></div>
                    <div style="position: relative; z-index: 1; display: flex; align-items: center; gap: 12px;">
                        <div style="flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" fill="white"/>
                            </svg>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px; opacity: 0.9;">
                                <?php _e('Points Purchase', 'points-rewards'); ?>
                            </div>
                            <div style="font-size: 13px; line-height: 1.4; opacity: 0.95;">
                                <?php printf(
                                    __('I alt nødvendige point: %d • Din saldo: %d point', 'points-rewards'),
                                    $total_points_needed,
                                    $user_points
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            error_log('Points & Rewards: Page notice - No points products found in cart');
        }
    }

    /**
     * Modify checkout block content to add points notice
     */
    public function modify_checkout_block_content($block_content, $block) {
        // Only modify woocommerce/checkout block
        if ($block['blockName'] !== 'woocommerce/checkout') {
            return $block_content;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return $block_content;
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            // Check if product has an explicit custom points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
            }
        }

        if ($has_points_products) {
            $user_points = PR_Points_Manager::get_user_total_points(get_current_user_id());

            // Debug log
            error_log('Points & Rewards: Modifying checkout block content - Points needed: ' . $total_points_needed . ', User balance: ' . $user_points);

            $notice_html = '<div class="pr-points-notice-wrapper" style="margin-bottom: 24px; width: 100%;">';
            $notice_html .= '<div class="pr-points-notice" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15); border: 1px solid rgba(255, 255, 255, 0.1); position: relative; overflow: hidden;">';
            $notice_html .= '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%); opacity: 0.5;"></div>';
            $notice_html .= '<div style="position: relative; z-index: 1; display: flex; align-items: center; gap: 12px;">';
            $notice_html .= '<div style="flex-shrink: 0;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" fill="white"/></svg></div>';
            $notice_html .= '<div style="flex: 1;"><div style="font-weight: 600; font-size: 14px; margin-bottom: 4px; opacity: 0.9;">' . __('Pointindkøb', 'points-rewards') . '</div>';
            $notice_html .= '<div style="font-size: 13px; line-height: 1.4; opacity: 0.95;">' . sprintf(__('I alt nødvendige point: %d • Din saldo: %d point', 'points-rewards'), $total_points_needed, $user_points) . '</div></div></div></div></div>';

            // Insert the notice after the opening div of the checkout block, but before the first inner block
            // This should place it at the top of the checkout form without breaking layout
            $pattern = '/(<div class="wp-block-woocommerce-checkout[^"]*"[^>]*>)(.*?)(<div class="wp-block-woocommerce-checkout)/s';
            if (preg_match($pattern, $block_content)) {
                $block_content = preg_replace($pattern, '$1' . $notice_html . '$3', $block_content, 1);
            } else {
                // Fallback: insert after opening div
                $block_content = preg_replace(
                    '/(<div class="wp-block-woocommerce-checkout[^"]*"[^>]*>)/',
                    '$1' . $notice_html,
                    $block_content,
                    1
                );
            }
        }

        return $block_content;
    }

    /**
     * Add points notice to Kadence checkout
     */
    public function add_kadence_checkout_notice() {
        error_log('Points & Rewards: add_kadence_checkout_notice called');

        $cart = WC()->cart;
        if (!$cart) {
            error_log('Points & Rewards: No cart found in Kadence method');
            return;
        }

        $has_points_products = false;
        $total_points_needed = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            // Check if product has an explicit custom points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $has_points_products = true;
                $total_points_needed += $explicit_cost * $cart_item['quantity'];
                error_log('Points & Rewards: Kadence - Found points product - cost: ' . $explicit_cost . ', quantity: ' . $cart_item['quantity']);
            }
        }

        if ($has_points_products) {
            $user_points = PR_Points_Manager::get_user_total_points(get_current_user_id());

            error_log('Points & Rewards: Kadence - Displaying checkout notice - Points needed: ' . $total_points_needed . ', User balance: ' . $user_points);

            ?>
            <div class="pr-points-notice-wrapper" style="margin-bottom: 24px; width: 100%;">
                <div class="pr-points-notice" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    position: relative;
                    overflow: hidden;
                ">
                    <div style="
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
                        opacity: 0.5;
                    "></div>
                    <div style="position: relative; z-index: 1; display: flex; align-items: center; gap: 12px;">
                        <div style="flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" fill="white"/>
                            </svg>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px; opacity: 0.9;">
                                <?php _e('Points Purchase', 'points-rewards'); ?>
                            </div>
                            <div style="font-size: 13px; line-height: 1.4; opacity: 0.95;">
                                <?php printf(
                                    __('I alt nødvendige point: %d • Din saldo: %d point', 'points-rewards'),
                                    $total_points_needed,
                                    $user_points
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            error_log('Points & Rewards: Kadence - No points products found in cart');
        }
    }
}
?>
