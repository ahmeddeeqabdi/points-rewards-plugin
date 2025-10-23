<?php
if (!defined('ABSPATH')) exit;

class PR_Frontend_Display {
    public function __construct() {
        // Add points section to WooCommerce My Account page
        add_action('woocommerce_account_dashboard', array($this, 'display_points_dashboard'), 20);
        
        // Add custom endpoint for points history
        add_action('init', array($this, 'add_account_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_points_menu_item'));
        add_action('woocommerce_account_points-history_endpoint', array($this, 'display_points_history'));
    }

    public function display_points_dashboard() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $user_points = PR_Points_Manager::get_user_points($user_id);
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));

        // Get total spent
        global $wpdb;
        $total_spent_result = $wpdb->get_row($wpdb->prepare("
            SELECT SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user' AND pm_customer.meta_value = %d
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
        ", $user_id));

        $total_spent = floatval($total_spent_result->total_spent ?? 0);
        $purchase_points = intval(floor($total_spent / $conversion_rate));
        $total_points = $purchase_points + $registration_bonus;
        $available_points = $total_points - intval($user_points->redeemed_points);

        ?>
        <div class="pr-points-dashboard">
            <h2>⭐ Dine Pointsystem Stats</h2>
            
            <div class="pr-points-grid">
                <div class="pr-points-card">
                    <div class="pr-card-header">Tilgængelige Points</div>
                    <div class="pr-card-value"><?php echo esc_html($available_points); ?></div>
                    <div class="pr-card-label">Point Du Kan Bruge</div>
                </div>

                <div class="pr-points-card">
                    <div class="pr-card-header">Samlede Points</div>
                    <div class="pr-card-value"><?php echo esc_html($total_points); ?></div>
                    <div class="pr-card-label">Fortjent I Alt</div>
                </div>

                <div class="pr-points-card">
                    <div class="pr-card-header">Brugte Points</div>
                    <div class="pr-card-value"><?php echo esc_html(intval($user_points->redeemed_points)); ?></div>
                    <div class="pr-card-label">Point Brugt Til Køb</div>
                </div>

                <div class="pr-points-card">
                    <div class="pr-card-header">Samlet Forbrug</div>
                    <div class="pr-card-value"><?php echo wc_price($total_spent); ?></div>
                    <div class="pr-card-label">Brugt I Butikken</div>
                </div>
            </div>

            <div class="pr-points-breakdown">
                <h3>Point Sammenbrud</h3>
                <ul>
                    <li>
                        <strong>Registrerings Bonus:</strong> 
                        <span><?php echo esc_html($registration_bonus); ?> point</span>
                    </li>
                    <li>
                        <strong>Points Fra Køb:</strong> 
                        <span><?php echo esc_html($purchase_points); ?> point</span>
                        <small>(<?php echo esc_html($total_spent); ?> ÷ <?php echo esc_html($conversion_rate); ?>)</small>
                    </li>
                    <li>
                        <strong>Point Brugt:</strong> 
                        <span style="color: #dc3545;">-<?php echo esc_html(intval($user_points->redeemed_points)); ?> point</span>
                    </li>
                    <li style="border-top: 2px solid #2271b1; padding-top: 10px; margin-top: 10px;">
                        <strong>Tilgængelige Points:</strong> 
                        <span style="color: #2271b1; font-size: 1.2em;"><strong><?php echo esc_html($available_points); ?> point</strong></span>
                    </li>
                </ul>
            </div>

            <p class="pr-points-note">
                💡 Du kan bruge dine points til at købe produkter i vores butik. 
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Besøg butikken</a>
            </p>
        </div>
        <?php
    }

    public function add_account_endpoint() {
        add_rewrite_endpoint('points-history', EP_ROOT | EP_PAGES);
    }

    public function add_points_menu_item($items) {
        $items['points-history'] = __('Points History', 'ahmeds-pointsystem');
        return $items;
    }

    public function display_points_history() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        ?>
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
}
