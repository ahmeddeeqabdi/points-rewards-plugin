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
        if ($order->get_meta('_points_awarded')) return;
        
        $conversion_rate = get_option('pr_conversion_rate', 1);
        $order_total = $order->get_total();
        $points = floor($order_total / $conversion_rate);
        
        $this->add_points($user_id, $points);
        $order->update_meta_data('_points_awarded', 'yes');
        $order->save();
    }

    public function add_points($user_id, $points) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($existing) {
            $wpdb->update(
                $table_name,
                array('points' => $existing->points + $points),
                array('user_id' => $user_id)
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'points' => $points,
                    'redeemed_points' => 0
                )
            );
        }
    }

    public static function get_user_points($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        return $result ? (object) array(
            'points' => $result->points,
            'redeemed_points' => $result->redeemed_points
        ) : (object) array('points' => 0, 'redeemed_points' => 0);
    }

    public static function redeem_points($user_id, $points) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($existing && $existing->points >= $points) {
            $wpdb->update(
                $table_name,
                array(
                    'points' => $existing->points - $points,
                    'redeemed_points' => $existing->redeemed_points + $points
                ),
                array('user_id' => $user_id)
            );
            return true;
        }
        return false;
    }
}