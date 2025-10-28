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
            // Mark that this user received the registration bonus
            update_user_meta($user_id, 'pr_registration_bonus_awarded', '1');
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

        if ($points <= 0) {
            return; // No points to add
        }

        // First, try to update existing row
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name SET points = points + %d WHERE user_id = %d",
                $points,
                $user_id
            )
        );

        // If no row was updated (user doesn't exist yet), insert new row
        if ($updated === 0) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'points' => $points,
                    'redeemed_points' => 0
                ),
                array('%d', '%d', '%d')
            );

            if ($result === false) {
                error_log("Points & Rewards: Failed to insert points for user $user_id: " . $wpdb->last_error);
            }
        } elseif ($updated === false) {
            error_log("Points & Rewards: Failed to update points for user $user_id: " . $wpdb->last_error);
        }
    }

    public static function get_user_total_points($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $user_record = $wpdb->get_row($wpdb->prepare(
            "SELECT points, points_manually_set FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if (!$user_record) {
            // User has no record, return registration bonus
            $registration_bonus = intval(get_option('pr_registration_points', 0));
            error_log("Points & Rewards: User $user_id has no record, returning registration bonus: $registration_bonus");
            return $registration_bonus;
        }
        
        // If points were manually set by admin, use them as-is (they already include bonus)
        if (intval($user_record->points_manually_set) === 1) {
            error_log("Points & Rewards: User $user_id has manually set points: " . intval($user_record->points));
            return intval($user_record->points);
        } else {
            // Otherwise, add the registration bonus to earned points
            $registration_bonus = intval(get_option('pr_registration_points', 0));
            $total_points = intval($user_record->points) + $registration_bonus;
            error_log("Points & Rewards: User $user_id earned points: " . intval($user_record->points) . ", bonus: $registration_bonus, total: $total_points");
            return $total_points;
        }
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
        
        // If no existing record, user has no points to redeem
        if (!$existing) {
            error_log("Points & Rewards: User $user_id has no points record.");
            return false;
        }
        
        // Calculate total available points (respecting manually_set flag)
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $manually_set = intval($existing->points_manually_set);
        
        // If points were manually set by admin, use them as-is (they already include bonus)
        if ($manually_set === 1) {
            $total_available_points = (int)$existing->points;
        } else {
            // Otherwise, add the registration bonus to earned points
            $total_available_points = (int)$existing->points + $registration_bonus;
        }
        
        if ($total_available_points >= $points) {
            // Deduct from stored points first, then from the bonus
            $new_points = max(0, (int)$existing->points - $points);
            $result = $wpdb->update(
                $table_name,
                array(
                    'points' => $new_points,
                    'redeemed_points' => (int)$existing->redeemed_points + $points
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