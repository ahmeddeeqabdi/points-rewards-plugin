jQuery(document).ready(function ($) {
  // Toggle category selection based on restriction checkbox
  $("#pr_restrict_categories").on("change", function () {
    if ($(this).is(":checked")) {
      $(".pr-categories-row").slideDown();
    } else {
      $(".pr-categories-row").slideUp();
    }
  });

  // Show/hide purchase options
  $("#pr_enable_purchase").on("change", function () {
    if ($(this).is(":checked")) {
      $(".pr-restriction-row, .pr-categories-row").slideDown();
    } else {
      $(".pr-restriction-row, .pr-categories-row").slideUp();
    }
  });
});