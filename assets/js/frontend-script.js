(function(){
  function movePointsInfoNextToSizeGuide(){
    var sizeGuide = document.querySelector('.sgp-size-guide-wrapper');
    var pointsInfo = document.querySelector('.pr-points-info');
    if(!sizeGuide || !pointsInfo) return;

    // If already adjacent, do nothing
    if (sizeGuide.nextElementSibling === pointsInfo) return;

    // Move points info to be immediately after the size guide wrapper
    try {
      sizeGuide.parentNode.insertBefore(pointsInfo, sizeGuide.nextSibling);
    } catch(e) {
      // Fallback: append inside parent if direct insert fails
      try { sizeGuide.parentNode.appendChild(pointsInfo); } catch(e2) {}
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', movePointsInfoNextToSizeGuide);
  } else {
    movePointsInfoNextToSizeGuide();
  }

  // Re-run on variation change in case DOM updates
  document.addEventListener('woocommerce_variation_has_changed', movePointsInfoNextToSizeGuide);
})();
