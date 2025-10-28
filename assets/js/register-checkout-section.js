/**
 * Points & Rewards - Checkout Block Extension Registration
 * Registers the custom points section with WooCommerce Blocks
 */

const { registerPlugin } = wp.plugins;
const { Slot, Fill } = wc.blocksCheckout;

// Import the custom component
import CheckoutPointsSection from './checkout-points-section.js';

/**
 * Render function for the points plugin
 * Uses multiple slots to ensure compatibility with different checkout layouts
 */
const renderPointsCheckoutPlugin = () => {
    return (
        <>
            <Fill name="woocommerce/checkout/before-payment-methods">
                <CheckoutPointsSection />
            </Fill>
            <Fill name="woocommerce/checkout/payment-methods">
                <CheckoutPointsSection />
            </Fill>
            <Fill name="woocommerce/checkout/after-customer-details">
                <CheckoutPointsSection />
            </Fill>
            <Fill name="woocommerce/checkout/before-order-summary">
                <CheckoutPointsSection />
            </Fill>
        </>
    );
};

/**
 * Register the custom checkout section with WordPress
 * This adds our points section inside the checkout form using multiple slots
 */
registerPlugin('pr-checkout-points-section', {
    render: renderPointsCheckoutPlugin,
    scope: 'woocommerce-checkout',
});

console.log('Points & Rewards: Custom checkout section registered');
