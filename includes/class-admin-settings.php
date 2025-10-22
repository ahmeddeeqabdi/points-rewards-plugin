<?php
if (!defined('ABSPATH')) exit;

class PR_Admin_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_save_pr_settings', array($this, 'save_settings'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Points & Rewards',
            'Points & Rewards',
            'manage_options',
            'points-rewards',
            array($this, 'settings_page'),
            'dashicons-star-filled',
            56
        );

        add_submenu_page(
            'points-rewards',
            'Settings',
            'Settings',
            'manage_options',
            'points-rewards',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'points-rewards',
            'Users Points',
            'Users Points',
            'manage_options',
            'points-rewards-users',
            array($this, 'users_page')
        );
    }

    public function register_settings() {
        register_setting('pr_settings', 'pr_conversion_rate');
        register_setting('pr_settings', 'pr_registration_points');
        register_setting('pr_settings', 'pr_enable_purchase');
        register_setting('pr_settings', 'pr_restrict_categories');
        register_setting('pr_settings', 'pr_allowed_categories');
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'points-rewards') === false) return;
        
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
            <h1>Points & Rewards Settings</h1>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('pr_settings');
                wp_nonce_field('pr_settings_nonce', 'pr_settings_nonce'); 
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

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function users_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        
        $results = $wpdb->get_results("
            SELECT up.*, u.display_name, u.user_email 
            FROM $table_name up
            LEFT JOIN {$wpdb->users} u ON up.user_id = u.ID
            ORDER BY up.points DESC
        ");
        
        ?>
        <div class="wrap pr-users-wrap">
            <h1>Users Points</h1>
            
            <div class="pr-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
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
                                    <td><strong><?php echo esc_html($row->points); ?></strong></td>
                                    <td><?php echo esc_html($row->redeemed_points); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4">No users with points yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}