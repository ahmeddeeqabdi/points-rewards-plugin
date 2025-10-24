<?php
if (!defined('ABSPATH')) exit;

/**
 * Conflict Detector for Points & Rewards Plugin
 * 
 * Detects potential conflicts with other points/rewards plugins
 * and prevents data corruption from incompatible plugins
 */
class PR_Conflict_Detector {
    
    /**
     * Check for plugin conflicts on activation
     */
    public static function check_on_activation() {
        $conflicts = self::detect_conflicts();
        
        if (!empty($conflicts)) {
            $message = "Ahmed's Pointsystem detected potential conflicts:\n\n";
            foreach ($conflicts as $conflict) {
                $message .= "⚠️ " . $conflict['type'] . ": " . $conflict['message'] . "\n";
            }
            $message .= "\nPlease review CONFLICT_ANALYSIS.md for details.";
            
            error_log("Points Rewards Plugin Conflicts: " . $message);
            
            // Store conflicts for admin notice
            set_transient('pr_conflicts_detected', $conflicts, 3600);
        }
    }
    
    /**
     * Show admin notice if conflicts detected
     */
    public static function admin_notice() {
        $conflicts = get_transient('pr_conflicts_detected');
        
        if (empty($conflicts)) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Ahmed\'s Pointsystem - Conflicts Detected:</strong></p>';
        echo '<ul>';
        
        foreach ($conflicts as $conflict) {
            echo '<li>';
            echo '<strong>' . esc_html($conflict['type']) . ':</strong> ';
            echo esc_html($conflict['message']);
            if (!empty($conflict['action'])) {
                echo ' <em>' . esc_html($conflict['action']) . '</em>';
            }
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Main conflict detection logic
     */
    private static function detect_conflicts() {
        $conflicts = array();
        
        // Check for table conflicts
        $table_conflicts = self::check_table_conflicts();
        if (!empty($table_conflicts)) {
            $conflicts = array_merge($conflicts, $table_conflicts);
        }
        
        // Check for plugin conflicts
        $plugin_conflicts = self::check_plugin_conflicts();
        if (!empty($plugin_conflicts)) {
            $conflicts = array_merge($conflicts, $plugin_conflicts);
        }
        
        // Check for hook conflicts
        $hook_conflicts = self::check_hook_conflicts();
        if (!empty($hook_conflicts)) {
            $conflicts = array_merge($conflicts, $hook_conflicts);
        }
        
        return $conflicts;
    }
    
    /**
     * Check if another plugin is using wp_user_points table
     */
    private static function check_table_conflicts() {
        global $wpdb;
        $conflicts = array();
        $table_name = $wpdb->prefix . 'user_points';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            // Table exists, check if it's our table by examining structure
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
            
            if (empty($columns)) {
                $conflicts[] = array(
                    'type' => 'Database Error',
                    'message' => 'Cannot read columns from wp_user_points table',
                    'action' => 'Check database permissions'
                );
                return $conflicts;
            }
            
            $column_names = wp_list_pluck($columns, 'Field');
            $expected_columns = array('id', 'user_id', 'points', 'redeemed_points', 'is_revoked', 'points_manually_set');
            
            $missing_columns = array_diff($expected_columns, $column_names);
            
            if (!empty($missing_columns)) {
                $conflicts[] = array(
                    'type' => 'TABLE_CONFLICT - CRITICAL',
                    'message' => 'Another plugin may be using wp_user_points table with different structure. Missing expected columns: ' . implode(', ', $missing_columns),
                    'action' => 'Deactivate conflicting plugin or rename its table'
                );
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Check for known conflicting plugins
     */
    private static function check_plugin_conflicts() {
        $conflicts = array();
        $active_plugins = get_option('active_plugins', array());
        
        // Known conflicting plugins
        $known_conflicts = array(
            'wc-points-rewards/wc-points-rewards.php' => array(
                'name' => 'WP Points & Rewards',
                'reason' => 'Uses same wp_user_points table',
                'severity' => 'CRITICAL'
            ),
            'wp-points-rewards/wp-points-rewards.php' => array(
                'name' => 'WP Points & Rewards (Alt)',
                'reason' => 'Uses same wp_user_points table',
                'severity' => 'CRITICAL'
            ),
            'woocommerce-points-and-rewards/woocommerce-points-and-rewards.php' => array(
                'name' => 'WooCommerce Points and Rewards',
                'reason' => 'May interfere with cart/checkout hooks',
                'severity' => 'HIGH'
            ),
            'gamification-points/gamification-points.php' => array(
                'name' => 'Gamification Points',
                'reason' => 'May award duplicate points on order completion',
                'severity' => 'HIGH'
            ),
        );
        
        foreach ($known_conflicts as $plugin_path => $info) {
            if (in_array($plugin_path, $active_plugins)) {
                $severity = $info['severity'];
                
                if ($severity === 'CRITICAL') {
                    $conflicts[] = array(
                        'type' => 'PLUGIN_CONFLICT - CRITICAL ⚠️',
                        'message' => $info['name'] . ': ' . $info['reason'],
                        'action' => 'MUST deactivate before using Ahmed\'s Pointsystem'
                    );
                } else {
                    $conflicts[] = array(
                        'type' => 'PLUGIN_CONFLICT - HIGH ⚠️',
                        'message' => $info['name'] . ': ' . $info['reason'],
                        'action' => 'Deactivate recommended. May cause duplicate points or conflicts.'
                    );
                }
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Check for hook conflicts (multiple plugins hooking same hook)
     */
    private static function check_hook_conflicts() {
        $conflicts = array();
        global $wp_filter;
        
        // Critical hooks to monitor
        $critical_hooks = array(
            'user_register',
            'woocommerce_order_status_completed',
            'woocommerce_cart_calculate_fees'
        );
        
        foreach ($critical_hooks as $hook) {
            if (!isset($wp_filter[$hook])) {
                continue;
            }
            
            $hooked_functions = array();
            foreach ($wp_filter[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (isset($callback['function'])) {
                        $func = $callback['function'];
                        if (is_array($func)) {
                            $hooked_functions[] = get_class($func[0]) . '::' . $func[1];
                        } elseif (is_string($func)) {
                            $hooked_functions[] = $func;
                        }
                    }
                }
            }
            
            // Check if multiple points-related callbacks are hooked
            $points_callbacks = array_filter($hooked_functions, function($callback) {
                return stripos($callback, 'point') !== false || 
                       stripos($callback, 'reward') !== false ||
                       stripos($callback, 'loyalty') !== false;
            });
            
            if (count($points_callbacks) > 1) {
                $conflicts[] = array(
                    'type' => 'HOOK_CONFLICT - WARNING',
                    'message' => 'Multiple points/rewards plugins hooked to ' . $hook . ': ' . implode(', ', $points_callbacks),
                    'action' => 'May cause duplicate points. Check execution order in error log.'
                );
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Generate diagnostic report for debugging
     */
    public static function get_diagnostic_report() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $report = array(
            'timestamp' => current_time('mysql'),
            'plugin_version' => '1.0.0',
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => WC()->version ?? 'Not installed',
            'table_exists' => !empty($wpdb->get_var("SHOW TABLES LIKE '$table_name'")),
            'active_plugins' => count(get_option('active_plugins', array())),
            'detected_conflicts' => self::detect_conflicts(),
            'database_user' => $wpdb->dbuser,
            'table_stats' => null,
        );
        
        // Get table statistics if it exists
        if ($report['table_exists']) {
            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_revoked = 1 THEN 1 ELSE 0 END) as revoked_users,
                    SUM(CASE WHEN points_manually_set = 1 THEN 1 ELSE 0 END) as manual_users,
                    SUM(points) as total_points
                FROM $table_name
            ");
            $report['table_stats'] = $stats;
        }
        
        return $report;
    }
    
    /**
     * Log diagnostic report to debug.log
     */
    public static function log_diagnostic_report() {
        $report = self::get_diagnostic_report();
        error_log('Ahmed\'s Pointsystem - Diagnostic Report: ' . json_encode($report, JSON_PRETTY_PRINT));
    }
}

// Hook admin notice
add_action('admin_notices', array('PR_Conflict_Detector', 'admin_notice'));

// Run conflict check on admin init (once per session)
add_action('admin_init', function() {
    if (!get_transient('pr_conflict_check_done')) {
        PR_Conflict_Detector::check_on_activation();
        set_transient('pr_conflict_check_done', true, 3600); // Check once per hour
    }
});
