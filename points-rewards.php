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
require_once PR_PLUGIN_PATH . 'includes/class-product-points-cost.php';
require_once PR_PLUGIN_PATH . 'includes/class-frontend-display.php';
require_once PR_PLUGIN_PATH . 'includes/class-guest-recovery.php';
require_once PR_PLUGIN_PATH . 'includes/class-user-management.php';
require_once PR_PLUGIN_PATH . 'includes/class-blocks-integration.php';
 

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
        new PR_Product_Points_Cost();
        new PR_Frontend_Display();
        new PR_Guest_Recovery();
        new PR_User_Management();
        new PR_Blocks_Integration();

        // Enqueue frontend CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));

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
            is_revoked tinyint(1) NOT NULL DEFAULT 0,
            points_manually_set tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add missing columns for existing installations
        $this->add_missing_columns();

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

        // Flush rewrite rules to ensure new endpoints are registered
        flush_rewrite_rules();
    }

    /**
     * Add missing columns to existing user_points table for backwards compatibility
     */
    private function add_missing_columns() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        // Check if is_revoked column exists
        $column_exists = $wpdb->get_results(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = '$table_name' AND COLUMN_NAME = 'is_revoked'"
        );
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_revoked tinyint(1) NOT NULL DEFAULT 0");
        }

        // Check if points_manually_set column exists
        $column_exists = $wpdb->get_results(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = '$table_name' AND COLUMN_NAME = 'points_manually_set'"
        );
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN points_manually_set tinyint(1) NOT NULL DEFAULT 0");
        }
    }

    public function wc_missing_notice() {
        echo '<div class="error"><p><strong>Ahmed\'s Pointsystem</strong> requires WooCommerce to be installed and active.</p></div>';
    }

    public function enqueue_frontend_styles() {
        $css_path = PR_PLUGIN_PATH . 'assets/css/frontend-style.css';
        $css_version = file_exists($css_path) ? filemtime($css_path) : '1.0.0';

        wp_enqueue_style(
            'pr-frontend-style',
            PR_PLUGIN_URL . 'assets/css/frontend-style.css',
            array(),
            $css_version
        );

        // Product page layout is now handled purely with CSS Grid; JS helper no longer needed

        // Only enqueue checkout block scripts on checkout/cart pages
        if (!is_checkout() && !is_cart()) {
            return;
        }

        $checkout_component_path = PR_PLUGIN_PATH . 'assets/js/checkout-points-section.js';
        $checkout_component_version = file_exists($checkout_component_path) ? filemtime($checkout_component_path) : '1.0.0';

        $register_section_path = PR_PLUGIN_PATH . 'assets/js/register-checkout-section.js';
        $register_section_version = file_exists($register_section_path) ? filemtime($register_section_path) : '1.0.0';

        // Register scripts for WooCommerce Blocks
        wp_register_script(
            'pr-checkout-points-component',
            PR_PLUGIN_URL . 'assets/js/checkout-points-section.js',
            array('wp-element'),
            $checkout_component_version,
            true
        );

        wp_register_script(
            'pr-register-checkout-section',
            PR_PLUGIN_URL . 'assets/js/register-checkout-section.js',
            array(
                'wp-plugins',
                'wc-blocks-checkout',
                'pr-checkout-points-component'
            ),
            $register_section_version,
            true
        );

        // Enqueue the scripts
        wp_enqueue_script('pr-checkout-points-component');
        wp_enqueue_script('pr-register-checkout-section');

        // Localize script for AJAX
        wp_localize_script('pr-register-checkout-section', 'pr_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pr_points_payment_nonce')
        ));
    }
}

new Points_Rewards_Plugin();