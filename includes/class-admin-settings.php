<?php
if (!defined('ABSPATH')) exit;

class PR_Admin_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('update_option_pr_conversion_rate', array($this, 'recalculate_points_on_conversion_rate_change'), 10, 2);
        add_action('update_option_pr_registration_points', array($this, 'cleanup_on_settings_change'), 10, 2);
        add_action('update_option_pr_enable_purchase', array($this, 'cleanup_on_settings_change'), 10, 2);
        add_action('update_option_pr_restrict_categories', array($this, 'cleanup_on_settings_change'), 10, 2);
        add_action('update_option_pr_allowed_categories', array($this, 'cleanup_on_settings_change'), 10, 2);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Ahmed\'s Pointsystem',
            'Ahmed\'s Pointsystem',
            'manage_options',
            'ahmeds-pointsystem',
            array($this, 'settings_page'),
            'dashicons-star-filled',
            56
        );

        add_submenu_page(
            'ahmeds-pointsystem',
            'Settings',
            'Settings',
            'manage_options',
            'ahmeds-pointsystem',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'ahmeds-pointsystem',
            'Users Points',
            'Users Points',
            'manage_options',
            'ahmeds-pointsystem-users',
            array($this, 'users_page')
        );

        add_submenu_page(
            'ahmeds-pointsystem',
            'Guest Recovery',
            'Guest Recovery',
            'manage_options',
            'ahmeds-pointsystem-guest-recovery',
            array('PR_Guest_Recovery', 'display_guest_recovery_page')
        );
    }

    public function register_settings() {
        register_setting('pr_settings', 'pr_conversion_rate', array(
            'type' => 'number',
            'sanitize_callback' => array($this, 'sanitize_conversion_rate'),
            'default' => 1
        ));
        register_setting('pr_settings', 'pr_registration_points', array(
            'type' => 'number',
            'sanitize_callback' => array($this, 'sanitize_registration_points'),
            'default' => 10
        ));
        register_setting('pr_settings', 'pr_enable_purchase', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no'
        ));
        register_setting('pr_settings', 'pr_restrict_categories', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no'
        ));
        register_setting('pr_settings', 'pr_allowed_categories', array(
            'sanitize_callback' => array($this, 'sanitize_allowed_categories'),
            'default' => array()
        ));
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'ahmeds-pointsystem') === false) return;
        
        wp_enqueue_style(
            'pr-admin-style',
            PR_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'pr-admin-script',
            PR_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }

    public function handle_admin_actions() {
        // Check if the award existing points button was clicked
        if (!isset($_POST['pr_award_existing_points'])) {
            return; // Not our action
        }
        
        // Increase timeout for this operation
        @set_time_limit(300);
        
        // Verify nonce
        if (!isset($_POST['_wpnonce'])) {
            wp_die('Security check failed. Nonce missing.');
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'pr_award_existing_nonce')) {
            wp_die('Security check failed. Nonce expired or invalid.');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }
        
        // Sanitize input
        $award_points = sanitize_text_field($_POST['pr_award_existing_points']);
        
        if ($award_points !== '1') {
            return; // Invalid value
        }
        
        // Award the points
        $count = $this->award_registration_points_to_existing_users();
        
        // Set transient for success message
        if ($count > 0) {
            set_transient('pr_award_notice_success', sprintf(__('Successfully recalculated points for %d users.', 'ahmeds-pointsystem'), $count), 30);
        } else {
            set_transient('pr_award_notice_info', __('Recalculation complete.', 'ahmeds-pointsystem'), 30);
        }
        
        // Redirect to prevent form resubmission
        wp_safe_redirect(admin_url('admin.php?page=ahmeds-pointsystem'));
        exit;
    }

    public function settings_page() {
        $conversion_rate = get_option('pr_conversion_rate', 1);
        $registration_points = get_option('pr_registration_points', 0);
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        $allowed_categories = get_option('pr_allowed_categories', array());
        
        // Display success/info messages
        $success_message = get_transient('pr_award_notice_success');
        $info_message = get_transient('pr_award_notice_info');
        
        if ($success_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
            delete_transient('pr_award_notice_success');
        }
        
        if ($info_message) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($info_message) . '</p></div>';
            delete_transient('pr_award_notice_info');
        }
        
        ?>
        <div class="wrap pr-settings-wrap">
            <h1>⭐ Ahmed's Pointsystem Settings</h1>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('pr_settings');
                ?>
                
                <div class="pr-card">
                    <h2>💱 Points Conversion Rate</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_conversion_rate">Points Per Kroner</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="pr_conversion_rate" 
                                       name="pr_conversion_rate" 
                                       value="<?php echo esc_attr($conversion_rate); ?>" 
                                       step="0.01" 
                                       min="0.01" 
                                       class="regular-text" 
                                       placeholder="1.00" />
                                <p class="description">
                                    Set the conversion rate. For example: 1 = 1 point per Kr., 2 = 1 point per 2 Kr.</p>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="pr-card">
                    <h2>🎁 Registration Bonus</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_registration_points">Initial Welcome Bonus</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="pr_registration_points" 
                                       name="pr_registration_points" 
                                       value="<?php echo esc_attr($registration_points); ?>" 
                                       min="0" 
                                       step="1"
                                       class="regular-text" 
                                       placeholder="10" />
                                <p class="description">
                                    Free points awarded to every user. This bonus is applied dynamically to all users.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="pr-card">
                    <h2>🛍️ Product Purchase with Points</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_enable_purchase">Enable Checkout with Points</label>
                            </th>
                            <td>
                                <label class="pr-toggle">
                                    <input type="checkbox" 
                                           id="pr_enable_purchase" 
                                           name="pr_enable_purchase" 
                                           value="yes" 
                                           <?php checked($enable_purchase, 'yes'); ?> />
                                    <span class="pr-toggle-slider"></span>
                                </label>
                                <span class="pr-toggle-label">Allow customers to pay with points</span>
                                <p class="description">
                                    Let customers purchase products using their available points
                                </p>
                            </td>
                        </tr>
                        <tr class="pr-restriction-row">
                            <th scope="row">
                                <label for="pr_restrict_categories">Restrictions</label>
                            </th>
                            <td>
                                <label class="pr-toggle">
                                    <input type="checkbox" 
                                           id="pr_restrict_categories" 
                                           name="pr_restrict_categories" 
                                           value="yes" 
                                           <?php checked($restrict_categories, 'yes'); ?> />
                                    <span class="pr-toggle-slider"></span>
                                </label>
                                <span class="pr-toggle-label">
                                    Allow some of the products for purchasing through points
                                </span>
                            </td>
                        </tr>
                        <tr class="pr-categories-row" 
                            style="<?php echo $restrict_categories === 'yes' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="pr_allowed_categories">Select Product Category</label>
                            </th>
                            <td>
                                <?php
                                $categories = get_terms(array(
                                    'taxonomy' => 'product_cat',
                                    'hide_empty' => false,
                                ));
                                
                                if (!empty($categories)) {
                                    // Hidden field to ensure the field is always submitted
                                    echo '<input type="hidden" name="pr_allowed_categories" value="" />';
                                    echo '<select name="pr_allowed_categories[]" 
                                                  id="pr_allowed_categories" 
                                                  multiple 
                                                  class="pr-select">';
                                    foreach ($categories as $category) {
                                        $selected = in_array($category->term_id, (array)$allowed_categories) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>';
                                        echo esc_html($category->name);
                                        echo '</option>';
                                    }
                                    echo '</select>';
                                }
                                ?>
                                <p class="description">
                                    Select which product categories can be purchased with points (e.g., "gave produkt")
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>

            <!-- Apply Bonus Action (separate form to avoid nonce conflicts with main settings form) -->
            <div class="pr-card">
                <h2>📊 Apply Bonus to Users</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('pr_award_existing_nonce'); ?>
                    <input type="hidden" name="pr_award_existing_points" value="1" />
                    <p>Click the button below to recalculate and apply the current registration bonus to all users.</p>
                    <button type="submit" class="button button-primary" 
                            onclick="return confirm('This will apply the registration bonus to all users. Continue?');">
                        Recalculate Registration Bonus for All Users
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    public function users_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        // Get the current registration bonus setting
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        
        // First, ensure no duplicates exist
        $this->cleanup_duplicate_users();
        
        // Ultra-optimized query: Separate concerns for speed
        // Step 1: Get all signed-up users (everyone gets signup bonus)
        // Show all registered users regardless of purchase history
        $results = $wpdb->get_results("
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                up.points as points,
                up.redeemed_points as redeemed_points
            FROM {$wpdb->users} u
            INNER JOIN $table_name up ON u.ID = up.user_id
        ");
        
        // Step 2: Efficiently get total spent per user using a subquery
        // This uses WooCommerce order meta directly without multiple joins
        if (!empty($results)) {
            $user_ids = wp_list_pluck($results, 'user_id');
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            
            // Get order totals for these specific users
            // WooCommerce stores customer user ID in _customer_user postmeta
            // Only count orders from March 11, 2025 onwards
            $spent_query = $wpdb->prepare("
                SELECT 
                    pm_customer.meta_value as user_id,
                    SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
                INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order' 
                    AND p.post_status = 'wc-completed'
                    AND p.post_date >= '2025-03-11'
                    AND pm_customer.meta_value IN ($placeholders)
                GROUP BY pm_customer.meta_value
            ", ...$user_ids);
            
            $spent_by_user = $wpdb->get_results($spent_query, OBJECT_K);
            
            // Get conversion rate for calculating purchase-based points
            $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
            
            // Add spent amounts and calculate total points dynamically
            foreach ($results as &$result) {
                $result->total_spent = isset($spent_by_user[$result->user_id]) 
                    ? floatval($spent_by_user[$result->user_id]->total_spent) 
                    : 0;
                
                // Calculate purchase-based points from spending
                $purchase_points = intval(floor($result->total_spent / $conversion_rate));
                
                // Display total = purchase points + registration bonus
                $result->total_points_display = $purchase_points + $registration_bonus;
                $result->purchase_points = $purchase_points;
            }
            
            // Sort by total spent (descending) to show highest spenders first
            usort($results, function($a, $b) {
                return $b->total_spent <=> $a->total_spent;
            });
        }
        
        ?>
        <div class="wrap pr-users-wrap">
            <h1>Ahmed's Pointsystem - Users Points</h1>
            
            <div class="pr-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Total Spent</th>
                            <th>Points</th>
                            <th>Redeemed Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($results)) : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row->display_name); ?></td>
                                    <td><?php echo esc_html($row->user_email); ?></td>
                                    <td><?php echo wc_price($row->total_spent); ?></td>
                                    <td><strong><?php echo esc_html($row->total_points_display); ?></strong></td>
                                    <td><?php echo esc_html($row->redeemed_points); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5">No customers with completed orders yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function repair_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        // Step 1: Remove all duplicate entries, keeping only the first one per user
        $this->cleanup_duplicate_users();

        // Step 2: Create records for all users who don't have one
        $all_users = get_users(array('fields' => 'ID'));
        $created = 0;

        foreach ($all_users as $user_id) {
            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d", $user_id)
            );

            if ($exists == 0) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'points' => 0,
                        'redeemed_points' => 0
                    ),
                    array('%d', '%d', '%d')
                );

                if ($result !== false) {
                    $created++;
                }
            }
        }

        // Step 3: Check for negative or invalid points
        $result = $wpdb->query(
            "UPDATE $table_name SET points = 0 WHERE points < 0"
        );

        $result2 = $wpdb->query(
            "UPDATE $table_name SET redeemed_points = 0 WHERE redeemed_points < 0"
        );

        // Step 4: Verify all entries are clean
        $result3 = $wpdb->query(
            "DELETE FROM $table_name WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users})"
        );

        return $created;
    }

    public function award_registration_points_to_existing_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        // First, clean up any duplicates
        $this->cleanup_duplicate_users();
        
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));

        // Get all users with completed orders
        $all_users = $wpdb->get_results("
            SELECT DISTINCT pm_customer.meta_value as user_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
        ");

        if (empty($all_users)) {
            return 0; // No users with orders
        }

        // Get ALL spending data in one query for efficiency
        $all_spent = $wpdb->get_results("
            SELECT 
                pm_customer.meta_value as user_id,
                SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
            GROUP BY pm_customer.meta_value
        ");
        
        // Create lookup map for fast access
        $spent_map = array();
        foreach ($all_spent as $row) {
            $spent_map[$row->user_id] = floatval($row->total_spent);
        }

        $count = 0;

        // Batch update all users at once
        foreach ($all_users as $user) {
            $user_id = intval($user->user_id);
            $total_spent = isset($spent_map[$user_id]) ? $spent_map[$user_id] : 0;
            $purchase_points = intval(floor($total_spent / $conversion_rate));
            
            // Ensure user exists in table
            $wpdb->query($wpdb->prepare("
                INSERT IGNORE INTO $table_name (user_id, points, redeemed_points)
                VALUES (%d, %d, 0)
            ", $user_id, $purchase_points));
            
            // Update the user's points to be purchase points only
            $updated = $wpdb->update(
                $table_name,
                array('points' => $purchase_points),
                array('user_id' => $user_id),
                array('%d'),
                array('%d')
            );
            
            if ($updated !== false) {
                $count++;
            }
        }
        
        // Final cleanup to ensure no duplicates remain
        $this->cleanup_duplicate_users();

        return $count;
    }

    public function backfill_points_for_orders() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        if (!function_exists('wc_get_orders')) {
            return 0; // WooCommerce not available
        }

        $conversion_rate = max(0.01, (float) get_option('pr_conversion_rate', 1));

        // Get all completed orders
        $orders = wc_get_orders(array(
            'status' => array('wc-completed'),
            'limit' => -1,
            'return' => 'ids',
        ));

        $count = 0;

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // Check if already awarded
            if ($order->get_meta('_points_awarded') === 'yes') {
                continue;
            }

            $user_id = $order->get_user_id();
            $order_total = (float) $order->get_total();

            // Skip if no user or no order total
            if (!$user_id || $order_total <= 0) {
                $order->update_meta_data('_points_awarded', 'yes');
                $order->save();
                continue;
            }

            $points = (int) floor($order_total / $conversion_rate);
            if ($points > 0) {
                // First ensure user has a row
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT IGNORE INTO $table_name (user_id, points, redeemed_points) VALUES (%d, 0, 0)",
                        $user_id
                    )
                );

                // Then update points
                $result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $table_name SET points = points + %d WHERE user_id = %d",
                        $points,
                        $user_id
                    )
                );

                if ($result !== false) {
                    $count++;
                } else {
                    error_log("Points & Rewards: Failed to backfill points for order $order_id, user $user_id: " . $wpdb->last_error);
                }
            }

            // Mark as awarded
            $order->update_meta_data('_points_awarded', 'yes');
            $order->save();
        }

        return $count;
    }

    public function recalculate_points_on_conversion_rate_change($old_value, $new_value) {
        // Only recalculate if the conversion rate actually changed
        if ((float) $old_value === (float) $new_value) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        $old_conversion_rate = max(0.01, (float) $old_value);
        $new_conversion_rate = max(0.01, (float) $new_value);

        // Get all completed orders with _points_awarded flag
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(array(
                'status' => array('wc-completed'),
                'limit' => -1,
                'return' => 'ids',
            ));

            // Calculate adjustment factor
            $adjustment_factor = $old_conversion_rate / $new_conversion_rate;

            // Update existing points by multiplying with adjustment factor
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_name SET points = FLOOR(points * %f)",
                    $adjustment_factor
                )
            );

            if ($result === false) {
                error_log("Points & Rewards: Failed to recalculate points on conversion rate change: " . $wpdb->last_error);
            }

            // For new orders that might not have been processed yet, we'll let them be handled normally
        }

        // Always run cleanup after recalculation
        $this->cleanup_duplicate_users();
    }

    public function cleanup_on_settings_change($old_value, $new_value) {
        // Only run cleanup if the value actually changed
        if ($old_value !== $new_value) {
            $this->cleanup_duplicate_users();
        }
    }

    public function cleanup_duplicate_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';

        // Find all user_ids that have duplicates
        $duplicates = $wpdb->get_results("
            SELECT user_id, COUNT(*) as count 
            FROM $table_name 
            GROUP BY user_id 
            HAVING count > 1
        ");

        if (empty($duplicates)) {
            return; // No duplicates to clean up
        }

        // For each user with duplicates, merge all their rows
        foreach ($duplicates as $dup) {
            $user_id = $dup->user_id;

            // Get all rows for this user
            $user_rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY id ASC", $user_id)
            );

            if (count($user_rows) > 1) {
                // Sum up all points and redeemed_points
                $total_points = 0;
                $total_redeemed = 0;
                $first_row_id = $user_rows[0]->id;

                foreach ($user_rows as $row) {
                    $total_points += (int) $row->points;
                    $total_redeemed += (int) $row->redeemed_points;
                }

                // Update the first row with totals
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'points' => $total_points,
                        'redeemed_points' => $total_redeemed,
                    ),
                    array('id' => $first_row_id)
                );

                if ($result === false) {
                    error_log("Points & Rewards: Failed to update merged points for user $user_id: " . $wpdb->last_error);
                    continue;
                }

                // Delete all duplicate rows (keep only the first one)
                $delete_result = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM $table_name WHERE user_id = %d AND id > %d",
                        $user_id,
                        $first_row_id
                    )
                );

                if ($delete_result === false) {
                    error_log("Points & Rewards: Failed to delete duplicate rows for user $user_id: " . $wpdb->last_error);
                }
            }
        }
    }

    public function sanitize_conversion_rate($value) {
        $value = floatval($value);
        return max(0.01, $value); // Minimum 0.01 to prevent division by zero
    }

    public function sanitize_registration_points($value) {
        $value = intval($value);
        return max(0, $value); // Cannot be negative
    }

    public function sanitize_allowed_categories($value) {
        if (is_array($value)) {
            return array_map('intval', array_filter($value, 'is_numeric'));
        }
        if (is_string($value)) {
            // handle comma-separated input just in case
            $parts = array_filter(array_map('trim', explode(',', $value)));
            $numeric_parts = array_filter($parts, 'is_numeric');
            return array_map('intval', $numeric_parts);
        }
        return array();
    }
}