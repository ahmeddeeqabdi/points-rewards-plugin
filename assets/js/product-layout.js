(function(){
  function ensureLayout(){
    var container = document.querySelector('.woocommerce-variation-add-to-cart.variations_button');
    if(!container) return;

    var quantity   = container.querySelector('.quantity');
    var pointsBtn  = container.querySelector('button.single_add_to_cart_button[name="pr_purchase_with_points"]');
    var pointsInfo = container.querySelector('.pr-points-info');

    if (!quantity || !pointsBtn) return;

    // Ensure quantity appears before the points controls
    if (pointsBtn && quantity && pointsBtn.compareDocumentPosition(quantity) & Node.DOCUMENT_POSITION_FOLLOWING) {
      try { container.insertBefore(quantity, pointsBtn); } catch(e){}
    }

    // Create a group wrapper so text can sit above the points button
    var group = container.querySelector('.pr-button-group');
    if (!group) {
      group = document.createElement('div');
      group.className = 'pr-button-group';
      // Insert the group where the points button currently is
      try { container.insertBefore(group, pointsBtn); } catch(e){}
    }

    // Move points info and button into the group in the right order
    if (pointsInfo) {
      try { group.appendChild(pointsInfo); } catch(e){}
    }
    try { group.appendChild(pointsBtn); } catch(e){}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureLayout);
  } else {
    ensureLayout();
  }

  // Re-run when variations change as Woo may re-render the row
  document.addEventListener('found_variation', function(){
    setTimeout(ensureLayout, 0);
  });
})();
