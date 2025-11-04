<?php
if (!defined('ABSPATH')) exit;

class PR_Frontend_Display {
    public function __construct() {
        // Register point-log as a WooCommerce account endpoint
        add_action('init', array($this, 'register_point_log_endpoint'));

        // Add Point Log link to WooCommerce My Account menu
        add_filter('woocommerce_account_menu_items', array($this, 'add_point_log_menu_item'));
        
        // Render the point log content when the endpoint is accessed
        add_action('woocommerce_account_point-log_endpoint', array($this, 'display_point_log_content'));
    }

    public function register_point_log_endpoint() {
        // Register point-log as a WooCommerce account endpoint
        add_rewrite_endpoint('point-log', EP_ROOT | EP_PAGES);
    }

    public function display_points_dashboard() {
        echo '<script>console.log("display_points_dashboard() called");</script>';
        
        if (!is_user_logged_in()) {
            echo '<script>console.log("User not logged in");</script>';
            return array(
                'available_points' => 0,
                'total_points' => 0,
                'redeemed_points' => 0,
                'total_spent' => 0,
                'registration_bonus' => 0,
                'purchase_points' => 0,
                'access_revoked' => false
            );
        }

        $user_id = get_current_user_id();
        echo '<script>console.log("User ID: ' . intval($user_id) . '");</script>';
        
        // Check if user is revoked
        echo '<script>console.log("Checking if user is revoked");</script>';
        $user_management = new PR_User_Management();
        if ($user_management->is_user_revoked($user_id)) {
            echo '<script>console.log("User is revoked");</script>';
            return array(
                'available_points' => 0,
                'total_points' => 0,
                'redeemed_points' => 0,
                'total_spent' => 0,
                'registration_bonus' => 0,
                'purchase_points' => 0,
                'access_revoked' => true
            );
        }

        echo '<script>console.log("Getting user points");</script>';
        $user_points = PR_Points_Manager::get_user_points($user_id);
        echo '<script>console.log("User points retrieved: " + typeof ' . 'arguments' . ');</script>';
        
        if (!is_object($user_points)) {
            echo '<script>console.log("user_points is not an object, using fallback");</script>';
            $user_points = (object) array(
                'points' => 0,
                'redeemed_points' => 0
            );
        }
        
        echo '<script>console.log("Getting registration bonus and conversion rate");</script>';
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        $restrict_categories = get_option('pr_restrict_categories', 'no');
        $allowed_categories = get_option('pr_allowed_categories', array());

        echo '<script>console.log("Querying points_manually_set");</script>';
        // Check if this user's points were manually set by admin
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_points';
        $points_manually_set = intval($wpdb->get_var($wpdb->prepare(
            "SELECT points_manually_set FROM $table_name WHERE user_id = %d",
            $user_id
        )));

        echo '<script>console.log("Executing total_spent query");</script>';
        // Get total spent - only count from allowed categories if restriction is enabled
        // Include completed and processing orders (points are awarded immediately on checkout)
        $query = "
            SELECT SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user' AND pm_customer.meta_value = %d
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order' AND (p.post_status = 'wc-completed' OR p.post_status = 'wc-processing') AND p.post_date >= '2025-03-11'
        ";
        
        // If category restriction is enabled, only count from allowed categories
        if ($restrict_categories === 'yes' && !empty($allowed_categories)) {
            $category_ids = implode(',', array_map('intval', (array)$allowed_categories));
            $query .= " AND p.ID IN (
                SELECT DISTINCT pm_order.post_id
                FROM {$wpdb->postmeta} pm_order
                INNER JOIN {$wpdb->posts} product ON pm_order.meta_value = product.ID
                INNER JOIN {$wpdb->term_relationships} tr ON product.ID = tr.object_id
                WHERE pm_order.meta_key = '_product_id' AND tr.term_taxonomy_id IN (
                    SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} 
                    WHERE term_id IN ({$category_ids}) AND taxonomy = 'product_cat'
                )
            )";
        }
        
        echo '<script>console.log("Running query");</script>';
        $total_spent_result = $wpdb->get_row($wpdb->prepare($query, $user_id));
        echo '<script>console.log("Query result retrieved");</script>';
        
        $total_spent = 0;
        if ($total_spent_result && isset($total_spent_result->total_spent)) {
            $total_spent = floatval($total_spent_result->total_spent);
        }
        echo '<script>console.log("total_spent: ' . floatval($total_spent) . '");</script>';
        
        $purchase_points = intval(floor($total_spent / $conversion_rate));
        
        // If points were manually set, use the stored value
        // Otherwise, recalculate from spending + registration bonus
        if ($points_manually_set === 1) {
            $total_points = intval($user_points->points);
        } else {
            $total_points = $purchase_points + $registration_bonus;
        }
        
        $available_points = $total_points - intval($user_points->redeemed_points);

        echo '<script>console.log("Returning data from display_points_dashboard");</script>';
        return array(
            'available_points' => $available_points,
            'total_points' => $total_points,
            'redeemed_points' => intval($user_points->redeemed_points),
            'total_spent' => $total_spent,
            'registration_bonus' => $registration_bonus,
            'purchase_points' => $purchase_points,
            'points_manually_set' => $points_manually_set,
            'manually_set_points' => $points_manually_set === 1 ? intval($user_points->points) : 0,
        );
    }

    public function add_point_log_menu_item($items) {
        // Ensure point log link shows only for logged-in users
        if (!is_user_logged_in()) {
            return $items;
        }

    // Add the new item (German)
    $items['point-log'] = __('Punkteverlauf', 'ahmeds-pointsystem');

        return $items;
    }

    public function display_point_log_content() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(wc_get_endpoint_url('point-log', '', wc_get_page_permalink('myaccount'))));
            exit;
        }

        $data = $this->display_points_dashboard();
        if (!is_array($data)) {
            $data = array(
                'available_points' => 0,
                'total_points' => 0,
                'redeemed_points' => 0,
                'total_spent' => 0,
                'registration_bonus' => 0,
                'purchase_points' => 0,
                'access_revoked' => false
            );
        }
        
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        ?>
        <div class="pr-point-log-container">
            <?php
            if (isset($data['access_revoked']) && $data['access_revoked']) {
                ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                    <h2 style="color: #dc3545; margin-bottom: 20px;">🚫 Zugriff entzogen</h2>
                    <p style="font-size: 16px; color: #666; margin-bottom: 20px;">
                        Dein Zugriff auf die Prämien wurde von einem Administrator entzogen.
                    </p>
                    <p style="font-size: 14px; color: #999;">
                        Wenn du glaubst, dass dies ein Irrtum ist, kontaktiere bitte den Support.
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="pr-points-dashboard">
                    <h2>⭐ Deine Punkteübersicht</h2>

                    <div class="pr-points-grid">
                        <div class="pr-points-card">
                            <div class="pr-card-header">Verfügbare Punkte</div>
                            <div class="pr-card-value"><?php echo esc_html($data['available_points']); ?></div>
                            <div class="pr-card-label">Punkte, die du verwenden kannst</div>
                        </div>

                        <div class="pr-points-card">
                            <div class="pr-card-header">Gesamtpunkte</div>
                            <div class="pr-card-value"><?php echo esc_html($data['total_points']); ?></div>
                            <div class="pr-card-label">Insgesamt verdient</div>
                        </div>

                        <div class="pr-points-card">
                            <div class="pr-card-header">Eingelöste Punkte</div>
                            <div class="pr-card-value"><?php echo esc_html($data['redeemed_points']); ?></div>
                            <div class="pr-card-label">Für Einkäufe eingelöst</div>
                        </div>

                        <div class="pr-points-card">
                            <div class="pr-card-header">Gesamtausgaben</div>
                            <div class="pr-card-value"><?php echo wc_price($data['total_spent']); ?></div>
                            <div class="pr-card-label">Im Shop ausgegeben</div>
                        </div>
                    </div>

                    <div class="pr-points-breakdown">
                        <h3>Aufschlüsselung der Punkte</h3>
                        <ul>
                            <?php if (isset($data['points_manually_set']) && $data['points_manually_set'] === 1) { ?>
                                <li>
                                    <strong>⚙️ Punkte wurden vom Administrator manuell vergeben:</strong>
                                    <span style="color: #2271b1; font-weight: 600; "><?php echo esc_html($data['manually_set_points']); ?> Punkte</span>
                                </li>
                                <li style="opacity: 0.6;">
                                    <strong>Registrierungsbonus:</strong>
                                    <span>—</span>
                                    <small style="display: block; margin-top: 3px; font-style: italic;">(Nicht enthalten, da die Punkte manuell festgelegt wurden)</small>
                                </li>
                                <li style="opacity: 0.6;">
                                    <strong>Punkte aus Einkäufen:</strong>
                                    <span>—</span>
                                    <small style="display: block; margin-top: 3px; font-style: italic;">(Nicht enthalten, da die Punkte manuell festgelegt wurden)</small>
                                </li>
                            <?php } else { ?>
                                <li>
                                    <strong>Registrierungsbonus:</strong>
                                    <span><?php echo esc_html($data['registration_bonus']); ?> Punkte</span>
                                </li>
                                <li>
                                    <strong>Punkte aus Einkäufen:</strong>
                                    <span><?php echo esc_html($data['purchase_points']); ?> Punkte</span>
                                    <small>(<?php echo wc_price($data['total_spent']); ?> ÷ <?php echo esc_html($conversion_rate); ?>)</small>
                                </li>
                            <?php } ?>
                            <li>
                                <strong>Eingelöste Punkte:</strong>
                                <span style="color: #dc3545;">-<?php echo esc_html($data['redeemed_points']); ?> Punkte</span>
                            </li>
                            <li style="border-top: 2px solid #2271b1; padding-top: 10px; margin-top: 10px;">
                                <strong>Verfügbare Punkte:</strong>
                                <span style="color: #2271b1; font-size: 1.2em;"><strong><?php echo esc_html($data['available_points']); ?> Punkte</strong></span>
                            </li>
                        </ul>
                    </div>

                    <p class="pr-points-note">
                        💡 Du kannst deine Punkte verwenden, um bestimmte Produkte in unserem Shop zu kaufen.
                        <a href="<?php echo esc_url(home_url('/vare-kategori/gave-produkt/')); ?>">Produkte ansehen</a>
                    </p>
                </div>

                <div class="pr-points-history">
                    <h2>📊 Punkteverlauf</h2>

                    <div class="pr-history-info">
                        <p>Hier siehst du deine Punktetransaktionen und wie sich deine Punkte im Laufe der Zeit angesammelt haben.</p>
                    </div>

                    <h3>So sammelst du Punkte:</h3>
                    <ul class="pr-history-list">
                        <li>
                            <strong>✅ Registrierungsbonus:</strong>
                            Automatisch gutgeschrieben, wenn du ein Konto erstellst
                        </li>
                        <li>
                            <strong>💰 Durch Einkäufe:</strong>
                            Du sammelst bei jedem Einkauf Punkte. Die Anzahl hängt von deinem Einkaufswert ab.
                        </li>
                    </ul>

                    <h3>So kannst du Punkte einlösen:</h3>
                    <ul class="pr-history-list">
                        <li>
                            <strong>🎁 Produkte kaufen:</strong>
                            Verwende deine Punkte, um spezielle Produkte in unserer Geschenk-Kategorie zu kaufen.
                        </li>
                    </ul>
                </div>
            <?php } ?>
        </div>
        <?php
    }
}
?>