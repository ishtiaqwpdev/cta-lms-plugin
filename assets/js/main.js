/**
 * CTA Design System -- Main JavaScript
 * Clinical Training and Supervision Academy
 */

(function () {
  "use strict";

  var CTA_ICON_CHECK_CIRCLE =
    '<svg class="cta-icon cta-icon--inline" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="8 12 11 15 16 9"></polyline></svg>';

  var CTA_ICON_CHECK =
    '<svg class="cta-icon cta-icon--inline" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>';
  var SESSION_KEY = "cta_session";

  function loadUsers() {
    try {
      var raw = localStorage.getItem(USERS_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (err) {
      return [];
    }
  }

  function saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
  }

  function setSession(email) {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify({ email: email.toLowerCase() }));
  }

  function clearSession() {
    sessionStorage.removeItem(SESSION_KEY);
  }

  function getCurrentUser() {
    try {
      var raw = sessionStorage.getItem(SESSION_KEY);
      if (!raw) return null;
      var session = JSON.parse(raw);
      var users = loadUsers();
      return users.find(function (user) {
        return user.email === session.email;
      }) || null;
    } catch (err) {
      return null;
    }
  }

  function getInitials(fullName) {
    var parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "?";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function getFirstName(fullName) {
    return fullName.trim().split(/\s+/)[0] || "there";
  }

  function getUserLicenseLabel(user) {
    if (user.licenseNumber) return user.licenseNumber;
    return user.userType === "associate" ? "Registered Associate" : "Licensed Professional";
  }

  function findUserByLogin(identifier) {
    var value = identifier.trim().toLowerCase();
    var users = loadUsers();
    return users.find(function (user) {
      return user.email === value || user.email.split("@")[0] === value;
    }) || null;
  }

  function registerUser(profile) {
    var users = loadUsers();
    var email = profile.email.trim().toLowerCase();

    if (users.some(function (user) { return user.email === email; })) {
      return { ok: false, message: "An account with this email already exists. Please log in instead." };
    }

    var user = {
      email: email,
      fullName: profile.fullName.trim(),
      password: profile.password,
      userType: profile.userType || "associate",
      licenseNumber: profile.userType === "associate" ? "Registered Associate" : "Licensed Professional"
    };

    users.push(user);
    saveUsers(users);
    setSession(email);
    return { ok: true, user: user };
  }

  function loginUser(identifier, password) {
    var user = findUserByLogin(identifier);
    if (!user || user.password !== password) {
      return { ok: false, message: "Invalid user name or password. Please try again or sign up." };
    }
    setSession(user.email);
    return { ok: true, user: user };
  }

  function updateUserProfile(email, updates) {
    var users = loadUsers();
    var index = users.findIndex(function (user) { return user.email === email; });
    if (index === -1) return null;

    users[index] = Object.assign({}, users[index], updates, { email: email });
    saveUsers(users);
    return users[index];
  }

  function applyUserToDashboard(user) {
    var firstName = getFirstName(user.fullName);
    var initials = getInitials(user.fullName);
    var licenseLabel = getUserLicenseLabel(user);

    document.querySelectorAll("[data-user-avatar]").forEach(function (el) {
      if (user.avatarUrl) {
        el.textContent = "";
        el.style.backgroundImage = "url(\"" + user.avatarUrl + "\")";
        el.classList.add("dashboard-sidebar__avatar--photo");
      } else {
        el.textContent = initials;
        el.style.backgroundImage = "";
        el.classList.remove("dashboard-sidebar__avatar--photo");
      }
    });
    document.querySelectorAll("[data-user-name]").forEach(function (el) {
      el.textContent = user.fullName;
    });
    document.querySelectorAll("[data-user-license]").forEach(function (el) {
      el.textContent = licenseLabel;
    });
    document.querySelectorAll("[data-user-greeting]").forEach(function (el) {
      el.textContent = "Welcome back, " + firstName;
    });

    var nameInput = document.getElementById("settings-name");
    var emailInput = document.getElementById("settings-email");
    var licenseInput = document.getElementById("settings-license");
    var typeInput = document.getElementById("settings-type");
    var photoPreview = document.querySelector("[data-profile-photo-preview]");
    var photoImage = document.querySelector("[data-profile-photo-image]");

    if (nameInput) nameInput.value = user.fullName;
    if (emailInput) emailInput.value = user.email;
    if (licenseInput) licenseInput.value = user.licenseNumber || licenseLabel;
    if (typeInput && user.userType === "licensed") {
      typeInput.value = "lmft";
    }

    if (photoPreview && photoImage) {
      if (user.avatarUrl) {
        photoPreview.textContent = initials;
        photoPreview.hidden = true;
        photoImage.src = user.avatarUrl;
        photoImage.hidden = false;
      } else {
        photoPreview.textContent = initials;
        photoPreview.hidden = false;
        photoImage.src = "";
        photoImage.hidden = true;
      }
    }
  }

  function initProfilePhotoUpload() {
    var fileInput = document.getElementById("settings-photo");
    if (!fileInput) return;

    fileInput.addEventListener("change", function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      var maxSize = 10 * 1024 * 1024;
      if (file.size > maxSize) {
        fileInput.value = "";
        return;
      }

      var reader = new FileReader();
      reader.onload = function () {
        var user = getCurrentUser();
        if (!user) return;

        var updated = updateUserProfile(user.email, { avatarUrl: reader.result });
        if (updated) applyUserToDashboard(updated);
        fileInput.value = "";
      };
      reader.onerror = function () {
        fileInput.value = "";
      };
      reader.readAsDataURL(file);
    });
  }

  function initDashboardUser() {
    if (!document.body.classList.contains("dashboard-page")) return;

    // In the live WordPress environment the server is the single source of
    // truth for the session. Trust ctaAjax.isLoggedIn regardless of which
    // dashboard surface rendered (dashboard, course player, quiz, etc.) so we
    // never bounce an authenticated user to the login page.
    if (typeof ctaAjax !== "undefined") {
      if (ctaAjax.isLoggedIn === "yes") {
        var userDataEl = document.querySelector("[data-dashboard-user]");
        if (userDataEl && userDataEl.getAttribute("data-dashboard-user")) {
          try {
            var userData = JSON.parse(userDataEl.getAttribute("data-dashboard-user"));
            applyWpDashboardUser(userData);
          } catch (err) {
            /* ignore invalid JSON */
          }
        }
        return;
      }

      window.location.href = ctaAjax.loginUrl ? ctaAjax.loginUrl : "login.html";
      return;
    }

    // Static HTML mockup fallback (localStorage demo session, no WordPress).
    var user = getCurrentUser();
    if (!user) {
      window.location.href = "login.html";
      return;
    }

    applyUserToDashboard(user);
    initProfilePhotoUpload();

    document.querySelectorAll("[data-auth-logout]").forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        clearSession();
        window.location.href = "login.html";
      });
    });
  }

  function applyWpDashboardUser(userData) {
    if (!userData) return;

    var initials = userData.initials || "--";
    var name = userData.displayName || "";
    var license = userData.licenseNumber || userData.associateNumber || "";

    document.querySelectorAll("[data-user-avatar]").forEach(function (el) {
      el.textContent = initials;
    });
    document.querySelectorAll("[data-user-name]").forEach(function (el) {
      el.textContent = name;
    });
    document.querySelectorAll("[data-user-license]").forEach(function (el) {
      el.textContent = license;
    });
  }

  /**
   * Supervision associate dashboard -- uploads, deletes, portal, cancel booking.
   */
  function initCtaSupervisionDashboard() {
    var root = document.querySelector(".cta-supervision-dashboard");

    if (!root || typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;
    var uploadZone = document.getElementById("cta-upload-zone");
    var uploadInput = document.getElementById("cta-upload-input");
    var uploadProgress = document.getElementById("cta-upload-progress");
    var uploadError = document.getElementById("cta-upload-error");
    var categorySelect = document.getElementById("cta-doc-category");
    var allowedExt = ["pdf", "doc", "docx"];
    var maxBytes = 10 * 1024 * 1024;

    function showUploadError(message) {
      if (!uploadError) return;
      uploadError.textContent = message;
      uploadError.hidden = false;
    }

    function clearUploadError() {
      if (!uploadError) return;
      uploadError.textContent = "";
      uploadError.hidden = true;
    }

    function validateFile(file) {
      if (!file) {
        return "No file selected.";
      }

      if (file.size > maxBytes) {
        return "File exceeds the 10MB limit.";
      }

      var parts = file.name.split(".");
      var ext = parts.length > 1 ? parts.pop().toLowerCase() : "";

      if (allowedExt.indexOf(ext) === -1) {
        return "Only PDF, DOC, and DOCX files are allowed.";
      }

      return "";
    }

    function uploadFile(file) {
      var validationError = validateFile(file);

      if (validationError) {
        showUploadError(validationError);
        return;
      }

      clearUploadError();

      if (uploadProgress) {
        uploadProgress.hidden = false;
      }

      var formData = new FormData();
      formData.append("action", "cta_upload_document");
      formData.append("nonce", ctaAjax.nonce);
      formData.append("document_file", file);
      formData.append("doc_category", categorySelect ? categorySelect.value : "other");

      $.ajax({
        url: ctaAjax.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (uploadProgress) {
            uploadProgress.hidden = true;
          }

          if (!response.success || !response.data || !response.data.html) {
            showUploadError(
              response.data && response.data.message
                ? response.data.message
                : "Upload failed."
            );
            return;
          }

          var list = document.getElementById("cta-document-list");
          var empty = document.getElementById("cta-document-empty");

          if (empty) {
            empty.remove();
          }

          if (list) {
            list.insertAdjacentHTML("afterbegin", response.data.html);
          }
        },
        error: function () {
          if (uploadProgress) {
            uploadProgress.hidden = true;
          }
          showUploadError("Something went wrong. Please try again.");
        }
      });
    }

    if (uploadZone && uploadInput) {
      uploadZone.addEventListener("click", function () {
        uploadInput.click();
      });

      uploadZone.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          uploadInput.click();
        }
      });

      uploadInput.addEventListener("change", function () {
        if (uploadInput.files && uploadInput.files[0]) {
          uploadFile(uploadInput.files[0]);
          uploadInput.value = "";
        }
      });

      uploadZone.addEventListener("dragover", function (e) {
        e.preventDefault();
        uploadZone.classList.add("upload-zone--highlight");
      });

      uploadZone.addEventListener("dragleave", function () {
        uploadZone.classList.remove("upload-zone--highlight");
      });

      uploadZone.addEventListener("drop", function (e) {
        e.preventDefault();
        uploadZone.classList.remove("upload-zone--highlight");

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          uploadFile(e.dataTransfer.files[0]);
        }
      });
    }

    $(document).on("click", ".cta-delete-doc", function () {
      var btn = $(this);
      var documentId = btn.data("document-id");
      var row = btn.closest(".cta-document-row");

      if (!documentId || !window.confirm("Are you sure? This cannot be undone.")) {
        return;
      }

      btn.prop("disabled", true);

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_delete_document",
        nonce: ctaAjax.nonce,
        document_id: documentId
      })
        .done(function (response) {
          if (response.success && row.length) {
            row.fadeOut(300, function () {
              row.remove();
            });
            return;
          }

          window.alert(
            response.data && response.data.message
              ? response.data.message
              : "Unable to delete document."
          );
          btn.prop("disabled", false);
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          btn.prop("disabled", false);
        });
    });

    $(document).on("click", ".cta-cancel-booking", function () {
      var btn = $(this);
      var bookingId = btn.data("booking-id");
      var sessionDatetime = btn.data("session-datetime") || btn.data("session-date");
      var sessionStart = sessionDatetime ? new Date(String(sessionDatetime).replace(" ", "T")).getTime() : 0;
      var cutoff = Date.now() + 24 * 60 * 60 * 1000;

      if (sessionStart && sessionStart <= cutoff) {
        window.alert("Cannot cancel within 24 hours of the session.");
        return;
      }

      if (!window.confirm("Cancel this session booking?")) {
        return;
      }

      btn.prop("disabled", true).text("Cancelling...");

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_cancel_booking",
        nonce: ctaAjax.nonce,
        booking_id: bookingId
      })
        .done(function (response) {
          if (!response.success) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to cancel booking."
            );
            btn.prop("disabled", false).text("Cancel Booking");
            return;
          }

          var card = btn.closest(".cta-session-upcoming-card");
          card.find(".session-card__actions").html(
            '<span class="badge badge--outline">Cancelled</span>'
          );
          card.find(".badge--success").remove();
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          btn.prop("disabled", false).text("Cancel Booking");
        });
    });

    $(document).on("click", ".cta-manage-subscription", function () {
      var btn = $(this);
      var originalText = btn.text();

      btn.prop("disabled", true).text("Redirecting...");

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_get_portal_url",
        nonce: ctaAjax.nonce
      })
        .done(function (response) {
          btn.prop("disabled", false).text(originalText);

          if (response.success && response.data && response.data.demo_mode) {
            showDemoSubscriptionModal(response.data);
            return;
          }

          if (response.success && response.data && response.data.portal_url) {
            window.location.href = response.data.portal_url;
            return;
          }

          window.alert(
            response.data && response.data.message
              ? response.data.message
              : "Unable to open billing portal."
          );
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          btn.prop("disabled", false).text(originalText);
        });
    });
  }

  /**
   * Refresh an open Supervision Application Pending dashboard as soon as an admin approves it.
   */
  function initCtaSupervisionApprovalWatcher() {
    var pending = document.querySelector(
      ".cta-supervision-dashboard .cta-supervision-pending-approval"
    );

    if (!pending || typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var stopped = false;

    function checkAccess() {
      if (stopped) return;

      jQuery
        .post(ctaAjax.ajaxUrl, {
          action: "cta_get_supervision_access_status",
          nonce: ctaAjax.nonce
        })
        .done(function (response) {
          if (
            response.success &&
            response.data &&
            response.data.access_granted
          ) {
            stopped = true;
            window.location.reload();
          }
        })
        .always(function () {
          if (!stopped) {
            window.setTimeout(checkAccess, 5000);
          }
        });
    }

    window.setTimeout(checkAccess, 2000);
  }

  /**
   * Mobile menu toggle
   * Toggles .site-header__nav visibility and hamburger animation
   */
  function initMobileMenu() {
    const toggle = document.querySelector(".mobile-menu-toggle");
    const nav = document.querySelector(".site-header__nav");

    if (!toggle || !nav) return;

    toggle.addEventListener("click", function () {
      const isOpen = nav.classList.toggle("site-header__nav--open");
      toggle.classList.toggle("mobile-menu-toggle--active", isOpen);
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      document.body.style.overflow = isOpen ? "hidden" : "";
    });

    nav.querySelectorAll(".site-header__nav-link").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("site-header__nav--open");
        toggle.classList.remove("mobile-menu-toggle--active");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && nav.classList.contains("site-header__nav--open")) {
        nav.classList.remove("site-header__nav--open");
        toggle.classList.remove("mobile-menu-toggle--active");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      }
    });
  }

  /**
   * Generic accordion toggle
   * Works with any .accordion-item inside an .accordion container
   * Set data-accordion="single" on the container to allow only one open item
   */
  function initAccordion() {
    document.querySelectorAll(".accordion").forEach(function (accordion) {
      const isSingle = accordion.dataset.accordion === "single";

      accordion.querySelectorAll(".accordion-item").forEach(function (item) {
        const header = item.querySelector(".accordion-item__header");
        if (!header) return;

        const isInitiallyActive = item.classList.contains("accordion-item--active");
        header.setAttribute("aria-expanded", isInitiallyActive ? "true" : "false");

        header.addEventListener("click", function () {
          const isActive = item.classList.contains("accordion-item--active");

          if (isSingle) {
            accordion.querySelectorAll(".accordion-item--active").forEach(function (openItem) {
              if (openItem !== item) {
                openItem.classList.remove("accordion-item--active");
                const openHeader = openItem.querySelector(".accordion-item__header");
                if (openHeader) openHeader.setAttribute("aria-expanded", "false");
              }
            });
          }

          item.classList.toggle("accordion-item--active", !isActive);
          header.setAttribute("aria-expanded", !isActive ? "true" : "false");
        });
      });
    });
  }

  /**
   * Generic tab switcher
   * Expects structure:
   *   .tabs
   *     .tabs__list > .tabs__tab[data-tab="id"]
   *     .tabs__panel[data-tab-panel="id"]
   */
  function initTabs() {
    document.querySelectorAll(".tabs").forEach(function (tabsContainer) {
      const tabButtons = tabsContainer.querySelectorAll(".tabs__tab");
      const tabPanels = tabsContainer.querySelectorAll(".tabs__panel");

      if (!tabButtons.length || !tabPanels.length) return;

      tabButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          const targetId = button.dataset.tab;
          if (!targetId) return;

          tabButtons.forEach(function (btn) {
            btn.classList.remove("tabs__tab--active");
            btn.setAttribute("aria-selected", "false");
          });

          tabPanels.forEach(function (panel) {
            panel.classList.remove("tabs__panel--active");
            panel.setAttribute("hidden", "");
          });

          button.classList.add("tabs__tab--active");
          button.setAttribute("aria-selected", "true");

          const targetPanel = tabsContainer.querySelector(
            '[data-tab-panel="' + targetId + '"]'
          );

          if (targetPanel) {
            targetPanel.classList.add("tabs__panel--active");
            targetPanel.removeAttribute("hidden");
          }
        });
      });
    });
  }

  function initDashboardMobileMenu() {
    document.querySelectorAll(".dashboard-layout").forEach(function (layout) {
      var toggle = layout.parentElement.querySelector("[data-dashboard-menu-toggle]") ||
        layout.querySelector("[data-dashboard-menu-toggle]");
      if (!toggle) return;

      function closeMenu() {
        layout.classList.remove("dashboard-layout--menu-open");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      }

      function openMenu() {
        layout.classList.add("dashboard-layout--menu-open");
        toggle.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
      }

      toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        if (layout.classList.contains("dashboard-layout--menu-open")) {
          closeMenu();
        } else {
          openMenu();
        }
      });

      document.addEventListener("click", function (e) {
        if (!layout.classList.contains("dashboard-layout--menu-open")) {
          return;
        }

        var sidebar = layout.querySelector(".dashboard-sidebar");
        if (sidebar && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
          closeMenu();
        }
      });

      layout.querySelectorAll(".dashboard-sidebar__link").forEach(function (link) {
        link.addEventListener("click", function () {
          if (window.matchMedia("(max-width: 992px)").matches) {
            closeMenu();
          }
        });
      });
    });
  }

  /**
   * Dashboard sidebar panel switcher
   * Container: [data-dashboard]
   * Nav links: [data-dashboard-nav="panel-id"]
   * Panels: [data-dashboard-panel="panel-id"]
   */
  function initDashboardNav() {
    const layout = document.querySelector("[data-dashboard]");
    if (!layout) return;

    const links = layout.querySelectorAll("[data-dashboard-nav]");
    const panels = layout.querySelectorAll("[data-dashboard-panel]");
    if (!links.length || !panels.length) return;

    function showPanel(panelId) {
      links.forEach(function (link) {
        const isActive = link.dataset.dashboardNav === panelId;
        link.classList.toggle("dashboard-sidebar__link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });

      panels.forEach(function (panel) {
        const isActive = panel.dataset.dashboardPanel === panelId;
        panel.hidden = !isActive;
        panel.classList.toggle("dashboard-panel--active", isActive);
      });

      if (panelId && panelId !== "courses" && panelId !== "sessions") {
        window.location.hash = panelId;
      } else {
        history.replaceState(null, "", window.location.pathname + window.location.search);
      }
    }

    links.forEach(function (link) {
      link.addEventListener("click", function (e) {
        const panelId = link.dataset.dashboardNav;
        if (!panelId) return;

        const href = link.getAttribute("href") || "";
        const isSamePage =
          href === "#" ||
          href.startsWith("#") ||
          (href.indexOf("dashboard-ce.html") !== -1 && !href.includes("course-player")) ||
          href.indexOf("dashboard-supervision.html") !== -1;

        if (isSamePage && layout.contains(link)) {
          e.preventDefault();
          showPanel(panelId);
        }
      });
    });

    const hash = window.location.hash.replace("#", "");
    if (hash && layout.querySelector('[data-dashboard-panel="' + hash + '"]')) {
      showPanel(hash);
    }
  }

  /**
   * Dashboard settings save (mock -- static HTML demo only).
   */
  function initDashboardSettings() {
    // Live WordPress uses initCtaDashboardSettings + cta_save_profile.
    if (typeof ctaAjax !== "undefined") {
      return;
    }

    document.querySelectorAll(".dashboard-settings-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        handleSettingsSave(form);
      });
    });
  }

  function handleSettingsSave(form) {
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    var user = getCurrentUser();
    if (user) {
      var fullNameInput = form.querySelector('[name="full_name"]');
      var licenseInput = form.querySelector('[name="license_number"]');
      var updated = updateUserProfile(user.email, {
        fullName: fullNameInput ? fullNameInput.value : user.fullName,
        licenseNumber: licenseInput ? licenseInput.value : user.licenseNumber
      });
      if (updated) applyUserToDashboard(updated);
    }

    var existing = form.querySelector(".dashboard-settings__notice");
    if (existing) existing.remove();

    var notice = document.createElement("p");
    notice.className = "dashboard-settings__notice dashboard-settings__notice--success";
    notice.setAttribute("role", "status");
    notice.textContent = "Your changes have been saved successfully.";
    form.insertBefore(notice, form.firstChild);

    var btn = form.querySelector('[type="submit"]');
    if (btn) {
      var originalText = btn.textContent;
      btn.textContent = "Saved!";
      btn.disabled = true;
      setTimeout(function () {
        btn.textContent = originalText;
        btn.disabled = false;
      }, 2000);
    }
  }

  function initCoursePlayer() {
    var layout = document.querySelector("[data-course-player]");
    if (!layout) return;

    var video = layout.querySelector(".course-player__video");
    var markBtn = layout.querySelector("[data-mark-complete]");
    var quizBtn = layout.querySelector("[data-take-quiz]");
    var modal = document.getElementById("course-quiz-modal");
    var quizForm = document.getElementById("course-quiz-form");
    var quizResult = document.getElementById("course-quiz-result");

    var correctAnswers = { q1: "a", q2: "b", q3: "b" };

    function showPlayerNotice(message, type) {
      var existing = layout.querySelector(".course-player__notice");
      if (existing) existing.remove();

      var notice = document.createElement("p");
      notice.className = "course-player__notice" + (type ? " course-player__notice--" + type : "");
      notice.setAttribute("role", "status");
      notice.textContent = message;

      var actions = layout.querySelector("[data-course-player-actions]");
      if (actions) {
        actions.insertAdjacentElement("afterend", notice);
      }
    }

    function enableQuiz() {
      if (!quizBtn) return;
      quizBtn.disabled = false;
      quizBtn.removeAttribute("aria-disabled");
      quizBtn.classList.remove("btn-outline");
      quizBtn.classList.add("btn-primary");
    }

    function markLessonComplete() {
      if (video) {
        video.classList.add("course-player__video--watched");
        video.classList.remove("course-player__video--playing");
      }
      if (markBtn && !markBtn.disabled) {
        markBtn.textContent = "Completed";
        markBtn.disabled = true;
        markBtn.setAttribute("aria-disabled", "true");
      }
      enableQuiz();
      showPlayerNotice("Lesson marked complete. You can now take the quiz.", "success");
    }

    function startVideoPlayback() {
      if (!video || video.classList.contains("course-player__video--watched")) return;
      if (video.classList.contains("course-player__video--playing")) return;

      video.classList.add("course-player__video--playing");
      showPlayerNotice("Video playing\u2026 (demo preview)", "info");

      setTimeout(function () {
        video.classList.remove("course-player__video--playing");
        video.classList.add("course-player__video--watched");
        enableQuiz();
        showPlayerNotice("Video complete. Mark as complete or take the quiz.", "success");
      }, 2500);
    }

    if (video) {
      video.addEventListener("click", startVideoPlayback);
      video.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          startVideoPlayback();
        }
      });
    }

    if (markBtn) {
      markBtn.addEventListener("click", markLessonComplete);
    }

    function openQuizModal() {
      if (!modal || quizBtn.disabled) return;

      modal.hidden = false;
      modal.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden";

      if (quizForm) quizForm.hidden = false;
      if (quizResult) {
        quizResult.hidden = true;
        quizResult.textContent = "";
        quizResult.className = "course-quiz-result";
      }

      var closeBtn = modal.querySelector(".course-quiz-modal__close");
      if (closeBtn) closeBtn.focus();
    }

    function closeQuizModal() {
      if (!modal) return;

      modal.hidden = true;
      modal.setAttribute("aria-hidden", "true");
      document.body.style.overflow = "";

      if (quizBtn && !quizBtn.disabled) quizBtn.focus();
    }

    if (quizBtn && modal) {
      quizBtn.addEventListener("click", openQuizModal);

      modal.querySelectorAll("[data-quiz-close]").forEach(function (el) {
        el.addEventListener("click", closeQuizModal);
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !modal.hidden) {
          closeQuizModal();
        }
      });
    }

    if (quizForm && quizResult) {
      quizForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!quizForm.checkValidity()) {
          quizForm.reportValidity();
          return;
        }

        var score = 0;
        var total = Object.keys(correctAnswers).length;

        Object.keys(correctAnswers).forEach(function (key) {
          var selected = quizForm.querySelector('input[name="' + key + '"]:checked');
          if (selected && selected.value === correctAnswers[key]) {
            score += 1;
          }
        });

        var passed = score === total;

        quizForm.hidden = true;
        quizResult.hidden = false;
        quizResult.classList.add(passed ? "course-quiz-result--pass" : "course-quiz-result--fail");

        if (passed) {
          quizResult.innerHTML =
            "<strong>Quiz passed!</strong> You scored " +
            score +
            " of " +
            total +
            ". Module 3 is complete.";
          quizBtn.textContent = "Quiz Passed";
          quizBtn.disabled = true;
          quizBtn.setAttribute("aria-disabled", "true");
        } else {
          quizResult.innerHTML =
            "<strong>Not quite.</strong> You scored " +
            score +
            " of " +
            total +
            '. Review the lesson and try again. <button type="button" class="btn btn-primary course-quiz-result__retry">Retry Quiz</button>';
          quizResult.querySelector(".course-quiz-result__retry").addEventListener("click", function () {
            quizForm.reset();
            quizForm.hidden = false;
            quizResult.hidden = true;
          });
        }
      });
    }
  }

  /**
   * Course catalog -- category filters, sort, search
   */
  function initCatalogFilters() {
    if (document.getElementById("cta-courses-grid")) {
      return;
    }

    var catalog = document.querySelector("[data-course-catalog]");
    if (!catalog) return;

    var cards = Array.from(catalog.querySelectorAll(".course-card--catalog"));
    var filterGroup = document.querySelector("[data-catalog-filter]");
    var pills = filterGroup ? filterGroup.querySelectorAll(".catalog-filter__pill") : [];
    var sortSelect = document.getElementById("course-sort");
    var searchForm = document.querySelector(".course-banner__search-form");
    var searchInput = document.querySelector(".course-banner__search-input");
    var emptyEl = document.querySelector("[data-course-catalog-empty]");
    var pagination = document.querySelector("[data-course-pagination]");

    var currentFilter = "all";
    var currentSearch = "";
    var currentPage = 1;
    var perPage = 2;

    cards.forEach(function (card, index) {
      card.dataset.popular = String(index + 1);

      if (!card.dataset.price) {
        var priceEl = card.querySelector(".course-card__price");
        if (priceEl) {
          card.dataset.price = priceEl.textContent.replace(/[^0-9.]/g, "") || "0";
        }
      }

      if (!card.dataset.ceHours) {
        var badgeEl = card.querySelector(".course-card__badge");
        if (badgeEl) {
          card.dataset.ceHours = badgeEl.textContent.replace(/[^0-9.]/g, "") || "0";
        }
      }
    });

    function cardMatches(card) {
      var category = card.dataset.category || "";
      var matchCategory = currentFilter === "all" || category === currentFilter;

      if (!currentSearch) return matchCategory;

      var titleEl = card.querySelector(".course-card__title");
      var textEl = card.querySelector(".card__text");
      var title = titleEl ? titleEl.textContent.toLowerCase() : "";
      var text = textEl ? textEl.textContent.toLowerCase() : "";
      var matchSearch = title.indexOf(currentSearch) !== -1 || text.indexOf(currentSearch) !== -1;

      return matchCategory && matchSearch;
    }

    function sortCards(list) {
      var sortValue = sortSelect ? sortSelect.value : "popular";
      var sorted = list.slice();

      sorted.sort(function (a, b) {
        if (sortValue === "price-asc") {
          return parseFloat(a.dataset.price || 0) - parseFloat(b.dataset.price || 0);
        }

        if (sortValue === "ce-hours") {
          return parseFloat(b.dataset.ceHours || 0) - parseFloat(a.dataset.ceHours || 0);
        }

        return parseFloat(a.dataset.popular || 0) - parseFloat(b.dataset.popular || 0);
      });

      return sorted;
    }

    function getPageNumbers(totalPages, page) {
      if (totalPages <= 5) {
        var all = [];
        for (var i = 1; i <= totalPages; i += 1) {
          all.push(i);
        }
        return all;
      }

      var items = [1];

      if (page > 3) {
        items.push("...");
      }

      var start = Math.max(2, page - 1);
      var end = Math.min(totalPages - 1, page + 1);

      for (var p = start; p <= end; p += 1) {
        items.push(p);
      }

      if (page < totalPages - 2) {
        items.push("...");
      }

      items.push(totalPages);
      return items;
    }

    function renderPagination(totalItems) {
      if (!pagination) return;

      var totalPages = Math.max(1, Math.ceil(totalItems / perPage));

      if (currentPage > totalPages) {
        currentPage = totalPages;
      }

      pagination.innerHTML = "";

      if (totalItems === 0 || totalPages <= 1) {
        pagination.hidden = true;
        return;
      }

      pagination.hidden = false;

      var prevBtn = document.createElement("button");
      prevBtn.type = "button";
      prevBtn.className = "pagination__item";
      prevBtn.innerHTML = "&laquo;";
      prevBtn.setAttribute("aria-label", "Previous page");

      if (currentPage <= 1) {
        prevBtn.classList.add("pagination__item--disabled");
        prevBtn.disabled = true;
      } else {
        prevBtn.addEventListener("click", function () {
          currentPage -= 1;
          renderCatalog(false);
        });
      }

      pagination.appendChild(prevBtn);

      getPageNumbers(totalPages, currentPage).forEach(function (item) {
        if (item === "...") {
          var ellipsis = document.createElement("span");
          ellipsis.className = "pagination__item pagination__item--disabled";
          ellipsis.setAttribute("aria-hidden", "true");
          ellipsis.textContent = "...";
          pagination.appendChild(ellipsis);
          return;
        }

        var pageBtn = document.createElement("button");
        pageBtn.type = "button";
        pageBtn.className = "pagination__item";
        pageBtn.textContent = String(item);

        if (item === currentPage) {
          pageBtn.classList.add("pagination__item--active");
          pageBtn.setAttribute("aria-current", "page");
        } else {
          pageBtn.addEventListener("click", function () {
            currentPage = item;
            renderCatalog(false);
          });
        }

        pagination.appendChild(pageBtn);
      });

      var nextBtn = document.createElement("button");
      nextBtn.type = "button";
      nextBtn.className = "pagination__item";
      nextBtn.innerHTML = "&raquo;";
      nextBtn.setAttribute("aria-label", "Next page");

      if (currentPage >= totalPages) {
        nextBtn.classList.add("pagination__item--disabled");
        nextBtn.disabled = true;
      } else {
        nextBtn.addEventListener("click", function () {
          currentPage += 1;
          renderCatalog(false);
        });
      }

      pagination.appendChild(nextBtn);
    }

    function renderCatalog(resetPage) {
      if (resetPage !== false) {
        currentPage = 1;
      }

      var matched = sortCards(cards.filter(cardMatches));
      var start = (currentPage - 1) * perPage;
      var pageItems = matched.slice(start, start + perPage);

      cards.forEach(function (card) {
        card.hidden = true;
      });

      pageItems.forEach(function (card) {
        card.hidden = false;
        catalog.appendChild(card);
      });

      if (emptyEl) {
        emptyEl.hidden = matched.length > 0;
      }

      renderPagination(matched.length);
    }

    pills.forEach(function (pill) {
      pill.addEventListener("click", function () {
        var filterValue = pill.dataset.filter || "all";

        pills.forEach(function (btn) {
          btn.classList.remove("catalog-filter__pill--active");
        });

        pill.classList.add("catalog-filter__pill--active");
        currentFilter = filterValue;
        renderCatalog();
      });
    });

    if (sortSelect) {
      sortSelect.addEventListener("change", renderCatalog);
    }

    if (searchForm && searchInput) {
      searchForm.addEventListener("submit", function (e) {
        e.preventDefault();
        currentSearch = searchInput.value.trim().toLowerCase();
        renderCatalog();
      });

      searchInput.addEventListener("input", function () {
        if (!searchInput.value.trim()) {
          currentSearch = "";
          renderCatalog();
        }
      });
    }

    renderCatalog();
  }

  /**
   * Admin mockup sidebar panel switcher
   * Container: [data-admin-mockup]
   * Nav links: [data-admin-nav="panel-id"]
   * Panels: [data-admin-panel="panel-id"]
   */
  function initAdminMockup() {
    var layout = document.querySelector("[data-admin-mockup]");
    if (!layout) return;

    var links = layout.querySelectorAll("[data-admin-nav]");
    var panels = layout.querySelectorAll("[data-admin-panel]");
    if (!links.length || !panels.length) return;

    function showPanel(panelId) {
      links.forEach(function (link) {
        var isActive = link.dataset.adminNav === panelId;
        link.classList.toggle("admin-mockup__nav-link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });

      panels.forEach(function (panel) {
        var isActive = panel.dataset.adminPanel === panelId;
        panel.hidden = !isActive;
      });
    }

    links.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var panelId = link.dataset.adminNav;
        if (panelId) {
          showPanel(panelId);
        }
      });
    });
  }

  /**
   * Admin mockup shortcode copy buttons
   */
  function initAdminShortcodeCopy() {
    document.querySelectorAll(".shortcode-card__copy").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var targetId = btn.getAttribute("data-copy");
        var codeEl = document.getElementById(targetId);
        if (!codeEl) return;

        var text = codeEl.textContent;

        function showCopied() {
          var original = btn.textContent;
          btn.textContent = "Copied!";
          btn.classList.add("shortcode-card__copy--copied");
          setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove("shortcode-card__copy--copied");
          }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(showCopied);
        } else {
          var range = document.createRange();
          range.selectNodeContents(codeEl);
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
          showCopied();
        }
      });
    });
  }

  /**
   * Admin mockup settings save (mock)
   */
  function initAdminSettings() {
    document.querySelectorAll(".admin-settings-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type="submit"]');
        if (btn) {
          var originalText = btn.textContent;
          btn.textContent = "Saved!";
          btn.disabled = true;
          setTimeout(function () {
            btn.textContent = originalText;
            btn.disabled = false;
          }, 2000);
        }
      });
    });
  }

  function initCourseReviewForm() {
    document.querySelectorAll(".course-review-form").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        var btn = form.querySelector('[type="submit"]');
        if (btn) {
          var originalText = btn.textContent;
          btn.textContent = "Submitted!";
          btn.disabled = true;
          setTimeout(function () {
            btn.textContent = originalText;
            btn.disabled = false;
            form.reset();
          }, 2000);
        }
      });
    });
  }

  function initContactForm() {
    var form = document.getElementById("contact-form");
    var notice = document.getElementById("contact-form-notice");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      if (notice) {
        notice.hidden = false;
        notice.textContent = "Thank you! Your message has been sent. We'll respond within 1 business day.";
      }

      var btn = form.querySelector('[type="submit"]');
      if (btn) {
        var originalText = btn.textContent;
        btn.textContent = "Message Sent!";
        btn.disabled = true;
        setTimeout(function () {
          btn.textContent = originalText;
          btn.disabled = false;
          form.reset();
        }, 2500);
      }
    });
  }

  /**
   * FAQ page category tab filter
   * Container: [data-faq-page]
   * Tabs: [data-faq-filter="all" | category id]
   * Groups: [data-category="category-id"]
   */
  function initFaqFilters() {
    const page = document.querySelector("[data-faq-page]");
    if (!page) return;

    const tabButtons = page.querySelectorAll("[data-faq-filter]");
    const groups = page.querySelectorAll("[data-category]");

    if (!tabButtons.length || !groups.length) return;

    function applyFilter(filter) {
      groups.forEach(function (group) {
        const show = filter === "all" || group.dataset.category === filter;
        group.hidden = !show;
      });
    }

    tabButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        const filter = button.dataset.faqFilter;
        if (!filter) return;

        tabButtons.forEach(function (btn) {
          btn.classList.remove("tabs__tab--active");
          btn.setAttribute("aria-selected", "false");
        });

        button.classList.add("tabs__tab--active");
        button.setAttribute("aria-selected", "true");
        applyFilter(filter);
      });
    });
  }

  /**
   * Policies page sticky sidebar -- active section highlight
   * Container: [data-policies-page]
   * Nav links: [data-policies-nav]
   * Sections: .legal-section[id]
   */
  function initPoliciesNav() {
    const page = document.querySelector("[data-policies-page]");
    if (!page) return;

    const navLinks = page.querySelectorAll("[data-policies-nav]");
    const sections = page.querySelectorAll(".legal-section[id]");

    if (!navLinks.length || !sections.length) return;

    const visibility = {};

    function setActive(id) {
      navLinks.forEach(function (link) {
        const isActive = link.getAttribute("href") === "#" + id;
        link.classList.toggle("policies-sidebar__link--active", isActive);
        if (isActive) {
          link.setAttribute("aria-current", "true");
        } else {
          link.removeAttribute("aria-current");
        }
      });
    }

    sections.forEach(function (section) {
      visibility[section.id] = false;
    });

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          visibility[entry.target.id] = entry.isIntersecting;
        });

        for (var i = 0; i < sections.length; i++) {
          if (visibility[sections[i].id]) {
            setActive(sections[i].id);
            return;
          }
        }
      },
      {
        rootMargin: "-15% 0px -60% 0px",
        threshold: 0
      }
    );

    sections.forEach(function (section) {
      observer.observe(section);
    });

    navLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        const id = link.getAttribute("href").slice(1);
        if (id) setActive(id);
      });
    });

    if (window.location.hash) {
      const hashId = window.location.hash.slice(1);
      const match = page.querySelector("#" + hashId);
      if (match) setActive(hashId);
    }
  }

  /**
   * CE certificate download (mock PDF for static prototype)
   */
  function initCertificateDownload() {
    document.querySelectorAll("[data-certificate-download]").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();

        var user = getCurrentUser();
        var course = btn.getAttribute("data-certificate-course") || "CE Course";
        var certId = btn.getAttribute("data-certificate-id") || "CTA-CERT";
        var hours = btn.getAttribute("data-certificate-hours") || "0";
        var date = btn.getAttribute("data-certificate-date") || new Date().toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric"
        });
        var recipient = user ? user.fullName : "Certificate Recipient";
        var license = user ? getUserLicenseLabel(user) : "Licensed Professional";

        var html = [
          "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">",
          "<title>CE Certificate " + certId + "</title>",
          "<style>",
          "body{font-family:Georgia,serif;max-width:720px;margin:48px auto;padding:40px;border:3px solid #122B51;color:#122B51;}",
          "h1{font-size:28px;text-align:center;margin:0 0 8px;}",
          "p{text-align:center;margin:8px 0;}",
          ".meta{margin-top:32px;font-size:14px;line-height:1.7;}",
          ".footer{margin-top:40px;font-size:12px;color:#666;text-align:center;}",
          "</style></head><body>",
          "<h1>Certificate of Completion</h1>",
          "<p>Clinical Training &amp; Supervision Academy</p>",
          "<p><strong>" + recipient + "</strong></p>",
          "<p>has successfully completed</p>",
          "<p><strong>" + course + "</strong></p>",
          "<div class=\"meta\">",
          "<p>Certificate ID: " + certId + "</p>",
          "<p>Issue Date: " + date + "</p>",
          "<p>CE Hours: " + hours + "</p>",
          "<p>Credential: " + license + "</p>",
          "<p>California BBS Approved Provider</p>",
          "</div>",
          "<p class=\"footer\">This is a prototype certificate for demonstration purposes.</p>",
          "</body></html>"
        ].join("");

        var blob = new Blob([html], { type: "text/html;charset=utf-8" });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.href = url;
        link.download = certId + "-Certificate.html";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        var originalHtml = btn.innerHTML;
        btn.innerHTML = "Downloaded!";
        btn.classList.add("btn--downloaded");
        setTimeout(function () {
          btn.innerHTML = originalHtml;
          btn.classList.remove("btn--downloaded");
        }, 2000);
      });
    });
  }

  /**
   * Password show/hide toggle (eye icon)
   * Wrapper: [data-password-field]
   */
  function initPasswordToggle() {
    document.querySelectorAll("[data-password-field]").forEach(function (wrap) {
      var input = wrap.querySelector(".form-password__input");
      var btn = wrap.querySelector(".form-password__toggle");
      if (!input || !btn) return;

      btn.addEventListener("click", function () {
        var isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        btn.classList.toggle("form-password__toggle--visible", isHidden);
        btn.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
        btn.setAttribute("aria-pressed", isHidden ? "true" : "false");
      });
    });
  }

  /**
   * Demo payment modal when Stripe is not configured.
   */
  function showDemoPaymentModal(triggerBtn, paymentAction, paymentData) {
    if (typeof jQuery === "undefined") {
      return;
    }

    var $ = jQuery;

    $("#cta-demo-modal").remove();

    var productName =
      triggerBtn.data("course-title") ||
      triggerBtn.closest(".cta-pricing-card").find(".cta-pricing-card__name").last().text().trim() ||
      "Selected Plan";

    var price =
      triggerBtn.data("price") ||
      triggerBtn.closest(".cta-pricing-card").find(".price-amount").text().trim() ||
      "";

    var modalHtml =
      '<div id="cta-demo-modal" style="' +
      "position:fixed; top:0; left:0; width:100%; height:100%;" +
      "background:rgba(0,0,0,0.6); z-index:99999;" +
      "display:flex; align-items:center; justify-content:center;" +
      "font-family:'Montserrat',sans-serif;" +
      '">' +
      '<div id="cta-demo-inner" style="' +
      "background:#fff; width:100%; max-width:460px;" +
      "margin:20px; padding:40px; position:relative;" +
      '">' +
      '<button id="cta-demo-close" type="button" style="' +
      "position:absolute; top:16px; right:20px;" +
      "background:none; border:none; font-size:22px;" +
      "cursor:pointer; color:#6B7280;" +
      '">&times;</button>' +
      '<div id="cta-demo-step1">' +
      '<div style="text-align:center; margin-bottom:24px;">' +
      '<div style="font-size:13px; color:#6B7280; margin-bottom:4px;">SECURE CHECKOUT</div>' +
      "<h3 style=\"color:#122B51; font-size:20px; margin:0 0 4px;\">" +
      productName +
      "</h3>" +
      '<div style="font-size:28px; font-weight:700; color:#3266A9;">' +
      price +
      "</div>" +
      "</div>" +
      '<div style="margin-bottom:16px;">' +
      '<label style="display:block; font-size:13px; color:#374151; margin-bottom:6px; font-weight:600;">Card Number</label>' +
      '<input type="text" value="4242 4242 4242 4242" readonly style="width:100%; padding:12px; border:1px solid #D1D5DB; font-size:15px; font-family:\'Montserrat\',sans-serif; color:#6B7280; background:#F9FAFB;">' +
      "</div>" +
      '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">' +
      "<div><label style=\"display:block; font-size:13px; color:#374151; margin-bottom:6px; font-weight:600;\">Expiry</label>" +
      '<input type="text" value="12/28" readonly style="width:100%; padding:12px; border:1px solid #D1D5DB; font-size:15px; font-family:\'Montserrat\',sans-serif; color:#6B7280; background:#F9FAFB;"></div>' +
      "<div><label style=\"display:block; font-size:13px; color:#374151; margin-bottom:6px; font-weight:600;\">CVC</label>" +
      '<input type="text" value="&bull;&bull;&bull;" readonly style="width:100%; padding:12px; border:1px solid #D1D5DB; font-size:15px; font-family:\'Montserrat\',sans-serif; color:#6B7280; background:#F9FAFB;"></div>' +
      "</div>" +
      '<button id="cta-demo-pay" type="button" style="' +
      "width:100%; padding:14px; background:#3266A9; color:#fff;" +
      "border:none; font-size:16px; font-weight:600; cursor:pointer;" +
      "font-family:'Montserrat',sans-serif; margin-top:8px;" +
      '">Pay ' +
      price +
      "</button>" +
      '<p style="text-align:center; font-size:12px; color:#9CA3AF; margin-top:12px;">' +
      "Demo mode &mdash; no real payment processed" +
      "</p>" +
      "</div>" +
      '<div id="cta-demo-step2" style="display:none; text-align:center; padding:20px 0;">' +
      '<div class="cta-demo-spinner" style="width:48px; height:48px; border:4px solid #E5E7EB; border-top-color:#3266A9; border-radius:50%; animation:cta-spin 0.8s linear infinite; margin:0 auto 20px;"></div>' +
      '<p style="color:#122B51; font-size:16px; font-weight:600;">Processing payment...</p>' +
      '<p style="color:#6B7280; font-size:14px;">Please wait</p>' +
      "</div>" +
      '<div id="cta-demo-step3" style="display:none; text-align:center; padding:20px 0;">' +
      '<svg viewBox="0 0 80 80" style="width:80px; height:80px; margin:0 auto 20px; display:block;">' +
      '<circle cx="40" cy="40" r="36" fill="none" stroke="#16A34A" stroke-width="4" stroke-dasharray="226" stroke-dashoffset="226" id="cta-check-circle" style="transition:stroke-dashoffset 0.6s ease; transform:rotate(-90deg); transform-origin:center;"></circle>' +
      '<polyline points="24,42 35,53 56,30" fill="none" stroke="#16A34A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="50" stroke-dashoffset="50" id="cta-check-mark" style="transition:stroke-dashoffset 0.4s ease 0.5s;"></polyline>' +
      "</svg>" +
      '<h3 style="color:#16A34A; font-size:22px; margin:0 0 8px;">Payment Successful!</h3>' +
      '<p style="color:#122B51; font-size:15px; margin:0 0 4px; font-weight:600;">' +
      productName +
      "</p>" +
      '<p style="color:#6B7280; font-size:14px; margin:0 0 24px;">You now have access to your content.</p>' +
      '<p style="color:#9CA3AF; font-size:12px;">Redirecting to your dashboard...</p>' +
      "</div>" +
      "</div></div>" +
      "<style>@keyframes cta-spin { to { transform: rotate(360deg); } }</style>";

    $("body").append(modalHtml);

    $("#cta-demo-close").on("click", function () {
      $("#cta-demo-modal").fadeOut(200, function () {
        $(this).remove();
      });
    });

    $("#cta-demo-modal").on("click", function (e) {
      if ($(e.target).is("#cta-demo-modal")) {
        $(this).fadeOut(200, function () {
          $(this).remove();
        });
      }
    });

    $("#cta-demo-pay").on("click", function () {
      $("#cta-demo-step1").hide();
      $("#cta-demo-step2").show();

      $.ajax({
        url: ctaAjax.ajaxUrl,
        type: "POST",
        data: $.extend(
          {
            action: paymentAction,
            nonce: ctaAjax.nonce,
            demo_confirm: 1
          },
          paymentData || {}
        ),
        success: function (response) {
          if (!response.success) {
            $("#cta-demo-modal").remove();
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Something went wrong."
            );
            return;
          }

          setTimeout(function () {
            $("#cta-demo-step2").hide();
            $("#cta-demo-step3").show();

            setTimeout(function () {
              var circle = document.getElementById("cta-check-circle");
              if (circle) {
                circle.style.strokeDashoffset = "0";
              }
            }, 50);

            setTimeout(function () {
              var mark = document.getElementById("cta-check-mark");
              if (mark) {
                mark.style.strokeDashoffset = "0";
              }
            }, 550);

            setTimeout(function () {
              var redirectUrl =
                (response.data && response.data.redirect_url) ||
                (paymentAction === "cta_create_subscription"
                  ? ctaAjax.supervisionDashboardUrl
                  : "") ||
                (paymentAction === "cta_create_checkout"
                  ? ctaAjax.studentDashboardUrl
                  : "") ||
                (ctaAjax.isLoggedIn === "yes" ? ctaAjax.dashboardUrl : ctaAjax.loginUrl) ||
                window.location.href;

              if (
                paymentAction === "cta_create_subscription" &&
                response.data &&
                response.data.redirect_url
              ) {
                redirectUrl = response.data.redirect_url;
              } else if (
                paymentAction === "cta_create_subscription" &&
                ctaAjax.supervisionDashboardUrl
              ) {
                redirectUrl = ctaAjax.supervisionDashboardUrl;
              } else if (
                paymentAction === "cta_create_checkout" &&
                response.data &&
                response.data.redirect_url
              ) {
                redirectUrl = response.data.redirect_url;
              } else if (
                paymentAction === "cta_create_checkout" &&
                ctaAjax.studentDashboardUrl
              ) {
                redirectUrl = ctaAjax.studentDashboardUrl;
              }

              if (redirectUrl && redirectUrl.indexOf("_cta=") === -1) {
                redirectUrl +=
                  (redirectUrl.indexOf("?") === -1 ? "?" : "&") +
                  (paymentAction === "cta_create_subscription"
                    ? "subscription=success&cta_paid=1&_cta="
                    : "cta_enrolled=1&cta_paid=1&_cta=") +
                  Date.now();
              }

              window.location.href = redirectUrl;
            }, 2500);
          }, 1800);
        },
        error: function () {
          $("#cta-demo-modal").remove();
          window.alert("Connection error. Please try again.");
        }
      });
    });
  }

  /**
   * Demo subscription management modal (when Stripe portal is unavailable).
   */
  function showDemoSubscriptionModal(data) {
    if (typeof jQuery === "undefined") {
      return;
    }

    var $ = jQuery;
    var planName = (data && data.plan_name) || "Group Supervision";
    var status = (data && data.status) || "none";
    var price = (data && data.price) || "";
    var nextBilling = (data && data.next_billing) || "";
    var showRenew = !!(data && data.show_renew);
    var renewUrl = (data && data.renew_url) || "";
    var supportEmail = (data && data.support_email) || "";
    var isActive = status === "active";

    $("#cta-demo-modal").remove();

    var statusLabel = status === "none" ? "No subscription" : status.charAt(0).toUpperCase() + status.slice(1);
    var statusBg = isActive ? "#DCFCE7" : "#FEE2E2";
    var statusColor = isActive ? "#16A34A" : "#DC2626";
    var stripeConfigured = !!(data && data.stripe_configured);
    var paymentsBypass = !!(data && data.payments_bypass);
    var footerText = paymentsBypass
      ? "Testing Mode is on &mdash; turn off Skip payments in CTA LMS settings to open the real Stripe portal"
      : stripeConfigured
      ? "Subscribe with Stripe to unlock the Customer Billing Portal."
      : "Stripe is not configured &mdash; add API keys in CTA LMS settings";
    var supportBlock = supportEmail
      ? '<a href="mailto:' + supportEmail + '" style="color:#3266A9;text-decoration:none;">' + supportEmail + "</a>"
      : "support";

    var actionBlock = "";

    if (showRenew && renewUrl) {
      actionBlock =
        '<a href="' +
        renewUrl +
        '" class="cta-renew-btn" style="display:block;width:100%;padding:14px;background:#16A34A;color:#fff;text-align:center;font-weight:600;font-size:15px;font-family:\'Montserrat\',sans-serif;text-decoration:none;margin-bottom:10px;border:none;cursor:pointer;border-radius:10px;">\uD83D\uDD04 Renew Subscription</a>';
    } else if (supportEmail && !paymentsBypass) {
      actionBlock =
        '<a href="mailto:' +
        supportEmail +
        '" style="display:block;text-align:center;font-size:13px;color:#6B7280;margin-top:8px;margin-bottom:10px;text-decoration:underline;">Contact support about billing</a>';
    }

    var helpText = paymentsBypass
      ? "Testing Mode (Skip payments) is enabled, so Manage Subscription cannot open Stripe&rsquo;s Customer Billing Portal. An admin must turn Testing Mode OFF in CTA LMS &rarr; Settings, then use Stripe test keys + a real test subscription."
      : stripeConfigured
      ? "No Stripe customer is linked to this account yet. Complete a subscription checkout first, then Manage Subscription will open Stripe&rsquo;s Customer Portal."
      : "Stripe API keys are missing. After keys are saved and Testing Mode is off, Manage Subscription will open the real billing portal.";

    if (data && data.message) {
      helpText = data.message;
    }

    var modalHtml =
      '<div id="cta-demo-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;font-family:\'Montserrat\',sans-serif;">' +
      '<div style="background:#fff;width:100%;max-width:480px;margin:20px;padding:36px;position:relative;border-radius:10px;">' +
      '<button type="button" id="cta-demo-close" style="position:absolute;top:16px;right:20px;background:none;border:none;font-size:22px;cursor:pointer;color:#6B7280;">&times;</button>' +
      '<div style="text-align:center;margin-bottom:24px;">' +
      '<div style="font-size:13px;color:#6B7280;margin-bottom:4px;">SUBSCRIPTION MANAGEMENT</div>' +
      '<h3 style="color:#122B51;font-size:20px;margin:0 0 8px;">' + planName + "</h3>" +
      '<span style="display:inline-block;padding:4px 12px;background:' +
      statusBg +
      ";color:" +
      statusColor +
      ';font-size:13px;font-weight:600;border-radius:10px;">' +
      statusLabel +
      "</span>" +
      "</div>" +
      '<div style="background:#F9FAFB;border:1px solid #E5E7EB;padding:20px;margin-bottom:20px;border-radius:10px;">' +
      (price ? '<p style="margin:0 0 12px;color:#374151;"><strong>Plan:</strong> ' + price + "</p>" : "") +
      (nextBilling && isActive ? '<p style="margin:0;color:#374151;"><strong>Next billing:</strong> ' + nextBilling + "</p>" : "") +
      "</div>" +
      '<p style="font-size:14px;color:#6B7280;line-height:1.6;margin:0 0 20px;">' +
      helpText +
      "</p>" +
      actionBlock +
      '<button type="button" id="cta-demo-sub-close" style="width:100%;padding:14px;background:#3266A9;color:#fff;border:none;font-size:16px;font-weight:600;cursor:pointer;font-family:\'Montserrat\',sans-serif;border-radius:10px;">Close</button>' +
      '<p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:12px;">' + footerText + "</p>" +
      "</div></div>";

    $("body").append(modalHtml);

    $("#cta-demo-close, #cta-demo-sub-close").on("click", function () {
      $("#cta-demo-modal").fadeOut(200, function () {
        $(this).remove();
      });
    });

    $("#cta-demo-modal").on("click", function (e) {
      if ($(e.target).is("#cta-demo-modal")) {
        $(this).fadeOut(200, function () {
          $(this).remove();
        });
      }
    });
  }

  /**
   * Stripe checkout -- course purchase, subscriptions, and bundles.
   */
  function initCtaStripePayments() {
    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;

    function getPaymentAction(btn) {
      if (btn.is("#enroll-btn") || btn.is("[data-cta-course-checkout]")) {
        return "cta_create_checkout";
      }

      if (btn.hasClass("cta-subscribe-btn") || btn.is("[data-cta-supervision-subscribe]")) {
        return "cta_create_subscription";
      }

      return "cta_purchase_bundle";
    }

    function paymentNeedsAgencyInfo(action, btn) {
      if (!ctaAjax || ctaAjax.hasAgencyInfo === "yes") {
        return false;
      }

      if (action === "cta_create_subscription") {
        return true;
      }

      if (action !== "cta_purchase_bundle") {
        return false;
      }

      if (btn.data("includes-supervision") === 1 || btn.data("includes-supervision") === true) {
        return true;
      }

      var plan = String(btn.data("plan") || btn.data("plan-type") || "").toLowerCase();
      if (plan === "hybrid" || plan.indexOf("supervision") !== -1) {
        return true;
      }

      var label = String(btn.data("course-title") || btn.text() || "").toLowerCase();
      return (
        label.indexOf("supervision") !== -1 ||
        label.indexOf("hybrid") !== -1 ||
        label.indexOf("all-access") !== -1
      );
    }

    function showSupervisionAgencyModal(onSubmit, onCancel) {
      $("#cta-agency-modal").remove();

      var modalHtml =
        '<div id="cta-agency-modal" style="position:fixed;inset:0;background:rgba(18,43,81,0.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;">' +
        '<div style="background:#fff;max-width:480px;width:100%;border-radius:12px;padding:28px 24px;box-shadow:0 20px 50px rgba(0,0,0,0.2);font-family:\'Montserrat\',sans-serif;position:relative;">' +
        '<button type="button" id="cta-agency-close" aria-label="Close" style="position:absolute;top:12px;right:14px;border:none;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#6B7280;">&times;</button>' +
        '<h3 style="margin:0 0 8px;color:#122B51;font-size:20px;">Supervision Application</h3>' +
        '<p style="margin:0 0 18px;color:#4B5563;font-size:14px;line-height:1.5;">Employer/agency details are required to apply for clinical supervision. Your agency representative will receive approval documents by email.</p>' +
        '<div style="margin-bottom:12px;"><label for="cta-agency-employer" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Employer/Agency Name</label>' +
        '<input id="cta-agency-employer" type="text" autocomplete="organization" style="width:100%;padding:12px;border:1px solid #D1D5DB;border-radius:8px;font-size:15px;"></div>' +
        '<div style="margin-bottom:12px;"><label for="cta-agency-rep-name" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Agency Representative Name</label>' +
        '<input id="cta-agency-rep-name" type="text" autocomplete="name" style="width:100%;padding:12px;border:1px solid #D1D5DB;border-radius:8px;font-size:15px;"></div>' +
        '<div style="margin-bottom:18px;"><label for="cta-agency-rep-email" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Agency Representative Email</label>' +
        '<input id="cta-agency-rep-email" type="email" autocomplete="email" style="width:100%;padding:12px;border:1px solid #D1D5DB;border-radius:8px;font-size:15px;"></div>' +
        '<p id="cta-agency-error" style="display:none;color:#DC2626;font-size:13px;margin:0 0 12px;"></p>' +
        '<button type="button" id="cta-agency-continue" style="width:100%;padding:14px;background:#3266A9;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;">Continue to Purchase</button>' +
        "</div></div>";

      $("body").append(modalHtml);

      function closeModal() {
        $("#cta-agency-modal").remove();
        if (typeof onCancel === "function") {
          onCancel();
        }
      }

      $("#cta-agency-close").on("click", closeModal);
      $("#cta-agency-modal").on("click", function (evt) {
        if ($(evt.target).is("#cta-agency-modal")) {
          closeModal();
        }
      });

      $("#cta-agency-continue").on("click", function () {
        var employer = $("#cta-agency-employer").val().trim();
        var repName = $("#cta-agency-rep-name").val().trim();
        var repEmail = $("#cta-agency-rep-email").val().trim();
        var errorEl = $("#cta-agency-error");

        if (!employer || !repName || !repEmail) {
          errorEl.text("Please fill in all agency fields.").show();
          return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(repEmail)) {
          errorEl.text("Please enter a valid agency representative email.").show();
          return;
        }

        errorEl.hide();
        $("#cta-agency-modal").remove();

        if (typeof onSubmit === "function") {
          onSubmit({
            employer_agency_name: employer,
            agency_representative_name: repName,
            agency_representative_email: repEmail
          });
        }
      });
    }

    function handlePaymentClick(e) {
      e.preventDefault();

      var btn = $(this);
      var origText = btn.text();

      if (ctaAjax.isLoggedIn !== "yes") {
        if (ctaAjax.loginUrl) {
          window.location.href = ctaAjax.loginUrl;
          return;
        }

        window.alert(ctaAjax.loginRequiredMessage || "Please log in to continue.");
        return;
      }

      var action = getPaymentAction(btn);
      var paymentData = {
        course_id: btn.data("course-id") || "",
        bundle_id: btn.data("bundle-id") || "",
        plan_type: btn.data("plan") || btn.data("plan-type") || "",
        billing: btn.data("billing") || ""
      };

      function submitPaymentRequest() {
        btn.text("Processing...").prop("disabled", true);

        $.ajax({
          url: ctaAjax.ajaxUrl,
          type: "POST",
          data: $.extend(
            {
              action: action,
              nonce: ctaAjax.nonce
            },
            paymentData
          ),
          success: function (response) {
            btn.text(origText).prop("disabled", false);

            if (
              response &&
              !response.success &&
              response.data &&
              response.data.code === "agency_info_required"
            ) {
              showSupervisionAgencyModal(function (agencyData) {
                paymentData = $.extend(paymentData, agencyData);
                if (ctaAjax) {
                  ctaAjax.hasAgencyInfo = "yes";
                }
                submitPaymentRequest();
              });
              return;
            }

            if (response.success && response.data && response.data.demo_mode) {
              showDemoPaymentModal(btn, action, paymentData);
              return;
            }

            if (response.success && response.data && response.data.enrolled && response.data.redirect_url) {
              window.location.href = response.data.redirect_url;
              return;
            }

            if (response.success && response.data && response.data.checkout_url) {
              window.location.href = response.data.checkout_url;
              return;
            }

            if (response.success && action === "cta_create_subscription") {
              var dashUrl =
                (response.data && response.data.redirect_url) ||
                ctaAjax.supervisionDashboardUrl ||
                ctaAjax.dashboardUrl ||
                window.location.href;
              if (dashUrl.indexOf("cta_paid=") === -1) {
                dashUrl +=
                  (dashUrl.indexOf("?") === -1 ? "?" : "&") +
                  "subscription=success&cta_paid=1&_cta=" +
                  Date.now();
              }
              window.location.href = dashUrl;
              return;
            }

            if (!response.success) {
              var errorMessage =
                response.data && response.data.message
                  ? response.data.message
                  : "Something went wrong.";
              var registerUrl =
                response.data && response.data.register_url
                  ? response.data.register_url
                  : "";

              if (
                response.data &&
                response.data.code === "associate_required" &&
                registerUrl
              ) {
                if (window.confirm(errorMessage + "\n\nGo to Associate registration?")) {
                  window.location.href = registerUrl;
                }
                return;
              }

              window.alert(errorMessage);
            }
          },
          error: function () {
            btn.text(origText).prop("disabled", false);
            window.alert("Connection error. Please try again.");
          }
        });
      }

      if (paymentNeedsAgencyInfo(action, btn)) {
        showSupervisionAgencyModal(function (agencyData) {
          paymentData = $.extend(paymentData, agencyData);
          if (ctaAjax) {
            ctaAjax.hasAgencyInfo = "yes";
          }
          submitPaymentRequest();
        });
        return;
      }

      submitPaymentRequest();
    }

    $(document).on(
      "click",
      "#enroll-btn, [data-cta-course-checkout], .cta-bundle-btn, .cta-subscribe-btn, [data-cta-supervision-subscribe]",
      handlePaymentClick
    );
  }

  /**
   * Supervision session booking ([cta_supervision_booking] shortcode)
   */
  function initCtaSupervisionBooking() {
    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;
    var $root = $(".cta-supervision-booking");

    if (!$root.length) {
      return;
    }

    function filterSessionsByDate(date) {
      var visibleCount = 0;

      $root.find(".cta-session-card").each(function () {
        var $card = $(this);
        var match = $card.data("session-date") === date;

        $card.toggle(match);
        if (match) {
          visibleCount += 1;
        }
      });

      $root.find(".cta-session-list-empty").remove();

      if (visibleCount === 0) {
        $root.find("#cta-supervision-sessions").append(
          '<p class="cta-session-list-empty cta-empty-state">' +
            "No sessions on this date." +
            "</p>"
        );
      }
    }

    $root.on("click", ".cta-calendar-day:not(:disabled)", function () {
      var date = $(this).data("date");

      $root.find(".cta-calendar-day").removeClass("booking-calendar__day--selected");
      $(this).addClass("booking-calendar__day--selected");
      $root.find(".cta-booking-calendar").attr("data-selected-date", date);
      filterSessionsByDate(date);
    });

    var initialDate =
      $root.find(".cta-booking-calendar").attr("data-selected-date") ||
      $root.find(".cta-calendar-day.booking-calendar__day--selected").data("date");

    if (initialDate) {
      filterSessionsByDate(initialDate);
    }

    function showSupervisionBookingNotice(message, isPending) {
      var $notice = $root.find("[data-cta-booking-notice]");
      if (!$notice.length) {
        $notice = $(
          '<div class="cta-empty-state" data-cta-booking-notice style="margin-bottom:1.25rem;"></div>'
        );
        $root.find(".booking-section__header").after($notice);
      }

      var title = isPending
        ? "Supervision Application Pending"
        : "Booking unavailable";
      var ceUrl =
        $root.attr("data-ce-dashboard-url") ||
        (typeof ctaAjax !== "undefined" && ctaAjax.dashboardUrl) ||
        "/";

      $notice.html(
        "<h3>" +
          title +
          "</h3><p>" +
          $("<div>").text(message || "").html() +
          '</p><p style="margin-top:1rem;"><a class="btn btn-primary" href="' +
          ceUrl +
          '">Go to My Courses</a></p>'
      );

      $root.find(".cta-book-btn").prop("disabled", true);
      $("html, body").animate(
        { scrollTop: Math.max(0, ($notice.offset() || { top: 0 }).top - 80) },
        250
      );
    }

    $root.on("click", ".cta-book-btn:not(:disabled)", function () {
      var $btn = $(this);
      var $card = $btn.closest(".cta-session-card");
      var sessionId = $btn.data("session-id") || $card.data("session-id");
      var originalText = $btn.text();
      var canBook = $root.attr("data-can-book") === "yes";
      var userStatus = $root.attr("data-user-status") || "";
      var pendingMessage =
        $root.attr("data-pending-message") ||
        "Supervision Application Pending: booking stays locked until approved.";

      if (!sessionId) {
        return;
      }

      if (!canBook) {
        showSupervisionBookingNotice(
          pendingMessage,
          userStatus === "pending_approval" || userStatus === "awaiting_plan"
        );
        return;
      }

      $btn.prop("disabled", true).text("Booking...");

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_book_session",
        nonce: ctaAjax.nonce,
        session_id: sessionId
      })
        .done(function (response) {
          if (!response.success) {
            var code =
              response.data && response.data.code ? response.data.code : "";
            var message =
              response.data && response.data.message
                ? response.data.message
                : "Unable to book session.";

            if (
              code === "supervision_pending_approval" ||
              code === "supervision_awaiting_plan" ||
              code === "supervision_not_active"
            ) {
              $root.attr("data-can-book", "no");
              showSupervisionBookingNotice(message, true);
            } else {
              window.alert(message);
            }
            $btn.prop("disabled", false).text(originalText);
            return;
          }

          var dashboardUrl =
            (response.data && response.data.dashboard_url) ||
            (typeof ctaAjax !== "undefined" && ctaAjax.supervisionDashboardUrl) ||
            (typeof ctaAjax !== "undefined" && ctaAjax.dashboardUrl) ||
            "";

          if (dashboardUrl) {
            $btn.text("Booked! Redirecting...");
            window.setTimeout(function () {
              var separator = dashboardUrl.indexOf("?") === -1 ? "?" : "&";
              window.location.href =
                dashboardUrl +
                separator +
                "cta_booking=success&_cta=" +
                Date.now();
            }, 500);
            return;
          }

          var seatsRemaining =
            typeof response.data.seats_remaining === "number"
              ? response.data.seats_remaining
              : null;

          $card.find(".session-card__actions").html(
            '<span class="badge badge--success cta-session-booked-label">' +
              CTA_ICON_CHECK_CIRCLE +
              "Booked</span>" +
              '<button type="button" class="btn btn-outline btn--sm cta-cancel-btn" data-booking-id="' +
              response.data.booking_id +
              '" data-session-id="' +
              sessionId +
              '">Cancel</button>'
          );

          if (seatsRemaining !== null) {
            var $seats = $card.find(".cta-session-seats");

            if (seatsRemaining <= 0) {
              $seats.html('<span class="badge badge--outline">Full</span>');
            } else {
              $seats.text(seatsRemaining + " seats remaining");
            }
          }

          $card.attr("data-booking-id", response.data.booking_id);
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          $btn.prop("disabled", false).text(originalText);
        });
    });

    $root.on("click", ".cta-cancel-btn", function () {
      var $btn = $(this);
      var $card = $btn.closest(".cta-session-card");
      var bookingId = $btn.data("booking-id") || $card.data("booking-id");
      var sessionId = $btn.data("session-id") || $card.data("session-id");

      if (!bookingId) {
        return;
      }

      if (!window.confirm("Cancel this session booking?")) {
        return;
      }

      $btn.prop("disabled", true).text("Cancelling...");

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_cancel_booking",
        nonce: ctaAjax.nonce,
        booking_id: bookingId
      })
        .done(function (response) {
          if (!response.success) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to cancel booking."
            );
            $btn.prop("disabled", false).text("Cancel");
            return;
          }

          var $seats = $card.find(".cta-session-seats");
          var seatsText = $seats.text();
          var match = seatsText.match(/(\d+)/);

          if (match) {
            $seats.text(parseInt(match[1], 10) + 1 + " seats remaining");
          } else if ($seats.find(".badge--outline").length && $seats.text().indexOf("Full") !== -1) {
            $seats.text("1 seats remaining");
          }

          $card.find(".session-card__actions").html(
            '<button type="button" class="btn btn-primary cta-book-btn" data-session-id="' +
              sessionId +
              '" data-session-type="' +
              ($card.data("session-type") || "group") +
              '">Book Session</button>'
          );
          $card.removeAttr("data-booking-id");
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          $btn.prop("disabled", false).text("Cancel");
        });
    });
  }

  /**
   * WordPress CE course player -- mark module complete.
   */
  function initCtaWpCoursePlayer() {
    var markBtn = document.getElementById("cta-mark-complete");

    if (!markBtn || markBtn.disabled || !markBtn.getAttribute("data-module-id")) {
      return;
    }

    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;
    var playerRoot = document.querySelector(".cta-course-player");

    markBtn.addEventListener("click", function () {
      var courseId = markBtn.getAttribute("data-course-id");
      var moduleId = markBtn.getAttribute("data-module-id");
      var originalText = markBtn.textContent;

      markBtn.disabled = true;
      markBtn.textContent = "Saving...";

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_complete_module",
        nonce: ctaAjax.nonce,
        course_id: courseId,
        module_id: moduleId
      })
        .done(function (response) {
          if (!response.success) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to mark module complete."
            );
            markBtn.disabled = false;
            markBtn.textContent = originalText;
            return;
          }

          markBtn.innerHTML = CTA_ICON_CHECK_CIRCLE + " Completed";

          var moduleItem = playerRoot
            ? playerRoot.querySelector('.cta-module-list__item[data-module-id="' + moduleId + '"]')
            : null;

          if (moduleItem) {
            moduleItem.classList.add("cta-module-list__item--complete");
            moduleItem.classList.remove("cta-module-list__item--current");
            var icon = moduleItem.querySelector(".cta-module-list__icon");
            if (icon) {
              icon.innerHTML = CTA_ICON_CHECK_CIRCLE;
            }
          }

          if (response.data.quiz_unlocked) {
            var lockedMsg = document.querySelector(".cta-quiz-locked-message");
            var unlockedMsg = document.querySelector(".cta-quiz-unlocked-message");

            if (lockedMsg) {
              lockedMsg.hidden = true;
            }
            if (unlockedMsg) {
              unlockedMsg.hidden = false;
            }

            var notice = document.createElement("p");
            notice.className = "course-player__notice course-player__notice--success";
            notice.setAttribute("role", "status");
            notice.textContent = "Course Complete! Take the quiz to earn your certificate.";
            var actions = document.querySelector("[data-course-player-actions]");
            if (actions && !document.querySelector(".course-player__notice--success")) {
              actions.insertAdjacentElement("afterend", notice);
            }
            return;
          }

          if (response.data.next_module_url) {
            setTimeout(function () {
              window.location.href = response.data.next_module_url;
            }, 1000);
          }
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          markBtn.disabled = false;
          markBtn.textContent = originalText;
        });
    });
  }

  /**
   * WordPress CE quiz page ([cta_quiz] shortcode).
   */
  function initCtaQuiz() {
    var app = document.getElementById("cta-quiz-app");

    if (!app || typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;
    var courseId = app.getAttribute("data-course-id");
    var quizId = app.getAttribute("data-quiz-id");
    var attemptId = parseInt(app.getAttribute("data-attempt-id"), 10) || 0;
    // Quizzes are untimed -- never start a countdown, even if legacy markup has a limit.
    var timeLimitMins = 0;
    var passingScore = parseInt(app.getAttribute("data-passing-score"), 10) || 70;
    var questionCount = parseInt(app.getAttribute("data-question-count"), 10) || 0;
    var isExamPrep = app.getAttribute("data-exam-prep") === "1";
    var dashboardUrl = app.getAttribute("data-dashboard-url") || "";
    var timerEl = document.getElementById("cta-quiz-timer");
    var timerInterval = null;
    var secondsRemaining = 0;

    if (timerEl) {
      timerEl.hidden = true;
      timerEl.setAttribute("aria-hidden", "true");
    }

    var panels = {
      start: app.querySelector('[data-quiz-panel="start"]'),
      questions: app.querySelector('[data-quiz-panel="questions"]'),
      result: app.querySelector('[data-quiz-panel="result"]'),
      evaluation: app.querySelector('[data-quiz-panel="evaluation"]'),
      attestation: app.querySelector('[data-quiz-panel="attestation"]'),
      exam_complete: app.querySelector('[data-quiz-panel="exam_complete"]'),
      certificate: app.querySelector('[data-quiz-panel="certificate"]')
    };
    var questionsEl = document.getElementById("cta-quiz-questions");

    function showPanel(name) {
      Object.keys(panels).forEach(function (key) {
        var panel = panels[key];
        if (!panel) {
          return;
        }
        var active = key === name;
        panel.hidden = !active;
        panel.classList.toggle("cta-quiz-panel--active", active);
      });
    }

    function formatTime(seconds) {
      var mins = Math.floor(seconds / 60);
      var secs = seconds % 60;
      return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
    }

    function stopTimer() {
      if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
      }
    }

    function startTimer() {
      // Intentionally disabled: course quizzes have no time limit.
      stopTimer();
      if (timerEl) {
        timerEl.hidden = true;
        timerEl.textContent = "";
      }
    }

    function countAnswered() {
      var total = app.querySelectorAll(".cta-quiz-question").length;
      var answered = 0;

      app.querySelectorAll(".cta-quiz-question").forEach(function (questionEl) {
        if (questionEl.querySelector('input[type="radio"]:checked')) {
          answered += 1;
        }
      });

      return { answered: answered, total: total };
    }

    function updateAnswerCounter() {
      var counts = countAnswered();
      var progressEl = document.getElementById("cta-quiz-progress");
      var submitBtn = document.getElementById("cta-submit-quiz");

      if (progressEl) {
        progressEl.textContent =
          "Questions answered: " + counts.answered + " of " + counts.total;
      }

      if (submitBtn) {
        submitBtn.disabled = counts.answered < counts.total || counts.total === 0;
      }
    }

    function collectAnswers() {
      var answers = {};

      app.querySelectorAll(".cta-quiz-question").forEach(function (questionEl) {
        var qid = questionEl.getAttribute("data-question-id");
        var checked = questionEl.querySelector('input[type="radio"]:checked');
        if (qid && checked) {
          answers[qid] = checked.value;
        }
      });

      return answers;
    }

    function revealResults(results) {
      if (!Array.isArray(results)) {
        return;
      }

      results.forEach(function (item) {
        var questionEl = app.querySelector(
          '.cta-quiz-question[data-question-id="' + item.question_id + '"]'
        );

        if (!questionEl) {
          return;
        }

        var feedback = questionEl.querySelector(".cta-quiz-question__feedback");
        var options = questionEl.querySelectorAll(".cta-quiz-option");

        options.forEach(function (optionEl) {
          var input = optionEl.querySelector('input[type="radio"]');
          optionEl.classList.remove("cta-quiz-option--correct", "cta-quiz-option--wrong");

          if (input && input.value === item.correct_option) {
            optionEl.classList.add("cta-quiz-option--correct");
          } else if (input && input.checked && input.value !== item.correct_option) {
            optionEl.classList.add("cta-quiz-option--wrong");
          }
        });

        questionEl.querySelectorAll('input[type="radio"]').forEach(function (input) {
          input.disabled = true;
        });

        if (feedback) {
          var html = item.is_correct
            ? "<p class=\"cta-quiz-feedback cta-quiz-feedback--correct\">Correct.</p>"
            : "<p class=\"cta-quiz-feedback cta-quiz-feedback--wrong\">Incorrect. Correct answer: " +
              String(item.correct_option).toUpperCase() +
              ".</p>";

          if (item.explanation) {
            html += "<p class=\"cta-quiz-feedback__explanation\">" + item.explanation + "</p>";
          }

          feedback.innerHTML = html;
          feedback.hidden = false;
        }
      });
    }

    function renderResult(data) {
      var resultEl = document.getElementById("cta-quiz-result");
      if (!resultEl) {
        return;
      }

      var passed = !!data.passed;
      var nextStep = data.next_step || (isExamPrep ? "complete" : "evaluation");
      var html = "";

      if (passed) {
        html +=
          '<div class="cta-quiz-result__icon cta-quiz-result__icon--pass" aria-hidden="true">\u2713</div>' +
          "<h2>Congratulations! You passed!</h2>" +
          "<p>Score: " + data.score + "%</p>";

        if (nextStep === "complete" || isExamPrep) {
          html +=
            "<p><strong>Practice exam complete.</strong> No CE evaluation or certificate is required for Exam Preparation Programs.</p>";
          if (dashboardUrl) {
            html +=
              '<a href="' +
              dashboardUrl +
              '" class="btn btn-primary">Return to Dashboard</a> ';
          }
          html +=
            '<button type="button" class="btn btn-outline" id="cta-continue-exam-complete">View Completion</button>';
        } else {
          html +=
            "<p><strong>Complete the course evaluation to receive your certificate.</strong></p>" +
            '<button type="button" class="btn btn-primary" id="cta-continue-evaluation">Continue to Course Evaluation</button>';
        }
      } else {
        html +=
          '<div class="cta-quiz-result__icon cta-quiz-result__icon--fail" aria-hidden="true">\u2715</div>' +
          "<h2>You did not pass this time</h2>" +
          "<p>Score: " +
          data.score +
          "% (Passing: " +
          (data.passing_score || passingScore) +
          "%)</p>";

        html +=
          '<button type="button" class="btn btn-primary" id="cta-retry-quiz">Retry Quiz</button>';
      }

      resultEl.innerHTML = html;
      showPanel("result");

      if (passed) {
        var continueBtn = document.getElementById("cta-continue-evaluation");
        var examCompleteBtn = document.getElementById("cta-continue-exam-complete");

        if (examCompleteBtn) {
          examCompleteBtn.addEventListener("click", function () {
            showPanel("exam_complete");
          });
        } else if (continueBtn) {
          continueBtn.addEventListener("click", function () {
            showPanel("evaluation");
          });
        } else if (nextStep === "complete" || isExamPrep) {
          setTimeout(function () {
            showPanel("exam_complete");
          }, 1500);
        } else {
          setTimeout(function () {
            showPanel("evaluation");
          }, 1500);
        }
      }

      var retryBtn = document.getElementById("cta-retry-quiz");
      if (retryBtn) {
        retryBtn.addEventListener("click", function () {
          window.location.reload();
        });
      }
    }

    function submitQuiz(autoSubmit) {
      if (!attemptId) {
        return;
      }

      var submitBtn = document.getElementById("cta-submit-quiz");
      var counts = countAnswered();

      if (!autoSubmit) {
        if (counts.answered < counts.total) {
          window.alert("Please answer all questions before submitting.");
          return;
        }

        if (
          !window.confirm(
            "Are you sure? You cannot change answers after submitting."
          )
        ) {
          return;
        }
      }

      stopTimer();

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = autoSubmit ? "Time expired \u2014 submitting\u2026" : "Submitting...";
      }

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_submit_quiz",
        nonce: ctaAjax.nonce,
        attempt_id: attemptId,
        answers: collectAnswers()
      })
        .done(function (response) {
          if (!response.success || !response.data) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to submit quiz."
            );
            if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = "Submit Quiz";
            }
            return;
          }

          revealResults(response.data.results);
          renderResult(response.data);
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Quiz";
          }
        });
    }

    $(app).on("change", '.cta-quiz-question input[type="radio"]', updateAnswerCounter);

    var startBtn = document.getElementById("cta-start-quiz");
    var retakeBtn = document.getElementById("cta-retake-exam-quiz");

    function startQuizAttempt(button) {
      if (!button) {
        return;
      }
      var originalText = button.textContent;
      button.disabled = true;
      button.textContent = "Loading...";

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_start_quiz",
        nonce: ctaAjax.nonce,
        course_id: courseId,
        quiz_id: quizId || 0
      })
        .done(function (response) {
          if (!response.success || !response.data) {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to start quiz."
            );
            button.disabled = false;
            button.textContent = originalText;
            return;
          }

          attemptId = response.data.attempt_id;
          app.setAttribute("data-attempt-id", String(attemptId));

          if (response.data.question_count) {
            questionCount = parseInt(response.data.question_count, 10) || questionCount;
          }

          if (response.data.html && questionsEl) {
            questionsEl.innerHTML = response.data.html;
          }

          answeredCount = 0;
          updateAnswerCounter();
          showPanel("questions");
          button.disabled = false;
          button.textContent = originalText;
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
          button.disabled = false;
          button.textContent = originalText;
        });
    }

    if (startBtn) {
      startBtn.addEventListener("click", function () {
        startQuizAttempt(startBtn);
      });
    }
    if (retakeBtn) {
      retakeBtn.addEventListener("click", function () {
        startQuizAttempt(retakeBtn);
      });
    }

    var submitQuizBtn = document.getElementById("cta-submit-quiz");
    if (submitQuizBtn) {
      submitQuizBtn.addEventListener("click", function () {
        submitQuiz(false);
      });
    }

    function showCertificateFromResponse(response) {
      showPanel("certificate");

      var certPanel = panels.certificate;
      if (!certPanel || !response || !response.data) {
        return;
      }

      var numberEl = document.getElementById("cta-certificate-number");
      if (numberEl && response.data.certificate_number) {
        numberEl.textContent = response.data.certificate_number;
      }

      var actions = document.getElementById("cta-certificate-actions");
      var printUrl =
        response.data.print_url || response.data.download_url || "";
      var downloadUrl =
        response.data.download_url || response.data.print_url || "";
      var certId = response.data.certificate_id
        ? String(response.data.certificate_id)
        : "";

      if (actions) {
        actions.classList.add("cta-certificate-actions");

        var printBtn = actions.querySelector(".cta-print-cert-btn");
        var downloadBtn = actions.querySelector(".cta-download-cert-btn");

        if (!printBtn && printUrl) {
          printBtn = document.createElement("a");
          printBtn.className = "btn btn-primary cta-print-cert-btn";
          printBtn.target = "_blank";
          printBtn.rel = "noopener";
          printBtn.setAttribute("data-cert-action", "print");
          printBtn.textContent = "Print / Save as PDF";
          actions.appendChild(printBtn);
        }

        if (!downloadBtn && downloadUrl) {
          downloadBtn = document.createElement("a");
          downloadBtn.className = "btn btn-outline cta-download-cert-btn";
          downloadBtn.rel = "noopener";
          downloadBtn.setAttribute("data-cert-action", "download");
          downloadBtn.textContent = "Download Certificate";
          actions.appendChild(downloadBtn);
        }

        if (printBtn && printUrl) {
          printBtn.href = printUrl;
          if (certId) {
            printBtn.setAttribute("data-certificate-id", certId);
          }
        }

        if (downloadBtn && downloadUrl) {
          downloadBtn.href = downloadUrl;
          if (certId) {
            downloadBtn.setAttribute("data-certificate-id", certId);
          }
        }
      }

      certPanel.classList.add("cta-quiz-certificate-ready--animate");
    }

    var evalBtn = document.getElementById("cta-submit-evaluation");
    if (evalBtn) {
      evalBtn.addEventListener("click", function () {
        var form = document.getElementById("cta-evaluation-form");
        if (!form) {
          return;
        }

        var responses = {};
        var questions = form.querySelectorAll(".cta-evaluation-question");
        var missing = false;

        questions.forEach(function (questionEl) {
          var questionId = questionEl.getAttribute("data-question-id");
          var questionType = questionEl.getAttribute("data-question-type") || "";
          if (!questionId) {
            return;
          }

          if (questionType === "textarea" || questionType === "paragraph" || questionType === "short_text") {
            var textInput = questionEl.querySelector("textarea, input[type='text']");
            var textVal = textInput ? textInput.value : "";
            responses[questionId] = textVal;
            if (textInput && textInput.required && !String(textVal).trim()) {
              missing = true;
            }
            return;
          }

          if (questionType === "dropdown") {
            var select = questionEl.querySelector("select");
            var selectVal = select ? select.value : "";
            responses[questionId] = selectVal;
            if (select && select.required && !selectVal) {
              missing = true;
            }
            return;
          }

          if (questionType === "checkbox") {
            var checkedBoxes = questionEl.querySelectorAll('input[type="checkbox"]:checked');
            var values = [];
            checkedBoxes.forEach(function (box) {
              values.push(box.value);
            });
            responses[questionId] = values;
            if (questionEl.querySelectorAll('input[type="checkbox"]').length && values.length === 0 && questionEl.querySelector(".cta-required")) {
              missing = true;
            }
            return;
          }

          var selected = questionEl.querySelector('input[type="radio"]:checked');
          var requiredInput = questionEl.querySelector("input[required]");

          if (requiredInput && !selected) {
            missing = true;
            return;
          }

          responses[questionId] = selected ? selected.value : "";
        });

        if (missing) {
          window.alert("Please complete all required evaluation questions.");
          return;
        }

        var studentTimezone = "";
        try {
          studentTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
        } catch (err) {
          studentTimezone = "";
        }

        evalBtn.disabled = true;
        evalBtn.textContent = "Submitting...";

        $.post(ctaAjax.ajaxUrl, {
          action: "cta_submit_evaluation",
          nonce: ctaAjax.nonce,
          course_id: courseId,
          responses: responses,
          timezone: studentTimezone
        })
          .done(function (response) {
            if (!response.success || !response.data) {
              window.alert(
                response.data && response.data.message
                  ? response.data.message
                  : "Unable to submit evaluation."
              );
              evalBtn.disabled = false;
              evalBtn.textContent = "Submit Evaluation";
              return;
            }

            var nextStep = response.data.next_step || "attestation";
            if (nextStep === "certificate") {
              showCertificateFromResponse(response);
            } else {
              showPanel("attestation");
            }
          })
          .fail(function () {
            window.alert("Something went wrong. Please try again.");
            evalBtn.disabled = false;
            evalBtn.textContent = "Submit Evaluation";
          });
      });
    }

    var attestBtn = document.getElementById("cta-submit-attestation");
    if (attestBtn) {
      attestBtn.addEventListener("click", function () {
        var agree = document.getElementById("cta-attestation-agree");
        if (!agree || !agree.checked) {
          window.alert("Please check the attestation checkbox to continue.");
          if (agree) {
            agree.focus();
          }
          return;
        }

        var signatureField = document.getElementById("cta-attestation-signature");
        var signatureName = signatureField ? signatureField.value.trim() : "";
        if (!signatureName || signatureName.length < 2) {
          window.alert("Please complete the Typed Name field to electronically sign this attestation.");
          if (signatureField) {
            signatureField.focus();
          }
          return;
        }

        var dateField = document.getElementById("cta-attestation-date");
        var signatureDate = dateField ? dateField.value.trim() : "";
        if (!signatureDate) {
          window.alert("Please enter the attestation date.");
          if (dateField) {
            dateField.focus();
          }
          return;
        }

        attestBtn.disabled = true;
        attestBtn.textContent = "Submitting...";

        $.post(ctaAjax.ajaxUrl, {
          action: "cta_submit_attestation",
          nonce: ctaAjax.nonce,
          course_id: courseId,
          agree: 1,
          signature_name: signatureName,
          signature_date: signatureDate
        })
          .done(function (response) {
            if (!response.success || !response.data) {
              window.alert(
                response.data && response.data.message
                  ? response.data.message
                  : "Unable to submit attestation."
              );
              attestBtn.disabled = false;
              attestBtn.textContent = "Submit Attestation & Get Certificate";
              return;
            }

            showCertificateFromResponse(response);
          })
          .fail(function () {
            window.alert("Something went wrong. Please try again.");
            attestBtn.disabled = false;
            attestBtn.textContent = "Submit Attestation & Get Certificate";
          });
      });
    }

    if (panels.questions && !panels.questions.hidden) {
      updateAnswerCounter();
      startTimer();
    }
  }

  /**
   * WordPress CE / supervision dashboard profile settings save.
   */
  function initCtaDashboardSettings() {
    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;

    $(document).on("submit", ".cta-dashboard-settings-form", function (e) {
      e.preventDefault();

      var form = this;
      var $form = $(form);

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var fullName =
        ($form.find('[name="full_name"]').val() || "").trim() ||
        ($("#settings-name").val() || "").trim() ||
        ($("#sup-name").val() || "").trim();
      var licenseVal = (
        $form.find('[name="license_number"]').val() ||
        $("#settings-license").val() ||
        ""
      ).trim();
      var licenseType =
        $form.find('[name="license_type"]').val() ||
        $("#settings-license-type").val() ||
        "";
      var associateNumber = (
        $form.find('[name="associate_number"]').val() ||
        $("#sup-associate").val() ||
        ""
      ).trim();

      if (!fullName) {
        window.alert("Full name is required.");
        return;
      }

      if (licenseVal && !/[A-Za-z0-9]/.test(licenseVal)) {
        window.alert(
          "License number looks invalid. Include at least one letter or number."
        );
        return;
      }

      var btn = form.querySelector('[type="submit"]');
      var originalText = btn ? btn.textContent : "";

      if (btn) {
        btn.disabled = true;
        btn.textContent = "Saving...";
      }

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_save_profile",
        nonce: ctaAjax.nonce,
        full_name: fullName,
        license_number: licenseVal,
        license_type: licenseType,
        associate_number: associateNumber
      })
        .done(function (response) {
          var existing = form.querySelector(".dashboard-settings__notice");
          if (existing) {
            existing.remove();
          }

          var notice = document.createElement("p");
          notice.className =
            "dashboard-settings__notice dashboard-settings__notice--" +
            (response && response.success ? "success" : "error");
          notice.setAttribute("role", "status");
          notice.textContent =
            response && response.data && response.data.message
              ? response.data.message
              : response && response.success
                ? "Your changes have been saved successfully."
                : "Something went wrong. Please try again.";
          form.insertBefore(notice, form.firstChild);

          if (response && response.success && response.data) {
            var displayName = response.data.displayName || fullName;
            var licenseLabel =
              response.data.associateNumber ||
              response.data.licenseNumber ||
              "";
            var initials = response.data.initials || "";

            document.querySelectorAll("[data-user-name]").forEach(function (el) {
              el.textContent = displayName;
            });
            $(".dashboard-user__name").text(displayName);
            $(".dashboard-welcome__greeting").each(function () {
              var el = $(this);
              if (el.closest('[data-dashboard-panel="settings"]').length) {
                return;
              }
              var first = displayName.split(/\s+/)[0] || displayName;
              el.text("Welcome back, " + first);
            });

            if (licenseLabel) {
              document
                .querySelectorAll("[data-user-license]")
                .forEach(function (el) {
                  el.textContent = licenseLabel;
                });
            }

            if (initials) {
              document
                .querySelectorAll("[data-user-avatar]")
                .forEach(function (el) {
                  el.textContent = initials;
                });
            }
          }
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
        })
        .always(function () {
          if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
          }
        });
    });
  }

  /**
   * WordPress certificate print / download buttons.
   */
  function initCtaCertificateDownload() {
    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;

    $(document).on(
      "click",
      ".cta-print-cert-btn, .cta-download-cert-btn",
      function (e) {
        e.preventDefault();

        var btn = $(this);
        var certId = btn.data("certificate-id");
        var action = String(btn.data("cert-action") || "").toLowerCase();
        var isDownload =
          action === "download" ||
          (action !== "print" && btn.hasClass("cta-download-cert-btn"));
        var originalHtml = btn.html();

        if (!certId) {
          window.alert("Certificate not found.");
          return;
        }

        btn.prop("disabled", true).text(isDownload ? "Downloading..." : "Opening...");

        $.post(ctaAjax.ajaxUrl, {
          action: "cta_download_cert",
          nonce: ctaAjax.nonce,
          certificate_id: certId
        })
          .done(function (response) {
            if (!response.success || !response.data) {
              window.alert(
                response.data && response.data.message
                  ? response.data.message
                  : "Unable to open certificate."
              );
              btn.prop("disabled", false).html(originalHtml);
              return;
            }

            var printUrl = response.data.print_url || response.data.download_url;
            var downloadUrl =
              response.data.download_url || response.data.print_url;
            var targetUrl = isDownload ? downloadUrl : printUrl;

            if (!targetUrl) {
              window.alert("Unable to open certificate.");
              btn.prop("disabled", false).html(originalHtml);
              return;
            }

            if (isDownload) {
              window.location.href = targetUrl;
            } else {
              window.open(targetUrl, "_blank");
            }

            btn.prop("disabled", false).html(originalHtml);
          })
          .fail(function () {
            window.alert("Something went wrong. Please try again.");
            btn.prop("disabled", false).html(originalHtml);
          });
      }
    );

    $(document).on("click", ".cta-download-resource", function (e) {
      e.preventDefault();

      var btn = $(this);
      var resourceId = btn.data("resource-id");
      var originalHtml = btn.html();

      btn.prop("disabled", true);

      $.post(ctaAjax.ajaxUrl, {
        action: "cta_download_resource",
        nonce: ctaAjax.nonce,
        resource_id: resourceId
      })
        .done(function (response) {
          if (response.success && response.data && response.data.download_url) {
            window.open(response.data.download_url, "_blank");
          } else {
            window.alert(
              response.data && response.data.message
                ? response.data.message
                : "Unable to download file."
            );
          }
        })
        .fail(function () {
          window.alert("Something went wrong. Please try again.");
        })
        .always(function () {
          btn.prop("disabled", false).html(originalHtml);
        });
    });
  }

  /**
   * WordPress course catalog ([cta_course_catalog] shortcode)
   */
  function initCtaCourseCatalog() {
    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;

    if (!$("#cta-courses-grid").length) {
      return;
    }

    var filterTimer;
    var $catalog = $(".cta-course-catalog");
    var limit = parseInt($catalog.data("limit"), 10);

    if (isNaN(limit)) {
      limit = -1;
    }

    $("#cta-course-search").on("input", function () {
      clearTimeout(filterTimer);
      filterTimer = setTimeout(function () {
        fetchCourses();
      }, 400);
    });

    $(document).on("click", ".cta-course-catalog .cta-pill", function (e) {
      e.preventDefault();
      $(".cta-course-catalog .cta-pill").removeClass("cta-pill--active");
      $(this).addClass("cta-pill--active");
      fetchCourses();
    });

    $("#cta-course-sort").on("change", function () {
      fetchCourses();
    });

    function fetchCourses() {
      var category = $(".cta-course-catalog .cta-pill--active").attr("data-category") || "";
      var search = $("#cta-course-search").val() || "";
      var sort = $("#cta-course-sort").val() || "default";

      $("#cta-courses-loader").show();
      $("#cta-courses-grid").css("opacity", "0.3");

      $.ajax({
        url: ctaAjax.ajaxUrl,
        type: "POST",
        data: {
          action: "cta_filter_courses",
          nonce: ctaAjax.nonce,
          category: category,
          search: search,
          sort: sort,
          limit: limit,
          product_type: $(".cta-course-catalog").attr("data-product-type") || "ce"
        },
        success: function (response) {
          if (response.success) {
            $("#cta-courses-grid").html(response.data.html);
            $(".cta-filter-bar__count").text(
              "Showing " + response.data.count + " courses"
            );
          }
        },
        complete: function () {
          $("#cta-courses-loader").hide();
          $("#cta-courses-grid").css("opacity", "1");
        }
      });
    }
  }

  /**
   * Bundle / membership plan purchase buttons (handled by initCtaStripePayments).
   */
  function initCtaBundlePurchase() {
    /* Unified in initCtaStripePayments */
  }

  /**
   * Header / Elementor auth button: keep logged-in chrome in sync and open account menu.
   * Also upgrades hardcoded "Learner Login" / "Login" theme links into My Account controls.
   */
  function initCtaAuthChrome() {
    function hasWpLoginCookie() {
      return /(?:^|;\s*)wordpress_logged_in_/.test(document.cookie);
    }

    function normalizeAuthLabel(text) {
      return String(text || "")
        .replace(/\s+/g, " ")
        .replace(/[→➞»]+/g, "")
        .trim()
        .toLowerCase();
    }

    function isGuestAuthLabel(text) {
      var t = normalizeAuthLabel(text);
      return (
        t === "login" ||
        t === "log in" ||
        t === "learner login" ||
        t === "learner log in" ||
        t === "sign in"
      );
    }

    function isDashboardAuthLabel(text) {
      var t = normalizeAuthLabel(text);
      return (
        t === "my dashboard" ||
        t === "learner dashboard" ||
        t === "my account" ||
        t === "dashboard"
      );
    }

    function stripTrailingSlash(url) {
      return String(url || "").replace(/\/+$/, "");
    }

    function isLoggedInState() {
      var ajaxLoggedIn =
        typeof ctaAjax !== "undefined" && ctaAjax.isLoggedIn === "yes";
      return ajaxLoggedIn || hasWpLoginCookie();
    }

    function buildAuthRoot(loggedIn) {
      var dashUrl =
        (typeof ctaAjax !== "undefined" && ctaAjax.dashboardUrl) || "#";
      var loginUrl =
        (typeof ctaAjax !== "undefined" && ctaAjax.loginUrl) || "#";
      var logoutUrl =
        (typeof ctaAjax !== "undefined" && ctaAjax.logoutUrl) || "#";
      var coursesUrl =
        (typeof ctaAjax !== "undefined" && ctaAjax.coursesUrl) || "";
      var examPrepUrl =
        (typeof ctaAjax !== "undefined" && ctaAjax.examPrepUrl) || "";
      var name =
        (typeof ctaAjax !== "undefined" && ctaAjax.currentUser) || "";
      var label = name ? "Hi, " + name : "My Dashboard";

      var wrap = document.createElement("div");
      wrap.className = "cta-plugin-wrapper cta-auth-button-wrap";
      wrap.setAttribute("data-cta-auth-root", "");
      wrap.setAttribute("data-logged-in", loggedIn ? "yes" : "no");
      wrap.setAttribute("data-login-url", loginUrl);
      wrap.setAttribute("data-dashboard-url", dashUrl);
      wrap.setAttribute("data-logout-url", logoutUrl);
      wrap.setAttribute("data-dashboard-text", "My Dashboard");
      wrap.setAttribute("data-display-name", name);

      var guest = document.createElement("a");
      guest.href = loginUrl;
      guest.className = "btn btn-outline btn--sm cta-auth-button cta-auth-link cta-auth-link--guest";
      guest.setAttribute("data-cta-auth-guest", "");
      guest.textContent = "Learner Login";
      if (loggedIn) guest.hidden = true;

      var user = document.createElement("div");
      user.className = "cta-auth-account" + (loggedIn ? " is-openable" : "");
      user.setAttribute("data-cta-auth-user", "");
      if (!loggedIn) user.hidden = true;

      var toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "btn btn-outline btn--sm cta-auth-button cta-auth-account__toggle";
      toggle.setAttribute("data-cta-auth-toggle", "");
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-haspopup", "true");
      toggle.innerHTML =
        '<span class="cta-auth-account__label" data-cta-auth-label></span>' +
        '<span class="cta-auth-account__caret" aria-hidden="true">▾</span>';
      toggle.querySelector("[data-cta-auth-label]").textContent = label;

      var menu = document.createElement("div");
      menu.className = "cta-auth-account__menu";
      menu.setAttribute("data-cta-auth-menu", "");
      menu.setAttribute("hidden", "");

      var links = [
        { href: dashUrl, text: "My Dashboard" },
        { href: dashUrl + "#courses", text: "My Courses" },
        { href: dashUrl + "#certificates", text: "My Certificates" },
        { href: dashUrl + "#settings", text: "Account Settings" }
      ];
      if (coursesUrl) {
        links.push({ href: coursesUrl, text: "Browse CE Courses" });
      }
      if (examPrepUrl) {
        links.push({ href: examPrepUrl, text: "Browse Exam Preparation" });
      }
      if (logoutUrl) {
        links.push({ href: logoutUrl, text: "Log Out", logout: true });
      }

      links.forEach(function (item) {
        var a = document.createElement("a");
        a.href = item.href;
        a.textContent = item.text;
        if (item.logout) a.className = "cta-auth-account__logout";
        menu.appendChild(a);
      });

      user.appendChild(toggle);
      user.appendChild(menu);
      // Never leave both guest + account visible (theme CSS often ignores [hidden]).
      if (!loggedIn) {
        wrap.appendChild(guest);
      }
      wrap.appendChild(user);
      return wrap;
    }

    function setAuthNodeVisible(node, visible) {
      if (!node) return;
      node.hidden = !visible;
      node.classList.toggle("cta-auth-is-hidden", !visible);
      if (!visible) {
        node.setAttribute("aria-hidden", "true");
      } else {
        node.removeAttribute("aria-hidden");
      }
    }

    function syncAuthRoot(root) {
      if (!root) return;

      var serverLoggedIn = root.getAttribute("data-logged-in") === "yes";
      var loggedIn = serverLoggedIn || isLoggedInState();
      var guest = root.querySelector("[data-cta-auth-guest]");
      var user = root.querySelector("[data-cta-auth-user]");
      var label = root.querySelector("[data-cta-auth-label]");

      if (guest) {
        setAuthNodeVisible(guest, !loggedIn);
      }
      if (user) {
        setAuthNodeVisible(user, !!loggedIn);
        user.classList.toggle("is-openable", !!loggedIn);
      }

      root.setAttribute("data-logged-in", loggedIn ? "yes" : "no");
      root.classList.toggle("cta-auth-button-wrap--logged-in", !!loggedIn);

      if (loggedIn && label) {
        var name =
          (typeof ctaAjax !== "undefined" && ctaAjax.currentUser) ||
          root.getAttribute("data-display-name") ||
          "";
        if (name) {
          label.textContent = "Hi, " + name;
        } else {
          label.textContent =
            root.getAttribute("data-dashboard-text") || "My Dashboard";
        }
      }

      if (loggedIn && typeof ctaAjax !== "undefined" && ctaAjax.dashboardUrl) {
        // Never let account links point at the supervision portal.
        var dashUrl = ctaAjax.dashboardUrl;
        var supUrl = stripTrailingSlash(ctaAjax.supervisionDashboardUrl || "");
        if (supUrl && stripTrailingSlash(dashUrl) === supUrl) {
          dashUrl = ctaAjax.studentDashboardUrl || dashUrl;
        }
        root.setAttribute("data-dashboard-url", dashUrl);
        root.querySelectorAll("[href]").forEach(function (link) {
          var href = link.getAttribute("href") || "";
          var text = normalizeAuthLabel(link.textContent);
          if (href.indexOf("#certificates") !== -1) {
            link.setAttribute("href", dashUrl + "#certificates");
          } else if (href.indexOf("#settings") !== -1) {
            link.setAttribute("href", dashUrl + "#settings");
          } else if (href.indexOf("#courses") !== -1) {
            link.setAttribute("href", dashUrl + "#courses");
          } else if (text === "my dashboard") {
            link.setAttribute("href", dashUrl);
          }
        });
      }
    }

    function upgradeLegacyAuthLinks() {
      if (typeof ctaAjax === "undefined") return;

      var loggedIn = isLoggedInState();
      var loginUrl = stripTrailingSlash(ctaAjax.loginUrl || "");
      var dashUrl = stripTrailingSlash(ctaAjax.dashboardUrl || "");
      var supUrl = stripTrailingSlash(ctaAjax.supervisionDashboardUrl || "");

      // If localize accidentally equals supervision portal, prefer studentDashboardUrl.
      if (dashUrl && supUrl && dashUrl === supUrl && ctaAjax.studentDashboardUrl) {
        dashUrl = stripTrailingSlash(ctaAjax.studentDashboardUrl);
        ctaAjax.dashboardUrl = ctaAjax.studentDashboardUrl;
      }

      var candidates = document.querySelectorAll(
        "header a, footer a, nav a, .elementor-location-header a, .elementor-location-footer a, .site-header a"
      );

      candidates.forEach(function (anchor) {
        if (!anchor || anchor.closest("[data-cta-auth-root]")) return;
        if (anchor.getAttribute("data-cta-auth-upgraded") === "1") return;

        var text = anchor.textContent || "";
        var href = stripTrailingSlash(anchor.href || "");
        var pointsToLogin = loginUrl && href === loginUrl;
        var pointsToSup =
          supUrl && href === supUrl && isDashboardAuthLabel(text);
        var shouldUpgrade =
          isGuestAuthLabel(text) ||
          (isDashboardAuthLabel(text) && (pointsToLogin || pointsToSup));

        if (!shouldUpgrade) {
          return;
        }

        if (!loggedIn) {
          return;
        }

        // Logged-in: replace Elementor/theme login CTAs with account menu.
        var wrap = buildAuthRoot(true);
        wrap.setAttribute("data-cta-auth-upgraded-from", normalizeAuthLabel(text));
        anchor.setAttribute("data-cta-auth-upgraded", "1");
        var target =
          anchor.closest(".elementor-widget-button") ||
          anchor.closest(".elementor-button-wrapper") ||
          anchor;
        if (target.parentNode) {
          target.parentNode.replaceChild(wrap, target);
          syncAuthRoot(wrap);
        }
      });

      // Hide any leftover guest login CTAs once an account control exists.
      if (loggedIn) {
        document
          .querySelectorAll(
            "header a, footer a, nav a, .elementor-location-header a, .elementor-location-footer a, .site-header a"
          )
          .forEach(function (anchor) {
            if (!anchor || anchor.closest("[data-cta-auth-root]")) return;
            if (!isGuestAuthLabel(anchor.textContent || "")) return;
            var leftover =
              anchor.closest(".elementor-widget-button") ||
              anchor.closest(".elementor-button-wrapper") ||
              anchor;
            leftover.classList.add("cta-auth-is-hidden");
            leftover.setAttribute("hidden", "");
            leftover.setAttribute("aria-hidden", "true");
            leftover.style.setProperty("display", "none", "important");
          });
      }

      // Fix remaining logged-in menu items titled My Dashboard that still hit supervision.
      if (loggedIn && dashUrl) {
        document.querySelectorAll("a.cta-nav-my-dashboard, a").forEach(function (a) {
          if (!a || a.closest("[data-cta-auth-root]")) return;
          if (!isDashboardAuthLabel(a.textContent || "")) return;
          var href = stripTrailingSlash(a.href || "");
          if (supUrl && href === supUrl) {
            a.setAttribute("href", ctaAjax.dashboardUrl);
          }
          if (loginUrl && href === loginUrl) {
            a.setAttribute("href", ctaAjax.dashboardUrl);
          }
        });
      }
    }

    upgradeLegacyAuthLinks();
    document.querySelectorAll("[data-cta-auth-root]").forEach(syncAuthRoot);

    document.addEventListener("click", function (e) {
      var toggle = e.target.closest("[data-cta-auth-toggle]");
      if (toggle) {
        e.preventDefault();
        e.stopPropagation();
        var root = toggle.closest("[data-cta-auth-root]");
        var menu = root ? root.querySelector("[data-cta-auth-menu]") : null;
        if (!menu) return;
        var open = menu.hasAttribute("hidden");
        document.querySelectorAll("[data-cta-auth-menu]").forEach(function (m) {
          m.setAttribute("hidden", "");
        });
        document.querySelectorAll("[data-cta-auth-toggle]").forEach(function (t) {
          t.setAttribute("aria-expanded", "false");
        });
        if (open) {
          menu.removeAttribute("hidden");
          toggle.setAttribute("aria-expanded", "true");
        }
        return;
      }

      if (!e.target.closest("[data-cta-auth-root]")) {
        document.querySelectorAll("[data-cta-auth-menu]").forEach(function (m) {
          m.setAttribute("hidden", "");
        });
        document.querySelectorAll("[data-cta-auth-toggle]").forEach(function (t) {
          t.setAttribute("aria-expanded", "false");
        });
      }
    });
  }

  /**
   * WordPress login/register forms ([cta_login_form] shortcode)
   */
  function initCtaAuthForms() {
    var loginForm = document.getElementById("cta-login-form");
    var registerForm = document.getElementById("cta-register-form");

    if (!loginForm && !registerForm) {
      return;
    }

    if (typeof jQuery === "undefined" || typeof ctaAjax === "undefined") {
      return;
    }

    var $ = jQuery;
    var loginBtn = document.getElementById("cta-login-btn");
    var registerBtn = document.getElementById("cta-register-btn");
    var loginError = document.getElementById("cta-login-error");
    var registerError = document.getElementById("cta-register-error");
    var registerSuccess = document.getElementById("cta-register-success");
    var formContainer = document.querySelector(".auth-page__form-container");
    var loginBtnText = loginBtn ? loginBtn.textContent : "Log In";
    var registerBtnText = registerBtn ? registerBtn.textContent : "Create Account";

    function hideMessage(el) {
      if (!el) return;
      el.style.display = "none";
      el.textContent = "";
    }

    function showMessage(el, message, isSuccess) {
      if (!el) return;
      el.textContent = message;
      el.style.display = "block";
      if (isSuccess) {
        el.classList.add("cta-form-success");
        el.classList.remove("cta-form-error");
      } else {
        el.classList.add("cta-form-error");
        el.classList.remove("cta-form-success");
      }
    }

    function clearAuthMessages() {
      hideMessage(loginError);
      hideMessage(registerError);
      hideMessage(registerSuccess);
    }

    function toggleAuthForm(formToShow) {
      if (!loginForm || !registerForm) return;

      clearAuthMessages();

      if (formToShow === "register") {
        loginForm.classList.add("form-hidden");
        loginForm.setAttribute("hidden", "");
        registerForm.classList.remove("form-hidden");
        registerForm.removeAttribute("hidden");
      } else {
        registerForm.classList.add("form-hidden");
        registerForm.setAttribute("hidden", "");
        loginForm.classList.remove("form-hidden");
        loginForm.removeAttribute("hidden");
      }

      if (formContainer && formContainer.scrollIntoView) {
        formContainer.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }

    document.querySelectorAll("[data-cta-auth-toggle]").forEach(function (button) {
      button.addEventListener("click", function () {
        var target = button.getAttribute("data-cta-auth-toggle");
        toggleAuthForm(target === "register" ? "register" : "login");
      });
    });

    function fetchAuthNonce(kind) {
      var deferred = $.Deferred();

      if (typeof ctaAjax === "undefined" || !ctaAjax.ajaxUrl) {
        deferred.reject();
        return deferred.promise();
      }

      $.post(ctaAjax.ajaxUrl, { action: "cta_auth_nonce" })
        .done(function (response) {
          if (
            response &&
            response.success &&
            response.data &&
            ((kind === "login" && response.data.login_nonce) ||
              (kind === "register" && response.data.register_nonce))
          ) {
            deferred.resolve(
              kind === "login"
                ? response.data.login_nonce
                : response.data.register_nonce
            );
            return;
          }
          deferred.reject();
        })
        .fail(function () {
          deferred.reject();
        });

      return deferred.promise();
    }

    function resolveAuthNonce(kind, fallbackNonce) {
      var deferred = $.Deferred();

      fetchAuthNonce(kind)
        .done(function (freshNonce) {
          deferred.resolve(freshNonce || fallbackNonce || "");
        })
        .fail(function () {
          deferred.resolve(fallbackNonce || "");
        });

      return deferred.promise();
    }

    function authFailMessage(xhr) {
      var fallback = "Something went wrong. Please try again.";

      if (!xhr) {
        return fallback;
      }

      if (xhr.status === 0) {
        return "Network error. Please check your connection and try again.";
      }

      try {
        var parsed = xhr.responseJSON || JSON.parse(xhr.responseText || "");
        if (parsed && parsed.data && parsed.data.message) {
          return parsed.data.message;
        }
      } catch (e) {}

      if (xhr.responseText === "0" || xhr.responseText === "-1") {
        return "Your session expired. Please refresh the page and try again.";
      }

      return fallback;
    }

    if (loginBtn && loginForm) {
      loginBtn.addEventListener("click", function (e) {
        e.preventDefault();
        hideMessage(loginError);

        if (!loginForm.checkValidity()) {
          loginForm.reportValidity();
          return;
        }

        var email = loginForm.querySelector('[name="cta_email"]').value.trim();
        var password = loginForm.querySelector('[name="cta_password"]').value;
        var nonceField = loginForm.querySelector('[name="cta_login_nonce"]');
        var fallbackNonce = nonceField ? nonceField.value : "";

        loginBtn.textContent = "Logging in...";
        loginBtn.disabled = true;

        resolveAuthNonce("login", fallbackNonce)
          .then(function (nonce) {
            return $.post(ctaAjax.ajaxUrl, {
              action: "cta_login",
              nonce: nonce,
              email: email,
              password: password
            });
          })
          .done(function (response) {
            if (response && response.success) {
              showMessage(
                loginError,
                response.data && response.data.message
                  ? response.data.message
                  : "Login successful! Redirecting...",
                true
              );

              setTimeout(function () {
                window.location.href =
                  response.data && response.data.redirect_url
                    ? response.data.redirect_url
                    : ctaAjax.pluginUrl;
              }, 800);
              return;
            }

            showMessage(
              loginError,
              response && response.data && response.data.message
                ? response.data.message
                : "Login failed. Please try again.",
              false
            );
            loginBtn.textContent = loginBtnText;
            loginBtn.disabled = false;
          })
          .fail(function (xhr) {
            showMessage(loginError, authFailMessage(xhr), false);
            loginBtn.textContent = loginBtnText;
            loginBtn.disabled = false;
          });
      });
    }

    var userTypeSelect = registerForm
      ? registerForm.querySelector('[name="cta_user_type"]')
      : null;

    try {
      var authParams = new URLSearchParams(window.location.search);
      if (authParams.get("cta_auth") === "register") {
        toggleAuthForm("register");
        if (userTypeSelect) {
          userTypeSelect.value = "cta_associate";
        }
      }
    } catch (e) {}

    if (registerBtn && registerForm) {
      registerBtn.addEventListener("click", function (e) {
        e.preventDefault();
        hideMessage(registerError);
        hideMessage(registerSuccess);

        if (!registerForm.checkValidity()) {
          registerForm.reportValidity();
          return;
        }

        var fullname = registerForm.querySelector('[name="cta_fullname"]').value.trim();
        var email = registerForm.querySelector('[name="cta_reg_email"]').value.trim();
        var password = registerForm.querySelector('[name="cta_reg_password"]').value;
        var confirmPassword = registerForm.querySelector('[name="cta_reg_confirm_password"]').value;
        var userType = registerForm.querySelector('[name="cta_user_type"]').value;
        var nonceField = registerForm.querySelector('[name="cta_register_nonce"]');

        if (password !== confirmPassword) {
          showMessage(registerError, "Passwords do not match.", false);
          return;
        }

        if (password.length < 8) {
          showMessage(registerError, "Password must be at least 8 characters.", false);
          return;
        }

        if (!userType) {
          showMessage(registerError, "Please select a valid account type.", false);
          return;
        }

        registerBtn.textContent = "Creating account...";
        registerBtn.disabled = true;

        var fallbackNonce = nonceField ? nonceField.value : "";

        var registerPayloadBase = {
          action: "cta_register",
          fullname: fullname,
          email: email,
          password: password,
          confirm_password: confirmPassword,
          user_type: userType
        };

        resolveAuthNonce("register", fallbackNonce)
          .then(function (nonce) {
            return $.post(
              ctaAjax.ajaxUrl,
              $.extend({}, registerPayloadBase, { nonce: nonce })
            );
          })
          .done(function (response) {
            if (response && response.success) {
              showMessage(
                registerSuccess,
                response.data && response.data.message
                  ? response.data.message
                  : "Account created successfully! Redirecting...",
                true
              );

              setTimeout(function () {
                window.location.href =
                  response.data && response.data.redirect_url
                    ? response.data.redirect_url
                    : ctaAjax.pluginUrl;
              }, 1200);
              return;
            }

            showMessage(
              registerError,
              response && response.data && response.data.message
                ? response.data.message
                : "Registration failed. Please try again.",
              false
            );
            registerBtn.textContent = registerBtnText;
            registerBtn.disabled = false;
          })
          .fail(function (xhr) {
            showMessage(registerError, authFailMessage(xhr), false);
            registerBtn.textContent = registerBtnText;
            registerBtn.disabled = false;
          });
      });
    }
  }

  /**
   * Login / Register page (mock auth for static prototype)
   */
  function initAuthForms() {
    if (document.getElementById("cta-login-form")) {
      return;
    }

    var loginForm = document.getElementById("login-form");
    var registerForm = document.getElementById("register-form");
    var toggleButtons = document.querySelectorAll("[data-auth-toggle]");
    var loginError = document.getElementById("login-form-error");
    var registerError = document.getElementById("register-form-error");

    if (!loginForm && !registerForm) return;

    function showAuthError(el, message) {
      if (!el) return;
      if (!message) {
        el.hidden = true;
        el.textContent = "";
        return;
      }
      el.textContent = message;
      el.hidden = false;
    }

    function showLogin() {
      if (!loginForm || !registerForm) return;
      loginForm.classList.remove("form-hidden");
      loginForm.removeAttribute("hidden");
      registerForm.classList.add("form-hidden");
      registerForm.setAttribute("hidden", "");
      document.title = "Log In | Clinical Training and Supervision Academy";
      showAuthError(registerError, "");
    }

    function showRegister() {
      if (!loginForm || !registerForm) return;
      registerForm.classList.remove("form-hidden");
      registerForm.removeAttribute("hidden");
      loginForm.classList.add("form-hidden");
      loginForm.setAttribute("hidden", "");
      document.title = "Create Account | Clinical Training and Supervision Academy";
      showAuthError(loginError, "");
    }

    toggleButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        var target = button.getAttribute("data-auth-toggle");
        if (target === "register") {
          showRegister();
        } else {
          showLogin();
        }
      });
    });

    if (loginForm) {
      loginForm.addEventListener("submit", function (e) {
        e.preventDefault();
        showAuthError(loginError, "");

        if (!loginForm.checkValidity()) {
          loginForm.reportValidity();
          return;
        }

        var identifierField = loginForm.querySelector('[name="email"], [name="username"]');
        var identifier = identifierField ? identifierField.value : "";
        var password = loginForm.querySelector('[name="password"]').value;
        var result = loginUser(identifier, password);

        if (!result.ok) {
          showAuthError(loginError, result.message);
          return;
        }

        var btn = loginForm.querySelector('[type="submit"]');
        if (btn) {
          btn.textContent = "Logging in...";
          btn.disabled = true;
        }

        setTimeout(function () {
          window.location.href = "dashboard-ce.html";
        }, 400);
      });
    }

    if (registerForm) {
      registerForm.addEventListener("submit", function (e) {
        e.preventDefault();
        showAuthError(registerError, "");

        if (!registerForm.checkValidity()) {
          registerForm.reportValidity();
          return;
        }

        var password = registerForm.querySelector('[name="password"]').value;
        var confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;

        if (password !== confirmPassword) {
          showAuthError(registerError, "Passwords do not match. Please try again.");
          return;
        }

        var result = registerUser({
          fullName: registerForm.querySelector('[name="full_name"]').value,
          email: registerForm.querySelector('[name="email"]').value,
          password: password,
          userType: registerForm.querySelector('[name="user_type"]').value
        });

        if (!result.ok) {
          showAuthError(registerError, result.message);
          return;
        }

        var btn = registerForm.querySelector('[type="submit"]');
        if (btn) {
          btn.textContent = "Creating account...";
          btn.disabled = true;
        }

        setTimeout(function () {
          window.location.href = "dashboard-ce.html";
        }, 600);
      });
    }
  }

  /**
   * Module video preview popup on single course page.
   */
  function initCourseModulePreview() {
    var modal = document.getElementById("cta-course-video-modal");
    if (!modal) return;

    var player = modal.querySelector(".cta-video-modal__player");
    var titleEl = modal.querySelector(".cta-video-modal__title");

    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = "";
      if (player) {
        player.innerHTML = "";
      }
    }

    function openModal(btn) {
      var targetId = btn.getAttribute("data-target");
      var source = targetId ? document.getElementById(targetId) : null;
      if (!source || !player) return;

      if (titleEl) {
        titleEl.textContent = btn.getAttribute("data-module-title") || "";
      }

      player.innerHTML = source.innerHTML;
      modal.hidden = false;
      document.body.style.overflow = "hidden";
    }

    document.querySelectorAll("[data-cta-module-preview]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openModal(btn);
      });
    });

    modal.querySelectorAll("[data-cta-close-video-modal]").forEach(function (el) {
      el.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hidden) {
        closeModal();
      }
    });
  }

  function init() {
    initMobileMenu();
    initAccordion();
    initTabs();
    initFaqFilters();
    initPoliciesNav();
    initPasswordToggle();
    initCtaAuthChrome();
    initCtaAuthForms();
    initCtaCourseCatalog();
    initCourseModulePreview();
    initCtaStripePayments();
    initCtaSupervisionBooking();
    initCtaWpCoursePlayer();
    initCtaQuiz();
    initCtaDashboardSettings();
    initCtaCertificateDownload();
    initCtaSupervisionDashboard();
    initCtaSupervisionApprovalWatcher();
    initCtaBundlePurchase();
    initAuthForms();
    initDashboardUser();
    initCertificateDownload();
    initDashboardNav();
    initDashboardMobileMenu();
    initDashboardSettings();
    initCoursePlayer();
    initCatalogFilters();
    initAdminMockup();
    initAdminShortcodeCopy();
    initAdminSettings();
    initCourseReviewForm();
    initContactForm();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
