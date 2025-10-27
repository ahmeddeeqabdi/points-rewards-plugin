<?php
if (!defined('ABSPATH')) exit;

class PR_Product_Purchase {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_points_option'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_item_meta'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'process_points_payment'));
        
        // Cart and checkout functionality
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'add_cart_points_option'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'add_checkout_points_option'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_points_discount'));
        add_action('wp_ajax_pr_toggle_points_payment', array($this, 'ajax_toggle_points_payment'));
        add_action('wp_ajax_nopriv_pr_toggle_points_payment', array($this, 'ajax_toggle_points_payment'));
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_points_data'));
        add_action('woocommerce_checkout_update_order_review', array($this, 'handle_checkout_points_update'));
        add_action('woocommerce_checkout_create_order', array($this, 'apply_points_discount_to_order'), 10, 2);
        
        // Hide default add to cart button for points-only products
        add_filter('woocommerce_single_product_summary', array($this, 'hide_add_to_cart_for_points_only'), 9);
        
        // Hide payment gateways and show points message at checkout
        add_action('woocommerce_review_order_before_payment', array($this, 'checkout_payment_methods'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'checkout_payment_methods_top'));
        add_action('wp_footer', array($this, 'checkout_hide_payments_css'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_gateways'));
        
        // Display points instead of price for points-only products at checkout
        add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'format_checkout_line_subtotal'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'format_cart_item_subtotal'), 99, 3);
        add_filter('woocommerce_cart_item_price', array($this, 'format_cart_item_price'), 99, 3);
        
        // Additional filter for checkout review order table
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'format_checkout_item_quantity'), 10, 3);
        
        // WooCommerce Blocks support (block-based checkout)
        add_filter('woocommerce_store_api_product_quantity_editable', array($this, 'blocks_product_quantity_editable'), 10, 2);
        add_filter('woocommerce_store_api_cart_item_price_is_editable', array($this, 'blocks_item_price_editable'), 10, 2);
        add_filter('woocommerce_store_api_product_price', array($this, 'blocks_product_price'), 999, 2);
        add_filter('woocommerce_store_api_cart_item_product_price', array($this, 'blocks_cart_item_price'), 999, 2);
        add_filter('woocommerce_store_api_cart_item', array($this, 'blocks_cart_item'), 999, 2);
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
            $user_points = PR_Points_Manager::get_user_points($user_id);
            $product_id = $product->get_id();
            
            // Get the custom point cost (or calculated default)
            $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            // Get total points including registration bonus
            $registration_bonus = intval(get_option('pr_registration_points', 0));
            $total_available_points = $user_points->points + $registration_bonus;
            
            if ($total_available_points >= $required_points) {
                wp_nonce_field('pr_use_points_nonce', 'pr_points_nonce');
                ?>
                <button type="submit" 
                        name="pr_purchase_with_points" 
                        value="yes" 
                        class="single_add_to_cart_button button alt">
                    Purchase with <?php echo $required_points; ?> points
                </button>
                <p class="pr-points-info">You have: <?php echo $total_available_points; ?> points available</p>
                <?php
            } else {
                ?>
                <div class="pr-insufficient-points">
                    <p class="pr-points-required">Requires <?php echo $required_points; ?> points</p>
                    <p class="pr-points-available">You have: <?php echo $total_available_points; ?> points</p>
                    <p class="pr-earn-more">Earn more points by making purchases to unlock this item.</p>
                </div>
                <?php
            }
        } elseif ($this->can_purchase_with_points($product)) {
            $user_id = get_current_user_id();
            $user_points = PR_Points_Manager::get_user_points($user_id);
            $product_id = $product->get_id();
            
            // Get the custom point cost (or calculated default)
            $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
            
            // Get total points including registration bonus
            $registration_bonus = intval(get_option('pr_registration_points', 0));
            $total_available_points = $user_points->points + $registration_bonus;
            
            wp_nonce_field('pr_use_points_nonce', 'pr_points_nonce');
            ?>
            <div class="pr-purchase-option">
                <label>
                    <input type="checkbox" 
                           name="pr_use_points" 
                           value="yes" 
                           <?php echo $total_available_points < $required_points ? 'disabled' : ''; ?> />
                    Purchase with <?php echo $required_points; ?> points 
                    (You have: <?php echo $total_available_points; ?> points)
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
                gap: 20px !important;
                flex-wrap: wrap !important;
            }
            
            .woocommerce div.product form.cart .quantity {
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
     * Check if product is in points-only mode
     */
    private function is_points_only_product($product) {
        $points_only = get_option('pr_points_only_categories', 'no');
        if ($points_only !== 'yes') {
            return false;
        }
        
        return $this->can_purchase_with_points($product);
    }

    public function add_cart_item_data($cart_item_data, $product_id) {
        // Check for points-only purchase submission
        if (isset($_POST['pr_purchase_with_points'])) {
            if (wp_verify_nonce($_POST['pr_points_nonce'], 'pr_use_points_nonce')) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    wc_add_notice('Product not found.', 'error');
                    return false;
                }
                
                if ($this->is_points_only_product($product)) {
                    $user_id = get_current_user_id();
                    $user_points = PR_Points_Manager::get_user_points($user_id);
                    $required_points = PR_Product_Points_Cost::get_product_points_cost($product_id);
                    
                    $registration_bonus = intval(get_option('pr_registration_points', 0));
                    $total_available_points = $user_points->points + $registration_bonus;
                    
                    if ($total_available_points >= $required_points) {
                        $cart_item_data['pr_use_points'] = 'yes';
                        $cart_item_data['pr_points_cost'] = $required_points;
                        // Mark this for the cart price override filter
                        $cart_item_data['pr_points_only'] = 'yes';
                    } else {
                        wc_add_notice('Insufficient points to purchase this item.', 'error');
                        return false; // Prevent adding to cart
                    }
                }
            }
        } elseif (isset($_POST['post_data']) || !empty($_POST)) {
            // Validate that points-only products are NOT being added without points payment
            $product = wc_get_product($product_id);
            if ($product && $this->is_points_only_product($product)) {
                // User tried to add a points-only product without the points purchase button
                wc_add_notice('This product can only be purchased with points. Please use the "Purchase with points" button.', 'error');
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
                'name' => '💰 Payment Method',
                'value' => '<span style="color: #27ae60; font-weight: 600;">Points</span>'
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
            $order->add_order_note(sprintf(__('Customer used %d points for this purchase.', 'ahmeds-pointsystem'), $points_used));
            
            // Store points used in order meta
            $order->update_meta_data('_pr_points_used', $points_used);
            $order->save();
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
        $user_points = PR_Points_Manager::get_user_points($user_id);
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $total_available_points = $user_points->points + $registration_bonus;

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
        $user_points = PR_Points_Manager::get_user_points($user_id);
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $total_available_points = $user_points->points + $registration_bonus;

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
            $cart->add_fee(__('Points Discount', 'ahmeds-pointsystem'), -$total_discount, true, '');
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
    public function update_cart_item_points_data($cart) {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;

        $use_points = WC()->session->get('pr_use_points_for_cart', false);
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
                continue;
            }

            $product = $cart_item['data'];

            if ($this->is_points_only_product($product)) {
                // Force price to zero for points-only catalogue entries
                $product->set_price(0);
                $cart->cart_contents[$cart_item_key]['pr_points_only'] = 'yes';
                unset($cart->cart_contents[$cart_item_key]['pr_use_points']);
                continue;
            }

            if ($this->can_purchase_with_points($product)) {
                if ($use_points) {
                    // Mark item as using points (non points-only product)
                    $cart->cart_contents[$cart_item_key]['pr_use_points'] = 'yes';
                } else {
                    // Remove points payment from item
                    unset($cart->cart_contents[$cart_item_key]['pr_use_points']);
                }
            }
        }
    }

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
            $order->add_fee(__('Points Discount', 'ahmeds-pointsystem'), -$total_discount, true, '');
            
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
    private function cart_has_points_only_products() {
        $cart = WC()->cart;
        if (!$cart) return false;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($this->is_points_only_product($product)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Display payment method note at checkout (top position)
     */
    public function checkout_payment_methods_top() {
        if ($this->cart_has_points_only_products()) {
            ?>
            <div class="pr-checkout-points-notice" style="margin-bottom: 20px;">
                <p><strong>💡 Points-Only Products:</strong> Some items in your cart will be purchased using your points balance. You will only pay for any applicable shipping costs.</p>
            </div>
            <?php
        }
    }

    /**
     * Display payment method note at checkout
     */
    public function checkout_payment_methods() {
        if ($this->cart_has_points_only_products()) {
            ?>
            <div class="pr-checkout-points-notice">
                <p><strong>💡 Points-Only Products:</strong> Some items in your cart will be purchased using your points balance. You will only pay for any applicable shipping costs.</p>
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
     * Format line subtotal to show points instead of price for points-only products
     */
    public function format_checkout_line_subtotal($subtotal, $item, $order) {
        if (!$item->get_product_id()) return $subtotal;
        
        $product = wc_get_product($item->get_product_id());
        if (!$product) return $subtotal;
        
        if ($this->is_points_only_product($product)) {
            $points_cost = PR_Product_Points_Cost::get_product_points_cost($item->get_product_id());
            $quantity = $item->get_quantity();
            $total_points = $points_cost * $quantity;
            
            return '<span class="pr-points-product-price">' . $total_points . ' points</span>';
        }
        
        return $subtotal;
    }

    /**
     * Format cart/checkout line subtotal to show points instead of price for points-only products
     */
    public function format_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) return $subtotal;
        $product = $cart_item['data'];
        if (!$this->is_points_only_product($product)) return $subtotal;

        $product_id = $product->get_id();
        $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
        $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
        $total_points = $points_cost * max(1, $quantity);
        return '<span class="pr-points-product-price">' . esc_html($total_points) . ' points</span>';
    }

    /**
     * Format per-item price to points for points-only products (cart/checkout)
     */
    public function format_cart_item_price($price, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) return $price;
        $product = $cart_item['data'];
        if (!$this->is_points_only_product($product)) return $price;

        $product_id = $product->get_id();
        $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
        return '<span class="pr-points-product-price">' . esc_html($points_cost) . ' points</span>';
    }

    /**
     * Format checkout item quantity display to include points pricing
     */
    public function format_checkout_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) return $quantity_html;
        $product = $cart_item['data'];
        if (!$this->is_points_only_product($product)) return $quantity_html;

        $product_id = $product->get_id();
        $points_cost = PR_Product_Points_Cost::get_product_points_cost($product_id);
        $quantity = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1;
        $total_points = $points_cost * max(1, $quantity);
        
        // Return quantity with points price
        return sprintf(
            '%s &times; <span class="pr-points-product-price">%s points</span>',
            $quantity,
            esc_html($points_cost)
        );
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

    /**
     * WooCommerce Blocks: Return 0 price for points-only products
     */
    public function blocks_product_price($price, $product) {
        if (!$product) {
            return $price;
        }
        
        if ($this->is_points_only_product($product)) {
            return 0;
        }
        return $price;
    }

    /**
     * WooCommerce Blocks: Return 0 price for points-only cart items
     */
    public function blocks_cart_item_price($price, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $price;
        }

        $product = $cart_item['data'];
        if ($this->is_points_only_product($product)) {
            return 0;
        }
        return $price;
    }

    /**
     * WooCommerce Blocks: Modify cart item data to set price to 0 for points-only
     */
    public function blocks_cart_item($response, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $response;
        }

        $product = $cart_item['data'];
        if ($this->is_points_only_product($product)) {
            $response['prices'] = array(
                'price' => 0,
                'regular_price' => 0,
                'sale_price' => 0,
                'price_range' => null,
                'currency_code' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_minor_unit' => wc_get_price_decimals(),
                'currency_decimal_separator' => wc_get_price_decimal_separator(),
                'currency_thousand_separator' => wc_get_price_thousand_separator(),
                'currency_prefix' => '',
                'currency_suffix' => '',
            );
            $response['totals'] = array(
                'line_subtotal' => 0,
                'line_subtotal_tax' => 0,
                'line_total' => 0,
                'line_total_tax' => 0,
            );
        }
        return $response;
    }

    /**
     * WooCommerce Blocks: Mark price as non-editable for points-only products
     */
    public function blocks_product_quantity_editable($editable, $product) {
        if (!$product) {
            return $editable;
        }

        if ($this->is_points_only_product($product)) {
            return false;
        }
        return $editable;
    }

    /**
     * WooCommerce Blocks: Mark cart item price as non-editable for points-only products
     */
    public function blocks_item_price_editable($editable, $cart_item, $request = null) {
        if (!isset($cart_item['data']) || !is_a($cart_item['data'], 'WC_Product')) {
            return $editable;
        }

        $product = $cart_item['data'];
        if ($this->is_points_only_product($product)) {
            return false;
        }
        return $editable;
    }
}