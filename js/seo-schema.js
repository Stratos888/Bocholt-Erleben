// BEGIN: FILE_HEADER_SEO_SCHEMA
// Datei: js/seo-schema.js
// Zweck:
// - Erzeugt JSON-LD (SEO Schema) für Events
// - Injiziert strukturierte Daten basierend auf /data/events.json und freigegebenen DB-Submissions
// END: FILE_HEADER_SEO_SCHEMA

(async function injectEventSchema() {
  try {
    async function fetchJsonNoStore(url, required) {
      try {
        const res = await fetch(url, { cache: 'no-store' });
        if (!res.ok) throw new Error(`${url} failed: ${res.status}`);
        return await res.json();
      } catch (error) {
        if (required) throw error;
        return null;
      }
    }

    function extractEvents(payload) {
      if (Array.isArray(payload)) return payload;
      if (Array.isArray(payload?.events)) return payload.events;
      if (Array.isArray(payload?.data?.events)) return payload.data.events;
      return [];
    }

    function dedupeEvents(events) {
      const seen = new Set();
      const out = [];

      for (const event of events) {
        const idKey = String(event?.id || '').trim();
        const fallbackKey = [
          String(event?.title || '').trim().toLowerCase(),
          String(event?.date || '').trim(),
          String(event?.time || '').trim().toLowerCase(),
          String(event?.location || '').trim().toLowerCase()
        ].join('|');
        const key = idKey || fallbackKey;
        if (!key || seen.has(key)) continue;
        seen.add(key);
        out.push(event);
      }

      return out;
    }

    const [sheetPayload, approvedSubmissionPayload] = await Promise.all([
      fetchJsonNoStore('/data/events.json', true),
      fetchJsonNoStore('/api/events/public.php', false)
    ]);

    const events = dedupeEvents([
      ...extractEvents(sheetPayload),
      ...extractEvents(approvedSubmissionPayload)
    ]);

    if (!events.length) return;

    const slice = events.slice(0, 30);
    const nowIso = new Date().toISOString();

    const graph = slice
      .filter(e => e && e.id && e.title && e.date && e.location)
      .map(e => {
        const startDate = `${e.date}T00:00:00+01:00`;

        const obj = {
          "@type": "Event",
          "@id": `https://bocholt-erleben.de/#event-${String(e.id)}`,
          "name": String(e.title),
          "startDate": startDate,
          "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
          "eventStatus": "https://schema.org/EventScheduled",
          "location": {
            "@type": "Place",
            "name": String(e.location),
            "address": {
              "@type": "PostalAddress",
              "addressLocality": String(e.city || "Bocholt"),
              "addressCountry": "DE"
            }
          },
          "description": e.description ? String(e.description).slice(0, 500) : undefined,
          "url": e.url ? String(e.url) : "https://bocholt-erleben.de/",
          "organizer": {
            "@type": "Organization",
            "name": "Veranstaltungen in Bocholt",
            "url": "https://bocholt-erleben.de"
          }
        };

        if (e.time) {
          obj["additionalProperty"] = [{
            "@type": "PropertyValue",
            "name": "time",
            "value": String(e.time).slice(0, 30)
          }];
        }

        Object.keys(obj).forEach(k => (obj[k] === undefined) && delete obj[k]);
        return obj;
      });

    if (!graph.length) return;

    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify({
      "@context": "https://schema.org",
      "@type": "ItemList",
      "name": "Veranstaltungen in Bocholt",
      "itemListOrder": "https://schema.org/ItemListOrderAscending",
      "dateModified": nowIso,
      "itemListElement": graph.map((item, idx) => ({
        "@type": "ListItem",
        "position": idx + 1,
        "item": item
      }))
    });

    document.head.appendChild(script);
  } catch (_) {
    // bewusst still
  }
})();
