(function(){
  function reorderIfNeeded(){
    var container = document.querySelector('.woocommerce-variation-add-to-cart.variations_button');
    if(!container) return;

    var quantity = container.querySelector('.quantity');
    var pointsBtn = container.querySelector('button.single_add_to_cart_button[name="pr_purchase_with_points"]');
    var pointsInfo = container.querySelector('.pr-points-info');

    if (!quantity || !pointsBtn) return;

    // If the button currently appears before the quantity, move quantity before button
    if (pointsBtn && quantity && pointsBtn.compareDocumentPosition(quantity) & Node.DOCUMENT_POSITION_FOLLOWING) {
      // quantity is after button -> move it before button
      try { container.insertBefore(quantity, pointsBtn); } catch(e){}
    }

    // Keep points info immediately after the points button if present
    if (pointsInfo && pointsBtn && pointsInfo.previousElementSibling !== pointsBtn) {
      try { container.insertBefore(pointsInfo, pointsBtn.nextSibling); } catch(e){}
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', reorderIfNeeded);
  } else {
    reorderIfNeeded();
  }

  // Re-run when variations change as Woo may re-render the row
  document.addEventListener('found_variation', function(){
    setTimeout(reorderIfNeeded, 0);
  });
})();
