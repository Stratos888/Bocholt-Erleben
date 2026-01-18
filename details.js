/**
 * DETAILS.JS – Detail-Panel Modul
 * Zeigt Event-Details in einem Slide-in Panel
 */

const DetailPanel = {
  panel: null,
  overlay: null,
  content: null,

  init() {
    this.panel = document.getElementById("event-detail-panel");
    this.overlay = this.panel?.querySelector(".detail-panel-overlay");
    this.content = document.getElementById("detail-content");

    if (!this.panel || !this.overlay || !this.content) {
      console.warn("DetailPanel: elements not found");
      return;
    }

    const closeBtn = this.panel.querySelector(".detail-panel-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", () => this.hide());
    }

    this.overlay.addEventListener("click", () => this.hide());

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.panel.classList.contains("active")) {
        this.hide();
      }
    });

    debugLog("DetailPanel initialized");
  },

  show(event) {
  if (!this.panel || !this.content) return;

  this.renderContent(event);

  this.panel.classList.remove("hidden");
  requestAnimationFrame(() => {
    this.panel.classList.add("active");
    document.body.classList.add("is-panel-open");
  });
},

hide() {
  if (!this.panel) return;

  this.panel.classList.remove("active");
  document.body.classList.remove("is-panel-open");

  setTimeout(() => {
    this.panel.classList.add("hidden");
  }, 300);
},


  renderContent(event) {
    const title = event.title || "";
    const date = event.date ? formatDate(event.date) : "";
    const time = event.time || "";
    const location = event.location || "";
    const description = event.description || "";
    const url = event.url || "";

    this.content.innerHTML = `
      <div class="detail-header">
        <h2>${this.escape(title)}</h2>
        ${location ? `<div class="detail-location">${this.escape(location)}</div>` : ""}
      </div>

      <div class="detail-meta">
        ${date ? `<div><strong>Datum:</strong> ${date}</div>` : ""}
        ${time ? `<div><strong>Uhrzeit:</strong> ${this.escape(time)}</div>` : ""}
      </div>

      ${description ? `
        <div class="detail-description">
          <p>${this.escape(description)}</p>
        </div>
      ` : ""}

      ${url ? `
        <div class="detail-actions">
          <a href="${this.escape(url)}"
             target="_blank"
             rel="noopener noreferrer"
             class="detail-link-btn">
            Zur Website
          </a>
        </div>
      ` : ""}
    `;
  },

  escape(text) {
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  }
};

debugLog("DetailPanel loaded");
