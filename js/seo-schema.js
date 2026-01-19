/**
 * seo-schema.js
 * Baut JSON-LD Event Schema aus /data/events.json
 * MVP: nur Felder aus dem MVP-Schema verwenden.
 */

(async function injectEventSchema() {
  try {
    const res = await fetch('/data/events.json', { cache: 'no-store' });
    if (!res.ok) return;

    const data = await res.json();
    const events = Array.isArray(data.events) ? data.events : [];
    if (!events.length) return;

    // Maximal 30 Events ausgeben (MVP, verhindert riesige JSON-LD Blöcke)
    const slice = events.slice(0, 30);

    const nowIso = new Date().toISOString();

    const graph = slice
      .filter(e => e && e.id && e.title && e.date && e.location)
      .map(e => {
        // date: YYYY-MM-DD, time: optionaler Display-String
        // Für startDate nutzt SEO Schema am besten ISO. Ohne parsebare Uhrzeit → all-day Start.
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
              "addressLocality": "Bocholt",
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

        // optional: time als Zusatzinfo (Display)
        if (e.time) {
          obj["additionalProperty"] = [{
            "@type": "PropertyValue",
            "name": "time",
            "value": String(e.time).slice(0, 30)
          }];
        }

        // Entferne undefined-Felder
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
