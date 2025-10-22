<?php
/**
 * Plugin Name: Points & Rewards for WooCommerce
 * Plugin URI: https://example.com/points-rewards
 * Description: Custom points and rewards system for WooCommerce
 * Version: 1.0.0
 * Author: Ahmed
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: points-rewards
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('PR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PR_PLUGIN_PATH . 'includes/class-points-manager.php';
require_once PR_PLUGIN_PATH . 'includes/class-admin-settings.php';
require_once PR_PLUGIN_PATH . 'includes/class-product-purchase.php';

// Initialize plugin
class Points_Rewards_Plugin {
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'wc_missing_notice'));
            return;
        }

        new PR_Admin_Settings();
        new PR_Points_Manager();
        new PR_Product_Purchase();
    }

    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            redeemed_points int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Seed a points row for each existing user if missing
        $user_ids = get_users(array('fields' => 'ID'));
        foreach ($user_ids as $user_id) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO $table_name (user_id, points, redeemed_points) VALUES (%d, 0, 0)
                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)",
                    $user_id
                )
            );
        }

        // Backfill points for historical completed orders
        if (function_exists('wc_get_orders')) {
            $conversion_rate = (float) get_option('pr_conversion_rate', 1);
            if ($conversion_rate <= 0) {
                $conversion_rate = 1;
            }

            $orders = wc_get_orders(array(
                'status' => array('wc-completed'),
                'limit' => -1,
                'return' => 'ids',
            ));

            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || $order->get_meta('_points_awarded') === 'yes') {
                    continue;
                }

                $user_id = $order->get_user_id();
                $order_total = (float) $order->get_total();

                if (!$user_id || $order_total <= 0) {
                    $order->update_meta_data('_points_awarded', 'yes');
                    $order->save();
                    continue;
                }

                $points = (int) floor($order_total / $conversion_rate);
                if ($points > 0) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO $table_name (user_id, points, redeemed_points) VALUES (%d, %d, 0)
                            ON DUPLICATE KEY UPDATE points = points + VALUES(points)",
                            $user_id,
                            $points
                        )
                    );
                }

                $order->update_meta_data('_points_awarded', 'yes');
                $order->save();
            }
        }
    }

    public function wc_missing_notice() {
        echo '<div class="error"><p><strong>Points & Rewards</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}

new Points_Rewards_Plugin();