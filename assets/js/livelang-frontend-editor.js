(function () {
  "use strict";

  if (!window.LiveLangSettings) {
    return;
  }

  class LiveLangEditor {
    constructor(settings) {
      this.settings = settings;
      this.enabled = false;
      this.undoStack = [];
      this.redoStack = [];
      this.currentElement = null;

      this.handleClick = this.handleClick.bind(this);
      this.onEditableKeydown = this.onEditableKeydown.bind(this);

      document.addEventListener("DOMContentLoaded", () => {
        this.initBar();
        //this.updateCurrentLanguage();
        //this.languageSwitcherInit();
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

    getSelectedLang() {
      const parts = window.location.pathname.split("/").filter(Boolean);
      const lang = parts[0]; // 'fr'

      // simple validation (2-letter)
      return /^[a-z]{2}$/.test(lang) ? lang : "en";
    }

    updateCurrentLanguage() {
      // get lang param from URL
      const urlParams = new URLSearchParams(window.location.search);
      const langParam = this.getSelectedLang();
      if (langParam && langParam !== this.settings.currentLanguage) {
        this.changeLanguage(langParam, false);
        this.updateLanguageDropdown();
      }
    }

    updateLanguageDropdown() {
      if (!this.bar) return;
      
      const languages = this.getAllLanguages();
      const currentLang = this.settings.currentLanguage;
      const currentLabel = languages[currentLang] || currentLang;

      // Update button text
      const toggleBtn = this.bar.querySelector(".livelang-language-toggle .livelang-current-language-label");
      if (toggleBtn) {
         toggleBtn.innerHTML = currentLabel;
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

    // languageSwitcherInit() {
    //   document.addEventListener("click", (e) => {
    //     // Handle toggle button
    //     const toggleBtn = e.target.closest(".livelang-language-toggle");
    //     if (toggleBtn) {
    //       e.preventDefault();
    //       const dropdown = toggleBtn.closest(".livelang-language-dropdown");
    //       dropdown.classList.toggle("open");
    //       return;
    //     }

    //     // Handle language selection
    //     const langLink = e.target.closest(".livelang-language-list a");
    //     if (langLink) {
    //       e.preventDefault();
    //       const langCode = langLink.getAttribute("data-lang");
          
    //       // Special handling for Translation Bar switcher: No redirect, just update state
    //       if (langLink.closest('#livelang-toggle')) {
    //          this.changeLanguage(langCode, false); // false = no reload
    //          this.updateLanguageDropdown();
             
    //          // Close dropdown
    //          const dropdown = langLink.closest(".livelang-language-dropdown");
    //          //if(dropdown) dropdown.classList.remove("open");
    //          //return;
    //       }


    //       // Get the base URL from settings
    //       const homeUrl = this.settings.homeUrl || window.location.origin;
          
    //       // Get current path
    //       let path = window.location.pathname;

    //       // Remove any existing language prefix
    //       // Check first 3 characters - if /XX/ where XX is 2 letters, remove it
    //       if (path.length > 3 && /^\/[a-z]{2}\//.test(path)) {
    //         path = path.substring(3); // Remove /XX/ prefix
    //       }

    //       // Ensure path starts with /
    //       if (!path.startsWith("/")) {
    //         path = "/" + path;
    //       }

    //       // Remove trailing slash from homeUrl if present
    //       const baseUrl = homeUrl.replace(/\/$/, "");


    //       // If on homepage, redirect to clean language root URL
    //       if (path === "/" || path === "") {
    //          const date = new Date();
    //          date.setTime(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
    //          document.cookie =
    //            "livelang_lang=" +
    //            langCode +
    //            ";expires=" +
    //            date.toUTCString() +
    //            ";path=/";
    //          const newUrl = baseUrl + "/" + langCode + "/";
    //          window.location.href = newUrl;
    //       } else {
    //         // Other pages/posts - construct URL with language prefix
    //         const newUrl = baseUrl + "/" + langCode + path;

            
    //         // Set cookie before redirect
    //         const date = new Date();
    //         date.setTime(date.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days
    //         document.cookie =
    //           "livelang_lang=" +
    //           langCode +
    //           ";expires=" +
    //           date.toUTCString() +
    //           ";path=/";
            
    //         // Perform redirect
    //         window.location.href = newUrl;
    //       }
    //       return;
    //     }

    //     // Close dropdown when clicking outside
    //     const dropdown = document.querySelector(
    //       ".livelang-language-dropdown.open",
    //     );
    //     if (dropdown && !e.target.closest(".livelang-language-dropdown")) {
    //       dropdown.classList.remove("open");
    //     }
    //   });
    // }

    initBar() {
      this.bar = document.getElementById("livelang-toggle");
      if (!this.bar) return;

      this.actionsEl = this.bar.querySelector(".livelang-bar-actions");
      this.mainButton = this.bar.querySelector(".livelang-bar-main");
      this.globalCheckbox = this.bar.querySelector(".livelang-global");

      // main Translate toggle / Save button
      if (this.mainButton) {
        this.mainButton.addEventListener("click", (e) => {
          e.preventDefault();
          const currentText = this.mainButton.textContent.trim();
          if (
            this.enabled &&
            (currentText === this.settings.i18n.save ||
              currentText === "💾 " + this.settings.i18n.save)
          ) {
            this.saveCurrent();
          } else {
            this.toggleMode();
          }
        });
      }

      // delegate actions (undo / redo)
      if (this.actionsEl) {
        this.actionsEl.addEventListener("click", (e) => {
          const target = e.target.closest("[data-action]");
          if (!target) return;
          var action = target.getAttribute("data-action");
          e.preventDefault();
          if (action === "undo") {
            this.undo();
          } else if (action === "redo") {
            this.redo();
          }
        });
      }
    }

    toggleMode() {
      this.enabled = !this.enabled;

      if (this.enabled) {
        this.enableEditing();
      } else {
        this.disableEditing();
      }

      document.documentElement.classList.toggle(
        "livelang-edit-mode",
        this.enabled,
      );
      if (this.bar) {
        this.bar.classList.toggle("is-active", this.enabled);
        this.mainButton.innerHTML = this.enabled ? this.settings.i18n.translating : this.settings.i18n.translate;
      }
    }

    onMouseOver(e) {
      if (!this.enabled) return;

      // Don't hover over the livelang bar
      if (e.target.closest("#livelang-toggle")) return;

      // Don't hover over WP admin bar
      if (e.target.closest("#wpadminbar")) return;

      // Don't hover over language switcher
      if (e.target.closest("#livelang-language-switcher")) return;

      // Don't hover over currently edited element
      if (e.target === this.currentElement) return;

      let node = e.target;

      // If text node, use parent
      if (node.nodeType === Node.TEXT_NODE) {
        node = node.parentElement;
      }

      if (
        !node ||
        node === document.body ||
        node === document.documentElement
      ) {
        return;
      }

      // Check if element has children elements
      const hasChildren = node.querySelector("*") !== null;

      // Check if element has text content or is an editable input
      const hasText =
        !hasChildren && node.innerText && node.innerText.trim() !== "";
      const isInput =
        node.tagName === "INPUT" &&
        (node.type === "submit" ||
          node.type === "button" ||
          node.type === "reset" ||
          (node.placeholder && node.placeholder.trim()));

      if (hasText || isInput) {
        // Remove hovering class from previously hovered element
        document.querySelectorAll(".livelang-hovering").forEach((el) => {
          el.classList.remove("livelang-hovering");
        });

        // Add hovering class only to this element
        node.classList.add("livelang-hovering");
      }
    }

    onMouseOut(e) {
      if (!this.enabled) return;

      let node = e.target;

      // Ignore mouseout inside language switcher
      if (node && node.closest && node.closest("#livelang-language-switcher"))
        return;

      // If text node, use parent
      if (node.nodeType === Node.TEXT_NODE) {
        node = node.parentElement;
      }

      if (!node) return;

      // Remove hovering class from this element
      node.classList.remove("livelang-hovering");
    }

    enableEditing() {
      document.addEventListener("click", this.handleClick, true);
      // Add hover event listeners to apply .livelang-hovering class
      document.addEventListener("mouseover", this.onMouseOver.bind(this), true);
      document.addEventListener("mouseout", this.onMouseOut.bind(this), true);
    }

    disableEditing() {
      document.removeEventListener("click", this.handleClick, true);
      document.removeEventListener(
        "mouseover",
        this.onMouseOver.bind(this),
        true,
      );
      document.removeEventListener(
        "mouseout",
        this.onMouseOut.bind(this),
        true,
      );
      if (this.currentElement) {
        this.currentElement.contentEditable = "false";

        // remove styles attribute
        this.currentElement.style.outline = "";

        // Restore events on current element if it had them disabled
        if (this.currentElement._livelang_disabled_events) {
          this.restoreElementEvents(this.currentElement);
        }
        // Also restore parent button if one was disabled
        if (this.currentElement._livelang_disabled_button) {
          this.restoreElementEvents(
            this.currentElement._livelang_disabled_button,
          );
        }
      }

      // Restore events on all elements that have disabled events
      document.querySelectorAll("[data-livelang-original]").forEach((el) => {
        if (el._livelang_disabled_events) {
          this.restoreElementEvents(el);
        }
        if (el._livelang_disabled_button) {
          this.restoreElementEvents(el._livelang_disabled_button);
        }
      });

      this.currentElement = null;

      // Cleanup: remove data attributes so they don't persist if user re-enables
      document.querySelectorAll("[data-livelang-original]").forEach((el) => {
        delete el.dataset.livelangOriginal;
      });
    }

    cleanPreviousElement(nextNode) {
      if (this.currentElement && this.currentElement !== nextNode) {
        // remove key handler if attached
        try {
          this.currentElement.removeEventListener(
            "keydown",
            this.onEditableKeydown,
          );
        } catch (err) {}

        // If currentElement is a temporary input overlay, restore the original input
        if (this.currentElement._livelang_original_input) {
          const orig = this.currentElement._livelang_original_input;
          const tempValue = this.currentElement.value || "";
          const originalValue =
            this.currentElement.dataset.livelangOriginal || "";

          // Check if editing placeholder or value
          if (this.currentElement._livelang_is_placeholder) {
            // Save placeholder if changed
            if (tempValue.trim() && tempValue.trim() !== originalValue.trim()) {
              orig.placeholder = tempValue;
              orig.dataset.livelangOriginal = tempValue;
            }
          } else {
            // Save value if changed
            if (tempValue.trim() && tempValue.trim() !== originalValue.trim()) {
              orig.value = tempValue;
              orig.dataset.livelangOriginal = tempValue;
            }
          }

          // Restore events on original input
          this.restoreElementEvents(orig);
          // Restore outline on temp input before removing
          if (this.currentElement._livelang_original_outline !== undefined) {
            if (this.currentElement._livelang_original_outline) {
              this.currentElement.style.outline =
                this.currentElement._livelang_original_outline;
            } else {
              this.currentElement.style.removeProperty("outline");
            }
          } else {
            this.currentElement.style.removeProperty("outline");
          }
          // remove temporary input node
          try {
            this.currentElement.parentNode.removeChild(this.currentElement);
          } catch (err) {}
          // show original input again
          orig.style.display = "";
        } else {
          // Restore events on normal element if it's button or anchor
          if (
            this.currentElement.tagName === "BUTTON" ||
            this.currentElement.tagName === "A"
          ) {
            this.restoreElementEvents(this.currentElement);
          } else {
            // Restore parent button if one was disabled (WP block buttons)
            if (this.currentElement._livelang_disabled_button) {
              this.restoreElementEvents(
                this.currentElement._livelang_disabled_button,
              );
            }
          }
          // normal element cleanup
          try {
            this.currentElement.removeAttribute("contenteditable");
          } catch (err) {}
          // Restore original outline style
          if (this.currentElement._livelang_original_outline !== undefined) {
            if (this.currentElement._livelang_original_outline) {
              this.currentElement.style.outline =
                this.currentElement._livelang_original_outline;
            } else {
              this.currentElement.style.removeProperty("outline");
            }
          } else {
            this.currentElement.style.removeProperty("outline");
          }
        }

        // clear currentElement so makeEditable can set a new one
        this.currentElement = null;
        
        // Update main button back to "Translating" if we are still enabled
        if (this.enabled && this.mainButton) {
          this.mainButton.innerHTML = this.settings.i18n.translating;
        }
      }
    }

    placeCaretAtEnd(el) {
      if (!el) return;

      try {
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false); // place to end

        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      } catch (err) {
        // silently ignore for old browsers
      }
    }

    insertTextAtCursor(text) {
      try {
        const sel = window.getSelection();
        if (!sel) return;
        if (sel.rangeCount === 0) {
          this.placeCaretAtEnd(this.currentElement);
        }
        const range = sel.getRangeAt(0);
        range.deleteContents();
        const textNode = document.createTextNode(text);
        range.insertNode(textNode);
        range.setStartAfter(textNode);
        range.setEndAfter(textNode);
        sel.removeAllRanges();
        sel.addRange(range);
      } catch (err) {}
    }

    disableElementEvents(el) {
      if (!el) return;
      el._livelang_disabled_events = true;

      // Comprehensive event handler that blocks all events (for WP Interactivity API and others)
      // but allows mousedown/mouseup for caret placement and selection
      el._livelang_event_handler = (e) => {
        if (
          e.type !== "mousedown" &&
          e.type !== "mouseup" &&
          e.type !== "mousemove"
        ) {
          e.preventDefault();
        }
        e.stopPropagation();
        e.stopImmediatePropagation();
        return false;
      };

      // Block all common event types including WP Interactivity events
      const eventTypes = [
        "click",
        "submit",
        "mousedown",
        "mouseup",
        "dblclick",
        "change",
      ];
      eventTypes.forEach((eventType) => {
        el.addEventListener(eventType, el._livelang_event_handler, true);
      });

      // Add a marker class for additional CSS-based blocking if needed
      el.classList.add("livelang-editing");
    }

    restoreElementEvents(el) {
      if (!el || !el._livelang_disabled_events) return;
      // Remove event listener backup
      if (el._livelang_event_handler) {
        const eventTypes = [
          "click",
          "submit",
          "mousedown",
          "mouseup",
          "dblclick",
          "change",
        ];
        eventTypes.forEach((eventType) => {
          el.removeEventListener(eventType, el._livelang_event_handler, true);
        });
      }
      // Remove marker class
      el.classList.remove("livelang-editing");

      el._livelang_disabled_events = false;
      el._livelang_event_handler = null;
    }

    makeEditable(el) {
      if (!el) return;

      let isCurrentEditable =
        el.getAttribute && el.getAttribute("contenteditable") === "true";
      if (isCurrentEditable) {
        return;
      }

      // remove listener from previous editable element (if any)
      if (this.currentElement && this.currentElement !== el) {
        try {
          this.currentElement.removeEventListener(
            "keydown",
            this.onEditableKeydown,
          );
        } catch (err) {}
      }

      // Handle input elements (submit/button/reset) by creating a temporary text input
      if (el.tagName === "INPUT") {
        const inputType = (el.type || "").toLowerCase();
        if (
          inputType === "submit" ||
          inputType === "button" ||
          inputType === "reset"
        ) {

          // Create a temporary text input that replaces the visual appearance
          const temp = document.createElement("input");
          temp.type = "text";
          temp.className = "livelang-temp-input";
          temp.value = el.value || el.getAttribute("value") || "";
          temp.dataset.livelangOriginal = (temp.value || "")
            .replace(/\s+/g, " ")
            .trim();

          // Copy some sizing so it doesn't jump layout too much
          try {
            const rect = el.getBoundingClientRect();
            temp.style.minWidth = rect.width + "px";
          } catch (err) {}

          // Store original outline before applying editing style
          temp._livelang_original_outline = temp.style.outline || "";

          // Apply outline to temp input for visual feedback
          temp.style.outline = "2px solid dodgerblue";

          // Insert temp after original and hide original
          el.parentNode.insertBefore(temp, el.nextSibling);
          el.style.display = "none";

          // Disable events on the original input element
          this.disableElementEvents(el);

          // attach key handler
          temp.addEventListener("keydown", this.onEditableKeydown);

          // store reference to original input so we can restore it
          temp._livelang_original_input = el;

          temp.focus();
          temp.select();

          this.currentElement = temp;
          return;
        }
      }

      // Normal element flow
      if (!el.dataset.livelangOriginal) {
        el.dataset.livelangOriginal = (el.innerText || "")
          .replace(/\s+/g, " ")
          .trim();
      }

      // Disable events on button/anchor elements during editing
      if (el.tagName === "BUTTON" || el.tagName === "A") {
        this.disableElementEvents(el);
        el._livelang_disabled_button = el;
      } else {
        // For text nodes inside buttons (like WP block buttons), find and disable the parent button
        const parentButton = el.closest("button, a");
        if (parentButton) {
          this.disableElementEvents(parentButton);
          el._livelang_disabled_button = parentButton;
        }
      }

      el.setAttribute("contenteditable", "true");

      // Store original outline style before modifying
      el._livelang_original_outline = el.style.outline || "";

      el.style.outline = "2px solid dodgerblue";
      el.focus();

      // attach Enter key handler
      el.addEventListener("keydown", this.onEditableKeydown);

      // place caret at end for anchors and buttons
      if (el.tagName === "A" || el.tagName === "BUTTON") {
        this.placeCaretAtEnd(el);
      }

      this.currentElement = el;
      
      // Update main button to "Save" when an element becomes editable
      if (this.mainButton) {
        this.mainButton.innerHTML = this.settings.i18n.save;
      }
    }

    makeEditablePlaceholder(el) {
      if (!el || !el.placeholder) return;

      // Create a temporary text input for editing the placeholder
      const temp = document.createElement("input");
      temp.type = "text";
      temp.className = "livelang-temp-placeholder-input";
      temp.placeholder = "Edit placeholder...";
      temp.value = el.placeholder || "";
      temp.dataset.livelangOriginal = (el.placeholder || "")
        .replace(/\s+/g, " ")
        .trim();

      // Copy some sizing so it doesn't jump layout too much
      try {
        const rect = el.getBoundingClientRect();
        temp.style.minWidth = rect.width + "px";
      } catch (err) {}

      // Store original outline before applying editing style
      temp._livelang_original_outline = temp.style.outline || "";

      // Apply outline to temp input for visual feedback
      temp.style.outline = "2px solid dodgerblue";

      // Insert temp after original and hide original
      el.parentNode.insertBefore(temp, el.nextSibling);
      el.style.display = "none";

      // Disable events on the original input element
      this.disableElementEvents(el);

      // attach key handler
      temp.addEventListener("keydown", this.onEditableKeydown);

      // store reference to original input so we can restore it
      temp._livelang_original_input = el;
      temp._livelang_is_placeholder = true;

      temp.focus();
      temp.select();

      this.currentElement = temp;

      // Update main button to "Save" when an element becomes editable
      if (this.mainButton) {
        this.mainButton.innerHTML = this.settings.i18n.save;
      }
    }

    wrapBareTextNodes(rootEl) {
      const wrappers = [];

      const toWrap = Array.from(rootEl.childNodes).filter(
        (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== "",
      );

      toWrap.forEach((textNode) => {
        const text = textNode.textContent;
        const span = document.createElement("span");

        span.textContent = text;
        span.dataset.livelangOriginal = text.replace(/\s+/g, " ").trim();

        textNode.parentNode.replaceChild(span, textNode);
        wrappers.push(span);
      });

      return wrappers;
    }

    handleClick(e) {
      if (!this.enabled) return;

      // Ignore our own bar
      if (e.target.closest("#livelang-toggle")) return;

      // Ignore WP admin bar
      if (e.target.closest("#wpadminbar")) return;

      // Ignore language switcher (allow its own click handlers)
      if (e.target.closest("#livelang-language-switcher")) return;
      // Prevent navigation (but allow editing <a>)
      const link = e.target.closest("a");
      if (link) {
        e.preventDefault();
      }

      // Prevent submit buttons from submitting
      const submitBtn = e.target.closest(
        'button[type="submit"], input[type="submit"]',
      );
      if (submitBtn) {
        e.preventDefault();
        e.stopPropagation();
      }

      let node = e.target;

      // If pure text-node clicked → parent element used
      if (node.nodeType === Node.TEXT_NODE) {
        node = node.parentElement;
      }

      if (
        !node ||
        node === document.body ||
        node === document.documentElement
      ) {
        return;
      }

      // If already editing this element, don't create a new temp input
      if (node === this.currentElement) {
        return;
      }

      // Special handling for INPUT elements (submit/button/reset types)
      if (node.tagName === "INPUT") {
        const inputType = (node.type || "").toLowerCase();
        if (
          inputType === "submit" ||
          inputType === "button" ||
          inputType === "reset"
        ) {
          this.cleanPreviousElement(node);
          this.makeEditable(node);
          return;
        }
        // Handle input placeholder editing
        if (node.placeholder && node.placeholder.trim()) {
          this.cleanPreviousElement(node);
          this.makeEditablePlaceholder(node);
          return;
        }
      }

      // If there is no text at all, do nothing
      if (!node.innerText || node.innerText.trim() === "") {
        return;
      }

      // clean previous editable element
      this.cleanPreviousElement(node);

      // CASE 1: Direct editable element (no children) → edit this
      const hasChildren = node.querySelector("*") !== null;

      if (!hasChildren) {
        this.makeEditable(node);
        return;
      }

      // CASE 2: Wrap bare text nodes and edit first one
      const bareTextNodes = Array.from(node.childNodes).filter(
        (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== "",
      );

      if (bareTextNodes.length) {
        const wrappers = this.wrapBareTextNodes(node);
        const editable = wrappers[0];

        if (editable) {
          this.makeEditable(editable);
          return;
        }
      }

      // CASE 3: Drill down to first text-containing leaf node
      let leaf = node;
      while (leaf.children.length > 0) {
        const nextChild = Array.from(leaf.children).find(
          (child) => child.innerText && child.innerText.trim() !== "",
        );
        if (!nextChild) break;
        leaf = nextChild;
      }

      this.makeEditable(leaf);
    }

    onEditableKeydown(e) {
      // Allow inserting a space into buttons/anchors (space normally activates the button)
      if (
        (e.key === " " || e.key === "Spacebar" || e.code === "Space") &&
        this.currentElement &&
        (this.currentElement.tagName === "BUTTON" ||
          this.currentElement.tagName === "A")
      ) {
        e.preventDefault();
        this.insertTextAtCursor(" ");
        return;
      }

      // Save when Enter is pressed without Shift (Shift+Enter should insert newline)
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();

        // call save
        this.saveCurrent();

        // stop editing this element
        if (this.currentElement) {
          // If this is a temp input overlay, restore the original input (we restore inside saveCurrent too)
          if (this.currentElement._livelang_original_input) {
            // remove listener and restore original
            try {
              this.currentElement.removeEventListener(
                "keydown",
                this.onEditableKeydown,
              );
            } catch (err) {}

            // Restore outline on temp input before removing
            if (this.currentElement._livelang_original_outline !== undefined) {
              if (this.currentElement._livelang_original_outline) {
                this.currentElement.style.outline =
                  this.currentElement._livelang_original_outline;
              } else {
                this.currentElement.style.removeProperty("outline");
              }
            } else {
              this.currentElement.style.removeProperty("outline");
            }

            const orig = this.currentElement._livelang_original_input;
            try {
              this.currentElement.parentNode.removeChild(this.currentElement);
            } catch (err) {}
            orig.style.display = "";
            this.currentElement = null;
          } else {
            // normal element
            try {
              this.currentElement.removeAttribute("contenteditable");
            } catch (err) {}
            this.currentElement.style.outline = "";

            try {
              this.currentElement.removeEventListener(
                "keydown",
                this.onEditableKeydown,
              );
            } catch (err) {}

            this.currentElement = null;
          }
        }
      }
    }

    saveCurrent() {
      const saveTexts = this.settings.i18n;
      if (this.mainButton) {
        this.mainButton.innerHTML = "💾 " + saveTexts.saving;
      }

      var el = this.currentElement;
      if (!el) {
        if (this.enabled && this.mainButton) {
          this.mainButton.innerHTML = saveTexts.translating;
        }
        return;
      }

      // If el is the temp input overlay (we created in makeEditable)
      let originalText = "";
      let translatedText = "";
      let realElement = el;

      if (el._livelang_original_input) {
        // temp input overlay
        originalText = el.dataset.livelangOriginal || "";
        translatedText = (el.value || "").replace(/\s+/g, " ").trim();

        // reference to original input element
        realElement = el._livelang_original_input;
      } else if (el.tagName === "INPUT") {
        // In case a plain text input becomes currentElement (unlikely), read value
        originalText =
          el.dataset.livelangOriginal ||
          (el.value || "").replace(/\s+/g, " ").trim();
        translatedText = (el.value || "").replace(/\s+/g, " ").trim();
      } else {
        // Normal editable element
        originalText = el.dataset.livelangOriginal || "";
        translatedText = Array.from(el.childNodes)
          .filter(
            (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== "",
          )
          .map((n) => n.textContent.replace(/\s+/g, " ").trim())
          .join(" ");
      }

      if (!translatedText.trim() || !originalText.trim()) {
        if (this.enabled && this.mainButton) {
          this.mainButton.innerHTML = saveTexts.translating;
        }
        return;
      }

      // nothing changed – skip
      if (translatedText === originalText) {
        if (this.enabled && this.mainButton) {
          this.mainButton.innerHTML = saveTexts.translating;
        }
        return;
      }

      // push to undo stack: store realElement (the element to restore on undo)
      this.undoStack.push({
        element: realElement,
        previous: originalText,
        next: translatedText,
      });
      this.redoStack = [];

      // Update UI / underlying element:
      if (el._livelang_original_input) {
        // Check if editing placeholder or value
        if (el._livelang_is_placeholder) {
          // set original input's placeholder and remove temp overlay
          try {
            realElement.placeholder = translatedText;
            el.parentNode.removeChild(el);
          } catch (err) {}
        } else {
          // set original input's value and remove temp overlay
          try {
            realElement.value = translatedText;
            el.parentNode.removeChild(el);
          } catch (err) {}
        }
        realElement.style.display = "";
        // update dataset on real element for future edits
        realElement.dataset.livelangOriginal = translatedText;
      } else if (realElement.tagName === "INPUT") {
        realElement.value = translatedText;
        realElement.dataset.livelangOriginal = translatedText;
      } else {
        // normal element
        realElement.innerText = translatedText;
        realElement.dataset.livelangOriginal = translatedText;
        // remove contenteditable and key handler
        try {
          realElement.removeAttribute("contenteditable");
          realElement.removeEventListener("keydown", this.onEditableKeydown);
        } catch (err) {}
      }

      // send to server
      const mainButton = this.mainButton;
      fetch(this.settings.restUrl + "/save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": this.settings.nonce,
        },
        body: JSON.stringify({
          original: originalText,
          translated: translatedText,
          slug: this.settings.slug,
          language: this.settings.currentLanguage,
          is_global:
            this.globalCheckbox && this.globalCheckbox.checked ? "1" : "0",
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (mainButton) {
            mainButton.innerHTML = "✅ " + saveTexts.saved;
            setTimeout(() => {
              if (this.enabled) {
                mainButton.innerHTML = saveTexts.translating;
              } else {
                mainButton.innerHTML = saveTexts.translate;
              }
            }, 2000);
          }
        })
        .catch((error) => {
          console.error("Error saving translation:", error);
          if (mainButton) {
            mainButton.innerHTML = "❌ Error";
            setTimeout(() => {
              mainButton.innerHTML = this.enabled
                ? saveTexts.translating
                : saveTexts.translate;
            }, 2000);
          }
        });

      // clear currentElement reference after a short tick (ensures handlers removed)
      try {
        this.currentElement = null;
      } catch (err) {}
    }

    undo() {
      var last = this.undoStack.pop();
      if (!last) return;
      this.redoStack.push(last);
      if (last.element && last.element.tagName === "INPUT") {
        last.element.value = last.previous;
        last.element.dataset.livelangOriginal = last.previous;
      } else {
        last.element.innerText = last.previous;
        last.element.dataset.livelangOriginal = last.previous;
      }
    }

    redo() {
      var last = this.redoStack.pop();
      if (!last) return;
      this.undoStack.push(last);
      if (last.element && last.element.tagName === "INPUT") {
        last.element.value = last.next;
        last.element.dataset.livelangOriginal = last.next;
      } else {
        last.element.innerText = last.next;
        last.element.dataset.livelangOriginal = last.next;
      }
    }
  }

  // single instance (for debug: window.LiveLangEditorInstance)
  window.LiveLangEditorInstance = new LiveLangEditor(window.LiveLangSettings);
  
  
})();
