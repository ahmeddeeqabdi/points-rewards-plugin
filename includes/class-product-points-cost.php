<?php
if (!defined('ABSPATH')) exit;

class PR_Product_Points_Cost {
    public function __construct() {
        // Add metabox to product edit page
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        
        // Save metabox data
        add_action('save_post_product', array($this, 'save_product_metabox'));
        
        // Display on frontend in product purchase section
        add_filter('woocommerce_before_add_to_cart_button', array($this, 'display_custom_point_cost'), 5);
        
        // PART 1: Core WooCommerce - Zero out prices at the source (HIGH PRIORITY)
        // Product prices
        add_filter('woocommerce_product_get_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'zero_price_for_points_only'), 100, 2);
        
        // Variation prices
        add_filter('woocommerce_product_variation_get_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'zero_price_for_points_only'), 100, 2);
        
        // Variation price arrays
        add_filter('woocommerce_variation_prices_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_variation_prices_regular_price', array($this, 'zero_price_for_points_only'), 100, 2);
        add_filter('woocommerce_variation_prices_sale_price', array($this, 'zero_price_for_points_only'), 100, 2);
        
        // Display HTML (override monetary display with points)
        // NOTE: This is handled by PR_Product_Purchase::display_points_html at priority 100
        // Removed to avoid duplicate filter hooks
        
        // Add REST API filter for variation prices
        add_filter('woocommerce_rest_prepare_product_variation_object', array($this, 'add_points_cost_to_variation_rest'), 10, 3);
    }

    /**
     * Add metabox to product edit page
     */
    public function add_product_metabox() {
        add_meta_box(
            'pr_product_points_cost',
            'Points Purchase Cost',
            array($this, 'render_product_metabox'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render the metabox
     */
    public function render_product_metabox($post) {
        $custom_point_cost = get_post_meta($post->ID, '_pr_custom_point_cost', true);
        $product = wc_get_product($post->ID);
        
        // Get allowed purchase categories
        $allowed_categories = get_option('pr_allowed_categories', array());
        $product_categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'ids'));
        $is_in_purchase_category = !empty(array_intersect($product_categories, (array)$allowed_categories));
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        
        // Only show this metabox if product purchase is enabled and product is in allowed category
        if ($enable_purchase !== 'yes') {
            echo '<p style="color: #999;"><em>Product points purchase is currently disabled in Settings.</em></p>';
            return;
        }

        if (!$is_in_purchase_category) {
            echo '<p style="color: #999;"><em>This product is not in an allowed purchase category. Go to Settings to enable point purchases for this product\'s category.</em></p>';
            return;
        }

        wp_nonce_field('pr_product_points_cost_nonce', 'pr_product_points_cost_nonce');
        
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        $default_cost = ceil($product->get_price() / $conversion_rate);
        
        ?>
        <div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
            <p>
                <label for="pr_custom_point_cost" style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Custom Points Cost (Optional)
                </label>
                <input type="number" 
                       id="pr_custom_point_cost" 
                       name="pr_custom_point_cost" 
                       value="<?php echo esc_attr($custom_point_cost); ?>" 
                       min="1" 
                       step="1"
                       placeholder="<?php echo esc_attr($default_cost); ?>"
                       style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
            </p>
            <p style="color: #666; font-size: 13px; margin: 10px 0 0 0;">
                <strong>Calculated:</strong> <?php echo esc_html($default_cost); ?> points (based on product price: <?php echo wc_price($product->get_price()); ?> ÷ conversion rate <?php echo esc_html($conversion_rate); ?>)<br>
                <strong>Leave blank</strong> to disable points purchasing for this product.<br>
                <strong>Enter a custom value</strong> to enable points purchasing with that specific cost.
            </p>
        </div>
        <?php
    }

    /**
     * Save metabox data
     */
    public function save_product_metabox($post_id) {
        // Verify nonce
        if (!isset($_POST['pr_product_points_cost_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['pr_product_points_cost_nonce'], 'pr_product_points_cost_nonce')) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save custom point cost if provided
        $custom_point_cost = isset($_POST['pr_custom_point_cost']) ? sanitize_text_field($_POST['pr_custom_point_cost']) : '';

        if ($custom_point_cost !== '') {
            $custom_point_cost = intval($custom_point_cost);
            if ($custom_point_cost > 0) {
                update_post_meta($post_id, '_pr_custom_point_cost', $custom_point_cost);
            } else {
                delete_post_meta($post_id, '_pr_custom_point_cost');
            }
        } else {
            delete_post_meta($post_id, '_pr_custom_point_cost');
        }
    }

    /**
     * Get the points cost for a product
     * Returns the custom cost if set, otherwise falls back to calculated cost based on conversion rate
     */
    public static function get_product_points_cost($product_id) {
        // Check if custom cost is set for this product (works for parent and variations)
        $custom_cost = get_post_meta($product_id, '_pr_custom_point_cost', true);

        // If custom cost exists and is valid, use it
        if (!empty($custom_cost)) {
            $custom_cost = intval($custom_cost);
            if ($custom_cost > 0) {
                return $custom_cost;
            }
        }

        // Handle variations: if this is a variation, check parent for custom cost first
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $parent_custom_cost = get_post_meta($parent_id, '_pr_custom_point_cost', true);
                if (!empty($parent_custom_cost)) {
                    $parent_custom_cost = intval($parent_custom_cost);
                    if ($parent_custom_cost > 0) {
                        return $parent_custom_cost;
                    }
                }
            }
        }

        // No custom cost set - cannot purchase with points
        return 0;
    }

    /**
     * Get EXPLICIT custom points cost only - returns false if not explicitly set
     * This is used for display purposes to show points ONLY if the field was filled in
     */
    public static function get_explicit_custom_points_cost($product_id) {
        // Check if custom cost is set for this product
        $custom_cost = get_post_meta($product_id, '_pr_custom_point_cost', true);
        
        // Return the custom cost ONLY if it exists and is valid
        if (!empty($custom_cost)) {
            $custom_cost = intval($custom_cost);
            if ($custom_cost > 0) {
                return $custom_cost;
            }
        }

        // Check parent for variations
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $parent_custom_cost = get_post_meta($parent_id, '_pr_custom_point_cost', true);
                if (!empty($parent_custom_cost)) {
                    $parent_custom_cost = intval($parent_custom_cost);
                    if ($parent_custom_cost > 0) {
                        return $parent_custom_cost;
                    }
                }
            }
        }

        // Return false if not explicitly set
        return false;
    }

    /**
     * Display custom point cost info on frontend (in product page)
     */
    public function display_custom_point_cost() {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;
        
        global $product;
        
        if (!$this->can_purchase_with_points($product)) {
            return;
        }

        $product_id = $product->get_id();
        $points_cost = self::get_product_points_cost($product_id);
        // Only output when this product actually has a positive points cost
        if ($points_cost <= 0) {
            return;
        }
        $user_id = get_current_user_id();
        
        // Get total available points (properly handles manually set points)
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        $user_record = $wpdb->get_row($wpdb->prepare(
            "SELECT points, points_manually_set FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if (!$user_record) {
            $total_available_points = intval(get_option('pr_registration_points', 0));
        } elseif (intval($user_record->points_manually_set) === 1) {
            $total_available_points = intval($user_record->points);
        } else {
            $registration_bonus = intval(get_option('pr_registration_points', 0));
            $total_available_points = intval($user_record->points) + $registration_bonus;
        }

        // Store this info for use in the purchase option
        echo '<script type="application/json" id="pr-product-points-cost-' . esc_attr($product_id) . '">';
        echo json_encode([
            'product_id' => $product_id,
            'points_cost' => $points_cost,
            'user_available_points' => $total_available_points,
            'can_afford' => $total_available_points >= $points_cost
        ]);
        echo '</script>';
    }

    /**
     * Check if product can be purchased with points
     */
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
     * A product is points-only ONLY when:
     * 1) Points-only mode is enabled (pr_points_only_categories = yes)
     * 2) Category restrictions are enabled (pr_restrict_categories = yes)
     * 3) Product is in an allowed category
     * 4) Product has an EXPLICIT custom points cost set (not calculated)
     */
    public function is_points_only_product($product) {
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
        $explicit_cost = self::get_explicit_custom_points_cost($product->get_id());
        return $explicit_cost !== false;
    }    /**
     * CORE PRINCIPLE: Zero out price at the source (for points-only products)
     * This runs with priority 100 to override all other price modifications
     */
    public function zero_price_for_points_only($price, $product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return $price;
        }
        
        if ($this->is_points_only_product($product)) {
            return 0;
        }
        
        return $price;
    }

    /**
     * Display points instead of monetary price (for visual representation)
     * NOTE: This method is deprecated - use PR_Product_Purchase::display_points_html instead
     * Kept for backwards compatibility but should not be hooked
     */
    public function display_points_instead_of_price($price_html, $product) {
        // Always return original price_html - this hook should not be used
        return $price_html;
    }

    /**
     * Add points cost to variation REST API response
     * NOTE: This should only modify responses for points-only products
     */
    public function add_points_cost_to_variation_rest($response, $product, $request) {
        if (!is_a($response, 'WP_REST_Response')) {
            return $response;
        }
        
        // Only modify for points-only products
        if (!$this->is_points_only_product($product)) {
            return $response;
        }
        
        $data = $response->get_data();
        
        $points_cost = self::get_product_points_cost($product->get_id());
        // Add points cost to the price display text
        $data['price_html'] = '<span class="pr-points-price">' . $points_cost . ' points</span>';
        
        $response->set_data($data);
        return $response;
    }
}
?>
