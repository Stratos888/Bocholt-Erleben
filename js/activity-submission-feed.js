/* === BEGIN FILE: js/activity-submission-feed.js | Zweck: erweitert den bestehenden kuratierten Activity-Feed fail-soft um final freigegebene Submission-Aktivitäten; Umfang: komplette Datei === */
(() => {
  if (typeof OffersApp === "undefined" || !OffersApp || typeof OffersApp.init !== "function") {
    console.warn("Activity submission feed adapter skipped: OffersApp is unavailable.");
    return;
  }

  const originalInit = OffersApp.init.bind(OffersApp);

  function normalizeSubmissionOffer(app, raw, index) {
    const source = raw && typeof raw === "object" ? raw : {};
    const sourceUrl = String(source.url || source.link || "").trim();

    // OffersApp historically required an external URL although the existing
    // detail panel already supports URL-less activities. Use a non-public
    // normalization sentinel, then restore the actual empty URL immediately.
    const normalized = app.normalizeOffer(
      sourceUrl ? source : { ...source, url: "__activity_detail_only__" },
      index
    );
    if (normalized && !sourceUrl) normalized.url = "";
    return normalized;
  }

  async function loadApprovedSubmissionOffers(app) {
    try {
      const response = await fetch("/api/activities/public.php", { cache: "no-store" });
      if (!response.ok) {
        console.warn(`Public activity submissions unavailable: ${response.status} ${response.statusText}`);
        return [];
      }

      const payload = await response.json();
      const rawActivities = Array.isArray(payload?.data?.activities) ? payload.data.activities : [];
      const selected = window.NeutralSelection
        ? window.NeutralSelection.selectActivities(rawActivities, { limit: rawActivities.length })
        : rawActivities;
      const baseIndex = Array.isArray(app.offers) ? app.offers.length : 0;

      return selected
        .map((raw, index) => normalizeSubmissionOffer(app, raw, baseIndex + index))
        .filter(Boolean);
    } catch (error) {
      console.warn("Public activity submissions unavailable; curated activity feed remains active.", error);
      return [];
    }
  }

  OffersApp.init = async function initWithApprovedSubmissionFeed() {
    await originalInit();

    const additions = await loadApprovedSubmissionOffers(this);
    if (!additions.length) return;

    const existing = Array.isArray(this.offers) ? this.offers : [];
    const byId = new Map(existing.map((offer) => [String(offer?.id || ""), offer]));
    additions.forEach((offer) => {
      const id = String(offer?.id || "");
      if (id && !byId.has(id)) byId.set(id, offer);
    });

    const hadBaseOffers = existing.length > 0;
    this.offers = Array.from(byId.values());

    if (!hadBaseOffers) {
      this.bindControls();
      this.initFilterButtonLabels();
    }

    this.applyFilterAndRender();
    debugLog?.(`Activity submission feed merged - ${additions.length} approved submissions loaded`);
  };
})();
/* === END FILE: js/activity-submission-feed.js === */