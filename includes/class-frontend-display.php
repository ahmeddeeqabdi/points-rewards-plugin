<?php
if (!defined('ABSPATH')) exit;

class PR_Frontend_Display {
    public function __construct() {
        // Add custom rewrite rule for standalone point-log page
        add_action('init', array($this, 'add_point_log_rewrite_rule'));
        add_action('query_vars', array($this, 'add_point_log_query_var'));
        add_action('template_redirect', array($this, 'handle_point_log_request'));

        // Add Point Log link to WooCommerce My Account menu
        add_filter('woocommerce_account_menu_items', array($this, 'add_point_log_menu_item'));
        add_filter('woocommerce_get_endpoint_url', array($this, 'override_point_log_menu_link'), 10, 4);
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

    public function add_point_log_rewrite_rule() {
        add_rewrite_rule('^point-log/?$', 'index.php?point_log=1', 'top');
    }

    public function add_point_log_query_var($vars) {
        $vars[] = 'point_log';
        return $vars;
    }

    public function handle_point_log_request() {
        if (get_query_var('point_log')) {
            $this->display_point_log_page();
            // Important: exit after rendering a standalone page
            exit; 
        }
    }

    public function add_point_log_menu_item($items) {
        // Ensure point log link shows only for logged-in users
        if (!is_user_logged_in()) {
            return $items;
        }

        // Add the new item
        $items['point-log'] = __('Pointlog', 'ahmeds-pointsystem');

        // Optional: Reorder to place it correctly.
        // This is a common pattern to add an item before logout.
        $new_items = array();
        foreach ($items as $endpoint => $label) {
            if ('customer-logout' === $endpoint) {
                $new_items['point-log'] = __('Pointlog', 'ahmeds-pointsystem'); // Add it here if you want it before logout
            }
            $new_items[$endpoint] = $label;
        }
        if (!isset($new_items['point-log'])) { // If not added before logout, add at the end
             $new_items['point-log'] = __('Pointlog', 'ahmeds-pointsystem');
        }
        
        // If the above reordering logic caused an issue, simplify:
        // $items['point-log'] = __('Pointlog', 'ahmeds-pointsystem');
        // return $items;

        return $new_items;
    }

    public function override_point_log_menu_link($url, $endpoint, $value, $permalink) {
        if ($endpoint === 'point-log') {
            return home_url('/point-log/');
        }

        return $url;
    }

    public function display_point_log_page() {
        try {
            // Log start of page rendering to browser console
            echo '<script>console.log("Point Log page rendering started");</script>';
            
            // Check if user is logged in
            if (!is_user_logged_in()) {
                echo '<script>console.log("User not logged in, redirecting to login");</script>';
                wp_safe_redirect(wp_login_url(home_url('/point-log/')));
                exit;
            }

            echo '<script>console.log("User is logged in, fetching data");</script>';
            
            $data = $this->display_points_dashboard();
            if (!is_array($data)) {
                echo '<script>console.error("display_points_dashboard() returned non-array:", ' . json_encode($data) . ');</script>';
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
            
            echo '<script>console.log("Data fetched:", ' . json_encode($data) . ');</script>';
            
            $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
            echo '<script>console.log("Conversion rate:", ' . json_encode($conversion_rate) . ');</script>';

            // Start output buffering to avoid header conflicts
            ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo get_bloginfo('name'); ?> - Pointlog</title>
            <link rel="stylesheet" href="<?php echo PR_PLUGIN_URL; ?>assets/css/frontend-style.css">
        </head>
        <body>
        <div class="pr-point-log-wrapper">
            <div class="pr-point-log-container">
                <?php
                if (isset($data['access_revoked']) && $data['access_revoked']) {
                    ?>
                    <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                        <h2 style="color: #dc3545; margin-bottom: 20px;">🚫 Access Revoked</h2>
                        <p style="font-size: 16px; color: #666; margin-bottom: 20px;">
                            Your rewards access has been revoked by an administrator.
                        </p>
                        <p style="font-size: 14px; color: #999;">
                            If you believe this is an error, please contact support.
                        </p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="pr-points-dashboard">
                        <h2>⭐ Dine Pointsystem Stats</h2>

                        <div class="pr-points-grid">
                            <div class="pr-points-card">
                                <div class="pr-card-header">Tilgængelige Points</div>
                                <div class="pr-card-value"><?php echo esc_html($data['available_points']); ?></div>
                                <div class="pr-card-label">Point Du Kan Bruge</div>
                            </div>

                            <div class="pr-points-card">
                                <div class="pr-card-header">Samlede Points</div>
                                <div class="pr-card-value"><?php echo esc_html($data['total_points']); ?></div>
                                <div class="pr-card-label">Fortjent I Alt</div>
                            </div>

                            <div class="pr-points-card">
                                <div class="pr-card-header">Brugte Points</div>
                                <div class="pr-card-value"><?php echo esc_html($data['redeemed_points']); ?></div>
                                <div class="pr-card-label">Point Brugt Til Køb</div>
                            </div>

                            <div class="pr-points-card">
                                <div class="pr-card-header">Samlet Forbrug</div>
                                <div class="pr-card-value"><?php echo wc_price($data['total_spent']); ?></div>
                                <div class="pr-card-label">Brugt I Butikken</div>
                            </div>
                        </div>

                        <div class="pr-points-breakdown">
                            <h3>Point Sammenbrud</h3>
                            <ul>
                                <?php if (isset($data['points_manually_set']) && $data['points_manually_set'] === 1) { ?>
                                    <li>
                                        <strong>⚙️ Administrator har manuelt tildelt point:</strong>
                                        <span style="color: #2271b1; font-weight: 600;"><?php echo esc_html($data['manually_set_points']); ?> point</span>
                                    </li>
                                    <li style="opacity: 0.6;">
                                        <strong>Registrerings Bonus:</strong>
                                        <span>—</span>
                                        <small style="display: block; margin-top: 3px; font-style: italic;">(Ikke inkluderet, da point blev manuelt sat)</small>
                                    </li>
                                    <li style="opacity: 0.6;">
                                        <strong>Points Fra Køb:</strong>
                                        <span>—</span>
                                        <small style="display: block; margin-top: 3px; font-style: italic;">(Ikke inkluderet, da point blev manuelt sat)</small>
                                    </li>
                                <?php } else { ?>
                                    <li>
                                        <strong>Registrerings Bonus:</strong>
                                        <span><?php echo esc_html($data['registration_bonus']); ?> point</span>
                                    </li>
                                    <li>
                                        <strong>Points Fra Køb:</strong>
                                        <span><?php echo esc_html($data['purchase_points']); ?> point</span>
                                        <small>(<?php echo wc_price($data['total_spent']); ?> ÷ <?php echo esc_html($conversion_rate); ?>)</small>
                                    </li>
                                <?php } ?>
                                <li>
                                    <strong>Point Brugt:</strong>
                                    <span style="color: #dc3545;">-<?php echo esc_html($data['redeemed_points']); ?> point</span>
                                </li>
                                <li style="border-top: 2px solid #2271b1; padding-top: 10px; margin-top: 10px;">
                                    <strong>Tilgængelige Points:</strong>
                                    <span style="color: #2271b1; font-size: 1.2em;"><strong><?php echo esc_html($data['available_points']); ?> point</strong></span>
                                </li>
                            </ul>
                        </div>

                        <p class="pr-points-note">
                            💡 Du kan bruge dine points til at købe bestemte produkter i vores butik.
                            <a href="<?php echo esc_url(home_url('/vare-kategori/gave-produkt/')); ?>">Se produkter</a>
                        </p>
                    </div>

                    <div class="pr-points-history">
                        <h2>📊 Point Historie</h2>

                        <div class="pr-history-info">
                            <p>Her kan du se dine point transaktioner og hvordan dine points er blevet akkumuleret over tid.</p>
                        </div>

                        <h3>Hvordan Du Tjener Points:</h3>
                        <ul class="pr-history-list">
                            <li>
                                <strong>✅ Registrerings Bonus:</strong>
                                Automatisk givet når du opretter en konto
                            </li>
                            <li>
                                <strong>💰 Fra Køb:</strong>
                                Du tjener points hver gang du handler. Mængden afhænger af dit køb beløb.
                            </li>
                        </ul>

                        <h3>Hvordan Du Bruger Points:</h3>
                        <ul class="pr-history-list">
                            <li>
                                <strong>🛒 Produktkøb:</strong>
                                Under checkout kan du vælge at betale med points i stedet for penge
                            </li>
                            <li>
                                <strong>💳 Point Værdi:</strong>
                                Se dit kontrol panel ovenfor for at se hvor mange points du kan bruge
                            </li>
                        </ul>

                        <p class="pr-points-contact">
                            📧 Har du spørgsmål? <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>">Kontakt os</a>
                        </p>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        </body>
        </html>
        <?php
            // Flush the buffer and end
            $output = ob_get_clean();
            echo $output;
        } catch (Exception $e) {
            // Log the error to both console and WordPress log
            $error_msg = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            error_log('Point Log Page Error: ' . $error_msg);
            
            // Display error in console AND on page
            echo '<script>console.error("Point Log Page Exception:", ' . json_encode($error_msg) . ');</script>';
            
            // Display a simple error page
            ob_end_clean();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Error - Pointlog</title>
            </head>
            <body style="font-family: Arial, sans-serif; padding: 40px; text-align: center;">
                <h1 style="color: #dc3545;">Der opstod en fejl</h1>
                <p>Kunne ikke indlæse point log siden. Prøv venligst igen senere.</p>
                <p style="color: #666; font-size: 12px; margin-top: 20px;">
                    <strong>Debug Info:</strong><br>
                    <?php echo esc_html($error_msg); ?>
                </p>
                <p><a href="<?php echo esc_url(home_url('/my-account/')); ?>">Tilbage til Min Konto</a></p>
                <script>
                    console.error("Point Log Error Details:", <?php echo json_encode($error_msg); ?>);
                </script>
            </body>
            </html>
            <?php
        }
    } // This closes the class method 'display_point_log_page()'
} // This closes the PR_Frontend_Display class