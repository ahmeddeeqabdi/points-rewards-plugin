/**
 * Points & Rewards - WooCommerce Blocks Integration
 * Handles frontend display of points purchase notices
 */

(function () {
    'use strict';

    let lastNotice = '';

    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPointsNotice);
    } else {
        initPointsNotice();
    }

    function initPointsNotice() {
        // Check if we're on a checkout page with blocks
        if (!document.querySelector('.wc-block-checkout__form')) {
            return;
        }

        console.log('Points Rewards: Initializing checkout notice');

        // Check for points notice immediately
        checkAndDisplayNotice();

        // Re-check every 1 second for cart changes
        setInterval(checkAndDisplayNotice, 1000);
    }

    /**
     * Fetch and display the points purchase notice
     */
    function checkAndDisplayNotice() {
        // Fetch from the Store API cart endpoint
        fetch('/wp-json/wc/store/cart', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Cart API error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Log the response for debugging
                console.log('Points Rewards: Cart data received', data);
                console.log('Points Rewards: Extensions:', data.extensions);

                // Check if response has our custom points data
                if (data.extensions && data.extensions['points-rewards']) {
                    const pointsNotice = data.extensions['points-rewards'].pr_points_purchase_notice;
                    console.log('Points Rewards: Found notice:', pointsNotice);

                    // Only update if notice changed
                    if (pointsNotice !== lastNotice) {
                        console.log('Points Rewards: Notice update:', pointsNotice);
                        lastNotice = pointsNotice;
                        updatePointsNotice(pointsNotice);
                    }
                } else {
                    console.log('Points Rewards: No extensions found. Response keys:', Object.keys(data));
                    if (lastNotice !== '') {
                        console.log('Points Rewards: No points data in response');
                        lastNotice = '';
                        removePointsNotice();
                    }
                }
            })
            .catch(error => {
                console.debug('Points notice fetch error:', error);
            });
    }

    /**
     * Update or create the points purchase notice in the checkout form
     */
    function updatePointsNotice(noticeText) {
        const checkoutForm = document.querySelector('.wc-block-checkout__form');
        if (!checkoutForm) {
            return;
        }

        // If no notice text, remove it
        if (!noticeText || noticeText.trim() === '') {
            removePointsNotice();
            return;
        }

        // Remove existing notice
        removePointsNotice();

        // Create notice element with inline styles to ensure visibility
        const noticeDiv = document.createElement('div');
        noticeDiv.className = 'pr-points-purchase-notice wc-block-components-notice-banner is-info';
        noticeDiv.setAttribute('role', 'alert');
        noticeDiv.style.cssText = `
            background-color: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #333;
        `;

        const iconSvg = `
            <svg class="wc-block-components-notice-banner__icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true" focusable="false" style="flex-shrink: 0; color: #1976d2; width: 24px; height: 24px;">
                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
            </svg>
        `;

        const textDiv = document.createElement('div');
        textDiv.className = 'wc-block-components-notice-banner__text';
        textDiv.textContent = noticeText;
        textDiv.style.cssText = 'flex: 1;';

        noticeDiv.innerHTML = iconSvg;
        noticeDiv.appendChild(textDiv);

        // Insert at the very top of the checkout form
        const checkoutForm2 = document.querySelector('.wc-block-checkout__form');
        if (checkoutForm2 && checkoutForm2.firstChild) {
            checkoutForm2.insertBefore(noticeDiv, checkoutForm2.firstChild);
            console.log('Points Rewards: Notice displayed at top of form');
        }
    }

    /**
     * Remove the points purchase notice
     */
    function removePointsNotice() {
        const existingNotice = document.querySelector('.pr-points-purchase-notice');
        if (existingNotice) {
            existingNotice.remove();
        }
    }

})();