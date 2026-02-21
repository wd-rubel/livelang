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

    changeLanguage(langCode, reload = true) {
      if (!langCode || langCode === this.settings.currentLanguage) {
        return;
      }

      // Update current language
      this.settings.currentLanguage = langCode;

      if (reload) {
        // Get the base URL from settings
        const homeUrl = this.settings.homeUrl || window.location.origin;
        const baseUrl = homeUrl.replace(/\/$/, "");
        
        // Remove existing language prefix from path
        const path = window.location.pathname.replace(/^\/[a-z]{2}\//, "/");
        
        // Construct new URL with language prefix
        window.location.href = baseUrl + "/" + langCode + path;
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
      listItems.forEach(a => {
         if(a.getAttribute('data-lang') === currentLang) {
             a.classList.add('active');
         } else {
             a.classList.remove('active');
         }
      });
    }

    setDefaultLanguage() {
      if (
        this.settings.currentLanguage == undefined ||
        this.settings.currentLanguage == null
      ) {
        this.settings.currentLanguage = "en";
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
          
          // Special handling for Translation Bar switcher: No redirect, just update state
          if (langLink.closest('#livelang-toggle')) {
             this.changeLanguage(langCode, false); // false = no reload
             this.updateLanguageDropdown();
             
             // Close dropdown
            //  const dropdown = langLink.closest(".livelang-language-dropdown");
            //  if(dropdown) dropdown.classList.remove("open");
            //  return;
          }


          // Get the base URL from settings
          const homeUrl = this.settings.homeUrl || window.location.origin;
          
          // Get current path
          let path = window.location.pathname;

          // Remove any existing language prefix
          // Check first 3 characters - if /XX/ where XX is 2 letters, remove it
          if (path.length > 3 && /^\/[a-z]{2}\//.test(path)) {
            path = path.substring(3); // Remove /XX/ prefix
          }

          // Ensure path starts with /
          if (!path.startsWith("/")) {
            path = "/" + path;
          }

          // Remove trailing slash from homeUrl if present
          const baseUrl = homeUrl.replace(/\/$/, "");


          // If on homepage, redirect to clean language root URL
          if (path === "/" || path === "") {
             const date = new Date();
             date.setTime(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
             document.cookie =
               "livelang_lang=" +
               langCode +
               ";expires=" +
               date.toUTCString() +
               ";path=/";
             const newUrl = baseUrl + "/" + langCode + "/";
             window.location.href = newUrl;
          } else {
            // Other pages/posts - construct URL with language prefix
            const newUrl = baseUrl + "/" + langCode + path;

            
            // Set cookie before redirect
            const date = new Date();
            date.setTime(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
            document.cookie =
              "livelang_lang=" +
              langCode +
              ";expires=" +
              date.toUTCString() +
              ";path=/";
            
            // Perform redirect
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
  window.LiveLangFrontendInstance = new LiveLangFrontend(window.LiveLangSettings);
})();
