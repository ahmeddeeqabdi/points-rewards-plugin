# Ahmed's Pointsystem

A comprehensive WordPress plugin that adds a points and rewards system to WooCommerce stores, allowing customers to earn and spend points on purchases.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Features Guide](#features-guide)
  - [Points Earning System](#points-earning-system)
  - [Points Spending System](#points-spending-system)
  - [Guest Recovery](#guest-recovery)
  - [Product Points Management](#product-points-management)
  - [User Management](#user-management)
- [Frontend Features](#frontend-features)
- [Admin Features](#admin-features)
- [WooCommerce Blocks Support](#woocommerce-blocks-support)
- [Shortcodes](#shortcodes)
- [Developer Hooks](#developer-hooks)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)

## 🎯 Overview

Ahmed's Pointsystem is a full-featured points and rewards plugin designed specifically for WooCommerce. It enables store owners to create engaging loyalty programs where customers earn points through purchases and registrations, then redeem those points for discounts on future orders.

**Version:** 1.0.0  
**Author:** Ahmed  
**License:** GPL v2 or later  
**Text Domain:** ahmeds-pointsystem

## ✨ Features

### Core Features

- 💰 **Points Earning** - Award points based on purchase amount with configurable conversion rates
- 🎁 **Registration Bonus** - Give new users points when they register
- 🛒 **Points-Based Checkout** - Allow customers to pay entirely with points or combine points with money
- 👻 **Guest Recovery** - Recover guest purchases and award points retroactively
- 🏷️ **Product Points Pricing** - Set custom point costs for individual products
- 📊 **Points Dashboard** - Dedicated My Account page showing points history and balance
- 🎨 **WooCommerce Blocks** - Full support for modern WooCommerce block-based checkout
- 🔄 **Points Conversion** - Flexible points-to-currency conversion system
- 📱 **Responsive Design** - Mobile-friendly interface throughout

### Advanced Features

- 📦 **Category Restrictions** - Limit points earning/spending to specific product categories
- 🎯 **Points-Only Products** - Mark products as purchasable only with points
- 💳 **Variation Support** - Full support for variable products with point pricing
- 📈 **Transaction History** - Complete audit trail of all points activities
- 👥 **Bulk User Management** - Admin tools to add/remove points for multiple users
- 🔔 **Real-Time Updates** - Live points balance updates during checkout
- 🎨 **Custom Styling** - Dedicated CSS for admin and frontend with namespace isolation

## 📦 Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **WooCommerce:** 5.0 or higher (tested up to 9.0)

## 🚀 Installation

### Automatic Installation

1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Upload the `points-rewards-plugin` ZIP file
4. Click **Install Now**
5. After installation, click **Activate**

### Manual Installation

1. Download the plugin ZIP file
2. Extract the contents to `wp-content/plugins/points-rewards-plugin/`
3. Log in to WordPress admin
4. Navigate to **Plugins**
5. Find "Ahmed's Pointsystem" and click **Activate**

### Post-Installation

1. Navigate to **Ahmed's Pointsystem > Settings** in the WordPress admin menu
2. Configure your points conversion rate and other options
3. Set up registration bonus points if desired
4. Configure category restrictions if needed
5. Save your settings

## ⚙️ Configuration

### Basic Settings

Navigate to **Ahmed's Pointsystem > Settings** to configure:

#### General Settings

- **Conversion Rate**: Define how many DKK (or your currency) equals 1 point
  - Example: If set to 10, customers earn 1 point for every 10 DKK spent
- **Registration Points**: Number of points awarded when a new user registers
- **Enable Purchase with Points**: Toggle to allow customers to buy products using points

#### Category Restrictions

- **Restrict to Categories**: Limit points earning/spending to specific product categories
- **Allowed Categories**: Select which categories are eligible for points
- **Points-Only Categories**: Mark categories where products can ONLY be purchased with points

### Product-Level Settings

For each product, you can set:

- **Custom Points Cost**: Override the default conversion rate with a specific points price
- **Enable Points Payment**: Allow/disallow points payment for this product
- **Points-Only Purchase**: Require points payment (no monetary option)

Access these settings in the **Product Data** metabox when editing a product.

## 📖 Features Guide

### Points Earning System

#### How Points Are Earned

1. **Product Purchases**: Automatically calculated based on conversion rate
   - Example: 100 DKK order with 10 DKK/point rate = 10 points earned
2. **User Registration**: One-time bonus when account is created
3. **Admin Adjustments**: Manually added by store administrators

#### Earning Rules

- Points are awarded after order completion
- Only completed/processing orders qualify for points
- Cancelled/refunded orders deduct previously awarded points
- Guest purchases can be recovered and awarded retroactively

### Points Spending System

#### Checkout Integration

The plugin adds a points section to the checkout page showing:

- Current available points balance
- Equivalent currency value of points
- Option to use points for payment
- Real-time price updates as points are applied

#### Payment Methods

1. **Full Points Payment**: Use points to cover the entire order total
2. **Partial Points Payment**: Combine points with standard payment methods
3. **Standard Payment**: Pay with money and earn points

#### WooCommerce Blocks Checkout

For stores using the modern block-based checkout:

- Points information appears as a notice at the top of checkout
- Shows available points and their currency value
- Updates dynamically as cart changes
- Fully integrated with WooCommerce Blocks API

### Guest Recovery

Convert guest purchases into points retroactively.

#### How It Works

1. Navigate to **Ahmed's Pointsystem > Guest Recovery**
2. View list of guest orders (orders without a user account)
3. For each order, you can:
   - See order details (amount, date, status)
   - Assign to existing user by entering their email
   - Award calculated points based on order total
4. Points are automatically added to the user's balance

#### Use Cases

- Customer made purchases before creating an account
- Importing historical orders from another system
- Correcting orders that were mistakenly placed as guest

### Product Points Management

#### Setting Points Costs

1. Edit any product in WooCommerce
2. Scroll to the **Product Data** metabox
3. Find the **Points & Rewards** section
4. Configure:
   - **Points Cost**: Specific points price (overrides conversion rate)
   - **Enable Purchase**: Allow points payment for this product
   - **Points Only**: Require points (no money option)

#### Variable Products

For variable products:

- Points costs can be set per variation
- Each variation can have unique points pricing
- Frontend displays correct points for selected variation
- AJAX updates ensure accurate pricing display

#### Product Display

On product pages, customers see:

- Regular price
- Points equivalent (if points payment enabled)
- "Pay with Points" option
- Real-time variation updates

### User Management

#### Users Points Page

Navigate to **Ahmed's Pointsystem > Users Points** to:

- View all registered users with their points balances
- Search and filter users
- Bulk add/remove points for multiple users
- View individual user transaction history
- Export points data

#### Individual User Actions

For each user, you can:

1. **Add Points**: Give bonus points with a custom reason
2. **Remove Points**: Deduct points (e.g., for abuse or corrections)
3. **View History**: See complete transaction log
4. **Reset Balance**: Clear all points (requires confirmation)

## 🎨 Frontend Features

### My Account Dashboard

Customers can access their points dashboard at:

```
/my-account/point-log/
```

The dashboard displays:

- **Current Points Balance**: Prominently shown with currency equivalent
- **Transaction History**: Paginated list of all points activities
  - Transaction type (earned, spent, adjusted)
  - Amount (+ or -)
  - Date and time
  - Associated order number (if applicable)
  - Reason/description
- **Points Summary**: Overview of lifetime earned and spent

### Checkout Experience

#### Traditional Checkout

- Points section appears below payment methods
- Shows available balance
- Toggle to use points
- Live calculation of remaining amount
- Clear indication when points cover full order

#### Block-Based Checkout

- Points notice at top of checkout
- Seamlessly integrated with WooCommerce Blocks
- Real-time updates via REST API
- Consistent with WordPress block editor experience

### Product Pages

- Point costs displayed alongside prices
- "Pay with Points" badge for eligible products
- Variation selector updates points pricing
- Clear indication of points-only products

## 🛠️ Admin Features

### Settings Page

**Ahmed's Pointsystem > Settings**

Modern, user-friendly interface with:

- Tabbed sections for organization
- Toggle switches for boolean options
- Multi-select for categories
- Inline help text
- Save notifications
- Settings validation

### Users Management

**Ahmed's Pointsystem > Users Points**

Comprehensive user management:

- Sortable columns (name, email, points, join date)
- Bulk actions for multiple users
- Quick add/subtract points
- User search and filtering
- Points history modal
- Export functionality

### Guest Recovery

**Ahmed's Pointsystem > Guest Recovery**

Efficient guest order management:

- List of all guest orders
- Order status indicators
- Quick user assignment
- Automatic points calculation
- Bulk processing options
- Order filtering by status/date

### Product Metabox

Enhanced product editing with:

- Points cost field with validation
- Toggle for points payment
- Points-only checkbox
- Variation-specific settings
- Real-time preview
- Help tooltips

## 🧩 WooCommerce Blocks Support

The plugin fully supports WooCommerce's modern block-based checkout:

### Features

- **REST API Integration**: Custom endpoint for points data
- **Store API Extension**: Extends WooCommerce Store API
- **Checkout Block Notice**: Dedicated checkout block integration
- **Real-Time Updates**: Points info updates as cart changes
- **Frontend Scripts**: Registered properly with WooCommerce Blocks

### Technical Details

```javascript
// Registered frontend script
register_checkout_section.js - Registers the points section
checkout-points-section.js - Renders the points UI component
blocks-integration.js - Handles REST API communication
```

### REST Endpoint

```
GET /wp-json/ahmeds-pointsystem/v1/user-points
```

Returns:

```json
{
  "points": 150,
  "points_value": "15.00",
  "currency_symbol": "DKK"
}
```

## 📝 Shortcodes

### [pr_points_balance]

Display current user's points balance anywhere.

```php
[pr_points_balance]
```

**Output:** "Your Points: 150 (15.00 DKK)"

**Parameters:**

- None currently, displays logged-in user's balance

### Usage Examples

```php
// In a page or post
[pr_points_balance]

// In a widget (if HTML widget)
[pr_points_balance]

// In a template file
<?php echo do_shortcode('[pr_points_balance]'); ?>
```

## 🔧 Developer Hooks

### Actions

```php
// After points are awarded
do_action('pr_points_awarded', $user_id, $points, $order_id);

// After points are spent
do_action('pr_points_spent', $user_id, $points, $order_id);

// When points are adjusted by admin
do_action('pr_points_adjusted', $user_id, $points, $reason, $admin_id);

// Before guest order recovery
do_action('pr_before_guest_recovery', $order_id, $user_id);

// After guest order recovery
do_action('pr_after_guest_recovery', $order_id, $user_id, $points);
```

### Filters

```php
// Modify points earned from an order
apply_filters('pr_calculate_points', $points, $order_total, $order);

// Modify points value in currency
apply_filters('pr_points_to_currency', $currency_value, $points);

// Modify conversion rate
apply_filters('pr_conversion_rate', $rate);

// Modify registration bonus
apply_filters('pr_registration_points', $points, $user_id);

// Modify points display format
apply_filters('pr_points_display', $display_text, $points, $currency_value);

// Control if product is eligible for points
apply_filters('pr_product_eligible_for_points', $eligible, $product_id);

// Control if category is allowed
apply_filters('pr_category_allowed', $allowed, $category_id);
```

### Example Usage

```php
// Give double points on weekends
add_filter('pr_calculate_points', function($points, $order_total, $order) {
    $day = date('N'); // 1 = Monday, 7 = Sunday
    if ($day >= 6) { // Saturday or Sunday
        return $points * 2;
    }
    return $points;
}, 10, 3);

// Custom registration bonus for specific role
add_filter('pr_registration_points', function($points, $user_id) {
    $user = get_userdata($user_id);
    if (in_array('wholesale_customer', $user->roles)) {
        return 500; // Wholesale customers get more points
    }
    return $points;
}, 10, 2);

// Exclude specific product from points
add_filter('pr_product_eligible_for_points', function($eligible, $product_id) {
    $excluded_products = array(123, 456, 789);
    if (in_array($product_id, $excluded_products)) {
        return false;
    }
    return $eligible;
}, 10, 2);
```

## 🐛 Troubleshooting

### Points Not Appearing in Checkout

1. **Check Settings**: Ensure "Enable Purchase with Points" is enabled
2. **Verify Balance**: User must have points available
3. **Category Restrictions**: Check if product categories are allowed
4. **Product Settings**: Verify product hasn't disabled points payment
5. **Cache**: Clear site and browser cache

### Guest Recovery Not Working

1. **Order Status**: Only completed/processing orders can be recovered
2. **User Email**: Ensure email exactly matches an existing user
3. **Already Recovered**: Check if order was already assigned to a user
4. **Permissions**: Verify admin capabilities

### Points Calculation Incorrect

1. **Conversion Rate**: Verify settings in Admin > Settings
2. **Product Override**: Check if product has custom points cost
3. **Rounding**: Plugin rounds to nearest whole point
4. **Order Status**: Points only awarded for completed/processing orders

### WooCommerce Blocks Issues

1. **WooCommerce Version**: Ensure WooCommerce is up to date
2. **Block Theme**: Verify theme supports WooCommerce Blocks
3. **Script Registration**: Check browser console for JS errors
4. **API Endpoint**: Test REST endpoint: `/wp-json/ahmeds-pointsystem/v1/user-points`

### My Account Page Not Showing

1. **Flush Rewrite Rules**: Go to Settings > Permalinks and click Save
2. **Endpoint**: Check if `/my-account/point-log/` exists
3. **WooCommerce Active**: Ensure WooCommerce is installed and active
4. **User Logged In**: Points page only visible to logged-in users

### Points History Missing

1. **Transaction Recording**: Verify order was completed
2. **Database**: Check if `{prefix}_points_log` table exists
3. **User ID**: Ensure transactions associated with correct user
4. **Date Range**: Check if filtering by date range

### Styling Issues

1. **CSS Conflicts**: Check for theme conflicts with class names
2. **Namespace**: Plugin uses `pr-` prefix to avoid conflicts
3. **Browser Cache**: Clear browser cache and hard reload
4. **Minification**: Check if CSS minification is causing issues
5. **Custom CSS**: Add custom overrides in theme if needed

### Common Error Messages

**"Insufficient points for this purchase"**

- User doesn't have enough points for selected products
- Solution: Add more points or reduce order total

**"Points payment not available for this product"**

- Product or category restricted from points payment
- Solution: Check product settings and category restrictions

**"Guest order already has an assigned user"**

- Order was already recovered or wasn't a guest order
- Solution: Check order details in WooCommerce

**"Invalid conversion rate"**

- Conversion rate not set or set to 0
- Solution: Set valid conversion rate in settings

## 🔐 Security Features

- **Nonce Verification**: All admin actions protected with WordPress nonces
- **Capability Checks**: Proper permission verification throughout
- **Data Sanitization**: Input sanitized and validated
- **SQL Injection Prevention**: Uses WordPress $wpdb prepared statements
- **XSS Protection**: Output escaped appropriately
- **CSRF Protection**: Forms protected against cross-site request forgery

## 🌐 Localization

The plugin is translation-ready with text domain: `ahmeds-pointsystem`

### Available Languages

- English (default)
- German (included)
- Danish (included)

### Adding Translations

1. Use a plugin like Loco Translate or Poedit
2. Create translation files in `/languages/` directory
3. File format: `ahmeds-pointsystem-{locale}.po` and `.mo`
4. Example: `ahmeds-pointsystem-de_DE.po` for German

## 📊 Database Schema

The plugin creates the following custom table:

### {prefix}\_points_log

| Column      | Type        | Description                          |
| ----------- | ----------- | ------------------------------------ |
| id          | BIGINT(20)  | Primary key                          |
| user_id     | BIGINT(20)  | WordPress user ID                    |
| points      | INT(11)     | Points amount (+ or -)               |
| action      | VARCHAR(50) | Action type (earned/spent/adjusted)  |
| order_id    | BIGINT(20)  | WooCommerce order ID (if applicable) |
| description | TEXT        | Transaction description              |
| created_at  | DATETIME    | Timestamp                            |

### WordPress Options Used

- `pr_conversion_rate` - Points to currency conversion rate
- `pr_registration_points` - Bonus points for new users
- `pr_enable_purchase` - Enable points payment toggle
- `pr_restrict_categories` - Category restriction toggle
- `pr_allowed_categories` - Array of allowed category IDs
- `pr_points_only_categories` - Array of points-only category IDs

### User Meta

- `pr_user_points` - User's current points balance

### Product Meta

- `_pr_points_cost` - Custom points cost for product
- `_pr_enable_points_payment` - Enable points payment for product
- `_pr_points_only` - Require points payment for product

## 🚦 Performance Considerations

### Optimization Features

- **Efficient Queries**: Optimized database queries with proper indexing
- **Caching**: Utilizes WordPress object cache where applicable
- **Lazy Loading**: Assets loaded only when needed
- **Minimized API Calls**: Batched operations reduce server load
- **Pagination**: Large datasets paginated for better performance

### Recommended Settings

- Use object caching (Redis/Memcached) for high-traffic sites
- Enable WooCommerce session caching
- Keep WordPress and WooCommerce updated
- Regular database optimization

## 📞 Support

For support, feature requests, or bug reports:

- **Author:** Ahmed-Deeq Abdi
- **Contact Mail:** 1ahmed.deeq@gmail.com 
- **Plugin URI:** https://example.com/ahmeds-pointsystem
- **Documentation:** This README file
- **Version:** 1.0.0

## 📜 Changelog

### Version 1.0.0 (November 4, 2025)

- Initial release
- Points earning system based on purchases
- Registration bonus points
- Points-based checkout
- Guest order recovery
- Product-level points management
- User management dashboard
- WooCommerce Blocks integration
- My Account points dashboard
- Category restrictions
- Variable product support
- Admin settings interface
- Transaction history
- Bulk user operations
- REST API endpoints
- German and Danish translations

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🙏 Credits

Developed by Ahmed-Deeq Abdi

---

**Note:** This plugin requires WooCommerce to be installed and activated. It is tested with WordPress 5.8+ and WooCommerce 5.0+.
