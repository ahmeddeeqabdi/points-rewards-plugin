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

    public function settings_page() {
        $conversion_rate = get_option('pr_conversion_rate', 1);
        $registration_points = get_option('pr_registration_points', 0);
        $enable_purchase = get_option('pr_enable_purchase', 'no');
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        $allowed_categories = get_option('pr_allowed_categories', array());
        
        ?>
        <div class="wrap pr-settings-wrap">
            <h1>Ahmed's Pointsystem Settings</h1>
            
            <?php if (isset($_GET['awarded'])) : ?>
                <?php $count = intval($_GET['awarded']); ?>
                <?php if ($count > 0) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php printf('Successfully awarded registration points to %d user(s)!', $count); ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-info is-dismissible">
                        <p>All existing users have already received their registration points. No new points were awarded.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['backfilled'])) : ?>
                <?php $count = intval($_GET['backfilled']); ?>
                <?php if ($count > 0) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php printf('Successfully awarded points for %d order(s)!', $count); ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-info is-dismissible">
                        <p>All completed orders have already had points awarded. No new points were backfilled.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['cleaned'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Duplicate user entries have been successfully cleaned up!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('pr_settings');
                ?>
                
                <div class="pr-card">
                    <h2>Points Conversion</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_conversion_rate">Points per Kr.</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="pr_conversion_rate" 
                                       name="pr_conversion_rate" 
                                       value="<?php echo esc_attr($conversion_rate); ?>" 
                                       step="0.01" 
                                       min="0.01" 
                                       class="regular-text" />
                                <p class="description">
                                    How many Kr. equals 1 point (e.g., 1 = 1 point per Kr.)
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="pr-card">
                    <h2>Registration Bonus</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_registration_points">Initial Registration Points</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="pr_registration_points" 
                                       name="pr_registration_points" 
                                       value="<?php echo esc_attr($registration_points); ?>" 
                                       min="0" 
                                       class="regular-text" />
                                <p class="description">
                                    Points awarded when a new user registers
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label>Award to Existing Users</label>
                            </th>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('pr_award_existing_nonce'); ?>
                                    <input type="hidden" name="pr_award_existing_points" value="1" />
                                    <button type="submit" class="button button-secondary" 
                                            onclick="return confirm('This will award <?php echo esc_attr($registration_points); ?> points to all existing users. Continue?');">
                                        Award <?php echo esc_attr($registration_points); ?> Points to All Users
                                    </button>
                                </form>
                                <p class="description">
                                    Award registration bonus points to all existing users (one-time action)
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="pr-card">
                    <h2>Product Purchase with Points</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pr_enable_purchase">Enable Purchase with Points</label>
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
                                <p class="description">
                                    Allow customers to purchase products using points
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

                <div class="pr-card">
                    <h2>Database Maintenance</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label>Backfill Points for Existing Orders</label>
                            </th>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('pr_backfill_nonce'); ?>
                                    <input type="hidden" name="pr_backfill_orders" value="1" />
                                    <button type="submit" class="button button-secondary" 
                                            onclick="return confirm('This will recalculate and award points for all existing completed orders that are missing points. Continue?');">
                                        Backfill Points for Orders
                                    </button>
                                </form>
                                <p class="description">
                                    Award points to users for orders placed before the plugin was activated or where points were not awarded
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label>Clean Up Duplicate Users</label>
                            </th>
                            <td>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('pr_cleanup_nonce'); ?>
                                    <input type="hidden" name="pr_cleanup_duplicates" value="1" />
                                    <button type="submit" class="button button-secondary" 
                                            onclick="return confirm('This will merge duplicate user entries and remove duplicates. Continue?');">
                                        Clean Up Duplicates
                                    </button>
                                </form>
                                <p class="description">
                                    Merge duplicate user entries in the points table (safe operation)
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function users_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        // Get only members (users with points records)
        // Optimized query: get basic user and points data without complex JOINs
        $results = $wpdb->get_results("
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                up.points as points,
                up.redeemed_points as redeemed_points
            FROM {$wpdb->users} u
            INNER JOIN $table_name up ON u.ID = up.user_id
            ORDER BY up.points DESC, u.display_name ASC
        ");

        // Calculate total spent separately for display (simpler and faster)
        $results_with_spent = array();
        foreach ($results as $user) {
            $user->total_spent = 0;
            
            // Get user's total spent from WooCommerce orders
            if (function_exists('wc_get_orders')) {
                $orders = wc_get_orders(array(
                    'customer' => $user->user_id,
                    'status' => 'wc-completed',
                    'return' => 'objects',
                ));
                
                foreach ($orders as $order) {
                    $user->total_spent += $order->get_total();
                }
            }
            
            $results_with_spent[] = $user;
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
                        <?php if (!empty($results_with_spent)) : ?>
                            <?php foreach ($results_with_spent as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row->display_name); ?></td>
                                    <td><?php echo esc_html($row->user_email); ?></td>
                                    <td><?php echo wc_price($row->total_spent); ?></td>
                                    <td><strong><?php echo esc_html($row->points); ?></strong></td>
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

    public function handle_admin_actions() {
        if (isset($_POST['pr_award_existing_points']) && check_admin_referer('pr_award_existing_nonce')) {
            if (current_user_can('manage_options')) {
                $count = $this->award_registration_points_to_existing_users();
                wp_redirect(admin_url('admin.php?page=ahmeds-pointsystem&awarded=' . intval($count)));
                exit;
            }
        }

        if (isset($_POST['pr_backfill_orders']) && check_admin_referer('pr_backfill_nonce')) {
            if (current_user_can('manage_options')) {
                $count = $this->backfill_points_for_orders();
                wp_redirect(admin_url('admin.php?page=ahmeds-pointsystem&backfilled=' . intval($count)));
                exit;
            }
        }

        if (isset($_POST['pr_cleanup_duplicates']) && check_admin_referer('pr_cleanup_nonce')) {
            if (current_user_can('manage_options')) {
                $this->cleanup_duplicate_users();
                wp_redirect(admin_url('admin.php?page=ahmeds-pointsystem&cleaned=1'));
                exit;
            }
        }
    }

    public function award_registration_points_to_existing_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        $registration_points = get_option('pr_registration_points', 0);

        if ($registration_points <= 0) {
            return 0; // No points to award
        }

        // Get all registered users
        $all_users = get_users(array('fields' => 'ID'));

        if (empty($all_users)) {
            return 0; // No users
        }

        $count = 0;

        foreach ($all_users as $user_id) {
            // Check if user has a record and if they've received registration points
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
            );

            if ($existing === null) {
                // User has no record at all - create one with registration points
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'points' => $registration_points,
                        'redeemed_points' => 0
                    ),
                    array('%d', '%d', '%d')
                );

                if ($result !== false) {
                    $count++;
                }
            } else {
                // User has a record - check if they have registration bonus flag
                $has_registration_bonus = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
                        $user_id,
                        'pr_registration_bonus_awarded'
                    )
                );

                if (!$has_registration_bonus) {
                    // User doesn't have registration bonus yet - add it
                    $result = $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET points = points + %d WHERE user_id = %d",
                            $registration_points,
                            $user_id
                        )
                    );

                    if ($result !== false) {
                        // Mark that we've awarded the registration bonus
                        update_user_meta($user_id, 'pr_registration_bonus_awarded', '1');
                        $count++;
                    }
                }
            }
        }

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