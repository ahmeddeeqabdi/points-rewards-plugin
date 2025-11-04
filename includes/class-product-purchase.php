<?php
if (!defined('ABSPATH')) exit;

class PR_Product_Purchase {
    public function __construct() {
        // ========================================================================
        // PART 1: CORE WOOCOMMERCE INTEGRATION (Classic Cart/Checkout & Backend)
        // ========================================================================
        
        // 1. Zero out product prices at the source (HIGH PRIORITY = 100)
        add_filter('woocommerce_product_get_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_variation_prices_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_variation_prices_regular_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        add_filter('woocommerce_variation_prices_sale_price', array($this, 'zero_price_for_points_purchase'), 100, 2);
        
        // 2. Mutate cart item prices BEFORE totals calculation (CRITICAL - HIGH PRIORITY = 100)
        add_action('woocommerce_before_calculate_totals', array($this, 'force_zero_price_in_cart'), 100);
        
        // 3. Override displayed prices with points HTML (HIGH PRIORITY = 100)
        add_filter('woocommerce_get_price_html', array($this, 'display_points_html'), 100, 2);
        add_filter('woocommerce_cart_item_price', array($this, 'display_cart_item_points_price'), 100, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'display_cart_item_points_subtotal'), 100, 3);
        add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'format_checkout_line_subtotal'), 100, 3);
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'format_checkout_item_quantity'), 100, 3);
        
        // 4. Order storage and point deduction
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_item_meta'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'process_points_payment'));
        add_action('woocommerce_checkout_create_order', array($this, 'apply_points_discount_to_order'), 10, 2);
        
        // 5. Quantity validation for points purchases (HIGH PRIORITY = 100)
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart_points'), 100, 6);
        add_action('woocommerce_before_calculate_totals', array($this, 'validate_points_purchase_quantities'), 100);
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_points'), 100);
        
        // 5b. Intercept AJAX add-to-cart requests (for side cart plugins and custom AJAX)
    add_action('woocommerce_ajax_added_to_cart', array($this, 'validate_ajax_add_to_cart'), 10, 1);
    add_action('wp_ajax_woocommerce_add_to_cart', array($this, 'validate_ajax_request'), 1);
    add_action('wp_ajax_nopriv_woocommerce_add_to_cart', array($this, 'validate_ajax_request'), 1);
    add_action('wp_ajax_xoo_wsc_add_to_cart', array($this, 'validate_ajax_request'), 1);
    add_action('wp_ajax_nopriv_xoo_wsc_add_to_cart', array($this, 'validate_ajax_request'), 1);
        
        // 6. Capture points purchase data when adding to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_points_purchase_data_to_cart'), 10, 3);
        
        // ========================================================================
        // PART 2: WOOCOMMERCE BLOCKS / STORE API INTEGRATION
        // ========================================================================
        
        // 1. Extend API schema (if needed - usually handled by prepare hooks)
        // 2. Prepare API responses for cart items (HIGH PRIORITY = 100)
        add_filter('woocommerce_store_api_product_price_raw', array($this, 'blocks_zero_product_price'), 100, 2);
        add_filter('woocommerce_store_api_product_price', array($this, 'blocks_zero_product_price'), 100, 2);
        add_filter('woocommerce_store_api_cart_item_product_price', array($this, 'blocks_zero_cart_item_price'), 100, 3);
        add_filter('woocommerce_store_api_cart_item', array($this, 'blocks_modify_cart_item_data'), 100, 2);
    add_filter('woocommerce_rest_cart_totals', array($this, 'blocks_modify_cart_totals'), 100, 2);
    add_filter('woocommerce_store_api_cart_data', array($this, 'blocks_modify_cart_totals'), 100, 2);
        
        // 3. Filter cart totals in API
        // Note: woocommerce_rest_cart_totals might not exist, using alternative
        
        // 4. Disable price editing for points items
    add_filter('woocommerce_store_api_product_quantity_editable', array($this, 'blocks_disable_quantity_edit'), 100, 2);
    add_filter('woocommerce_store_api_cart_item_price_is_editable', array($this, 'blocks_disable_price_edit'), 100, 2);
        
        // ========================================================================
        // SUPPORTING FUNCTIONALITY
        // ========================================================================
        
        // User interface elements
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_points_option'));
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'add_cart_points_option'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'add_checkout_points_option'));
        
        // Cart data management
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_pr_toggle_points_payment', array($this, 'ajax_toggle_points_payment'));
        add_action('wp_ajax_nopriv_pr_toggle_points_payment', array($this, 'ajax_toggle_points_payment'));
        add_action('wp_ajax_pr_get_variation_points_cost', array($this, 'ajax_get_variation_points_cost'));
        add_action('wp_ajax_nopriv_pr_get_variation_points_cost', array($this, 'ajax_get_variation_points_cost'));
        
        // Checkout handling
        add_action('woocommerce_checkout_update_order_review', array($this, 'handle_checkout_points_update'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_points_discount'));
        
        // Payment gateway management
        add_filter('woocommerce_single_product_summary', array($this, 'hide_add_to_cart_for_points_only'), 9);
        add_action('woocommerce_review_order_before_payment', array($this, 'checkout_payment_methods'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'checkout_payment_methods_top'));
        add_action('wp_footer', array($this, 'checkout_hide_payments_css'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
        
        // Scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_variation_price_script'));
    }

    /**
     * Enqueue script to handle variation price updates for points display
     */
    public function enqueue_variation_price_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || !$product->is_type('variable')) {
            return;
        }
        
        // Only enqueue when the current product is truly points-only
        if (!$this->is_points_only_product($product)) {
            return;
        }
        
        // Inline script to update points price when variation changes
        wp_add_inline_script('wc-single-product', '
        jQuery(document).ready(function ($) {
            // Get the parent product ID
            var productId = ' . intval($product->get_id()) . ';
            
            // Listen for variation change event
            $(document).on("found_variation", function(e, variation) {
                // If variation has price, update the points display
                if (variation && variation.variation_id) {
                    // Fetch the variation points cost from server
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: {
                            action: "pr_get_variation_points_cost",
                            variation_id: variation.variation_id,
                            security: "' . wp_create_nonce('pr_get_variation_points_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success && response.data.points_cost) {
                                // On single product page, do not show the price span
                                $(".woocommerce-variation-price").html("");
                            }
                        }
                    });
                }
            });
        });
        ');
    }

    /**
     * AJAX handler for getting variation points cost
     */
    public function ajax_get_variation_points_cost() {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'pr_get_variation_points_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Get variation ID
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        
        if (!$variation_id) {
            wp_send_json_error('Invalid variation ID');
            return;
        }
        
        // Get the points cost for this variation
        $points_cost = PR_Product_Points_Cost::get_product_points_cost($variation_id);
        
        wp_send_json_success(array(
            'points_cost' => $points_cost
        ));
    }

    public function add_points_option() {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;
        
        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't show points option for revoked users
        }
        
        global $product;
        
        if ($this->is_points_only_product($product)) {
            // For points-only products, show purchase button only if user has enough points
            $user_id = get_current_user_id();
            $product_id = $product->get_id();
            
            // Get the custom point cost (or calculated default)
            $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            // Get total available points (respecting manually set points flag)
            $total_available_points = PR_Points_Manager::get_user_total_points($user_id);
            
            if ($total_available_points >= $required_points) {
                wp_nonce_field('pr_use_points_nonce', 'pr_points_nonce');
                ?>
                <button type="submit" 
                        name="pr_purchase_with_points" 
                        value="yes" 
                        class="single_add_to_cart_button button alt">
                    <?php printf(__('Mit %d Punkten bezahlen', 'ahmeds-pointsystem'), $required_points); ?>
                </button>
                <p class="pr-points-info" style="margin-top: 8px; white-space: nowrap;"><?php printf(__('Du hast %d Punkte', 'ahmeds-pointsystem'), $total_available_points); ?></p>
                <?php
            } else {
                ?>
                <div class="pr-insufficient-points">
                    <p class="pr-points-required"><?php printf(__('Erfordert %d Punkte', 'ahmeds-pointsystem'), $required_points); ?></p>
                    <p class="pr-points-available"><?php printf(__('Du hast: %d Punkte', 'ahmeds-pointsystem'), $total_available_points); ?></p>
                    <p class="pr-earn-more"><?php _e('Verdiene mehr Punkte durch Einkäufe, um diesen Artikel freizuschalten.', 'ahmeds-pointsystem'); ?></p>
                </div>
                <?php
            }
        } elseif ($this->can_purchase_with_points($product)) {
            $user_id = get_current_user_id();
            $product_id = $product->get_id();
            
            // Get the custom point cost (or calculated default)
            $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            // Get total available points (respecting manually set points flag)
            $total_available_points = PR_Points_Manager::get_user_total_points($user_id);
            
            wp_nonce_field('pr_use_points_nonce', 'pr_points_nonce');
            ?>
            <div class="pr-purchase-option">
                <label>
                    <input type="checkbox" 
                           name="pr_use_points" 
                           value="yes" 
                           <?php echo $total_available_points < $required_points ? 'disabled' : ''; ?> />
                    <?php printf(__('Mit %d Punkten bezahlen (Du hast: %d Punkte)', 'ahmeds-pointsystem'), $required_points, $total_available_points); ?>
                </label>
            </div>
            <?php
        }
    }

    /**
     * Hide the default add to cart button for points-only products
     */
    public function hide_add_to_cart_for_points_only() {
        global $product;
        
        if (!$this->is_points_only_product($product)) {
            return; // Not a points-only product, allow normal behavior
        }
        
        // Hide the default add to cart button with CSS
        ?>
        <style>
            .woocommerce div.product form.cart .single_add_to_cart_button.button:not([name="pr_purchase_with_points"]) {
                display: none !important;
            }
            
            /* Wrap the quantity and button elements in a flex container */
            .woocommerce div.product form.cart {
                display: flex !important;
                align-items: flex-end !important;
                gap: 12px !important;
                flex-wrap: nowrap !important;
            }
            
            .woocommerce div.product form.cart .quantity {
                margin: 0 !important;
                flex-shrink: 0 !important;
            }
            
            .woocommerce div.product form.cart .single_add_to_cart_button[name="pr_purchase_with_points"] {
                flex-shrink: 0 !important;
                white-space: nowrap !important;
                margin: 0 !important;
            }
        </style>
        <?php
    }

    private function can_purchase_with_points($product) {
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        
        if ($restrict_categories !== 'yes') {
            return true;
        }
        
        $allowed_categories = get_option('pr_allowed_categories', array());
        
        // If no categories are selected, allow all products
        if (empty($allowed_categories)) {
            return true;
        }
        
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        
        return !empty(array_intersect($product_categories, (array)$allowed_categories));
    }

    /**
     * Capture points purchase data when adding to cart
     */
    public function add_points_purchase_data_to_cart($cart_item_data, $product_id, $variation_id) {
        error_log("Points cart data: add_points_purchase_data_to_cart called for product $product_id");
        
        $session = (is_user_logged_in() && WC()->session);
        $cart_toggle_active = $session ? (bool) WC()->session->get('pr_use_points_for_cart', false) : false;
        $using_points = false;

        // Check if the user selected to purchase with points via product form submission
        if (isset($_POST['pr_use_points']) && $_POST['pr_use_points'] === 'yes') {
            if (isset($_POST['pr_points_nonce']) && wp_verify_nonce($_POST['pr_points_nonce'], 'pr_use_points_nonce')) {
                $cart_item_data['pr_use_points'] = 'yes';
                $using_points = true;
                error_log("Points cart data: Added pr_use_points=yes to cart item data");
            } else {
                error_log("Points cart data: Nonce verification failed for pr_use_points submission");
            }
        }

        // Points-only button submission
        if (!$using_points && isset($_POST['pr_purchase_with_points']) && $_POST['pr_purchase_with_points'] === 'yes') {
            if (isset($_POST['pr_points_nonce']) && wp_verify_nonce($_POST['pr_points_nonce'], 'pr_use_points_nonce')) {
                $cart_item_data['pr_use_points'] = 'yes';
                $using_points = true;
                error_log("Points cart data: Points-only product flagged as points purchase");
            } else {
                error_log("Points cart data: Nonce verification failed for points-only submission");
            }
        }

        // Cart-level toggle applies points to all eligible items (covers Store API / blocks submissions)
        if (!$using_points && $cart_toggle_active) {
            $product = wc_get_product($variation_id ? $variation_id : $product_id);
            if ($product && $this->can_purchase_with_points($product)) {
                $cart_item_data['pr_use_points'] = 'yes';
                $using_points = true;
                error_log("Points cart data: Cart-wide toggle forced points usage for product $product_id");
            }
        }

        if ($using_points) {
            error_log("Points cart data: Product $product_id marked for points redemption");
        } else {
            error_log("Points cart data: No points usage detected for product $product_id during add-to-cart");
        }
        
        return $cart_item_data;
    }
    
    private function get_user_total_available_points($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $user_record = $wpdb->get_row($wpdb->prepare(
            "SELECT points, points_manually_set FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if (!$user_record) {
            // User has no points record yet
            error_log("Points Debug: User $user_id has no record, returning registration bonus");
            return intval(get_option('pr_registration_points', 0));
        }
        
        $points = intval($user_record->points);
        $manually_set = intval($user_record->points_manually_set);
        
        error_log("Points Debug: User $user_id, points=$points, manually_set=$manually_set");
        
        // If points were manually set by admin, use them as-is (they already include bonus)
        if ($manually_set === 1) {
            error_log("Points Debug: Using manually set points: $points");
            return $points;
        }
        
        // Otherwise, add the registration bonus to earned points
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $total = $points + $registration_bonus;
        error_log("Points Debug: Adding registration bonus ($registration_bonus) to earned points ($points) = $total");
        return $total;
    }

    /**
     * Gather a snapshot of all cart items currently using points.
     * Returns an array with item metadata and the total points committed.
     */
    private function get_points_cart_snapshot($cart) {
        $snapshot = array(
            'items' => array(),
            'total_points' => 0,
        );

        if (!$cart || !is_a($cart, 'WC_Cart')) {
            return $snapshot;
        }

        $cart_toggle_active = (is_user_logged_in() && WC()->session) ? (bool) WC()->session->get('pr_use_points_for_cart', false) : false;

    // Iterate in cart order so previously validated items remain untouched
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $product = $cart_item['data'];
            $product_id = $product->get_id();
            $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 0;
            if ($quantity <= 0) {
                continue;
            }

            $uses_points = false;

            if ($this->is_points_only_product($product)) {
                $uses_points = true;
            } elseif (isset($cart_item['pr_use_points']) && $cart_item['pr_use_points'] === 'yes') {
                $uses_points = true;
            } elseif ($cart_toggle_active && $this->can_purchase_with_points($product)) {
                $uses_points = true;
            }

            if (!$uses_points) {
                continue;
            }

            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            if ($points_cost <= 0) {
                continue;
            }

            $total_cost = $points_cost * $quantity;

            $snapshot['items'][$cart_item_key] = array(
                'product_id' => $product_id,
                'variation_id' => $product->is_type('variation') ? $product->get_id() : 0,
                'quantity' => $quantity,
                'cost_per_unit' => $points_cost,
                'total_cost' => $total_cost,
            );

            $snapshot['total_points'] += $total_cost;
        }

        return $snapshot;
    }

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
     * CENTRALIZED HELPER: Check if this is a points purchase (points-only OR regular product paid with points)
     */
    private function is_points_purchase($product, $cart_item = null) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }
        
        // Check if it's a points-only product
        if ($this->is_points_only_product($product)) {
            return true;
        }
        
        // Check if user is paying with points for a regular product
        if ($cart_item && isset($cart_item['pr_use_points'])) {
            return true;
        }
        
        // Check session for cart-wide points payment
        if (is_user_logged_in() && WC()->session && WC()->session->get('pr_use_points_for_cart', false)) {
            if ($this->can_purchase_with_points($product)) {
                return true;
            }
        }
        
        return false;
    }

    // ========================================================================
    // PART 1: CORE WOOCOMMERCE - PRICE ZEROING AT THE SOURCE
    // ========================================================================

    /**
     * CRITICAL: Zero out product price for points purchases (Priority 100)
     * This is the EARLIEST interception point - affects all price getters
     */
    public function zero_price_for_points_purchase($price, $product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return $price;
        }
        
        // Only zero out if it's a points purchase
        if ($this->is_points_purchase($product)) {
            return 0;
        }
        
        return $price;
    }

    /**
     * CRITICAL: Force zero price in cart items BEFORE totals calculation (Priority 100)
     * This is THE MOST IMPORTANT step - directly mutates WC_Product objects in cart
     */
    public function force_zero_price_in_cart($cart) {
        if (!$cart || !is_a($cart, 'WC_Cart')) {
            return;
        }
        
        if (!is_user_logged_in()) {
            return;
        }
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') {
            return;
        }
        
        // Loop through cart items
    // Iterate in cart order so earlier redemptions remain intact
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }
            
            $product = $cart_item['data'];
            
            // If this is a points purchase, force price to 0
            if ($this->is_points_purchase($product, $cart_item)) {
                $product->set_price(0);
                
                // Mark as points purchase for later reference
                $cart->cart_contents[$cart_item_key]['pr_is_points_purchase'] = true;
            }
        }
    }

    /**
     * Display points HTML instead of price (Priority 100)
     */
    public function display_points_html($price_html, $product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return $price_html;
        }
        
        if ($this->is_points_only_product($product)) {
            // On single product page, hide the price span
            if (is_product()) {
                return '';
            }
            // On listing/category pages, show the points price
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product->get_id());
            return '<span class="pr-points-price">' . esc_html($points_cost) . ' points</span>';
        }
        
        return $price_html;
    }

    /**
     * Display points for cart item price (Priority 100)
     */
    public function display_cart_item_points_price($price, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $price;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            $product_id = $product->get_id();
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            return '<span class="pr-points-product-price">' . esc_html($points_cost) . ' ' . __('Punkte', 'ahmeds-pointsystem') . '</span>';
        }
        
        return $price;
    }

    /**
     * Display points for cart item subtotal (Priority 100)
     */
    public function display_cart_item_points_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $subtotal;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            $product_id = $product->get_id();
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
            $total_points = $points_cost * max(1, $quantity);
            return '<span class="pr-points-product-price">' . esc_html($total_points) . ' ' . __('Punkte', 'ahmeds-pointsystem') . '</span>';
        }
        
        return $subtotal;
    }

    /**
     * Display points for checkout line subtotal (Priority 100)
     */
    public function format_checkout_line_subtotal($subtotal, $item, $order) {
        if (!$item->get_product_id()) {
            return $subtotal;
        }
        
        $product = wc_get_product($item->get_product_id());
        if (!$product) {
            return $subtotal;
        }
        
        if ($this->is_points_only_product($product)) {
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($item->get_product_id());
            $quantity = $item->get_quantity();
            $total_points = $points_cost * $quantity;
            
            return '<span class="pr-points-product-price">' . $total_points . ' ' . __('Punkte', 'ahmeds-pointsystem') . '</span>';
        }
        
        return $subtotal;
    }

    /**
     * Display points for checkout item quantity (Priority 100)
     */
    public function format_checkout_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $quantity_html;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            $product_id = $product->get_id();
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
            $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
            
            // Return quantity with points price
            return sprintf(
                '%s &times; <span class="pr-points-product-price">%s ' . __('Punkte', 'ahmeds-pointsystem') . '</span>',
                $quantity,
                esc_html($points_cost)
            );
        }
        
        return $quantity_html;
    }

    // ========================================================================
    // PART 2: WOOCOMMERCE BLOCKS / STORE API INTEGRATION
    // ========================================================================

    /**
     * Blocks: Zero out product price in Store API (Priority 100)
     */
    public function blocks_zero_product_price($price, $product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return $price;
        }
        
        if ($this->is_points_purchase($product)) {
            return 0;
        }
        
        return $price;
    }

    /**
     * Blocks: Zero out cart item price in Store API (Priority 100)
     */
    public function blocks_zero_cart_item_price($price, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $price;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            return 0;
        }
        
        return $price;
    }

    /**
     * Blocks: Modify cart item data in Store API to show 0 prices (Priority 100)
     */
    public function blocks_modify_cart_item_data($response, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $response;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            // Set all price fields to 0
            $response['prices'] = array(
                'price' => '0',
                'regular_price' => '0',
                'sale_price' => '0',
                'price_range' => null,
                'currency_code' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_minor_unit' => wc_get_price_decimals(),
                'currency_decimal_separator' => wc_get_price_decimal_separator(),
                'currency_thousand_separator' => wc_get_price_thousand_separator(),
                'currency_prefix' => '',
                'currency_suffix' => '',
                'raw_prices' => array(
                    'price' => 0,
                    'regular_price' => 0,
                    'sale_price' => 0,
                ),
            );
            
            // Set totals to 0
            $response['totals'] = array(
                'line_subtotal' => '0',
                'line_subtotal_tax' => '0',
                'line_total' => '0',
                'line_total_tax' => '0',
            );
            
            // Add points information
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($product->get_id());
            $response['pr_is_points_purchase'] = true;
            $response['pr_points_cost'] = $points_cost;
        }
        
        return $response;
    }

    /**
     * Blocks: Normalize cart totals in Store API / REST responses (Priority 100)
     */
    public function blocks_modify_cart_totals($data, $context = null) {
        if (!is_array($data)) {
            return $data;
        }

        // Handle full cart payloads (Store API) that contain a nested totals array.
        if (isset($data['totals']) && is_array($data['totals'])) {
            $data['totals'] = $this->adjust_api_totals($data['totals']);
            return $data;
        }

        return $this->adjust_api_totals($data);
    }

    /**
     * Determine if the current cart is a points purchase and normalise totals accordingly.
     */
    private function adjust_api_totals($totals) {
        if (!function_exists('WC') || !WC()->cart) {
            return $totals;
        }

        $cart = WC()->cart;

        if (empty($cart->get_cart())) {
            return $totals;
        }

        if (!$this->cart_all_items_are_points_purchases($cart)) {
            return $totals;
        }

        $cart_totals = $cart->get_totals();
        $shipping_total = isset($cart_totals['shipping_total']) ? (float) $cart_totals['shipping_total'] : 0.0;
        $shipping_tax_total = isset($cart_totals['shipping_tax']) ? (float) $cart_totals['shipping_tax'] : 0.0;
        $payable_total = $shipping_total + $shipping_tax_total;

        $totals = $this->zero_out_totals_array($totals);

        $totals = $this->set_total_amount($totals, 'total_shipping', $shipping_total);
        $totals = $this->set_total_amount($totals, 'total_shipping_tax', $shipping_tax_total);
        $totals = $this->set_total_amount($totals, 'shipping_total', $shipping_total);
        $totals = $this->set_total_amount($totals, 'shipping_tax', $shipping_tax_total);
        $totals = $this->set_total_amount($totals, 'total_price', $payable_total);
        $totals = $this->set_total_amount($totals, 'total_payable', $payable_total);
        $totals = $this->set_total_amount($totals, 'total', $payable_total);
        $totals = $this->set_total_amount($totals, 'total_tax', $shipping_tax_total);
        $totals = $this->set_total_amount($totals, 'grand_total', $payable_total);

        return $totals;
    }

    /**
     * Set all non-shipping totals to zero while preserving structure.
     */
    private function zero_out_totals_array($totals) {
        if (!is_array($totals)) {
            return $totals;
        }

        foreach ($totals as $key => $value) {
            if (strpos($key, 'shipping') !== false) {
                continue;
            }

            $totals[$key] = $this->format_total_amount($value, 0.0);
        }

        return $totals;
    }

    /**
     * Update a single totals entry with a specific amount.
     */
    private function set_total_amount($totals, $key, $amount) {
        if (!is_array($totals) || !array_key_exists($key, $totals)) {
            return $totals;
        }

        $totals[$key] = $this->format_total_amount($totals[$key], $amount);

        return $totals;
    }

    /**
     * Format totals data while respecting its original structure.
     */
    private function format_total_amount($field, $amount) {
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                if (is_array($value)) {
                    $field[$key] = $this->format_total_amount($value, $amount);
                    continue;
                }

                if (in_array($key, array('value', 'amount', 'raw', 'total', 'subtotal'), true)) {
                    $field[$key] = wc_format_decimal($amount, wc_get_price_decimals());
                } elseif ($key === 'formatted') {
                    $field[$key] = wp_strip_all_tags(wc_price($amount));
                }
            }

            return $field;
        }

        if (is_numeric($field) || (is_string($field) && $this->string_is_numeric_like($field))) {
            return wc_format_decimal($amount, wc_get_price_decimals());
        }

        return $field;
    }

    /**
     * Quick helper to determine if a string looks numeric after removing currency symbols.
     */
    private function string_is_numeric_like($value) {
        if (!is_string($value)) {
            return false;
        }

        $normalized = preg_replace('/[^0-9\.,\-]/', '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return $normalized !== '' && is_numeric($normalized);
    }

    /**
     * Check if every cart item is being purchased with points.
     */
    private function cart_all_items_are_points_purchases($cart) {
        $has_items = false;

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $has_items = true;

            if (!$this->is_points_purchase($cart_item['data'], $cart_item)) {
                return false;
            }
        }

        return $has_items;
    }

    /**
     * Blocks: Disable quantity editing for points-only products
     */
    public function blocks_disable_quantity_edit($editable, $product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return $editable;
        }
        
        if ($this->is_points_only_product($product)) {
            return false;
        }
        
        return $editable;
    }

    /**
     * Blocks: Disable price editing for points purchases
     */
    public function blocks_disable_price_edit($editable, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $editable;
        }
        
        $product = $cart_item['data'];
        
        if ($this->is_points_purchase($product, $cart_item)) {
            return false;
        }
        
        return $editable;
    }

    // ========================================================================
    // SUPPORTING FUNCTIONALITY (Existing methods continue below)
    // ========================================================================

    public function add_cart_item_data($cart_item_data, $product_id) {
        // Check for points-only purchase submission
        if (isset($_POST['pr_purchase_with_points'])) {
            if (wp_verify_nonce($_POST['pr_points_nonce'], 'pr_use_points_nonce')) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    wc_add_notice(__('Produkt nicht gefunden.', 'ahmeds-pointsystem'), 'error');
                    return false;
                }
                
                if ($this->is_points_only_product($product)) {
                    $user_id = get_current_user_id();
                    $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
                    
                    // Get total available points (respecting manually set points flag)
                    $total_available_points = $this->get_user_total_available_points($user_id);
                    
                    error_log("Points Purchase Debug: User ID: $user_id, Required: $required_points, Available: $total_available_points");
                    
                    if ($total_available_points >= $required_points) {
                        $cart_item_data['pr_use_points'] = 'yes';
                        $cart_item_data['pr_points_cost'] = $required_points;
                        // Mark this for the cart price override filter
                        $cart_item_data['pr_points_only'] = 'yes';
                    } else {
                        error_log("Points Purchase Rejected: User has $total_available_points but needs $required_points");
                        wc_add_notice(__('Unzureichende Punkte, um diesen Artikel zu kaufen.', 'ahmeds-pointsystem'), 'error');
                        return false; // Prevent adding to cart
                    }
                }
            }
        } elseif (isset($_POST['post_data']) || !empty($_POST)) {
            // Validate that points-only products are NOT being added without points payment
            $product = wc_get_product($product_id);
            if ($product && $this->is_points_only_product($product)) {
                // User tried to add a points-only product without the points purchase button
            wc_add_notice(__('Dieses Produkt kann nur mit Punkten gekauft werden. Bitte wähle „Mit Punkten bezahlen“ aus.', 'ahmeds-pointsystem'), 'error');
                return false; // Prevent adding to cart
            }
        }
        
        // Check for product page submission
        if (isset($_POST['pr_points_nonce'])) {
            if (wp_verify_nonce($_POST['pr_points_nonce'], 'pr_use_points_nonce')) {
                if (isset($_POST['pr_use_points']) && sanitize_text_field($_POST['pr_use_points']) === 'yes') {
                    $cart_item_data['pr_use_points'] = 'yes';
                }
            }
        }
        
        // Check for cart/checkout points payment
        $use_points_cart = WC()->session->get('pr_use_points_for_cart', false);
        if ($use_points_cart) {
            $product = wc_get_product($product_id);
            if ($product && $this->can_purchase_with_points($product) && !$this->is_points_only_product($product)) {
                $cart_item_data['pr_use_points'] = 'yes';
            }
        }
        
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        // Check if this is a points-only product or explicitly marked with pr_use_points
        $is_points_purchase = false;
        
        if (isset($cart_item['pr_use_points'])) {
            $is_points_purchase = true;
        } elseif (isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')) {
            $product = $cart_item['data'];
            if ($this->is_points_only_product($product)) {
                $is_points_purchase = true;
            }
        }
        
        if ($is_points_purchase) {
            $item_data[] = array(
                'name' => __('💰 Zahlungsmethode', 'ahmeds-pointsystem'),
                'value' => '<span style="color: #27ae60; font-weight: 600;">' . __('Punkte', 'ahmeds-pointsystem') . '</span>'
            );
        }
        return $item_data;
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['pr_use_points'])) {
            $item->add_meta_data('_pr_use_points', 'yes');
        }
    }

    public function process_points_payment($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        if (!$user_id) return;
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't process points payment for revoked users
        }
        
        $points_used = 0;
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_pr_use_points') === 'yes') {
                $product_id = $item->get_product_id();
                
                // Get the custom point cost (or calculated default)
                $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
                $quantity = $item->get_quantity();
                $total_item_points = $required_points * $quantity;
                
                $points_used += $total_item_points;
                
                // Mark the item as paid with points
                $item->add_meta_data('_pr_points_used', $total_item_points);
                $item->save();
            }
        }
        
        if ($points_used > 0) {
            // Deduct points from user
            PR_Points_Manager::redeem_points($user_id, $points_used);
            
            // Add order note
            $order->add_order_note(sprintf(__('Kunde hat %d Punkte für diesen Kauf verwendet.', 'ahmeds-pointsystem'), $points_used));
            
            // Store points used in order meta
            $order->update_meta_data('_pr_points_used', $points_used);
            $order->save();
        }
    }

    /**
     * Validate points availability BEFORE adding item to cart
     * Prevents adding quantities that exceed customer's available points
     * This runs on woocommerce_add_to_cart_validation (HIGH PRIORITY = 100)
     */
    public function validate_add_to_cart_points($passed, $product_id, $quantity, $variation_id = null, $variations = null, $cart_item_data = null) {
        error_log("Points validation: validate_add_to_cart_points called for product $product_id, quantity $quantity");

        // Only validate if user is logged in
        if (!is_user_logged_in()) {
            error_log("Points validation: User not logged in, allowing");
            return $passed;
        }

        $user_id = get_current_user_id();

        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return $passed; // Don't validate for revoked users
        }

        // Get the product
        $product = wc_get_product($variation_id ? $variation_id : $product_id);
        if (!$product) {
            return $passed;
        }

        $quantity = max(1, intval($quantity));

        $points_cost = PR_Product_Points_Cost::get_product_points_cost($product->get_id());
        $is_points_only = $this->is_points_only_product($product);
        $can_purchase_with_points = $this->can_purchase_with_points($product);

        // Determine if customer intends to use points for this addition
        $request_use_points = false;
        if (isset($_REQUEST['pr_use_points']) && sanitize_text_field($_REQUEST['pr_use_points']) === 'yes') {
            $request_use_points = true;
        }
        if (isset($_REQUEST['pr_purchase_with_points']) && sanitize_text_field($_REQUEST['pr_purchase_with_points']) === 'yes') {
            $request_use_points = true;
        }

        $cart_toggle_active = (is_user_logged_in() && WC()->session) ? (bool) WC()->session->get('pr_use_points_for_cart', false) : false;

        $addition_uses_points = false;
        if ($is_points_only) {
            $addition_uses_points = true;
        } elseif ($can_purchase_with_points && ($request_use_points || $cart_toggle_active)) {
            $addition_uses_points = true;
        }

        error_log("Points validation: Product is_points_only: " . ($is_points_only ? 'yes' : 'no') . ", can_purchase_with_points: " . ($can_purchase_with_points ? 'yes' : 'no') . ", request_use_points: " . ($request_use_points ? 'yes' : 'no') . ", addition_uses_points: " . ($addition_uses_points ? 'yes' : 'no'));

        // If this addition doesn't use points, allow it
        if (!$addition_uses_points) {
            error_log("Points validation: Addition will not use points, allowing");
            return $passed;
        }

        if ($points_cost <= 0) {
            error_log("Points validation: Product $product_id does not have a valid points cost configured");
            wc_add_notice(
                __('Dieses Produkt ist nicht für die Punkteeinlösung vorgesehen. Bitte kontaktiere den Support.', 'ahmeds-pointsystem'),
                'error'
            );
            return false;
        }

        // Calculate existing points commitment
        $cart = WC()->cart;
        $snapshot = $this->get_points_cart_snapshot($cart);
        $current_points_committed = $snapshot['total_points'];

        // Determine how many points this addition would require
        $incoming_points = $points_cost * $quantity;
        $available_points = PR_Points_Manager::get_user_total_points($user_id);
        $projected_total = $current_points_committed + $incoming_points;

        error_log("Points validation: Available points: $available_points, currently committed: $current_points_committed, incoming: $incoming_points, projected total: $projected_total");

        // Allow the addition - cart validation will adjust quantities if needed
        error_log("Points validation: Allowing addition, cart will adjust if necessary");
        return $passed;
    }

    /**
     * AJAX ADD-TO-CART VALIDATION: Intercept AJAX requests and validate before processing
     * This catches side cart plugins and custom AJAX implementations
     */
    public function validate_ajax_request() {
        error_log("Points AJAX validation: Intercepting AJAX add-to-cart request");
        
        // Extract product info from request
        $product_id = isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0;
        $variation_id = isset($_REQUEST['variation_id']) ? absint($_REQUEST['variation_id']) : 0;
        $quantity = isset($_REQUEST['quantity']) ? absint($_REQUEST['quantity']) : 1;
        if ($quantity < 1 && isset($_REQUEST['qty'])) {
            $quantity = absint($_REQUEST['qty']);
        }
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        if (!$product_id) {
            error_log("Points AJAX validation: No product_id in request");
            return;
        }
        
        error_log("Points AJAX validation: product_id=$product_id, variation_id=$variation_id, quantity=$quantity");
        
        // Run the same validation as add_to_cart
        $validation_result = $this->validate_add_to_cart_points(
            true, 
            $product_id, 
            $quantity, 
            $variation_id, 
            array(), 
            array()
        );
        
        if (!$validation_result) {
            error_log("Points AJAX validation: Validation FAILED, sending error response");
            
            // Get the WooCommerce notices
            $notices = wc_get_notices('error');
            $error_message = !empty($notices) ? $notices[0]['notice'] : 'Cannot add this item to cart.';
            
            // Clear notices so they don't show twice
            wc_clear_notices();
            
            // Send JSON error response and stop execution
            wp_send_json_error(array(
                'error' => true,
                'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id),
                'message' => $error_message
            ));
            exit;
        }
        
        error_log("Points AJAX validation: Validation passed, allowing AJAX add-to-cart to continue");
    }

    /**
     * POST-AJAX VALIDATION: Check cart after AJAX add-to-cart completes
     * This is a fallback in case the AJAX validation is bypassed
     */
    public function validate_ajax_add_to_cart($product_id) {
        error_log("Points AJAX post-validation: Checking cart after AJAX add for product $product_id");
        
        if (!is_user_logged_in()) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $user_id = get_current_user_id();
        $available_points = PR_Points_Manager::get_user_total_points($user_id);
        $snapshot = $this->get_points_cart_snapshot($cart);

        if (empty($snapshot['items'])) {
            return;
        }

        if ($snapshot['total_points'] <= $available_points) {
            return; // No adjustment needed
        }

        // Reuse cart validation logic to trim excess while preserving earlier items
        $this->validate_points_purchase_quantities();

        // After adjustment, if totals still exceed balance, surface an error
        $snapshot_after = $this->get_points_cart_snapshot($cart);
        if ($snapshot_after['total_points'] > $available_points) {
            wc_add_notice(
                sprintf(
                    __('Du hast nicht genug Punkte. Du hast %d Punkte, benötigst aber %d. Bitte überprüfe deinen Warenkorb.', 'ahmeds-pointsystem'),
                    $available_points,
                    $snapshot_after['total_points']
                ),
                'error'
            );
        }
    }

    /**
     * FINAL CHECKOUT VALIDATION: Block checkout if multiple points items or insufficient points
     * This runs on woocommerce_checkout_process (HIGH PRIORITY = 100)
     */
    public function validate_checkout_points() {
        error_log("Points checkout validation: validate_checkout_points called");

        if (!is_user_logged_in()) {
            error_log("Points checkout validation: User not logged in, allowing");
            return;
        }

        $user_id = get_current_user_id();

        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't validate for revoked users
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $available_points = PR_Points_Manager::get_user_total_points($user_id);
        $snapshot = $this->get_points_cart_snapshot($cart);

        if (empty($snapshot['items'])) {
            return;
        }

        $total_points_needed = $snapshot['total_points'];
        error_log("Points checkout validation: total points required $total_points_needed, available $available_points");

        if ($total_points_needed > $available_points) {
            wc_add_notice(
                sprintf(
                    __('Du hast nicht genug Punkte, um diesen Kauf abzuschließen. Du hast %d Punkte, benötigst aber %d. Bitte entferne einige Artikel', 'ahmeds-pointsystem'),
                    $available_points,
                    $total_points_needed
                ),
                'error'
            );
        }
    }

    /**
     * Validate and enforce quantity limits for points purchases
     * Removes items or reduces quantities if customer doesn't have enough points
     * This runs on woocommerce_before_calculate_totals (HIGH PRIORITY = 100)
     */
    public function validate_points_purchase_quantities() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();

        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't process for revoked users
        }

        $available_points = PR_Points_Manager::get_user_total_points($user_id);
        $snapshot = $this->get_points_cart_snapshot($cart);

        if (empty($snapshot['items'])) {
            return; // No points items present
        }

        if ($snapshot['total_points'] <= $available_points) {
            return; // Everything fits within balance
        }

        error_log("Points cart validation: Adjusting cart because total points {$snapshot['total_points']} exceed available $available_points");

        $cumulative_points = 0;
        $adjusted = false;
        $adjustment_messages = array();

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($snapshot['items'][$cart_item_key])) {
                continue; // Not a points item
            }

            $item = $snapshot['items'][$cart_item_key];
            $cost_per_unit = $item['cost_per_unit'];
            $quantity = $item['quantity'];
            $product_name = isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')
                ? $cart_item['data']->get_name()
                : __('diesen Artikel', 'ahmeds-pointsystem');

            if ($cost_per_unit <= 0 || $quantity <= 0) {
                continue;
            }

            $remaining_points = $available_points - $cumulative_points;

            if ($remaining_points <= 0) {
                $cart->remove_cart_item($cart_item_key);
                $adjusted = true;
                $adjustment_messages[] = sprintf(
                    __('Entfernt %1$s, weil dein Punktekonto vollständig aufgebraucht war.', 'ahmeds-pointsystem'),
                    esc_html($product_name)
                );
                error_log("Points cart validation: Removed item $cart_item_key due to zero remaining points");
                continue;
            }

            $item_total_points = $cost_per_unit * $quantity;

            if ($item_total_points <= $remaining_points) {
                $cumulative_points += $item_total_points;
                continue;
            }

            $max_quantity = intval(floor($remaining_points / $cost_per_unit));

            if ($max_quantity > 0) {
                $cart->set_quantity($cart_item_key, $max_quantity);
                $cumulative_points += $cost_per_unit * $max_quantity;
                $adjusted = true;
                $adjustment_messages[] = sprintf(
                    __('Reduziert %1$s auf Anzahl %2$d, weil nur %3$d Punkte übrig waren.', 'ahmeds-pointsystem'),
                    esc_html($product_name),
                    $max_quantity,
                    $remaining_points
                );
                error_log("Points cart validation: Reduced item $cart_item_key to quantity $max_quantity");
            } else {
                $cart->remove_cart_item($cart_item_key);
                $adjusted = true;
                $adjustment_messages[] = sprintf(
                    __('Entfernt %1$s, da %2$d Punkte erforderlich sind, aber nur %3$d übrig waren.', 'ahmeds-pointsystem'),
                    esc_html($product_name),
                    $cost_per_unit,
                    $remaining_points
                );
                error_log("Points cart validation: Removed item $cart_item_key due to insufficient remaining points");
            }
        }

        if ($adjusted) {
            $detail_message = '';
            if (!empty($adjustment_messages)) {
                $detail_message = '<br />' . implode('<br />', $adjustment_messages);
            }

            wc_add_notice(
                sprintf(
                    __('Wir haben deinen Warenkorb innerhalb deiner %1$d verfügbaren Punkte gehalten.%2$s', 'ahmeds-pointsystem'),
                    $available_points,
                    $detail_message
                ),
                'error'
            );
        }
    }

    /**
     * Add points payment option to cart page
     */
    public function add_cart_points_option() {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;

        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't show points option for revoked users
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        // Check if cart has any eligible products (exclude points-only products)
        $has_eligible_products = false;
        $total_points_needed = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if ($this->can_purchase_with_points($product) && !$this->is_points_only_product($product)) {
                $has_eligible_products = true;
                $product_id = $product->get_id();
                $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
                $total_points_needed += $points_cost * $cart_item['quantity'];
            }
        }

        if (!$has_eligible_products) return;

        $user_id = get_current_user_id();
        $total_available_points = $this->get_user_total_available_points($user_id);

        $can_afford = $total_available_points >= $total_points_needed;
        $use_points = WC()->session->get('pr_use_points_for_cart', false);

        ?>
        <tr class="pr-cart-points-row">
            <th colspan="2">
                <div class="pr-cart-points-option">
                    <label>
                        <input type="checkbox" 
                               name="pr_use_points_cart" 
                               value="yes" 
                               <?php echo $use_points ? 'checked' : ''; ?>
                               <?php echo !$can_afford ? 'disabled' : ''; ?>
                               onchange="prTogglePointsPayment(this.checked)" />
                        Pay with points (<?php echo $total_points_needed; ?> points needed, you have <?php echo $total_available_points; ?>)
                    </label>
                    <?php if (!$can_afford): ?>
                        <p style="color: #dc3545; margin: 5px 0 0 0; font-size: 12px;">You don't have enough points for this purchase.</p>
                    <?php endif; ?>
                </div>
            </th>
        </tr>
        <?php
    }

    /**
     * Add points payment option to checkout page
     */
    public function add_checkout_points_option() {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;

        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't show points option for revoked users
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        // Check if cart has any eligible products (exclude points-only products)
        $has_eligible_products = false;
        $total_points_needed = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if ($this->can_purchase_with_points($product) && !$this->is_points_only_product($product)) {
                $has_eligible_products = true;
                $product_id = $product->get_id();
                $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
                $total_points_needed += $points_cost * $cart_item['quantity'];
            }
        }

        if (!$has_eligible_products) return;

        $user_id = get_current_user_id();
        // Get total available points (respecting manually set points flag)
        $total_available_points = $this->get_user_total_available_points($user_id);

        $can_afford = $total_available_points >= $total_points_needed;
        $use_points = WC()->session->get('pr_use_points_for_cart', false);
        
        // Also check for checkout form submission
        if (isset($_POST['pr_use_points_checkout']) && $_POST['pr_use_points_checkout'] === 'yes') {
            $use_points = true;
            WC()->session->set('pr_use_points_for_cart', true);
        }

        ?>
        <tr class="pr-checkout-points-row">
            <td colspan="2">
                <div class="pr-checkout-points-option">
                    <label>
                        <input type="checkbox" 
                               name="pr_use_points_checkout" 
                               value="yes" 
                               <?php echo $use_points ? 'checked' : ''; ?>
                               <?php echo !$can_afford ? 'disabled' : ''; ?>
                               onchange="prTogglePointsPayment(this.checked)" />
                        Pay with points (<?php echo $total_points_needed; ?> points needed, you have <?php echo $total_available_points; ?>)
                    </label>
                    <?php if (!$can_afford): ?>
                        <p style="color: #dc3545; margin: 5px 0 0 0; font-size: 12px;">You don't have enough points for this purchase.</p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Calculate points discount for cart
     */
    public function calculate_points_discount($cart) {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;

        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't apply points discount for revoked users
        }

        $use_points = WC()->session->get('pr_use_points_for_cart', false);
        if (!$use_points) return;

        $total_discount = 0;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if ($this->can_purchase_with_points($product)) {
                $product_id = $product->get_id();
                $quantity = $cart_item['quantity'];
                
                if ($this->is_points_only_product($product)) {
                    // Points-only products should already be zero-priced via price filters; no extra discount here
                    continue;
                } elseif ($use_points) {
                    // For regular points-eligible products, only discount if points payment is selected
                    $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
                    $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
                    $discount_amount = ($points_cost * $quantity) * $conversion_rate;
                    $total_discount += $discount_amount;
                }
            }
        }

        if ($total_discount > 0) {
            $cart->add_fee(__('Punkte-Rabatt', 'ahmeds-pointsystem'), -$total_discount, true, '');
        }
    }

    /**
     * AJAX handler for toggling points payment
     */
    public function ajax_toggle_points_payment() {
        if (!is_user_logged_in()) {
            wp_die('Not logged in');
        }

        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'pr_points_payment_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            wp_die('Access denied'); // Don't allow revoked users to toggle points payment
        }

        $use_points = isset($_POST['use_points']) && $_POST['use_points'] === 'true';
        WC()->session->set('pr_use_points_for_cart', $use_points);

        wp_send_json_success();
    }

    /**
     * Update cart item data when points payment is toggled
     */
    /**
     * Apply points discount to order during checkout
     */
    public function apply_points_discount_to_order($order, $data) {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;

        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't apply points discount for revoked users
        }

        $use_points = WC()->session->get('pr_use_points_for_cart', false);
        if (!$use_points) return;

        $total_discount = 0;
        $points_used = 0;
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($this->can_purchase_with_points($product)) {
                $product_id = $product->get_id();
                $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
                $quantity = $item->get_quantity();
                
                // Calculate discount based on points cost
                $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
                $discount_amount = ($points_cost * $quantity) * $conversion_rate;
                $total_discount += $discount_amount;
                $points_used += $points_cost * $quantity;
                
                // Mark item as paid with points
                $item->add_meta_data('_pr_use_points', 'yes');
                $item->add_meta_data('_pr_points_used', $points_cost * $quantity);
            }
        }

        if ($total_discount > 0) {
            // Add a negative fee for the points discount
            $order->add_fee(__('Punkte-Rabatt', 'ahmeds-pointsystem'), -$total_discount, true, '');
            
            // Store points information in order meta
            $order->update_meta_data('_pr_points_used', $points_used);
            $order->update_meta_data('_pr_points_discount', $total_discount);
        }
    }

    /**
     * Handle checkout points update via AJAX
     */
    public function handle_checkout_points_update($posted_data) {
        if (!is_user_logged_in()) return;
        
        $user_id = get_current_user_id();
        
        // Check if user is revoked
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            return; // Don't allow revoked users to toggle points payment
        }
        
        parse_str($posted_data, $data);
        
        if (isset($data['pr_use_points_checkout'])) {
            $use_points = $data['pr_use_points_checkout'] === 'yes';
            WC()->session->set('pr_use_points_for_cart', $use_points);
        }
    }

    /**
     * Check if cart has any points-only products
     */
    /**
     * Check if cart has any products with explicit points costs
     */
    private function cart_has_points_products() {
        $cart = WC()->cart;
        if (!$cart) return false;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) continue;
            
            // Check if product has explicit points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate total points needed for all products with explicit points costs in cart
     */
    private function calculate_cart_points_needed() {
        $cart = WC()->cart;
        if (!$cart) return 0;
        
        $total_points = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) continue;
            
            // Check if product has explicit points cost
            $explicit_cost = PR_Product_Points_Cost::get_explicit_custom_points_cost($product->get_id());
            if ($explicit_cost !== false) {
                $total_points += $explicit_cost * $cart_item['quantity'];
            }
        }
        
        return $total_points;
    }

    /**
     * Display payment method note at checkout (top position)
     */
    public function checkout_payment_methods_top() {
        if ($this->cart_has_points_products()) {
            $total_points_needed = $this->calculate_cart_points_needed();
            $user_points = $this->get_user_total_available_points(get_current_user_id());
            ?>
            <div class="pr-checkout-points-notice" style="margin-bottom: 20px;">
                <p><strong>⭐ Points Purchase:</strong> You are purchasing products using points. Total points needed: <?php echo $total_points_needed; ?>. Your current balance: <?php echo $user_points; ?> points.</p>
            </div>
            <?php
        }
    }

    /**
     * Display payment method note at checkout
     */
    public function checkout_payment_methods() {
        if ($this->cart_has_points_products()) {
            $total_points_needed = $this->calculate_cart_points_needed();
            $user_points = $this->get_user_total_available_points(get_current_user_id());
            ?>
            <div class="pr-checkout-points-notice">
                <p><strong>⭐ Points Purchase:</strong> You are purchasing products using points. Total points needed: <?php echo $total_points_needed; ?>. Your current balance: <?php echo $user_points; ?> points.</p>
            </div>
            <?php
        }
    }

    /**
     * Hide payment gateways if cart contains points-only products
     */
    public function checkout_hide_payments_css() {
        if (!is_checkout()) return;

        // Only hide payment section if the cart contains ONLY points-only products
        // and the order does not need any payment (e.g. free shipping)
        if ($this->cart_is_all_points_only_products() && function_exists('WC') && WC()->cart && !WC()->cart->needs_payment()) {
            ?>
            <style>
                #payment { display: none !important; }
                .woocommerce-checkout #order_review_heading { display: block !important; }
                .pr-checkout-points-notice {
                    background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%);
                    border-left: 4px solid #2271b1;
                    padding: 15px 20px;
                    margin-bottom: 20px;
                    border-radius: 6px;
                    color: #0c5aa0;
                    box-shadow: 0 2px 4px rgba(34, 113, 177, 0.1);
                }
                .pr-checkout-points-notice p { margin: 0; font-size: 15px; line-height: 1.6; font-weight: 500; }
            </style>
            <?php
        } else {
            // Keep the notice styling available even when payments are visible
            ?>
            <style>
                .pr-checkout-points-notice {
                    background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%);
                    border-left: 4px solid #2271b1;
                    padding: 15px 20px;
                    margin-bottom: 20px;
                    border-radius: 6px;
                    color: #0c5aa0;
                    box-shadow: 0 2px 4px rgba(34, 113, 177, 0.1);
                }
                .pr-checkout-points-notice p { margin: 0; font-size: 15px; line-height: 1.6; font-weight: 500; }
            </style>
            <?php
        }
    }

    /**
     * Remove payment gateways when no monetary payment is required.
     * Keep shipping methods visible; gateways remain if shipping requires payment.
     */
    public function filter_payment_gateways($gateways) {
        if (!is_checkout()) return $gateways;
        if (!function_exists('WC') || !WC()->cart) return $gateways;

        // If all items are points-only AND no payment is needed, remove gateways
        if ($this->cart_is_all_points_only_products() && !WC()->cart->needs_payment()) {
            return array();
        }

        // Otherwise, keep gateways (customer may need to pay shipping)
        return $gateways;
    }

    /**
     * Check if all products in the cart are points-only
     */
    private function cart_is_all_points_only_products() {
        $cart = WC()->cart;
        if (!$cart) return false;

        $has_items = false;
        foreach ($cart->get_cart() as $cart_item) {
            $has_items = true;
            $product = $cart_item['data'];
            if (!$this->is_points_only_product($product)) {
                return false;
            }
        }
        return $has_items; // true only if there was at least one item and all were points-only
    }
}
?>