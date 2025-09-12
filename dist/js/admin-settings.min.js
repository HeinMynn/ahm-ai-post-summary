/**
 * Admin Settings JavaScript for AHM AI Post Summary
 * Version: 1.1.5
 */

(function ($) {
  "use strict";

  // Wait for DOM to be ready
  $(document).ready(function () {
    // Handle API provider change
    $("#ahmaipsu_api_provider").change(function () {
      var provider = $(this).val();
      if (provider === "gemini") {
        $("#gemini-instructions").show();
        $("#chatgpt-instructions").hide();
      } else {
        $("#gemini-instructions").hide();
        $("#chatgpt-instructions").show();
      }

      // Update validation button visibility
      updateValidationButtonVisibility();
    });

    // Handle API key input changes
    $("#ahmaipsu_api_key").on("input change", function () {
      updateValidationButtonVisibility();
    });

    // Handle API key validation - use event delegation for dynamically created buttons
    $(document).on("click", "#validate-api-key", function () {
      validateApiKey();
    });

    // Update validation button visibility on load
    updateValidationButtonVisibility();

    // Handle test summary generation
    $("#generate_test_summary").click(function () {
      var content = $("#test_content").val();
      if (!content) return;

      $("#test_result").html("Generating summary...");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "ahmaipsu_test",
          content: content,
          nonce: ahmaipsu_admin_vars.test_nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#test_result").html(
              "<strong>Summary:</strong> " + response.data
            );
          } else {
            $("#test_result").html(
              '<span style="color: red;">Error: ' + response.data + "</span>"
            );
          }
        },
      });
    });

    // Initialize theme selection functionality
    initializeThemeSelector();

    // Add form submission feedback
    initializeFormFeedback();
  });

  // Form submission feedback
  function initializeFormFeedback() {
    var $form = $("#ahmaipsu-settings-form");
    var $saveButton = $('#ahmaipsu-save-button, input[name="submit"]');

    if ($form.length && $saveButton.length) {
      $form.on("submit", function () {
        var originalText = $saveButton.val();

        // Show loading state
        $saveButton.val("Saving...");
        $saveButton.prop("disabled", true);
        $saveButton.addClass("ahmaipsu-saving");

        // Add a temporary notice
        var $tempNotice = $(
          '<div class="notice notice-info ahmaipsu-temp-notice"><p>💾 Saving your settings...</p></div>'
        );
        $(".wrap h1").after($tempNotice);

        // Hide existing notices
        $(".notice:not(.ahmaipsu-temp-notice)").fadeOut();
      });
    }

    // Auto-hide success/error messages after some time
    setTimeout(function () {
      $(".notice.notice-success, .notice.notice-error")
        .not(".ahmaipsu-temp-notice")
        .fadeOut(function () {
          $(this).remove();
        });
    }, 5000); // Hide after 5 seconds
  }

  // Theme Selection Functionality
  function initializeThemeSelector() {
    // Handle theme radio button changes
    $('input[name="ahmaipsu_settings[ahmaipsu_theme]"]').change(function () {
      var selectedTheme = $(this).val();
      updateThemeSelection(selectedTheme);
    });

    // Initialize with current selection
    var currentTheme = $(
      'input[name="ahmaipsu_settings[ahmaipsu_theme]"]:checked'
    ).val();
    if (currentTheme) {
      updateThemeSelection(currentTheme);
    }

    // Add click handlers for theme option containers
    $(".ahmaipsu-theme-option").click(function (e) {
      // Don't trigger if clicking on the radio button itself
      if (e.target.type !== "radio" && !$(e.target).is("label")) {
        var radio = $(this).find('input[type="radio"]');
        if (!radio.is(":checked")) {
          radio.prop("checked", true).trigger("change");
        }
      }
    });

    // Add hover effects (optional - CSS handles most of this now)
    $(".ahmaipsu-theme-option").hover(
      function () {
        if (!$(this).find('input[type="radio"]').is(":checked")) {
          $(this).addClass("ahmaipsu-theme-hover");
        }
      },
      function () {
        $(this).removeClass("ahmaipsu-theme-hover");
      }
    );
  }

  function updateThemeSelection(selectedTheme) {
    // Remove selected class from all options
    $(".ahmaipsu-theme-option").removeClass("selected");

    // Add selected class to chosen option
    $('.ahmaipsu-theme-option[data-theme="' + selectedTheme + '"]').addClass(
      "selected"
    );

    // Add subtle animation to the selected preview
    var selectedPreview = $(
      '.ahmaipsu-theme-option[data-theme="' +
        selectedTheme +
        '"] .ahmaipsu-theme-preview'
    );
    selectedPreview.addClass("ahmaipsu-preview-selected");

    // Remove animation class from other previews after animation completes
    setTimeout(function () {
      $(".ahmaipsu-theme-preview").removeClass("ahmaipsu-preview-selected");
    }, 600);

    // Show confirmation message
    showThemeSelectedMessage(selectedTheme);
  }

  function showThemeSelectedMessage(theme) {
    // Remove any existing message
    $(".ahmaipsu-theme-selected-message").remove();

    // Create new message
    var themeNames = {
      classic: "🎯 Classic",
      minimal: "✨ Minimal",
      modern: "🚀 Modern",
      elegant: "💎 Elegant",
      card: "📋 Card",
    };

    var themeName = themeNames[theme] || theme;
    var message = $(
      '<div class="ahmaipsu-theme-selected-message"><p><strong>✓ Theme Selected:</strong> ' +
        themeName +
        " theme will be applied to your summaries when you save the settings.</p></div>"
    );

    // Insert message after theme selector
    $(".ahmaipsu-theme-selector").after(message);

    // Auto-hide after 4 seconds
    setTimeout(function () {
      message.fadeOut(500, function () {
        $(this).remove();
      });
    }, 4000);
  }

  // Function to update validation button visibility
  function updateValidationButtonVisibility() {
    var apiKey = $("#ahmaipsu_api_key").val().trim();
    var button = $("#validate-api-key");

    // The button is created by PHP, so we just need to show/hide it
    if (apiKey && apiKey.length > 0) {
      button.show();
    } else {
      button.hide();
      $("#api-validation-result").empty();
    }
  }

  // Separate validation function for reusability
  function validateApiKey() {
    var apiKey = $("#ahmaipsu_api_key").val().trim();
    var provider = $("#ahmaipsu_api_provider").val() || "gemini";
    var button = $("#validate-api-key");
    var resultDiv = $("#api-validation-result");

    if (!apiKey) {
      resultDiv.html(
        '<div class="notice notice-error inline"><p>Please enter an API key to validate.</p></div>'
      );
      return;
    }

    // Disable button and show loading state
    button.prop("disabled", true);
    button.html(
      '<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; margin-right: 5px; vertical-align: text-bottom;"></span>Validating...'
    );

    resultDiv.html(
      '<div class="notice notice-info inline"><p>⏳ Testing API connection...</p></div>'
    );

    $.ajax({
      url: ahmaipsu_admin_vars.ajax_url,
      type: "POST",
      data: {
        action: "ahmaipsu_validate_api_key",
        api_key: apiKey,
        api_provider: provider,
        nonce: ahmaipsu_admin_vars.validate_nonce,
      },
      success: function (response) {
        if (response.success) {
          resultDiv.html(
            '<div class="notice notice-success inline"><p>' +
              response.data +
              "</p></div>"
          );
        } else {
          resultDiv.html(
            '<div class="notice notice-error inline"><p>❌ ' +
              response.data +
              "</p></div>"
          );
        }
      },
      error: function (xhr, status, error) {
        resultDiv.html(
          '<div class="notice notice-error inline"><p>❌ Connection failed: ' +
            error +
            "</p></div>"
        );
      },
      complete: function () {
        // Re-enable button and restore original text
        button.prop("disabled", false);
        button.html(
          '<span class="dashicons dashicons-shield-alt" style="margin-right: 5px; vertical-align: text-bottom;"></span>Validate API Key'
        );
      },
    });
  }

  // API Key validation (loaded separately when needed)
  window.ahmaipsu_init_api_validation = function () {
    document.addEventListener("DOMContentLoaded", function () {
      var apiKeyField = document.querySelector(
        'input[name="ahmaipsu_settings[ahmaipsu_api_key]"]'
      );
      var globalEnableField = document.getElementById("ahmaipsu_global_enable");

      function checkApiKey() {
        if (apiKeyField.value.trim() !== "") {
          globalEnableField.disabled = false;
        } else {
          globalEnableField.disabled = true;
          globalEnableField.checked = false;
        }
      }

      if (apiKeyField) {
        apiKeyField.addEventListener("input", checkApiKey);
        apiKeyField.addEventListener("change", checkApiKey);
      }
    });
  };

  // Form submission feedback
  function initializeFormFeedback() {
    $("#ahmaipsu-settings-form").on("submit", function () {
      var $button = $("#ahmaipsu-save-button");
      var originalText = $button.val();

      // Show loading state
      $button.val(ahmaipsu_admin_vars.saving_text || "Saving...");
      $button.prop("disabled", true);

      // Add a subtle loading animation
      $button.addClass("ahmaipsu-saving");

      // Note: The page will reload after submission, so we don't need to reset the button
    });

    // Auto-hide notices after 5 seconds
    setTimeout(function () {
      $(".notice.is-dismissible").fadeOut();
    }, 5000);
  }

  // Initialize form feedback
  initializeFormFeedback();
})(jQuery);
