(function ($) {
  "use strict";

  function confirmDelete(e) {
    if (!window.confirm(ctaAdmin.i18n.confirmDelete)) {
      e.preventDefault();
    }
  }

  function copyShortcode(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }

    var temp = $("<textarea>");
    $("body").append(temp);
    temp.val(text).select();
    document.execCommand("copy");
    temp.remove();
    return $.Deferred().resolve().promise();
  }

  function initCopyButtons() {
    $(document).on("click", ".cta-copy-shortcode", function () {
      var btn = $(this);
      var code = btn.data("shortcode");

      copyShortcode(code).then(function () {
        var original = btn.text();
        btn.text(ctaAdmin.i18n.copied);
        setTimeout(function () {
          btn.text(original);
        }, 1500);
      });
    });
  }

  function initDeleteConfirms() {
    $(document).on("click", ".cta-delete-course", confirmDelete);
  }

  function initSlugGeneration() {
    var $title = $("#cta-course-title");
    var $slug = $("#cta-course-slug");

    if (!$title.length || !$slug.length) {
      return;
    }

    var slugEdited = !!$slug.val();

    $slug.on("input", function () {
      slugEdited = true;
    });

    $title.on("input", function () {
      if (slugEdited && $slug.val()) {
        return;
      }

      $slug.val(
        $title
          .val()
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "-")
          .replace(/^-+|-+$/g, "")
      );
    });
  }

  function initObjectivesRepeater() {
    $("#cta-add-objective").on("click", function () {
      $("#cta-objectives-repeater").append(
        '<div class="cta-objective-row">' +
          '<input type="text" class="regular-text" name="learning_objectives[]" value="">' +
          '<button type="button" class="button cta-remove-objective">Remove</button>' +
          "</div>"
      );
    });

    $("#cta-add-goal").on("click", function () {
      $("#cta-goals-repeater").append(
        '<div class="cta-objective-row">' +
          '<input type="text" class="large-text" name="educational_goals[]" value="">' +
          '<button type="button" class="button cta-remove-objective">Remove</button>' +
          "</div>"
      );
    });

    $("#cta-add-completion").on("click", function () {
      $("#cta-completion-repeater").append(
        '<div class="cta-objective-row">' +
          '<input type="text" class="large-text" name="completion_requirements[]" value="">' +
          '<button type="button" class="button cta-remove-objective">Remove</button>' +
          "</div>"
      );
    });

    $(document).on("click", ".cta-remove-objective", function () {
      var $row = $(this).closest(".cta-objective-row");
      var $list = $row.parent();
      var rows = $list.find(".cta-objective-row");
      if (rows.length <= 1) {
        rows.find("input").val("");
        return;
      }
      $row.remove();
    });
  }

  function initModulesPanel() {
    var $panel = $("#cta-modules-panel");

    if (!$panel.length) {
      return;
    }

    var courseId = $panel.data("course-id");

    $("#cta-modules-list").sortable({
      handle: ".cta-module-row__handle",
      update: function () {
        var order = [];
        $("#cta-modules-list .cta-module-row").each(function () {
          order.push($(this).data("module-id"));
        });

        $.post(ctaAdmin.ajaxUrl, {
          action: "cta_reorder_modules",
          nonce: ctaAdmin.nonce,
          course_id: courseId,
          order: order
        });
      }
    });

    $("#cta-save-module").on("click", function () {
      var btn = $(this);
      btn.prop("disabled", true);

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_save_module",
        nonce: ctaAdmin.nonce,
        course_id: courseId,
        module_id: $("#cta-module-id").val(),
        title: $("#cta-module-title").val(),
        description: $("#cta-module-description").val(),
        video_url: normalizeModuleVideoUrl(),
        duration_mins: $("#cta-module-duration").val(),
        is_locked: $("#cta-module-locked").is(":checked") ? 1 : 0
      })
        .done(function (response) {
          if (!response.success) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to save module."
            );
            return;
          }

          var moduleId = $("#cta-module-id").val();
          if (moduleId) {
            $('#cta-modules-list .cta-module-row[data-module-id="' + moduleId + '"]').replaceWith(
              response.data.html
            );
          } else {
            $("#cta-modules-list").append(response.data.html);
          }

          $("#cta-module-id, #cta-module-title, #cta-module-description, #cta-module-video, #cta-module-duration").val("");
          $("#cta-module-locked").prop("checked", true);
          btn.text("Add Module");
        })
        .always(function () {
          btn.prop("disabled", false);
        });
    });

    $(document).on("click", ".cta-delete-module", function () {
      if (!window.confirm(ctaAdmin.i18n.confirmDelete)) {
        return;
      }

      var row = $(this).closest(".cta-module-row");
      var moduleId = $(this).data("module-id");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_delete_module",
        nonce: ctaAdmin.nonce,
        course_id: courseId,
        module_id: moduleId
      }).done(function (response) {
        if (response.success) {
          row.remove();
        }
      });
    });

    function toggleVideoSourceUI() {
      var type = $("#cta-module-video-type").val();
      var $input = $("#cta-module-video");
      var $select = $("#cta-module-video-select");
      var $help = $(".cta-module-video-help");

      $help.hide();
      $help.filter('[data-help="' + type + '"]').show();

      if (type === "wordpress") {
        $input.attr("placeholder", "Select a video from Media Library");
        $input.prop("readonly", true);
        $select.show();
      } else if (type === "youtube") {
        $input.attr("placeholder", "https://www.youtube.com/watch?v=...");
        $input.prop("readonly", false);
        $select.hide();
      } else if (type === "vimeo") {
        $input.attr("placeholder", "Vimeo ID or https://vimeo.com/123456789");
        $input.prop("readonly", false);
        $select.hide();
      } else {
        $input.attr("placeholder", "https://example.com/video.mp4");
        $input.prop("readonly", false);
        $select.hide();
      }
    }

    function normalizeModuleVideoUrl() {
      var type = $("#cta-module-video-type").val();
      var value = String($("#cta-module-video").val() || "").trim();

      if (!value) {
        return "";
      }

      if (type === "vimeo") {
        var vimeoId = value.replace(/\D/g, "");
        return vimeoId ? "https://vimeo.com/" + vimeoId : "";
      }

      return value;
    }

    if ($("#cta-module-video-type").length) {
      $("#cta-module-video-type").on("change", function () {
        $("#cta-module-video").val("");
        toggleVideoSourceUI();
      });
      toggleVideoSourceUI();
    }

    $("#cta-module-video-select").on("click", function (e) {
      e.preventDefault();

      if (typeof wp === "undefined" || !wp.media) {
        window.alert("WordPress media library is not available.");
        return;
      }

      var frame = wp.media({
        title: "Select Video",
        button: { text: "Use this video" },
        library: { type: "video" },
        multiple: false
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        $("#cta-module-video").val(attachment.url || "");
      });

      frame.open();
    });

    $(document).on("click", ".cta-edit-module", function () {
      var $row = $(this).closest(".cta-module-row");
      $("#cta-module-id").val($row.data("module-id"));
      $("#cta-module-title").val($row.data("title") || "");
      $("#cta-module-description").val($row.data("description") || "");
      $("#cta-module-video").val($row.data("video-url") || "");
      $("#cta-module-duration").val($row.data("duration") || "");
      $("#cta-module-locked").prop("checked", String($row.data("locked")) !== "0");

      var videoUrl = String($row.data("video-url") || "");
      if (videoUrl.indexOf("youtube.com") !== -1 || videoUrl.indexOf("youtu.be") !== -1) {
        $("#cta-module-video-type").val("youtube");
      } else if (videoUrl.indexOf("vimeo.com") !== -1) {
        $("#cta-module-video-type").val("vimeo");
        var vimeoMatch = videoUrl.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        $("#cta-module-video").val(vimeoMatch ? vimeoMatch[1] : videoUrl);
      } else if (videoUrl.indexOf("/wp-content/") !== -1) {
        $("#cta-module-video-type").val("wordpress");
      } else {
        $("#cta-module-video-type").val("url");
      }

      toggleVideoSourceUI();
      $("#cta-save-module").text("Update Module");
      $("html, body").animate({ scrollTop: $("#cta-modules-panel").offset().top - 40 }, 200);
    });
  }

  function buildQuizQuestionCard(index, data) {
    data = data || {};

    return (
      '<div class="cta-quiz-question-card" data-index="' +
      index +
      '">' +
      '<div class="cta-quiz-question-card__header">' +
      "<strong>Question " +
      (index + 1) +
      "</strong>" +
      '<button type="button" class="button-link-delete cta-remove-quiz-question">Remove</button>' +
      "</div>" +
      '<p><textarea class="large-text cta-q-text" rows="2" placeholder="Question text">' +
      (data.question_text || "") +
      "</textarea></p>" +
      '<div class="cta-quiz-options-grid">' +
      '<p><label>Option A</label><input type="text" class="regular-text cta-q-a" value="' +
      (data.option_a || "") +
      '"></p>' +
      '<p><label>Option B</label><input type="text" class="regular-text cta-q-b" value="' +
      (data.option_b || "") +
      '"></p>' +
      '<p><label>Option C</label><input type="text" class="regular-text cta-q-c" value="' +
      (data.option_c || "") +
      '"></p>' +
      '<p><label>Option D</label><input type="text" class="regular-text cta-q-d" value="' +
      (data.option_d || "") +
      '"></p>' +
      "</div>" +
      '<p><label>Correct Answer</label> ' +
      '<select class="cta-q-correct">' +
      '<option value="a"' +
      (data.correct_option === "a" ? " selected" : "") +
      ">A</option>" +
      '<option value="b"' +
      (data.correct_option === "b" ? " selected" : "") +
      ">B</option>" +
      '<option value="c"' +
      (data.correct_option === "c" ? " selected" : "") +
      ">C</option>" +
      '<option value="d"' +
      (data.correct_option === "d" ? " selected" : "") +
      ">D</option>" +
      "</select></p>" +
      '<p><label>Explanation (shown after quiz)</label>' +
      '<textarea class="large-text cta-q-explanation" rows="2" placeholder="Answer rationale / explanation (shown after answering)">' +
      (data.explanation || "") +
      "</textarea></p>" +
      "</div>"
    );
  }

  function renderQuizQuestions(questions) {
    var $list = $("#cta-quiz-questions");
    $list.empty();

    if (!questions || !questions.length) {
      $list.append(buildQuizQuestionCard(0, {}));
      return;
    }

    questions.forEach(function (question, index) {
      $list.append(buildQuizQuestionCard(index, question));
    });
  }

  function collectQuizQuestions() {
    var questions = [];

    $("#cta-quiz-questions .cta-quiz-question-card").each(function (index) {
      var $card = $(this);
      var questionText = $.trim($card.find(".cta-q-text").val());

      if (!questionText) {
        return;
      }

      questions.push({
        question_text: questionText,
        option_a: $.trim($card.find(".cta-q-a").val()),
        option_b: $.trim($card.find(".cta-q-b").val()),
        option_c: $.trim($card.find(".cta-q-c").val()),
        option_d: $.trim($card.find(".cta-q-d").val()),
        correct_option: $card.find(".cta-q-correct").val() || "a",
        explanation: $.trim($card.find(".cta-q-explanation").val()),
        order_index: index
      });
    });

    return questions;
  }

  function renderQuizSavedList(questions, quizTitle) {
    var $list = $("#cta-quiz-saved-list");
    var $status = $("#cta-quiz-status-line");

    if (quizTitle) {
      $status.html(
        "<p>Assessment selected: <strong>" +
          $("<div>").text(quizTitle).html() +
          "</strong></p>"
      );
    }

    if (!questions || !questions.length) {
      $list.empty();
      if (!quizTitle) {
        $status.html("<p>No quiz created yet.</p>");
      }
      return;
    }

    var html =
      "<h3>Saved Questions (" + questions.length + ")</h3>" +
      '<ol class="cta-quiz-saved-list__items">';

    questions.forEach(function (question, index) {
      var text = question.question_text || "";
      if (text.length > 90) {
        text = text.substring(0, 90) + "...";
      }

      html +=
        "<li><strong>Q" +
        (index + 1) +
        ":</strong> " +
        $("<div>").text(text).html() +
        ' <span class="cta-quiz-saved-list__answer">(' +
        String(question.correct_option || "a").toUpperCase() +
        ")</span></li>";
    });

    html += "</ol>";
    $list.html(html);
  }

  function populateAssessmentSelect(quizzes, activeId) {
    var $select = $("#cta-active-quiz-select");
    if (!$select.length) {
      return;
    }

    $select.empty();
    (quizzes || []).forEach(function (quiz) {
      var opt = $("<option></option>")
        .attr("value", quiz.id)
        .text(quiz.title + (quiz.questions ? " (" + quiz.questions + " Q)" : ""));
      if (String(quiz.id) === String(activeId)) {
        opt.prop("selected", true);
      }
      $select.append(opt);
    });
  }

  function loadQuizPanel(courseId, quizId) {
    var payload = {
      action: "cta_load_quiz",
      nonce: ctaAdmin.nonce,
      course_id: courseId
    };
    if (quizId) {
      payload.quiz_id = quizId;
    }

    return $.post(ctaAdmin.ajaxUrl, payload).done(function (response) {
      if (response.success) {
        var quiz = response.data.quiz || null;
        var activeId = quiz ? quiz.id : 0;

        if (quiz && quiz.title) {
          $("#cta-quiz-title").val(quiz.title);
        } else {
          $("#cta-quiz-title").val("");
        }

        if (quiz && quiz.quiz_type && $("#cta-quiz-type").length) {
          $("#cta-quiz-type").val(quiz.quiz_type);
        }

        $("#cta-quiz-panel").attr("data-quiz-id", activeId || 0);
        populateAssessmentSelect(response.data.quizzes || [], activeId);
        renderQuizQuestions(response.data.questions || []);
        renderQuizSavedList(
          response.data.questions || [],
          quiz ? quiz.title : ""
        );
      } else {
        renderQuizQuestions([]);
        renderQuizSavedList([], "");
      }
    });
  }

  function createExamAssessment(courseId, quizType, title) {
    return $.post(ctaAdmin.ajaxUrl, {
      action: "cta_create_exam_assessment",
      nonce: ctaAdmin.nonce,
      course_id: courseId,
      quiz_type: quizType,
      quiz_title: title || ""
    });
  }

  function initQuizPanel() {
    var $panel = $("#cta-quiz-panel");

    if (!$panel.length) {
      return;
    }

    var courseId = $panel.data("course-id");
    var isExamPrep = String($panel.data("is-exam-prep") || "") === "1";
    var initialQuizId = parseInt($panel.data("quiz-id"), 10) || 0;

    loadQuizPanel(courseId, initialQuizId).fail(function () {
      renderQuizQuestions([]);
    });

    $("#cta-active-quiz-select").on("change", function () {
      var quizId = parseInt($(this).val(), 10) || 0;
      loadQuizPanel(courseId, quizId);
    });

    function handleAddAssessment(type, title) {
      createExamAssessment(courseId, type, title)
        .done(function (response) {
          if (!response.success) {
            if (response.data && response.data.quiz_id) {
              loadQuizPanel(courseId, response.data.quiz_id);
              return;
            }
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to create assessment."
            );
            return;
          }
          loadQuizPanel(courseId, response.data.quiz_id);
        })
        .fail(function () {
          window.alert("Unable to create assessment.");
        });
    }

    $("#cta-add-assessment-practice").on("click", function () {
      handleAddAssessment("practice", "Practice Assessment");
    });
    $("#cta-add-assessment-form-a").on("click", function () {
      handleAddAssessment("form_a", "Form A — Comprehensive Simulation");
    });
    $("#cta-add-assessment-form-b").on("click", function () {
      handleAddAssessment("form_b", "Form B — Comprehensive Simulation");
    });
    $("#cta-add-assessment-custom").on("click", function () {
      var title = window.prompt("Custom assessment title:", "Custom Assessment");
      if (!title) {
        return;
      }
      handleAddAssessment("custom", title);
    });

    $("#cta-add-quiz-question").on("click", function () {
      var count = $("#cta-quiz-questions .cta-quiz-question-card").length;
      $("#cta-quiz-questions").append(buildQuizQuestionCard(count, {}));
    });

    $(document).on("click", ".cta-remove-quiz-question", function () {
      var $cards = $("#cta-quiz-questions .cta-quiz-question-card");

      if ($cards.length <= 1) {
        $cards.find("input, textarea").val("");
        $cards.find(".cta-q-correct").val("a");
        return;
      }

      $(this).closest(".cta-quiz-question-card").remove();

      $("#cta-quiz-questions .cta-quiz-question-card").each(function (idx) {
        $(this).attr("data-index", idx);
        $(this)
          .find(".cta-quiz-question-card__header strong")
          .text("Question " + (idx + 1));
      });
    });

    $("#cta-save-quiz").on("click", function () {
      var btn = $(this);
      var $status = $("#cta-quiz-save-status");
      var questions = collectQuizQuestions();
      var quizId = parseInt($panel.attr("data-quiz-id"), 10) || 0;

      if (!questions.length) {
        window.alert("Please add at least one question.");
        return;
      }

      btn.prop("disabled", true).text("Saving...");
      $status.removeClass("is-success is-error").text("");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_save_quiz",
        nonce: ctaAdmin.nonce,
        course_id: courseId,
        quiz_id: quizId,
        quiz_title: $("#cta-quiz-title").val(),
        quiz_type: $("#cta-quiz-type").length ? $("#cta-quiz-type").val() : (isExamPrep ? "practice" : "final"),
        questions_json: JSON.stringify(questions)
      })
        .done(function (response) {
          if (!response.success) {
            $status
              .addClass("is-error")
              .text(
                response.data && response.data.message
                  ? response.data.message
                  : "Unable to save quiz."
              );
            return;
          }

          $status.addClass("is-success").text(response.data.message || "Quiz saved.");
          btn.text(isExamPrep ? "Save Assessment" : "Save Quiz");
          if (response.data.quiz && response.data.quiz.id) {
            $panel.attr("data-quiz-id", response.data.quiz.id);
          }
          populateAssessmentSelect(response.data.quizzes || [], response.data.quiz_id);
          renderQuizQuestions(response.data.questions || collectQuizQuestions());
          renderQuizSavedList(
            response.data.questions || collectQuizQuestions(),
            response.data.quiz ? response.data.quiz.title : $("#cta-quiz-title").val()
          );
        })
        .always(function () {
          btn.prop("disabled", false);
        });
    });
  }

  function initStripeTest() {
    $("#cta-test-stripe").on("click", function () {
      var $result = $("#cta-stripe-test-result");
      $result.removeClass("is-success is-error").text(ctaAdmin.i18n.stripeTesting);

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_test_stripe_connection",
        nonce: ctaAdmin.nonce,
        secret_key: $("#cta_stripe_secret_key").val()
      }).done(function (response) {
        if (response.success) {
          $result.addClass("is-success").text(response.data.message || ctaAdmin.i18n.stripeSuccess);
          return;
        }

        $result
          .addClass("is-error")
          .text(response.data && response.data.message ? response.data.message : ctaAdmin.i18n.stripeFailed);
      });
    });

    $("#cta-ensure-portal").on("click", function () {
      var $result = $("#cta-portal-test-result");
      $result.removeClass("is-success is-error").text("Configuring portal...");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_ensure_billing_portal",
        nonce: ctaAdmin.nonce
      }).done(function (response) {
        if (response.success) {
          $result.addClass("is-success").text(response.data.message || "Portal ready.");
          return;
        }

        $result
          .addClass("is-error")
          .text(response.data && response.data.message ? response.data.message : "Portal configuration failed.");
      }).fail(function () {
        $result.addClass("is-error").text("Portal configuration failed.");
      });
    });
  }

  function initCertificatePreview() {
    $("#cta-preview-certificate").on("click", function () {
      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_preview_certificate",
        nonce: ctaAdmin.nonce
      }).done(function (response) {
        if (!response.success || !response.data || !response.data.html) {
          window.alert("Unable to generate preview.");
          return;
        }

        var preview = window.open("", "_blank");
        if (preview) {
          preview.document.open();
          preview.document.write(response.data.html);
          preview.document.close();
        }
      });
    });
  }

  function initEmailSettings() {
    var $settings = $(".cta-email-settings");
    if (!$settings.length) return;

    function getEditorContent(editorId) {
      if (
        window.tinymce &&
        window.tinymce.get(editorId) &&
        !window.tinymce.get(editorId).isHidden()
      ) {
        return window.tinymce.get(editorId).getContent();
      }

      return $("#" + editorId).val() || "";
    }

    $settings.on("click", ".cta-email-tab", function () {
      var type = $(this).data("email-tab");

      $(".cta-email-tab")
        .removeClass("cta-email-tab--active")
        .attr("aria-selected", "false");
      $(this)
        .addClass("cta-email-tab--active")
        .attr("aria-selected", "true");

      $(".cta-email-panel").prop("hidden", true);
      $('.cta-email-panel[data-email-panel="' + type + '"]').prop(
        "hidden",
        false
      );
    });

    $settings.on("click", ".cta-preview-email", function () {
      var $button = $(this);
      var $panel = $button.closest(".cta-email-panel");
      var $result = $button.siblings(".cta-inline-result");
      var type = $button.data("email-type");
      var editorId = $button.data("editor-id");

      $button.prop("disabled", true);
      $result.removeClass("is-error is-success").text("Generating preview...");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_preview_email",
        nonce: ctaAdmin.nonce,
        email_type: type,
        subject: $panel.find(".cta-email-subject").val(),
        body: getEditorContent(editorId)
      })
        .done(function (response) {
          if (!response.success || !response.data || !response.data.html) {
            $result
              .addClass("is-error")
              .text(
                response.data && response.data.message
                  ? response.data.message
                  : "Unable to generate preview."
              );
            return;
          }

          $("#cta-email-preview-subject").text(
            response.data.subject || "Email Preview"
          );

          var frame = document.getElementById("cta-email-preview-frame");
          var frameDocument =
            frame.contentDocument ||
            (frame.contentWindow && frame.contentWindow.document);

          if (frameDocument) {
            frameDocument.open();
            frameDocument.write(response.data.html);
            frameDocument.close();
          }

          $("#cta-email-preview-modal").prop("hidden", false);
          $result.addClass("is-success").text("Preview ready.");
        })
        .fail(function () {
          $result.addClass("is-error").text("Unable to generate preview.");
        })
        .always(function () {
          $button.prop("disabled", false);
        });
    });

    $settings.find("form").on("submit", function () {
      if (window.tinyMCE && typeof window.tinyMCE.triggerSave === "function") {
        window.tinyMCE.triggerSave();
      }
    });
  }

  function initUserStats() {
    $(document).on("click", ".cta-view-user-stats", function () {
      var userId = $(this).data("user-id");
      var $modal = $("#cta-user-stats-modal");
      var $body = $("#cta-user-stats-body");

      $body.text("Loading...");
      $modal.prop("hidden", false);

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_get_stats",
        nonce: ctaAdmin.nonce,
        user_id: userId
      }).done(function (response) {
        if (!response.success) {
          $body.text("Unable to load stats.");
          return;
        }

        var data = response.data;
        $body.html(
          "<p><strong>Courses Enrolled:</strong> " + data.courses_enrolled + "</p>" +
            "<p><strong>Courses Completed:</strong> " + data.courses_completed + "</p>" +
            "<p><strong>Certificates:</strong> " + data.certificates_count + "</p>" +
            "<p><strong>Supervision Status:</strong> " + (data.supervision_status || "&mdash;") + "</p>" +
            "<p><strong>Total Paid:</strong> $" + data.total_paid + "</p>"
        );
      });
    });
  }

  function initUserLicenseEdit() {
    var $modal = $("#cta-user-license-modal");
    var $form = $("#cta-user-license-form");
    var $status = $("#cta-license-save-status");
    var $activeRow = null;

    if (!$modal.length || !$form.length || typeof ctaAdmin === "undefined") {
      return;
    }

    $(document).on("click", ".cta-edit-user-license", function () {
      var $row = $(this).closest("tr");
      $activeRow = $row;

      $("#cta-license-user-id").val($row.data("user-id") || "");
      $("#cta-license-number-input").val($row.data("license-number") || "");
      $("#cta-license-type-input").val($row.data("license-type") || "");
      $("#cta-license-modal-user").text(
        "Student: " + ($row.data("display-name") || "")
      );
      $status.text("");
      $modal.prop("hidden", false);
    });

    $form.on("submit", function (e) {
      e.preventDefault();

      var licenseNumber = $.trim($("#cta-license-number-input").val() || "");
      if (licenseNumber && !/[A-Za-z0-9]/.test(licenseNumber)) {
        $status.text("Include at least one letter or number.");
        return;
      }

      var $btn = $form.find('button[type="submit"]');
      $btn.prop("disabled", true);
      $status.text("Saving...");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_save_license",
        nonce: ctaAdmin.nonce,
        user_id: $("#cta-license-user-id").val(),
        license_number: licenseNumber,
        license_type: $("#cta-license-type-input").val() || ""
      })
        .done(function (response) {
          if (!response.success) {
            $status.text(
              (response.data && response.data.message) || "Save failed."
            );
            return;
          }

          var data = response.data || {};
          $status.text(data.message || "Saved.");

          if ($activeRow && $activeRow.length) {
            $activeRow.attr("data-license-number", data.license_number || "");
            $activeRow.attr("data-license-type", data.license_type || "");
            $activeRow.data("license-number", data.license_number || "");
            $activeRow.data("license-type", data.license_type || "");

            if (data.has_license) {
              $activeRow
                .find(".cta-user-license-number")
                .text(data.license_number || "");
            } else {
              $activeRow
                .find(".cta-user-license-number")
                .html(
                  '<span class="cta-status-badge cta-status-badge--draft">Missing</span>'
                );
            }

            $activeRow
              .find(".cta-user-license-type")
              .text(data.license_type ? data.license_type : "\u2014");
          }

          setTimeout(function () {
            $modal.prop("hidden", true);
          }, 600);
        })
        .fail(function () {
          $status.text("Something went wrong.");
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    });
  }

  function initModals() {
    $(document).on("click", ".cta-admin-modal__close", function () {
      $(this).closest(".cta-admin-modal").prop("hidden", true);
    });

    $("#cta-open-session-modal").on("click", function () {
      $("#cta-session-modal").prop("hidden", false);
    });

    $("#cta-session-type").on("change", function () {
      if ($(this).val() === "individual") {
        $("#cta-session-seats-wrap").hide();
      } else {
        $("#cta-session-seats-wrap").show();
      }
    });
  }

  function initBookings() {
    $("#cta-add-session-form").on("submit", function (e) {
      e.preventDefault();

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_add_session",
        nonce: ctaAdmin.nonce,
        session_date: $("#cta-session-date").val(),
        session_time: $("#cta-session-time").val(),
        session_type: $("#cta-session-type").val(),
        seats_total: $("#cta-session-seats").val()
      }).done(function (response) {
        if (!response.success) {
          window.alert(
            response.data && response.data.message
              ? response.data.message
              : "Unable to create session."
          );
          return;
        }

        $("#cta-sessions-list").append(response.data.html);
        $("#cta-session-modal").prop("hidden", true);
        $("#cta-add-session-form")[0].reset();
      });
    });

    $(document).on("click", ".cta-cancel-session", function () {
      if (!window.confirm(ctaAdmin.i18n.confirmCancel)) {
        return;
      }

      var row = $(this).closest("tr");
      var sessionId = $(this).data("session-id");

      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_cancel_session",
        nonce: ctaAdmin.nonce,
        session_id: sessionId
      }).done(function (response) {
        if (response.success) {
          row.remove();
        }
      });
    });
  }

  function initCourseVideoSource() {
    if (!$("#cta-course-video-type").length) {
      return;
    }

    function toggleCourseVideoUI() {
      var type = $("#cta-course-video-type").val();
      var $input = $("#cta-course-video-value");
      var $hidden = $("#cta-course-video-url");
      var $select = $("#cta-course-video-select");
      var $help = $(".cta-course-video-help");

      $help.hide();
      $help.filter('[data-help="' + type + '"]').show();

      if (type === "wordpress") {
        $input.attr("placeholder", "Select a video from Media Library");
        $input.prop("readonly", true);
        $select.show();
      } else if (type === "youtube") {
        $input.attr("placeholder", "https://www.youtube.com/watch?v=...");
        $input.prop("readonly", false);
        $select.hide();
      } else if (type === "vimeo") {
        $input.attr("placeholder", "Vimeo ID (numbers only)");
        $input.prop("readonly", false);
        $select.hide();
      } else {
        $input.attr("placeholder", "https://example.com/video.mp4");
        $input.prop("readonly", false);
        $select.hide();
      }

      if (type === "wordpress" && $hidden.val()) {
        $input.val($hidden.val());
      }
    }

    $("#cta-course-video-type").on("change", function () {
      $("#cta-course-video-value, #cta-course-video-url").val("");
      toggleCourseVideoUI();
    });

    $("#cta-course-video-select").on("click", function (e) {
      e.preventDefault();

      if (typeof wp === "undefined" || !wp.media) {
        window.alert("WordPress media library is not available.");
        return;
      }

      var frame = wp.media({
        title: "Select Video",
        button: { text: "Use this video" },
        library: { type: "video" },
        multiple: false
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        $("#cta-course-video-value").val(attachment.url || "");
        $("#cta-course-video-url").val(attachment.url || "");
      });

      frame.open();
    });

    toggleCourseVideoUI();
  }

  function initAssociateApprovals() {
    var $table = $("#cta-pending-approvals-table");
    var $notice = $("#cta-approvals-notice");
    var $detailsModal = $("#cta-purchase-details-modal");
    var $rejectModal = $("#cta-reject-associate-modal");

    if (!$table.length || typeof ctaAdmin === "undefined") {
      return;
    }

    function showNotice(type, message) {
      if (!$notice.length) {
        return;
      }

      $notice
        .removeClass("notice-success notice-error")
        .addClass(type === "success" ? "notice-success" : "notice-error")
        .html("<p>" + $("<div>").text(message).html() + "</p>")
        .prop("hidden", false)
        .removeAttr("hidden")
        .show();
    }

    function reloadApprovalsPage(flash) {
      var url = new URL(window.location.href);
      url.searchParams.set("cta_approval", flash);
      window.location.href = url.toString();
    }

    $table.on("click", ".cta-view-supervision-purchase", function () {
      var details = {};
      var rawDetails = $(this).attr("data-purchase-details");

      try {
        details = rawDetails ? JSON.parse(rawDetails) : {};
      } catch (e) {
        details = {};
      }

      var $list = $("#cta-purchase-details-list").empty();
      var fields = [
        ["Associate", details.user_name],
        ["Email", details.user_email],
        ["Approval Status", details.approval_status || details.status],
        ["Plan Status", details.plan_status || details.plan_name],
        ["Access", details.access],
        ["Plan", details.plan_name],
        ["Registered", details.registered_date],
        ["Purchase / Assigned", details.purchase_date],
        ["Assigned By", details.assigned_by],
        ["Assignment Note", details.assigned_note],
        ["Amount", details.amount],
        ["Billing", details.billing],
        ["Description", details.description],
        ["Stripe Reference", details.stripe_reference],
        ["Rejection Reason", details.rejection_reason]
      ];

      fields.forEach(function (field) {
        if (!field[1]) return;
        $("<dt>").text(field[0]).appendTo($list);
        $("<dd>").text(field[1]).appendTo($list);
      });

      $detailsModal.prop("hidden", false).removeAttr("hidden");
    });

    $table.on("click", ".cta-open-reject-associate", function () {
      var $button = $(this);
      var userId = $button.data("user-id");
      var userName = $button.data("user-name") || "";

      $("#cta-reject-user-id").val(userId);
      $("#cta-reject-nonce").val($button.data("review-nonce") || "");
      $("#cta-rejection-reason").val("");
      $("#cta-reject-associate-name").text(
        userName ? "Reject supervision access for " + userName + "?" : ""
      );
      $rejectModal.prop("hidden", false).removeAttr("hidden");
    });

    function handleFormSubmit(e) {
      var $form = $(this);
      var isApprove = $form.hasClass("cta-approval-form--approve");
      var isAssign = $form.hasClass("cta-approval-form--assign");
      var userId = $form.find('input[name="user_id"]').val();
      var reason = $form.find('[name="reason"]').val() || "";
      var planSlug = $form.find('[name="plan_slug"]').val() || "group";
      var note = $form.find('[name="note"]').val() || "";
      var $row = $form.closest("tr");

      var confirmMsg = isAssign
        ? (ctaAdmin.i18n && ctaAdmin.i18n.assignConfirm) ||
          "Assign this agency-paid plan to the Associate?"
        : isApprove
          ? ctaAdmin.i18n.approveConfirm
          : ctaAdmin.i18n.rejectConfirm;

      if (!window.confirm(confirmMsg)) {
        e.preventDefault();
        return;
      }

      // Prefer AJAX when available; fall back to normal form POST.
      if (!window.jQuery || !ctaAdmin.ajaxUrl) {
        return;
      }

      e.preventDefault();

      var $buttons = $row.length ? $row.find("button, select, input") : $form.find("button, select, input");

      $buttons.prop("disabled", true);

      var payload = {
        action: isAssign
          ? "cta_assign_associate_plan"
          : isApprove
            ? "cta_approve_associate"
            : "cta_reject_associate",
        nonce: ctaAdmin.nonce,
        user_id: userId,
        reason: reason,
        plan_slug: planSlug,
        note: note
      };

      $.post(ctaAdmin.ajaxUrl, payload)
        .done(function (response) {
          if (!response || !response.success) {
            $buttons.prop("disabled", false);
            showNotice(
              "error",
              (response && response.data && response.data.message) ||
                ctaAdmin.i18n.actionFailed
            );
            return;
          }

          showNotice(
            "success",
            (response.data && response.data.message) ||
              (isAssign
                ? ctaAdmin.i18n.assignSuccess
                : isApprove
                  ? ctaAdmin.i18n.approveSuccess
                  : ctaAdmin.i18n.rejectSuccess)
          );

          $rejectModal.prop("hidden", true);

          if (isAssign) {
            reloadApprovalsPage($table.attr("data-current-status") || "all");
            return;
          }

          // Keep the record visible: reload so Approved/Rejected tabs stay in sync.
          reloadApprovalsPage(isApprove ? "approved" : "rejected");
        })
        .fail(function (xhr) {
          $buttons.prop("disabled", false);
          var msg = ctaAdmin.i18n.actionFailed;
          if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          }
          showNotice("error", msg);
        });
    }

    $table.on("submit", ".cta-approval-form", handleFormSubmit);
    $rejectModal.on("submit", ".cta-approval-form", handleFormSubmit);
  }

  function initResourcesPanel() {
    var $panel = $("#cta-resources-panel");
    if (!$panel.length || typeof ctaAdmin === "undefined") {
      return;
    }

    var courseId = $panel.data("course-id");
    var $list = $("#cta-resources-list");

    if ($list.length && $.fn.sortable) {
      $list.sortable({
        handle: ".cta-drag-handle",
        update: function () {
          var order = [];
          $list.find("tr[data-resource-id]").each(function () {
            order.push($(this).data("resource-id"));
          });
          $.post(ctaAdmin.ajaxUrl, {
            action: "cta_reorder_resources",
            nonce: ctaAdmin.nonce,
            course_id: courseId,
            order: order
          });
        }
      });
    }

    var allowedResourceExt = ["pdf", "doc", "docx"];
    var maxResourceBytes = 20 * 1024 * 1024;

    function resourceFileExtension(filename) {
      if (!filename || filename.indexOf(".") === -1) {
        return "";
      }
      return String(filename.split(".").pop()).toLowerCase();
    }

    $("#cta-resource-select-file").on("click", function (e) {
      e.preventDefault();
      if (typeof wp === "undefined" || !wp.media) {
        window.alert("Media library is unavailable.");
        return;
      }

      var frame = wp.media({
        title: "Select course material (PDF, DOC, or DOCX \u2014 max 20MB)",
        button: { text: "Use this file" },
        multiple: false,
        library: {
          type: [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
          ]
        }
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        var filename = attachment.filename || attachment.title || "";
        var ext = resourceFileExtension(filename);
        var filesize = typeof attachment.filesizeInBytes === "number"
          ? attachment.filesizeInBytes
          : (typeof attachment.filesize === "number" ? attachment.filesize : 0);

        if (allowedResourceExt.indexOf(ext) === -1) {
          window.alert("Only PDF, DOC, and DOCX files are allowed for course materials.");
          return;
        }

        if (filesize > maxResourceBytes) {
          window.alert("File exceeds the 20MB size limit. Please upload a smaller PDF, DOC, or DOCX file.");
          return;
        }

        $("#cta-resource-attachment-id").val(attachment.id || "");
        $("#cta-resource-file-url").val(attachment.url || "");
        $("#cta-resource-file-label").text(filename || "File selected");
        if (!$("#cta-resource-file-type").val() && ext) {
          $("#cta-resource-file-type").val(ext);
        }
        if (!$("#cta-resource-title").val() && attachment.title) {
          $("#cta-resource-title").val(attachment.title);
        }
      });

      frame.open();
    });

    function resetResourceForm() {
      $("#cta-resource-id").val("");
      $("#cta-resource-attachment-id").val("");
      $("#cta-resource-file-url").val("");
      $("#cta-resource-title").val("");
      $("#cta-resource-module").val("0");
      $("#cta-resource-file-type").val("");
      $("#cta-resource-practice").prop("checked", false);
      $("#cta-resource-file-label").text("");
      $("#cta-resource-form-heading").text("Add Material");
      $("#cta-resource-submit").text("Add Material");
      $("#cta-resource-cancel-edit").hide();
    }

    $panel.on("click", ".cta-edit-resource", function () {
      var $row = $(this).closest("tr");
      $("#cta-resource-id").val($row.data("resource-id") || "");
      $("#cta-resource-title").val($row.data("title") || "");
      $("#cta-resource-module").val(String($row.data("module-id") || "0"));
      $("#cta-resource-file-type").val($row.data("file-type") || "");
      $("#cta-resource-practice").prop("checked", String($row.data("practice")) === "1");
      $("#cta-resource-attachment-id").val("");
      $("#cta-resource-file-url").val("");
      $("#cta-resource-file-label").text(
        "Current file kept unless you select a replacement"
      );
      $("#cta-resource-form-heading").text("Edit / Replace Material");
      $("#cta-resource-submit").text("Update Material");
      $("#cta-resource-cancel-edit").show();
      $("html, body").animate({ scrollTop: $("#cta-resource-form").offset().top - 80 }, 200);
    });

    $("#cta-resource-cancel-edit").on("click", function () {
      resetResourceForm();
    });
  }

  function initAdminSubscriptionControls() {
    $(document).on("click", ".cta-admin-sync-sub", function () {
      var userId = $(this).data("user-id");
      var $btn = $(this);
      $btn.prop("disabled", true);
      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_sync_subscription",
        nonce: ctaAdmin.nonce,
        user_id: userId
      })
        .done(function (response) {
          window.alert(
            response.success
              ? response.data.message
              : (response.data && response.data.message) || "Sync failed."
          );
          if (response.success) {
            window.location.reload();
          }
        })
        .fail(function () {
          window.alert("Sync failed. Please try again.");
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    });

    $(document).on("click", ".cta-admin-reactivate-sub", function () {
      if (!window.confirm("Reactivate this subscription so auto-renewal continues?")) {
        return;
      }
      var userId = $(this).data("user-id");
      var $btn = $(this);
      $btn.prop("disabled", true);
      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_reactivate_subscription",
        nonce: ctaAdmin.nonce,
        user_id: userId
      })
        .done(function (response) {
          window.alert(
            response.success
              ? response.data.message
              : (response.data && response.data.message) || "Reactivate failed."
          );
          if (response.success) {
            window.location.reload();
          }
        })
        .fail(function () {
          window.alert("Reactivate failed. Please try again.");
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    });

    $(document).on("click", ".cta-admin-cancel-sub", function () {
      var mode = $(this).data("mode") || "at_period_end";
      var msg =
        mode === "immediately"
          ? "Cancel this subscription immediately in Stripe? Access will end now."
          : "Cancel at period end? The student keeps access until the paid period ends.";
      if (!window.confirm(msg)) {
        return;
      }
      var userId = $(this).data("user-id");
      var $btn = $(this);
      $btn.prop("disabled", true);
      $.post(ctaAdmin.ajaxUrl, {
        action: "cta_admin_cancel_subscription",
        nonce: ctaAdmin.nonce,
        user_id: userId,
        mode: mode
      })
        .done(function (response) {
          window.alert(
            response.success
              ? response.data.message
              : (response.data && response.data.message) || "Cancel failed."
          );
          if (response.success) {
            window.location.reload();
          }
        })
        .fail(function () {
          window.alert("Cancel failed. Please try again.");
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    });
  }

  function initCourseEvaluationBuilder() {
    var $panel = $("#cta-course-evaluation-panel");

    if (!$panel.length || typeof ctaAdmin === "undefined") {
      return;
    }

    var courseId = $panel.data("course-id");

    function getQuestionOrder() {
      var order = [];
      $("#cta-course-eval-questions-list .cta-course-eval-row").each(function () {
        order.push($(this).data("question-id"));
      });
      return order;
    }

    function refreshTable(html) {
      if (html) {
        $("#cta-course-eval-questions-list").html(html);
      }
    }

    function resetEvalForm() {
      $("#cta-course-eval-question-id").val("0");
      $("#cta-course-eval-section, #cta-course-eval-label, #cta-course-eval-options").val("");
      $("#cta-course-eval-type").val("rating");
      $("#cta-course-eval-required").prop("checked", true);
      $("#cta-course-eval-status").val("active");
      $("#cta-course-eval-save").text("Save Question");
      $("#cta-course-eval-cancel").hide();
    }

    function postEvalAction(action, extra, $status) {
      var payload = $.extend(
        {
          action: action,
          nonce: ctaAdmin.nonce,
          course_id: courseId
        },
        extra || {}
      );

      if ($status) {
        $status.removeClass("is-success is-error").text("");
      }

      return $.post(ctaAdmin.ajaxUrl, payload).done(function (response) {
        if (!response.success) {
          var msg =
            response.data && response.data.message
              ? response.data.message
              : "Request failed.";
          if ($status) {
            $status.addClass("is-error").text(msg);
          } else {
            window.alert(msg);
          }
          return;
        }

        if (response.data && response.data.html) {
          refreshTable(response.data.html);
        }

        if ($status && response.data && response.data.message) {
          $status.addClass("is-success").text(response.data.message);
        }
      });
    }

    $("#cta-course-eval-save").on("click", function () {
      var $status = $("#cta-course-eval-save-status");
      var label = $("#cta-course-eval-label").val();

      if (!label || !String(label).trim()) {
        $status.removeClass("is-success").addClass("is-error").text("Question label is required.");
        return;
      }

      postEvalAction(
        "cta_save_course_eval_question",
        {
          question_id: $("#cta-course-eval-question-id").val(),
          section_label: $("#cta-course-eval-section").val(),
          label: label,
          question_type: $("#cta-course-eval-type").val(),
          options_text: $("#cta-course-eval-options").val(),
          is_required: $("#cta-course-eval-required").is(":checked") ? 1 : 0,
          status: $("#cta-course-eval-status").val()
        },
        $status
      ).done(function (response) {
        if (response.success) {
          resetEvalForm();
        }
      });
    });

    $("#cta-course-eval-cancel").on("click", function () {
      resetEvalForm();
      $("#cta-course-eval-save-status").text("");
    });

    $(document).on("click", ".cta-course-eval-edit", function () {
      var $row = $(this).closest(".cta-course-eval-row");
      $("#cta-course-eval-question-id").val($row.data("question-id"));
      $("#cta-course-eval-section").val($row.data("section"));
      $("#cta-course-eval-label").val($row.data("label"));
      $("#cta-course-eval-type").val($row.data("type"));
      $("#cta-course-eval-options").val($row.data("options"));
      $("#cta-course-eval-required").prop("checked", String($row.data("required")) === "1");
      $("#cta-course-eval-status").val($row.data("status"));
      $("#cta-course-eval-save").text("Update Question");
      $("#cta-course-eval-cancel").show();
      $("#cta-course-eval-save-status").text("");
    });

    $(document).on("click", ".cta-course-eval-delete", function () {
      if (!window.confirm(ctaAdmin.i18n.confirmDelete)) {
        return;
      }

      var $row = $(this).closest(".cta-course-eval-row");
      postEvalAction(
        "cta_delete_course_eval_question",
        { question_id: $row.data("question-id") },
        $("#cta-course-eval-action-status")
      ).done(function (response) {
        if (response.success) {
          resetEvalForm();
        }
      });
    });

    function moveQuestion($row, direction) {
      var $sibling = direction === "up" ? $row.prev(".cta-course-eval-row") : $row.next(".cta-course-eval-row");
      if (!$sibling.length) {
        return;
      }

      if (direction === "up") {
        $row.insertBefore($sibling);
      } else {
        $row.insertAfter($sibling);
      }

      postEvalAction(
        "cta_reorder_course_eval_questions",
        { order: getQuestionOrder() },
        $("#cta-course-eval-action-status")
      );
    }

    $(document).on("click", ".cta-course-eval-move-up", function () {
      moveQuestion($(this).closest(".cta-course-eval-row"), "up");
    });

    $(document).on("click", ".cta-course-eval-move-down", function () {
      moveQuestion($(this).closest(".cta-course-eval-row"), "down");
    });

    $("#cta-sync-eval-objectives").on("click", function () {
      postEvalAction("cta_sync_course_eval_objectives", {}, $("#cta-course-eval-action-status"));
    });

    $("#cta-copy-eval-camft").on("click", function () {
      postEvalAction("cta_copy_course_eval_camft", {}, $("#cta-course-eval-action-status"));
    });
  }

  $(function () {
    if (typeof ctaAdmin === "undefined") {
      return;
    }

    try { initCopyButtons(); } catch (e) {}
    try { initDeleteConfirms(); } catch (e) {}
    try { initSlugGeneration(); } catch (e) {}
    try { initObjectivesRepeater(); } catch (e) {}
    try { initCourseVideoSource(); } catch (e) {}
    try { initModulesPanel(); } catch (e) {}
    try { initCourseEvaluationBuilder(); } catch (e) {}
    try { initQuizPanel(); } catch (e) {}
    try { initResourcesPanel(); } catch (e) {}
    try { initStripeTest(); } catch (e) {}
    try { initCertificatePreview(); } catch (e) {}
    try { initEmailSettings(); } catch (e) {}
    try { initUserStats(); } catch (e) {}
    try { initUserLicenseEdit(); } catch (e) {}
    try { initModals(); } catch (e) {}
    try { initBookings(); } catch (e) {}
    try { initAssociateApprovals(); } catch (e) {}
    try { initAdminSubscriptionControls(); } catch (e) {}
  });
})(jQuery);
