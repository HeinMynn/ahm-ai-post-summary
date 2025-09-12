/**
 * Post Editor JavaScript for AHM AI Post Summary
 * Version: 1.1.4
 */

(function ($) {
  "use strict";

  // Wait for DOM to be ready
  $(document).ready(function () {
    var postId = ahmaipsu_editor_vars.post_id;
    var checkingForUpdate = false;

    // Handle regenerate button click
    $(document).on("click", "#ahmaipsu-regenerate-btn", function (e) {
      e.preventDefault();

      var $button = $(this);
      var $status = $("#ahmaipsu-regenerate-status");
      var $icon = $button.find(".dashicons");

      // Disable button and show loading state
      $button.prop("disabled", true);
      $icon.removeClass("dashicons-update").addClass("dashicons-update-alt");
      $button.addClass("ahmaipsu-spinning");
      $status.text("Generating new summary...").show();

      $.ajax({
        url: ahmaipsu_editor_vars.ajax_url,
        type: "POST",
        data: {
          action: "ahmaipsu_regenerate_instantly",
          post_id: postId,
          nonce: ahmaipsu_editor_vars.regenerate_nonce,
        },
        success: function (response) {
          if (response.success) {
            // Update the summary text
            $("#gpt-summary-text").text(response.data.summary);
            $("#gpt-summary-preview").show();
            $("#gpt-summary-placeholder").hide();

            // Show success status
            $status
              .text("Summary regenerated successfully!")
              .css("color", "#46b450");

            // Hide status after 3 seconds
            setTimeout(function () {
              $status.fadeOut();
            }, 3000);
          } else {
            // Show error status
            $status.text("Error: " + response.data).css("color", "#dc3232");
          }
        },
        error: function () {
          $status
            .text("Network error occurred. Please try again.")
            .css("color", "#dc3232");
        },
        complete: function () {
          // Re-enable button and restore icon
          $button.prop("disabled", false);
          $button.removeClass("ahmaipsu-spinning");
          $icon
            .removeClass("dashicons-update-alt")
            .addClass("dashicons-update");
        },
      });
    });

    // Function to check for summary updates
    function checkSummaryUpdate() {
      if (checkingForUpdate) return;
      checkingForUpdate = true;

      $.ajax({
        url: ahmaipsu_editor_vars.ajax_url,
        type: "POST",
        data: {
          action: "ahmaipsu_check_update",
          post_id: postId,
          nonce: ahmaipsu_editor_vars.nonce,
        },
        success: function (response) {
          if (response.success) {
            var currentDisplayed = $("#gpt-summary-text").text().trim();
            var newSummary = response.data.summary;
            var isGenerating = response.data.generating;

            // If we got a new summary that is different from what is displayed
            if (newSummary && newSummary !== currentDisplayed) {
              $("#gpt-summary-text").text(newSummary);
              $("#gpt-summary-preview").show();
              $("#gpt-summary-placeholder").hide();
              $("#gpt-summary-status").hide();

              // Show success message
              showStatusMessage(
                response.data.regenerated
                  ? "Summary regenerated!"
                  : "Summary generated!",
                "success"
              );

              // Uncheck regenerate checkbox
              $('input[name="ahmaipsu_regenerate"]').prop("checked", false);

              checkingForUpdate = false;
              return; // Stop checking
            }

            // If still generating, continue checking
            if (isGenerating) {
              setTimeout(function () {
                checkingForUpdate = false;
                checkSummaryUpdate();
              }, 2000);
              return;
            }
          }

          $("#gpt-summary-status").hide();
          checkingForUpdate = false;
        },
        error: function () {
          $("#gpt-summary-status").hide();
          checkingForUpdate = false;
        },
      });
    }

    // Function to show status messages
    function showStatusMessage(message, type) {
      var bgColor = type === "success" ? "#d4edda" : "#f8d7da";
      var textColor = type === "success" ? "#155724" : "#721c24";

      $("#gpt-summary-status")
        .css({
          "background-color": bgColor,
          color: textColor,
          border: "1px solid " + (type === "success" ? "#c3e6cb" : "#f5c6cb"),
        })
        .find(".spinner")
        .hide()
        .end()
        .find("#gpt-summary-status-text")
        .text(message)
        .end()
        .show();

      // For success messages, add a refresh reminder after a delay
      if (type === "success") {
        setTimeout(function () {
          $("#gpt-summary-status-text").html(
            message +
              '<br><small style="font-style: italic; opacity: 0.8;">If the summary did not update above, please refresh this page.</small>'
          );
        }, 4000);
      }

      setTimeout(function () {
        $("#gpt-summary-status").fadeOut();
      }, 8000);
    }

    // Listen for post save events
    $(document).on("heartbeat-send", function (event, data) {
      // WordPress heartbeat - we can use this to detect saves
      data.ahmaipsu_check = {post_id: postId};
    });

    // Check for updates when regenerate checkbox is checked and post is saved
    var originalSummary = $("#gpt-summary-text").text().trim();

    // Also listen for WordPress post update events
    $(document).on("heartbeat-tick.gpt-summary", function (e, data) {
      if (data.wp_autosave && data.wp_autosave.post_id == postId) {
        // Post was saved, check for updates
        setTimeout(checkSummaryUpdate, 1000);
      }
    });

    // Alternative approach: Monitor for page changes
    var originalUrl = window.location.href;
    var urlCheckInterval = setInterval(function () {
      if (window.location.href !== originalUrl) {
        // URL changed (likely due to post save), check for updates
        setTimeout(checkSummaryUpdate, 500);
        originalUrl = window.location.href;
      }
    }, 1000);

    // Also check periodically after form submission
    $("form#post").on("submit", function () {
      var regenerateChecked =
        $('input[name="ahmaipsu_regenerate"]:checked').length > 0;
      var enabledChecked =
        $('input[name="ahmaipsu_enabled"]:checked').length > 0;
      var hasNoSummary = !$("#gpt-summary-text").text().trim();

      if (regenerateChecked || (enabledChecked && hasNoSummary)) {
        $("#gpt-summary-status")
          .css({
            "background-color": "#fff3cd",
            color: "#856404",
            border: "1px solid #ffeaa7",
          })
          .find(".spinner")
          .show()
          .css("visibility", "visible")
          .end()
          .find("#gpt-summary-status-text")
          .text("Generating summary...")
          .end()
          .show();

        // Start checking immediately and repeatedly
        var checkInterval = setInterval(function () {
          $.ajax({
            url: ahmaipsu_editor_vars.ajax_url,
            type: "POST",
            data: {
              action: "ahmaipsu_check_update",
              post_id: postId,
              original_summary: originalSummary,
              nonce: ahmaipsu_editor_vars.nonce,
            },
            success: function (response) {
              if (response.success) {
                var newSummary = response.data.summary;
                var isGenerating = response.data.generating;

                // If generation is complete and we have a new summary
                if (
                  !isGenerating &&
                  newSummary &&
                  newSummary !== originalSummary
                ) {
                  // Summary was updated
                  $("#gpt-summary-text").text(newSummary);
                  $("#gpt-summary-preview").show();
                  $("#gpt-summary-placeholder").hide();
                  $("#gpt-summary-status").hide();

                  // Show success message
                  showStatusMessage(
                    response.data.regenerated
                      ? "Summary regenerated!"
                      : "Summary generated!",
                    "success"
                  );

                  // Stop checking
                  clearInterval(checkInterval);

                  // Uncheck the regenerate checkbox
                  $('input[name="ahmaipsu_regenerate"]').prop("checked", false);

                  // Update the original summary for future comparisons
                  originalSummary = newSummary;
                }
              }
            },
            error: function () {
              // Hide status on error
              $("#gpt-summary-status").hide();
              clearInterval(checkInterval);
            },
          });
        }, 2000); // Check every 2 seconds

        // Stop checking after 30 seconds to prevent infinite polling
        setTimeout(function () {
          clearInterval(checkInterval);
          $("#gpt-summary-status").hide();
        }, 30000);
      }
    });
  });
})(jQuery);
