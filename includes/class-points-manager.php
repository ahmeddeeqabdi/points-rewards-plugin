<?php
if (!defined('ABSPATH')) exit;

class PR_Points_Manager {
    public function __construct() {
        // Award points on user registration
        add_action('user_register', array($this, 'award_registration_points'));
        
        // Award points on order completion
        add_action('woocommerce_order_status_completed', array($this, 'award_purchase_points'));
    }

    public function award_registration_points($user_id) {
        $registration_points = get_option('pr_registration_points', 0);
        if ($registration_points > 0) {
            $this->add_points($user_id, $registration_points);
        }
    }

    public function award_purchase_points($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        if (!$user_id) return;
        
        // Check if points already awarded
        if ($order->get_meta('_points_awarded') === 'yes') return;
        
        // Additional check: only award if order was just completed (not re-completed)
        $order_status_changes = $order->get_meta('_points_status_history');
        if (!$order_status_changes) {
            $order_status_changes = array();
        }
        
        // Only award if this is the first time it becomes completed
        if (in_array('completed', $order_status_changes)) {
            return;
        }
        
        $order_status_changes[] = 'completed';
        $order->update_meta_data('_points_status_history', $order_status_changes);
        
        $conversion_rate = max(0.01, get_option('pr_conversion_rate', 1));
        $order_total = $order->get_total();
        $points = (int) floor($order_total / $conversion_rate);
        
        $this->add_points($user_id, $points);
        $order->update_meta_data('_points_awarded', 'yes');
        $order->save();
    }

    public function add_points($user_id, $points) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table_name (user_id, points, redeemed_points) 
                 VALUES (%d, %d, 0) 
                 ON DUPLICATE KEY UPDATE points = points + VALUES(points)",
                $user_id,
                $points
            )
        );
        
        if ($result === false) {
            error_log("Points & Rewards: Failed to add points for user $user_id: " . $wpdb->last_error);
        }
    }

    public static function get_user_points($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($result === null && $wpdb->last_error) {
            error_log("Points & Rewards: Failed to get points for user $user_id: " . $wpdb->last_error);
        }
        
        return $result ? (object) array(
            'points' => (int) $result->points,
            'redeemed_points' => (int) $result->redeemed_points
        ) : (object) array('points' => 0, 'redeemed_points' => 0);
    }

    public static function redeem_points($user_id, $points) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($existing === null && $wpdb->last_error) {
            error_log("Points & Rewards: Failed to get points for redemption, user $user_id: " . $wpdb->last_error);
            return false;
        }
        
        if ($existing && $existing->points >= $points) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'points' => $existing->points - $points,
                    'redeemed_points' => $existing->redeemed_points + $points
                ),
                array('user_id' => $user_id)
            );
            
            if ($result === false) {
                error_log("Points & Rewards: Failed to redeem points for user $user_id: " . $wpdb->last_error);
                return false;
            }
            return true;
        }
        return false;
    }
}