/* === BEGIN FILE: js/image-credits-page.js | Zweck: rendert /bildnachweise/ aus den zentralen Event- und Activity-Visual-Pools; Umfang: Datenfetch + Gruppenrendering fuer die Bildnachweiseite === */
(function () {
  "use strict";

  const DATASETS = Object.freeze([
    { url: "/data/activity_visual_pool.json", type: "activity" },
    { url: "/data/event_visual_pool.json", type: "event" }
  ]);

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function escapeHtml(value) {
    return window.ImageAttribution?.escapeHtml
      ? window.ImageAttribution.escapeHtml(value)
      : asString(value)
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#039;");
  }

  function slugify(value) {
    return window.ImageAttribution?.slugify
      ? window.ImageAttribution.slugify(value)
      : asString(value).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "bild";
  }

  function isVisibleStatus(status, type) {
    const value = asString(status).toLowerCase();
    if (type === "activity") return value === "ready" || value === "fallback";
    return value === "ready" || value === "fallback";
  }

  async function fetchJson(url) {
    const response = await fetch(url, { cache: "no-store" });
    if (!response.ok) throw new Error(`${url}: ${response.status}`);
    return response.json();
  }

  function buildActivityEntries(payload) {
    const pools = payload && typeof payload === "object" && payload.pools && typeof payload.pools === "object"
      ? payload.pools
      : {};
    const entries = [];

    Object.entries(pools).forEach(([poolKey, pool]) => {
      const images = Array.isArray(pool?.images) ? pool.images : [];
      const offerIds = Array.isArray(pool?.primary_offer_ids)
        ? pool.primary_offer_ids.map(asString).filter(Boolean)
        : [];
      const primaryOfferId = offerIds[0] || "";
      const poolLabel = asString(pool?.label) || poolKey;

      images.forEach((image) => {
        if (!image || !isVisibleStatus(image.status, "activity")) return;

        const imageId = asString(image.id);
        const anchorBase = primaryOfferId
          ? `activity-${primaryOfferId}`
          : `activity-visual-${imageId || poolKey}`;

        entries.push({
          sortTitle: poolLabel.toLowerCase(),
          title: poolLabel,
          group: "activityImages",
          groupLabel: "Aktivitätsbild",
          options: {
            entityType: "activity",
            entityId: primaryOfferId,
            entityTitle: poolLabel,
            poolKey,
            imageId,
            anchorId: anchorBase,
            relatedIds: offerIds
          },
          aliases: [
            anchorBase,
            imageId ? `activity-visual-${imageId}` : "",
            poolKey ? `activity-pool-${poolKey}` : ""
          ].filter(Boolean),
          meta: {
            ...image,
            poolKey,
            entityType: "activity",
            entityId: primaryOfferId,
            entityTitle: poolLabel
          }
        });
      });
    });

    return entries;
  }

  function buildEventEntries(payload) {
    const pools = payload && typeof payload === "object" && payload.pools && typeof payload.pools === "object"
      ? payload.pools
      : {};
    const entries = [];

    Object.entries(pools).forEach(([visualKey, pool]) => {
      const images = Array.isArray(pool?.images) ? pool.images : [];
      const poolLabel = asString(pool?.label) || visualKey;

      images.forEach((image) => {
        if (!image || !isVisibleStatus(image.status, "event")) return;

        const imageId = asString(image.id);
        const anchorBase = imageId ? `event-visual-${imageId}` : `event-pool-${visualKey}`;

        entries.push({
          sortTitle: `${poolLabel} ${imageId}`.toLowerCase(),
          title: `${poolLabel}${imageId ? ` · ${imageId}` : ""}`,
          group: "eventSymbolic",
          groupLabel: "Eventbild",
          options: {
            entityType: "event",
            entityTitle: poolLabel,
            visualKey,
            imageId,
            anchorId: anchorBase
          },
          aliases: [
            anchorBase,
            imageId ? `visual-${imageId}` : "",
            visualKey ? `event-pool-${visualKey}` : ""
          ].filter(Boolean),
          meta: {
            ...image,
            poolKey: visualKey,
            visualKey,
            entityType: "event",
            entityTitle: poolLabel
          }
        });
      });
    });

    return entries;
  }

  function groupEntries(entries) {
    const groups = [
      {
        id: "activityImages",
        title: "Aktivitätsbilder",
        description: "Realfotos und freigegebene Visuals, die für Aktivitäten in Karten und Detailansichten verwendet werden."
      },
      {
        id: "eventSymbolic",
        title: "Eventbilder und Symbolbilder",
        description: "Geprüfte Event-Visuals. Viele Eventbilder sind bewusst Symbolbilder und keine dokumentarischen Aufnahmen eines konkreten Termins."
      }
    ];

    return groups.map((group) => ({
      ...group,
      entries: entries
        .filter((entry) => entry.group === group.id)
        .sort((a, b) => a.sortTitle.localeCompare(b.sortTitle, "de"))
    })).filter((group) => group.entries.length);
  }

  function renderNavigation(groups) {
    return `
      <nav class="image-credits-nav" aria-label="Bildnachweis-Gruppen">
        ${groups.map((group) => `
          <a class="image-credits-nav__link" href="#${escapeHtml(group.id)}">
            <span>${escapeHtml(group.title)}</span>
            <span>${group.entries.length}</span>
          </a>
        `).join("")}
      </nav>
    `.trim();
  }

  function renderGroups(groups) {
    return groups.map((group) => `
      <section class="image-credits-section" id="${escapeHtml(group.id)}" aria-labelledby="${escapeHtml(group.id)}-title">
        <div class="image-credits-section__header">
          <h2 id="${escapeHtml(group.id)}-title">${escapeHtml(group.title)}</h2>
          <p>${escapeHtml(group.description)}</p>
        </div>
        <div class="image-credits-list">
          ${group.entries.map((entry) => window.ImageAttribution.renderCreditCard(entry)).join("\n")}
        </div>
      </section>
    `.trim()).join("\n");
  }

  function highlightHashTarget() {
    const rawHash = decodeURIComponent(window.location.hash || "").replace(/^#/, "");
    if (!rawHash) return;

    const target = document.getElementById(rawHash);
    const card = target?.closest?.(".image-credit-card") || target;
    if (!card) return;

    card.classList.add("is-targeted");
    setTimeout(() => card.classList.remove("is-targeted"), 3200);
  }

  async function init() {
    const root = document.querySelector("[data-image-credits-root]");
    const status = document.querySelector("[data-image-credits-status]");
    if (!root || !window.ImageAttribution?.renderCreditCard) return;

    try {
      if (status) status.textContent = "Bildnachweise werden geladen …";

      const [activityPayload, eventPayload] = await Promise.all(DATASETS.map((dataset) => fetchJson(dataset.url)));
      const entries = [
        ...buildActivityEntries(activityPayload),
        ...buildEventEntries(eventPayload)
      ];
      const groups = groupEntries(entries);

      if (!entries.length) {
        root.innerHTML = `<section class="content-card"><p>Aktuell sind keine Bildnachweise verfügbar.</p></section>`;
        if (status) status.textContent = "Keine Bildnachweise gefunden.";
        return;
      }

      root.innerHTML = `
        ${renderNavigation(groups)}
        ${renderGroups(groups)}
      `.trim();

      if (status) status.textContent = `${entries.length} Bildnachweise geladen.`;
      if (window.Icons?.hydrate) window.Icons.hydrate(root);
      highlightHashTarget();
    } catch (error) {
      root.innerHTML = `
        <section class="content-card image-credits-error" role="alert">
          <h2>Bildnachweise konnten nicht geladen werden</h2>
          <p>Bitte versuche es später erneut.</p>
        </section>
      `.trim();
      if (status) status.textContent = "Bildnachweise konnten nicht geladen werden.";
      // Keep console output for operational debugging only.
      console.error("Image credits failed", error);
    }
  }

  window.addEventListener("hashchange", highlightHashTarget);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
}());
/* === END FILE: js/image-credits-page.js === */
