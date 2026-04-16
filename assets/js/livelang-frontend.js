(function () {
  "use strict";

  if (!window.LiveLangSettings) {
    return;
  }

  class LiveLangFrontend {
    constructor(settings) {
      this.settings = settings;
      this.enabled = false;
      this.undoStack = [];
      this.redoStack = [];
      this.currentElement = null;

      this.bar = document.getElementById("livelang-bar");

      document.addEventListener("DOMContentLoaded", () => {
        this.languageSwitcherInit();
        this.setDefaultLanguage();
      });
    }

    getAllLanguages() {
      // Return languages from settings (passed from PHP)
      if (
        this.settings.languages &&
        typeof this.settings.languages === "object"
      ) {
        return this.settings.languages;
      }
      // Fallback to defaults if not provided
      return {
        en: "English",
        es: "Spanish",
        fr: "French",
      };
    }

    getCleanPath() {
      const homeUrl = this.settings.homeUrl || window.location.origin;
      let homePath = "";
      try {
        homePath = new URL(homeUrl).pathname.replace(/\/$/, "");
      } catch (e) {}

      let path = window.location.pathname;
      if (homePath && path.startsWith(homePath)) {
        path = path.substring(homePath.length);
      }
      if (!path.startsWith("/")) path = "/" + path;

      // Remove existing language prefix
      if (path.length >= 3 && /^\/[a-z]{2,5}(\/|$)/.test(path)) {
        path = path.replace(/^\/[a-z]{2,5}/, "");
        if (!path.startsWith("/")) path = "/" + path;
      }

      return path;
    }

    changeLanguage(langCode, reload = true) {
      if (!langCode || langCode === this.settings.currentLanguage) {
        return;
      }

      // Update current language
      this.settings.currentLanguage = langCode;

      if (reload) {
        const homeUrl = this.settings.homeUrl || window.location.origin;
        const baseUrl = homeUrl.replace(/\/$/, "");

        const path = this.getCleanPath();
        if (
          LiveLangSettings.defaultLanguage &&
          langCode === LiveLangSettings.defaultLanguage
        ) {
          window.location.href = baseUrl + path;
        } else {
          window.location.href = baseUrl + "/" + langCode + path;
        }
      }
    }

    updateLanguageDropdown() {
      if (!this.bar) return;

      const languages = this.getAllLanguages();
      const currentLang = this.settings.currentLanguage;
      const currentLabel = languages[currentLang] || currentLang;

      // Update button text
      const toggleBtn = this.bar.querySelector(".livelang-language-toggle");
      if (toggleBtn) {
        toggleBtn.innerHTML = `${currentLabel} <span class="livelang-toggle-icon">▼</span>`;
      }

      // Update active class in list
      const listItems = this.bar.querySelectorAll(".livelang-language-list a");
      listItems.forEach((a) => {
        if (a.getAttribute("data-lang") === currentLang) {
          a.classList.add("active");
        } else {
          a.classList.remove("active");
        }
      });
    }

    setDefaultLanguage() {
      if (
        this.settings.currentLanguage == undefined ||
        this.settings.currentLanguage == null
      ) {
        this.settings.currentLanguage =
          LiveLangSettings.defaultLanguage || "en";
      }
      this.updateLanguageDropdown();
    }

    languageSwitcherInit() {
      document.addEventListener("click", (e) => {
        // Handle toggle button
        const toggleBtn = e.target.closest(".livelang-language-toggle");
        if (toggleBtn) {
          e.preventDefault();
          const dropdown = toggleBtn.closest(".livelang-language-dropdown");
          dropdown.classList.toggle("open");
          //return;
        }

        // Handle language selection
        const langLink = e.target.closest("a[data-lang]");
        if (langLink) {
          e.preventDefault();
          const langCode = langLink.getAttribute("data-lang");

          if (langLink.closest("#livelang-toggle")) {
            this.changeLanguage(langCode, false); // false = no reload
            this.updateLanguageDropdown();
          }

          const homeUrl = this.settings.homeUrl || window.location.origin;
          const baseUrl = homeUrl.replace(/\/$/, "");
          const path = this.getCleanPath();

          const date = new Date();
          date.setTime(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
          document.cookie =
            "livelang_lang=" +
            langCode +
            ";expires=" +
            date.toUTCString() +
            ";path=/";

          // Omit language prefix if switching to default language
          if ( langCode === LiveLangSettings.defaultLanguage ) {
            if (path === "/" || path === "") {
                window.location.href = baseUrl + "/";
            } else {
                window.location.href = baseUrl + path;
            }
            return;
          }

          if (path === "/" || path === "") {
            const newUrl = baseUrl + "/" + langCode + "/";
            window.location.href = newUrl;
          } else {
            const newUrl = baseUrl + "/" + langCode + path;
            window.location.href = newUrl;
          }
          return;
        }

        // Close dropdown when clicking outside
        const dropdown = document.querySelector(
          ".livelang-language-dropdown.open",
        );
        if (dropdown && !e.target.closest(".livelang-language-dropdown")) {
          dropdown.classList.remove("open");
        }
      });
    }
  }

  // single instance (for debug: window.LiveLangFrontendInstance)
  window.LiveLangFrontendInstance = new LiveLangFrontend(
    window.LiveLangSettings,
  );
})();
