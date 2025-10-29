<?php
if (!defined('ABSPATH')) exit;

class PR_Guest_Recovery {
    public function __construct() {
        // When a user registers, check if they have pre-March 11 guest orders
        add_action('user_register', array($this, 'award_guest_spending_points_on_signup'));
        
        // Handle admin actions
        add_action('admin_init', array($this, 'handle_guest_recovery_actions'));
    }

    public function get_guest_orders_before_date($date = '2025-03-11') {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT DISTINCT
                pm_email.meta_value as email,
                COUNT(p.ID) as order_count,
                SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent,
                GROUP_CONCAT(DISTINCT DATE(p.post_date)) as order_dates
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id 
                AND pm_customer.meta_key = '_customer_user' 
                AND pm_customer.meta_value = '0'
            INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_billing_email'
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id 
                AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
                AND p.post_status = 'wc-completed'
                AND p.post_date >= %s
                AND pm_email.meta_value NOT IN (
                    SELECT user_email FROM {$wpdb->users}
                )
            GROUP BY pm_email.meta_value
            ORDER BY total_spent DESC
        ", $date);

        return $wpdb->get_results($query);
    }

    public function get_guest_spending_for_email($email) {
        global $wpdb;

        $total_spent = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id 
                AND pm_customer.meta_key = '_customer_user' 
                AND pm_customer.meta_value = '0'
            INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_billing_email' 
                AND pm_email.meta_value = %s
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id 
                AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
                AND p.post_status = 'wc-completed'
                AND p.post_date >= '2025-03-11'
        ", $email));

        return floatval($total_spent ?? 0);
    }

    public function award_guest_spending_points_on_signup($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) return;

        $email = $user->user_email;
        
        // Check if admin set an override points value for this email
        $override_points = get_option('pr_guest_override_points_' . sanitize_key($email));
        
        if ($override_points !== false) {
            // Use the admin-set override points
            $points_to_award = intval($override_points);
            delete_option('pr_guest_override_points_' . sanitize_key($email));
            error_log("Points Rewards: Using admin override points ($points_to_award) for user signup: $email");
        } else {
            // Calculate from guest spending
            $total_spent = $this->get_guest_spending_for_email($email);

            if ($total_spent > 0) {
                // Calculate points from pre-March 11 guest spending
                $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
                $guest_spending_points = intval(floor($total_spent / $conversion_rate));
                $registration_bonus = intval(get_option('pr_registration_points', 0));
                $points_to_award = $guest_spending_points + $registration_bonus;
            } else {
                // No guest spending found, just add registration bonus
                $registration_bonus = intval(get_option('pr_registration_points', 0));
                $points_to_award = $registration_bonus;
            }
        }

