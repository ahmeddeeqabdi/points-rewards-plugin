<?php
if (!defined('ABSPATH')) exit;

class PR_User_Management {
    public function __construct() {
        add_action('admin_init', array($this, 'handle_user_actions'));
    }

    public function handle_user_actions() {
        if (!isset($_POST['pr_user_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pr_user_management_nonce')) {
            wp_die('Security check failed.');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        $action = sanitize_text_field($_POST['pr_user_action']);
        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_die('Invalid user ID.');
        }

        if ($action === 'set_points') {
            $this->handle_set_points($user_id);
        } elseif ($action === 'revoke_access') {
            $this->handle_revoke_access($user_id);
        } elseif ($action === 'restore_access') {
            $this->handle_restore_access($user_id);
        }
    }

    /**
     * Handle manual point setting
     */
    private function handle_set_points($user_id) {
        $new_points = intval($_POST['points_value'] ?? 0);
        
        if ($new_points < 0) {
            set_transient('pr_user_notice_error', 'Points cannot be negative.', 30);
            $this->redirect_to_users_page();
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        // Get current points
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT points FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        if ($current) {
            $old_points = $current->points;
            
            $updated = $wpdb->update(
                $table_name,
                array(
                    'points' => $new_points,
                    'points_manually_set' => 1
                ),
                array('user_id' => $user_id),
                array('%d', '%d'),
                array('%d')
            );

            if ($updated !== false) {
                $user = get_user_by('ID', $user_id);
                $difference = $new_points - $old_points;
                $action_text = $difference >= 0 ? 'added' : 'removed';
                
                set_transient(
                    'pr_user_notice_success',
                    sprintf(
                        'Points for %s updated: %s (was %d, now %d)',
                        esc_html($user->display_name),
                        $action_text . ' ' . abs($difference),
                        $old_points,
                        $new_points
                    ),
                    30
                );

                // Log the action
                error_log("Points Rewards: Admin manually set points for user $user_id ($user->user_email) from $old_points to $new_points");
            } else {
                set_transient('pr_user_notice_error', 'Failed to update points.', 30);
            }
        } else {
            set_transient('pr_user_notice_error', 'User not found in points system.', 30);
        }

        $this->redirect_to_users_page();
    }

    /**
     * Handle access revocation
     */
    private function handle_revoke_access($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        $updated = $wpdb->update(
            $table_name,
            array('is_revoked' => 1),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );

        if ($updated !== false) {
            $user = get_user_by('ID', $user_id);
            set_transient(
                'pr_user_notice_success',
                sprintf('Access revoked for user: %s', esc_html($user->display_name)),
                30
            );

            error_log("Points Rewards: Admin revoked rewards access for user $user_id ($user->user_email)");
        } else {
            set_transient('pr_user_notice_error', 'Failed to revoke access.', 30);
        }

        $this->redirect_to_users_page();
    }

    /**
     * Handle access restoration
     */
    private function handle_restore_access($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        $updated = $wpdb->update(
            $table_name,
            array('is_revoked' => 0),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );

        if ($updated !== false) {
            $user = get_user_by('ID', $user_id);
            set_transient(
                'pr_user_notice_success',
                sprintf('Access restored for user: %s', esc_html($user->display_name)),
                30
            );

            error_log("Points Rewards: Admin restored rewards access for user $user_id ($user->user_email)");
        } else {
            set_transient('pr_user_notice_error', 'Failed to restore access.', 30);
        }

        $this->redirect_to_users_page();
    }

    /**
     * Check if user has revoked access
     */
    public function is_user_revoked($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        $is_revoked = $wpdb->get_var($wpdb->prepare(
            "SELECT is_revoked FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        return intval($is_revoked) === 1;
    }

    /**
     * Redirect to users page
     */
    private function redirect_to_users_page() {
        wp_safe_redirect(admin_url('admin.php?page=ahmeds-pointsystem-users'));
        exit;
    }
}
?>
