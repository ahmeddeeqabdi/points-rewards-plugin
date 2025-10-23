jQuery(document).ready(function ($) {
  // Smooth animations for toggles
  const animationSpeed = 300;

  // Toggle category selection based on restriction checkbox
  $("#pr_restrict_categories").on("change", function () {
    if ($(this).is(":checked")) {
      $(".pr-categories-row").slideDown(animationSpeed);
    } else {
      $(".pr-categories-row").slideUp(animationSpeed);
    }
  });

  // Show/hide purchase options
  $("#pr_enable_purchase").on("change", function () {
    if ($(this).is(":checked")) {
      $(".pr-restriction-row, .pr-categories-row").slideDown(animationSpeed);
    } else {
      $(".pr-restriction-row, .pr-categories-row").slideUp(animationSpeed);
    }
  });

  // Initialize visibility on page load
  if (!$("#pr_enable_purchase").is(":checked")) {
    $(".pr-restriction-row, .pr-categories-row").hide();
  }
  if (!$("#pr_restrict_categories").is(":checked")) {
    $(".pr-categories-row").hide();
  }

  // Prevent clicking recalculate button without saving settings first
  $("form").last().on("submit", function (e) {
    const $settingsForm = $("form").first();
    const hasUnsavedChanges = $settingsForm.find("input, select").is(function () {
      const $input = $(this);
      const initialValue = $input.data("initial-value");
      const currentValue = $input.val();
      return initialValue !== undefined && initialValue !== currentValue;
    });

    if (hasUnsavedChanges) {
      e.preventDefault();
      alert("Please save your settings first by clicking 'Save Settings' before applying the bonus.");
      return false;
    }

    $(this).find("button").prop("disabled", true);
    $(this).find("button").text("Recalculating...");
  });

  // Store initial values for change detection
  $("form").first().find("input, select").each(function () {
    $(this).data("initial-value", $(this).val());
  });

  // Detect when form values change
  $("form").first().on("change", "input, select", function () {
    $(this).data("initial-value", $(this).val());
  });

  // Add focus styles for better accessibility
  $("input, select, textarea").on("focus", function () {
    $(this).closest(".form-table").find("tr").css("background-color", "#f9f9f9");
  }).on("blur", function () {
    $(this).closest(".form-table").find("tr").css("background-color", "");
  });

  // Live input validation feedback
  $("#pr_registration_points, #pr_conversion_rate").on("change", function () {
    const value = $(this).val();
    if (value === "" || ($(this).attr("type") === "number" && value < 0)) {
      $(this).css("border-color", "#dc3545");
    } else {
      $(this).css("border-color", "#ccd0d4");
    }
  });

  // Add helpful tooltips
  $("[title]").on("mouseover", function () {
    if ($(this).data("tooltip-shown")) return;
    const tooltip = $('<div class="pr-tooltip">' + $(this).attr("title") + "</div>");
    $(this).after(tooltip);
    $(this).data("tooltip-shown", true);
  }).on("mouseout", function () {
    $(this).next(".pr-tooltip").remove();
    $(this).data("tooltip-shown", false);
  });
});