        // Award the points
        if ($points_to_award > 0) {
            $points_manager = new PR_Points_Manager();
            $points_manager->add_points($user_id, $points_to_award);

            // Log this action
            update_user_meta($user_id, 'pr_guest_spending_points_awarded', $points_to_award);
        }
    }

    public function send_invitation_email($email, $guest_total_spent = null) {
        // Check if we've already sent an invite to this email recently (within last hour)
        $last_invite_timestamp = get_option('pr_guest_invited_' . sanitize_email($email));
        if ($last_invite_timestamp && (time() - $last_invite_timestamp) < 3600) {
            error_log("Points Rewards: Skipped duplicate invite to $email - sent less than 1 hour ago");
            return false;
        }

        if ($guest_total_spent === null) {
            $guest_total_spent = $this->get_guest_spending_for_email($email);
        }

        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        $guest_spending_points = intval(floor($guest_total_spent / $conversion_rate));
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $total_points = $guest_spending_points + $registration_bonus;

        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $register_url = home_url('/my-account/#register');
        $logo_url = get_option('woocommerce_placeholder_image', '');

        $subject = "Deltag i vores belønningsprogram og få dine point! - $site_name";

        // Build HTML email
        $html = '<!DOCTYPE html>
<html lang="da-DK" style="height: 100%; position: relative;">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <title>' . esc_html($site_name) . '</title>
    </head>
    <body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" style="height: 100%; position: relative; background-color: #fff; margin: 0; padding: 0;">
        <div id="wrapper" dir="ltr" style="background-color: #fff; margin: 0; padding: 70px 0 70px 0; width: 100%; -webkit-text-size-adjust: none;">
            <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
                <tr>
                    <td align="center" valign="top">
                        <table border="0" cellpadding="0" cellspacing="0" width="634" id="template_container" style="background-color: #fff; border-radius: 0px; box-shadow: 0 0px 0px 0px rgba(0,0,0,.1);">
                            <tr>
                                <td align="center" valign="top">
                                    <!-- Header with Dark Background -->
                                    <table id="template_header_image_container" style="width: 100%; background-color: #0f2846;" width="100%">
                                        <tr>
                                            <td align="center" valign="middle" style="text-align: center; padding-top: 20px; padding-bottom: 20px;">
                                                <h2 style="margin: 0; color: #fff; font-family: Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 600;">⭐ ' . esc_html($site_name) . '</h2>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Main Content -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
                                        <tr>
                                            <td valign="top" style="background-color: #fff; padding-top: 0px; padding-bottom: 25px;">
                                                <table border="0" cellpadding="20" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td valign="top" style="padding: 48px 48px;">
                                                            <div style="color: #7a7a7a; text-align: left; font-size: 16px; line-height: 26px; font-family: Helvetica, Arial, sans-serif; font-weight: 400;">
                                                                <h1 style="margin: 0 0 20px 0; color: #000844; font-size: 27px; line-height: 1.4;">Hej!</h1>

                                                                <p style="margin: 0 0 16px 0;">Vi har set, at du har foretaget køb som gæst på ' . esc_html($site_name) . '.</p>

                                                                <p style="margin: 0 0 16px 0;">Vi er begejstret for at invitere dig til at deltage i vores belønningsprogram!</p>

                                                                <p style="margin: 0 0 24px 0;"><strong>Når du opretter en konto og tilmelder dig, modtager du:</strong></p>

                                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-radius: 8px; padding: 20px;">
                                                                    <tr>
                                                                        <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                                                                            <span style="font-size: 18px; color: #000844; font-weight: 600;">✓ ' . esc_html($registration_bonus) . ' velkomstbonus point</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                                                                            <span style="font-size: 18px; color: #000844; font-weight: 600;">✓ ' . esc_html($guest_spending_points) . ' point fra dine tidligere køb</span>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding: 12px 0;">
                                                                            <span style="font-size: 20px; color: #0f2846; font-weight: 700;">✓ I alt: ' . esc_html($total_points) . ' point som du kan bruge med det samme!</span>
                                                                        </td>
                                                                    </tr>
                                                                </table>

                                                                <p style="margin: 24px 0 16px 0; padding: 16px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;"><strong>⚠️ Vigtig bemærkning:</strong> Du skal tilmelde dig med <strong>denne e-mailadresse</strong> for at modtage dine point fra tidligere køb. Hvis du bruger en anden e-mailadresse, vil systemet ikke kunne genkende dine historiske køb.</p>

                                                                <p style="margin: 24px 0 32px 0; text-align: center;">
                                                                    <a href="' . esc_url($register_url) . '" style="display: inline-block; background-color: #0f2846; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 16px;">Tilmeld dig nu og få dine point</a>
                                                                </p>

                                                                <p style="margin: 0 0 16px 0;">Tak for at handle hos os.</p>

                                                                <p style="margin: 0 0 0 0;">
                                                                    Venlig hilsen,<br>
                                                                    <strong>' . esc_html($site_name) . '</strong>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($email, $subject, $html, $headers);

        if ($sent) {
            update_option('pr_guest_invited_' . sanitize_email($email), time());
        } else {
            // Log failed email attempt for debugging
            error_log("Points Rewards: Failed to send guest recovery email to $email");
        }

        return $sent;
    }

    public function send_test_email($email) {
        // Create sample data for test email
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $sample_guest_spending = 100; // Sample spending amount
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));
        $sample_guest_points = intval(floor($sample_guest_spending / $conversion_rate));
        
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $register_url = home_url('/my-account/#register');
        
        $subject = "TESTEMIL - " . esc_html($site_name) . " - Deltag i vores belønningsprogram og få dine point!";

        // Build HTML email with sample data
        $html = '<!DOCTYPE html>
