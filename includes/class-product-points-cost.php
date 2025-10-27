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
        
        // Modify price display for points-only products
        add_filter('woocommerce_get_price_html', array($this, 'modify_price_display'), 10, 2);
        add_filter('woocommerce_product_get_price', array($this, 'modify_price_value'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'modify_price_value'), 10, 2);
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
                <strong>Default:</strong> <?php echo esc_html($default_cost); ?> points (based on product price: <?php echo wc_price($product->get_price()); ?> ÷ conversion rate <?php echo esc_html($conversion_rate); ?>)<br>
                <strong>Leave blank</strong> to use the default calculated cost above.<br>
                <strong>Enter a custom value</strong> to override with a specific point cost for this product.
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
     */
    public static function get_product_points_cost($product_id) {
        // Check if custom cost is set
        $custom_cost = get_post_meta($product_id, '_pr_custom_point_cost', true);
        
        if ($custom_cost) {
            return intval($custom_cost);
        }

        // Fall back to calculated cost based on conversion rate
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }

        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        $product_price = floatval($product->get_price());
        
        return intval(ceil($product_price / $conversion_rate));
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
        $user_id = get_current_user_id();
        $user_points_obj = PR_Points_Manager::get_user_points($user_id);
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $total_available_points = $user_points_obj->points + $registration_bonus;

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
     */
    private function is_points_only_product($product) {
        $points_only = get_option('pr_points_only_categories', 'no');
        if ($points_only !== 'yes') {
            return false;
        }
        
        return $this->can_purchase_with_points($product);
    }

    /**
     * Modify price display for points-only products
     */
    public function modify_price_display($price_html, $product) {
        if ($this->is_points_only_product($product)) {
            $points_cost = self::get_product_points_cost($product->get_id());
            return '<span class="pr-points-price">' . $points_cost . ' points</span>';
        }
        
        return $price_html;
    }

    /**
     * Modify price value for points-only products (set to 0 so they appear free)
     */
    public function modify_price_value($price, $product) {
        if ($this->is_points_only_product($product)) {
            return 0;
        }
        
        return $price;
    }
}
?>
