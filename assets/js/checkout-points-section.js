/**
 * Points & Rewards - Custom Checkout Section Component
 * Displays points purchase notice in the WooCommerce Checkout Block
 */

const CheckoutPointsSection = ({ cart, extensions }) => {
    console.log('Points & Rewards: JavaScript component called', { cart: !!cart, extensions: !!extensions });

    // Check if we have points data
    if (!cart || !extensions || !extensions['points-rewards']) {
        console.log('Points & Rewards: JavaScript - Missing required data');
        return null;
    }

    const pointsNotice = extensions['points-rewards'].pr_points_purchase_notice;
    console.log('Points & Rewards: JavaScript - Notice content:', pointsNotice);

    // Don't render if there's no notice
    if (!pointsNotice || pointsNotice.trim() === '') {
        console.log('Points & Rewards: JavaScript - No notice to display');
        return null;
    }

    console.log('Points & Rewards: JavaScript - Rendering notice component');

    // Render the points notice using WooCommerce's notice component
    return (
        <div className="pr-points-notice-wrapper" style={{ marginBottom: '24px', width: '100%' }}>
            <div className="pr-points-notice" style={{
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: 'white',
                padding: '16px 20px',
                borderRadius: '12px',
                boxShadow: '0 4px 12px rgba(102, 126, 234, 0.15)',
                border: '1px solid rgba(255, 255, 255, 0.1)',
                position: 'relative',
                overflow: 'hidden'
            }}>
                <div style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    background: 'radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%)',
                    opacity: 0.5
                }}></div>
                <div style={{
                    position: 'relative',
                    zIndex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px'
                }}>
                    <div style={{ flexShrink: 0 }}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z" fill="white"/>
                        </svg>
                    </div>
                    <div style={{ flex: 1 }}>
                        <div style={{
                            fontWeight: 600,
                            fontSize: '14px',
                            marginBottom: '4px',
                            opacity: 0.9
                        }}>
                            Zahlung mit Punkten
                        </div>
                        <div style={{
                            fontSize: '13px',
                            lineHeight: '1.4',
                            opacity: 0.95
                        }}>
                            {pointsNotice}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CheckoutPointsSection;