<html lang="da-DK" style="height: 100%; position: relative;">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <title>' . esc_html($site_name) . '</title>
    </head>
    <body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" style="height: 100%; position: relative; background-color: #fff; margin: 0; padding: 0;">
        <div id="wrapper" dir="ltr" style="background-color: #fff; margin: 0; padding: 70px 0 70px 0; width: 100%; -webkit-text-size-adjust: none;">
            <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
                <tr>
                    <td align="center" valign="top">
                        <table border="0" cellpadding="0" cellspacing="0" width="634" id="template_container" style="background-color: #fff; border-radius: 0px; box-shadow: 0 0px 0px 0px rgba(0,0,0,.1);">
                            <tr>
                                <td align="center" valign="top">
                                    <!-- Header with Dark Background -->
                                    <table id="template_header_image_container" style="width: 100%; background-color: #0f2846;" width="100%">
                                        <tr>
                                            <td align="center" valign="middle" style="text-align: center; padding-top: 20px; padding-bottom: 20px;">
                                                <h2 style="margin: 0; color: #fff; font-family: Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 600;">⭐ ' . esc_html($site_name) . '</h2>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Main Content -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
                                        <tr>
                                            <td valign="top" style="background-color: #fff; padding-top: 0px; padding-bottom: 25px;">
                                                <table border="0" cellpadding="20" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td valign="top">
                                                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333; font-family: Helvetica, Arial, sans-serif; line-height: 1.6;">Hej!</p>

                                                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333; font-family: Helvetica, Arial, sans-serif; line-height: 1.6;">Vi har opdaget at du har foretaget køb på vores butik som gæst. Vi vil gerne tilbyde dig at blive medlem og få adgang til vores eksklusive belønningsprogram!</p>

                                                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333; font-family: Helvetica, Arial, sans-serif; line-height: 1.6;"><strong>Som medlem får du:</strong></p>
                                                            <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 16px; color: #333; font-family: Helvetica, Arial, sans-serif;">
                                                                <li style="margin-bottom: 8px;">En velkomst bonus på 10 point</li>
                                                                <li style="margin-bottom: 8px;">Point på hvert køb</li>
                                                                <li style="margin-bottom: 8px;">Mulighed for at prøve nye produkter – betalt med dine point.</li>
                                                            </ul>

                                                            <p style="margin: 0 0 20px 0; font-size: 16px; color: #333; font-family: Helvetica, Arial, sans-serif; line-height: 1.6;"><strong>Og det bedste er - du starter allerede med point fra dine tidligere køb!</strong></p>

                                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-radius: 8px; padding: 20px;">
                                                                <tr>
                                                                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span style="font-size: 18px; color: #000844; font-weight: 600;">✓ ' . esc_html($registration_bonus) . ' velkomstbonus point</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                                                                        <span style="font-size: 18px; color: #000844; font-weight: 600;">✓ ' . esc_html($sample_guest_points) . ' point fra dine tidligere køb</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="padding: 12px 0;">
                                                                        <span style="font-size: 20px; color: #0f2846; font-weight: 700;">✓ I alt: ' . esc_html($registration_bonus + $sample_guest_points) . ' point som du kan bruge med det samme!</span>
                                                                    </td>
                                                                </tr>
                                                            </table>

                                                            <p style="margin: 24px 0 16px 0; padding: 16px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;"><strong>⚠️ Vigtig bemærkning:</strong> Du skal tilmelde dig med <strong>denne e-mailadresse</strong> for at modtage dine point fra tidligere køb. Hvis du bruger en anden e-mailadresse, vil systemet ikke kunne genkende dine historiske køb.</p>

                                                            <p style="margin: 24px 0 32px 0; text-align: center;">
                                                                <a href="' . esc_url($register_url) . '" style="display: inline-block; background-color: #0f2846; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 16px;">Tilmeld dig nu og få dine point</a>
                                                            </p>

                                                            <p style="margin: 0 0 16px 0;">Tak for at handle hos os.</p>

                                                            <p style="margin: 0 0 0 0;">
                                                                Venlig hilsen,<br>
                                                                <strong>' . esc_html($site_name) . '</strong>
                                                            </p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($email, $subject, $html, $headers);
        
        if (!$sent) {
            // Log failed email attempt for debugging
            error_log("Points Rewards: Failed to send test email to $email");
        }
        
        return $sent;
    }

    public function handle_guest_recovery_actions() {
        if (!isset($_POST['pr_guest_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pr_guest_recovery_nonce')) {
            wp_die('Security check failed.');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        $action = sanitize_text_field($_POST['pr_guest_action']);

        if ($action === 'set_guest_points') {
            $guest_email = sanitize_email($_POST['guest_email'] ?? '');
            $new_points = intval($_POST['points_value'] ?? 0);

            if (!is_email($guest_email)) {
                set_transient('pr_guest_notice_error', 'Invalid email address.', 30);
            } elseif ($new_points < 0) {
                set_transient('pr_guest_notice_error', 'Points cannot be negative.', 30);
            } else {
                set_transient('pr_guest_notice_success', sprintf('Points for %s will be set to %d when they sign up with this email.', esc_html($guest_email), $new_points), 30);
                update_option('pr_guest_override_points_' . sanitize_key($guest_email), $new_points);
                error_log("Points Rewards: Admin set override points for guest email $guest_email to $new_points");
            }

            wp_safe_redirect(admin_url('admin.php?page=ahmeds-pointsystem-guest-recovery'));
            exit;
        }

        if ($action === 'send_test_email') {
            $test_email = sanitize_email($_POST['test_email'] ?? '');

            if (!is_email($test_email)) {
                set_transient('pr_guest_notice_error', 'Invalid email address.', 30);
            } else {
                if ($this->send_test_email($test_email)) {
                    set_transient('pr_guest_notice_success', "Test email sent to $test_email", 30);
                } else {
                    set_transient('pr_guest_notice_error', "Failed to send test email to $test_email", 30);
                }
            }

            wp_safe_redirect(admin_url('admin.php?page=ahmeds-pointsystem-guest-recovery'));
            exit;
        }

        if ($action === 'send_invite') {
            $email = sanitize_email($_POST['guest_email'] ?? '');

            if (!is_email($email)) {
                wp_die('Invalid email address.');
            }

            if ($this->send_invitation_email($email)) {
                set_transient('pr_guest_notice_success', "Invitation email sent to $email", 30);
            } else {
                set_transient('pr_guest_notice_error', "Failed to send email to $email", 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=ahmeds-pointsystem-guest-recovery'));
            exit;
        }
    }

    public static function display_guest_recovery_page() {
        $instance = new self();
        $instance->guest_recovery_page();
    }

    public function guest_recovery_page() {
        // Display notices
        $success_message = get_transient('pr_guest_notice_success');
        $error_message = get_transient('pr_guest_notice_error');

        if ($success_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
            delete_transient('pr_guest_notice_success');
        }

        if ($error_message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            delete_transient('pr_guest_notice_error');
        }

        $guests = $this->get_guest_orders_before_date();
        $registration_bonus = intval(get_option('pr_registration_points', 0));
        $conversion_rate = max(0.01, floatval(get_option('pr_conversion_rate', 1)));

        ?>
        <div class="wrap pr-guest-recovery-wrap">
            <h1>Guest Recovery - Convert Guests to Members</h1>
            <p>These are customers who made purchases as guests since March 11, 2025. Send them invitation emails to sign up and claim their points!</p>

            <!-- Test Email Section -->
            <div class="pr-card">
                <h2>Test Email Preview</h2>
                <p>Send a test email to yourself to preview the guest invitation before deployment:</p>
                <form method="post" action="" style="display: flex; gap: 10px; align-items: flex-end;">
                    <?php wp_nonce_field('pr_guest_recovery_nonce'); ?>
                    <input type="hidden" name="pr_guest_action" value="send_test_email" />
                    <div style="flex: 1;">
                        <label for="test_email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email Address:</label>
                        <input type="email" id="test_email" name="test_email" 
                               value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" 
                               class="regular-text" required />
                    </div>
                    <button type="submit" class="button button-primary">Send Test Email</button>
                </form>
            </div>

            <?php if (!empty($guests)) : ?>
                <div class="pr-card">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Potential Points</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guests as $guest) :
                                $guest_points = intval(floor($guest->total_spent / $conversion_rate));
                                $total_points = $guest_points + $registration_bonus;
                                $already_invited = get_option('pr_guest_invited_' . sanitize_email($guest->email));
                            ?>
                                <tr>
                                    <td><?php echo esc_html($guest->email); ?></td>
                                    <td><?php echo esc_html($guest->order_count); ?></td>
                                    <td><?php echo wc_price($guest->total_spent); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($total_points); ?> points</strong><br>
                                        <small>(<?php echo esc_html($guest_points); ?> from spending + <?php echo esc_html($registration_bonus); ?> welcome bonus)</small>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <form method="post" action="" style="display:inline;">
                                                <?php wp_nonce_field('pr_guest_recovery_nonce'); ?>
                                                <input type="hidden" name="pr_guest_action" value="send_invite" />
                                                <input type="hidden" name="guest_email" value="<?php echo esc_attr($guest->email); ?>" />
                                                <button type="submit" class="button button-primary button-small"
                                                        onclick="return confirm('Send invitation to <?php echo esc_attr($guest->email); ?>?');">
                                                    <?php echo $already_invited ? 'Resend' : 'Send Invite'; ?>
                                                </button>
                                            </form>

                                            <button type="button" class="button button-small pr-guest-set-points-btn" 
                                                    data-guest-email="<?php echo esc_attr($guest->email); ?>"
                                                    data-guest-points="<?php echo esc_attr($total_points); ?>"
                                                    data-guest-breakdown="<?php echo esc_attr($guest_points . ' + ' . $registration_bonus); ?>">
                                                ⚙️ Points
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="notice notice-info">
                    <p>No guest orders found before March 11, 2025.</p>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .pr-guest-recovery-wrap .pr-card {
                background: white;
                border: 1px solid #ccc;
                border-radius: 5px;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
        </style>

        <!-- Modal for Setting Guest Points -->
        <div id="pr-guest-set-points-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:white; padding:30px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.3); width:90%; max-width:400px;">
                <h2 style="margin-top:0;">Adjust Points for <span id="pr-guest-modal-email"></span></h2>
                <p>Current Points: <strong id="pr-guest-modal-current-points"></strong></p>
                <p style="font-size:13px; color:#666;">Breakdown: <span id="pr-guest-modal-breakdown"></span></p>
                
                <form id="pr-guest-set-points-form" method="post" action="">
                    <?php wp_nonce_field('pr_guest_recovery_nonce'); ?>
                    <input type="hidden" name="pr_guest_action" value="set_guest_points" />
                    <input type="hidden" name="guest_email" id="pr-guest-modal-email-input" value="" />
                    
                    <p>
                        <label for="pr-guest-modal-points" style="display:block; margin-bottom:8px; font-weight:600;">New Points Value:</label>
                        <input type="number" id="pr-guest-modal-points" name="points_value" min="0" value="" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;" required />
                    </p>
                    
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="button button-primary">Update Points</button>
                        <button type="button" class="button" onclick="document.getElementById('pr-guest-set-points-modal').style.display='none';">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.querySelectorAll('.pr-guest-set-points-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('pr-guest-modal-email-input').value = this.dataset.guestEmail;
                    document.getElementById('pr-guest-modal-email').textContent = this.dataset.guestEmail;
                    document.getElementById('pr-guest-modal-current-points').textContent = this.dataset.guestPoints;
                    document.getElementById('pr-guest-modal-breakdown').textContent = this.dataset.guestBreakdown;
                    document.getElementById('pr-guest-modal-points').value = this.dataset.guestPoints;
                    document.getElementById('pr-guest-set-points-modal').style.display = 'flex';
                    document.getElementById('pr-guest-modal-points').focus();
                    document.getElementById('pr-guest-modal-points').select();
                });
            });

            // Close modal on outside click
            document.getElementById('pr-guest-set-points-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        </script>

        <style>
            .pr-guest-set-points-btn {
                font-size: 12px !important;
                padding: 4px 8px !important;
            }
        </style>
        <?php
    }
}

new PR_Guest_Recovery();
?>
