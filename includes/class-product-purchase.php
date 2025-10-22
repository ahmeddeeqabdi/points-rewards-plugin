<?php
if (!defined('ABSPATH')) exit;

class PR_Product_Purchase {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_points_option'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_item_meta'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'process_points_payment'));
    }

    public function add_points_option() {
        if (!is_user_logged_in()) return;
        
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        if ($enable_purchase !== 'yes') return;
        
        global $product;
        
        if ($this->can_purchase_with_points($product)) {
            $user_id = get_current_user_id();
            $user_points = PR_Points_Manager::get_user_points($user_id);
            $product_price = $product->get_price();
            $conversion_rate = get_option('pr_conversion_rate', 1);
            $required_points = ceil($product_price / $conversion_rate);
            
            ?>
            <div class="pr-purchase-option">
                <label>
                    <input type="checkbox" 
                           name="pr_use_points" 
                           value="yes" 
                           <?php echo $user_points->points < $required_points ? 'disabled' : ''; ?> />
                    Purchase with <?php echo $required_points; ?> points 
                    (You have: <?php echo $user_points->points; ?> points)
                </label>
            </div>
            <?php
        }
    }

    private function can_purchase_with_points($product) {
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        
        if ($restrict_categories !== 'yes') {
            return true;
        }
        
        $allowed_categories = get_option('pr_allowed_categories', array());
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        
        return !empty(array_intersect($product_categories, (array)$allowed_categories));
    }

    public function add_cart_item_data($cart_item_data, $product_id) {
        if (isset($_POST['pr_use_points']) && $_POST['pr_use_points'] === 'yes') {
            $cart_item_data['pr_use_points'] = 'yes';
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['pr_use_points'])) {
            $item_data[] = array(
                'name' => 'Payment Method',
                'value' => 'Points'
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
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_pr_use_points') === 'yes') {
                $product_price = $item->get_total();
                $conversion_rate = get_option('pr_conversion_rate', 1);
                $required_points = ceil($product_price / $conversion_rate);
                
                PR_Points_Manager::redeem_points($user_id, $required_points);
            }
        }
    }
}