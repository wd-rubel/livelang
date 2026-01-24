// Tabs
document.addEventListener("DOMContentLoaded", function () {
  var tabLinks = document.querySelectorAll(".livelang-settings-wrap .nav-tab");
  var panels = document.querySelectorAll(".livelang-tab-panel");

  function activateTab(name) {
    tabLinks.forEach(function (link) {
      if (link.getAttribute("data-livelang-tab") === name) {
        link.classList.add("nav-tab-active");
      } else {
        link.classList.remove("nav-tab-active");
      }
    });
    panels.forEach(function (panel) {
      if (panel.id === "livelang-tab-" + name) {
        panel.style.display = "block";
      } else {
        panel.style.display = "none";
      }
    });

    // Load languages when languages tab is activated
    if (name === "languages") {
      loadLanguages();
    }
  }

  tabLinks.forEach(function (link) {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      var tab = link.getAttribute("data-livelang-tab");
      if (tab) {
        activateTab(tab);
      }
    });
  });

  // activate default tab
  activateTab("general");

  // Clear translations
  var btn = document.getElementById("livelang-clear-translations-maintenance");
  if (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      if (!confirm("Are you sure you want to clear all translations?")) {
        return;
      }
      var xhr = new XMLHttpRequest();
      var data = new FormData();
      data.append("action", "livelang_clear_translations");
      data.append("_ajax_nonce", LiveLangAdminSettings.nonce);
      xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
      xhr.onload = function () {
        if (xhr.status === 200) {
          alert("All translations cleared.");
          location.reload();
        } else {
          alert("Error");
        }
      };
      xhr.send(data);
    });
  }

  // Language Management
  var languagesCount = 0; // track current number of languages loaded

  function loadLanguages() {
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_get_languages");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success && response.data) {
            renderLanguages(response.data);
            initSortable();
          }
        } catch (e) {
          console.error("Error parsing response", e);
        }
      }
    };
    xhr.send(data);
  }
  function updateAddLanguageButton(languageCount) {
    var addBtn = document.getElementById("livelang-add-language-btn");
    if (!addBtn) return;

    // Keep button clickable; only show visual hint when at/over limit
    languagesCount = parseInt(languageCount, 10) || 0;
    if (languagesCount >= 3) {
      addBtn.style.opacity = "0.85";
      addBtn.style.cursor = "pointer";
      addBtn.title =
        "Maximum 3 languages allowed in free plan. Click to learn more.";
    } else {
      addBtn.style.opacity = "1";
      addBtn.style.cursor = "pointer";
      addBtn.title = "";
    }
  }

  function showUpgradeNotice() {
    var container = document.getElementById("livelang-tab-languages");
    if (!container) return;

    var existing = document.getElementById("livelang-upgrade-notice");
    if (existing) {
      clearTimeout(existing._hideTimeout);
      existing.parentNode.removeChild(existing);
    }

    var notice = document.createElement("div");
    notice.id = "livelang-upgrade-notice";
    notice.className = "notice notice-warning";
    notice.style.marginBottom = "12px";
    notice.innerHTML =
      "<p><strong>Maximum 3 languages reached.</strong> Upgrade to the Pro version for unlimited languages.</p>";
    container.insertBefore(notice, container.firstChild);

    // Auto-hide after 5 seconds
    notice._hideTimeout = setTimeout(function () {
      if (notice && notice.parentNode) {
        notice.parentNode.removeChild(notice);
      }
    }, 5000);
  }

  function renderLanguages(languages) {
    var tbody = document.getElementById("livelang-languages-tbody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!languages || languages.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5">No languages found.</td></tr>';
      updateAddLanguageButton(0);
      return;
    }

    // Update button state based on language count
    updateAddLanguageButton(languages.length);

    languages.forEach(function (lang, index) {
      var row = document.createElement("tr");
      row.setAttribute("data-code", lang.code);

      var dragCell = document.createElement("td");
      dragCell.innerHTML = "⋮⋮";
      dragCell.style.cursor = "move";
      dragCell.style.textAlign = "center";

      var codeCell = document.createElement("td");
      codeCell.textContent = lang.code;

      var labelCell = document.createElement("td");
      var labelInput = document.createElement("input");
      labelInput.type = "text";
      labelInput.value = lang.label;
      labelInput.style.width = "100%";
      labelInput.style.padding = "5px";
      labelCell.appendChild(labelInput);

      var defaultCell = document.createElement("td");
      var defaultRadio = document.createElement("input");
      defaultRadio.type = "radio";
      defaultRadio.name = "default_language";
      defaultRadio.value = lang.code;
      defaultRadio.checked = lang.is_default == 1;
      defaultRadio.style.cursor = "pointer";
      defaultCell.appendChild(defaultRadio);

      var actionsCell = document.createElement("td");
      var saveBtn = document.createElement("button");
      saveBtn.className = "button button-small";
      saveBtn.textContent = "Save";
      saveBtn.style.marginRight = "5px";
      saveBtn.addEventListener("click", function (e) {
        e.preventDefault();
        updateLanguage(lang.code, labelInput.value);
      });

      var deleteBtn = document.createElement("button");
      deleteBtn.className = "button button-small button-secondary";
      deleteBtn.textContent = "Delete";
      deleteBtn.addEventListener("click", function (e) {
        e.preventDefault();
        if (confirm("Are you sure you want to delete this language?")) {
          deleteLanguage(lang.code);
        }
      });

      actionsCell.appendChild(saveBtn);
      actionsCell.appendChild(deleteBtn);

      defaultRadio.addEventListener("change", function (e) {
        if (this.checked) {
          setDefaultLanguage(lang.code);
        }
      });

      row.appendChild(dragCell);
      row.appendChild(codeCell);
      row.appendChild(labelCell);
      row.appendChild(defaultCell);
      row.appendChild(actionsCell);

      tbody.appendChild(row);
    });
  }

  function initSortable() {
    var tbody = document.getElementById("livelang-languages-tbody");
    if (!tbody) return;

    jQuery(tbody).sortable({
      items: "tr",
      update: function () {
        var order = [];
        jQuery(tbody)
          .find("tr")
          .each(function () {
            var code = jQuery(this).attr("data-code");
            if (code) {
              order.push(code);
            }
          });
        reorderLanguages(order);
      },
    });
  }

  function addLanguage() {
    // If already at or above limit, show upgrade notice and do not proceed
    if (languagesCount >= 3) {
      showUpgradeNotice();
      return;
    }
    var codeInput = document.getElementById("livelang-language-code");
    var labelInput = document.getElementById("livelang-language-label");

    if (!codeInput || !labelInput) return;

    var code = codeInput.value.trim();
    var label = labelInput.value.trim();

    if (!code || !label) {
      alert("Please fill in both code and label");
      return;
    }

    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_add_language");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);
    data.append("code", code);
    data.append("label", label);

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            codeInput.value = "";
            labelInput.value = "";
            loadLanguages();
          } else {
            alert(response.data.message || "Error adding language");
          }
        } catch (e) {
          console.error("Error parsing response", e);
        }
      }
    };
    xhr.send(data);
  }

  function updateLanguage(code, label) {
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_update_language");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);
    data.append("code", code);
    data.append("label", label);

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            alert("Language updated");
            loadLanguages();
          } else {
            alert(response.data.message || "Error updating language");
          }
        } catch (e) {
          console.error("Error parsing response", e);
        }
      }
    };
    xhr.send(data);
  }

  function deleteLanguage(code) {
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_delete_language");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);
    data.append("code", code);

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            alert("Language deleted");
            loadLanguages();
          } else {
            alert(response.data.message || "Error deleting language");
          }
        } catch (e) {
          console.error("Error parsing response", e);
        }
      }
    };
    xhr.send(data);
  }

  function reorderLanguages(order) {
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_reorder_languages");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);

    order.forEach(function (code, index) {
      data.append("order[" + index + "]", code);
    });

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status !== 200) {
        console.error("Error reordering languages");
      }
    };
    xhr.send(data);
  }

  function setDefaultLanguage(code) {
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append("action", "livelang_set_default_language");
    data.append("_ajax_nonce", LiveLangAdminSettings.langNonce);
    data.append("code", code);

    xhr.open("POST", LiveLangAdminSettings.ajaxUrl, true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            loadLanguages();
          } else {
            alert(response.data.message || "Error setting default language");
          }
        } catch (e) {
          console.error("Error parsing response", e);
        }
      }
    };
    xhr.send(data);
  }

  // Add language button
  var addLangBtn = document.getElementById("livelang-add-language-btn");
  if (addLangBtn) {
    addLangBtn.addEventListener("click", function (e) {
      e.preventDefault();
      addLanguage();
    });
  }
});
