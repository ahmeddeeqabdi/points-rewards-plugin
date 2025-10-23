<?php
/**
 * Plugin Name: Ahmed's Pointsystem
 * Plugin URI: https://example.com/ahmeds-pointsystem
 * Description: Custom points and rewards system for WooCommerce by Ahmed
 * Version: 1.0.0
 * Author: Ahmed
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ahmeds-pointsystem
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

        // Schedule daily maintenance
        add_action('wp', array($this, 'schedule_maintenance'));
    }

    public function schedule_maintenance() {
        if (!wp_next_scheduled('pr_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'pr_daily_maintenance');
        }
        add_action('pr_daily_maintenance', array($this, 'run_daily_maintenance'));
    }

    public function run_daily_maintenance() {
        // Prevent running too frequently - check if it ran in the last hour
        $last_run = get_option('pr_daily_maintenance_last_run', 0);
        if (time() - $last_run < 3600) {
            return; // Already ran in the last hour
        }

        $admin_settings = new PR_Admin_Settings();
        // Only run repair and backfill - NOT award_registration_points_to_existing_users
        // (that should only run on activation or manual button click)
        $admin_settings->repair_database();
        $admin_settings->backfill_points_for_orders();
        
        // Update last run time
        update_option('pr_daily_maintenance_last_run', time());
    }

    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points bigint(20) NOT NULL DEFAULT 0,
            redeemed_points bigint(20) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Only run activation setup once
        if (get_option('pr_activated') === 'yes') {
            return; // Already activated
        }

        // Create admin settings instance to use repair functions
        $admin_settings = new PR_Admin_Settings();
        
        // Run full database repair on activation
        $admin_settings->repair_database();
        
        // Backfill points for historical completed orders
        $admin_settings->backfill_points_for_orders();
        
        // Award registration bonus to all existing users
        $admin_settings->award_registration_points_to_existing_users();
        
        // Mark as activated
        update_option('pr_activated', 'yes');
        
        // Schedule daily maintenance
        if (!wp_next_scheduled('pr_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'pr_daily_maintenance');
        }
    }

    public function wc_missing_notice() {
        echo '<div class="error"><p><strong>Ahmed\'s Pointsystem</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}

new Points_Rewards_Plugin();