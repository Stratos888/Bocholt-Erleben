# SEO Weekend Intent Landing

Workpack: #399
Branch: `seo/weekend-intent-landing`

## Ziel
Eine einzige stabile, indexierbare Landingpage `/events/wochenende/` für den bereits belegten Wochenend-Intent bereitstellen.

## Scope
- Freitag–Sonntag deterministisch aus dem bestehenden Eventbestand auswählen.
- Mehrtagesevents per Range-Overlap berücksichtigen.
- Neue Landingpage statisch im bestehenden Deploy-Render befüllen.
- Von `/events/` intern verlinken.
- Live-Sitemap ergänzen.
- Bestehende neutral-selection-, static-render- und SEO-Contracts erweitern.

## Nicht im Scope
- keine `/events/heute/`-Route
- keine Änderung der Eventfilter-UX
- keine neue Datenquelle oder parallele Eventlogik
- kein direkter Main-/Live-Release

## Evidence-Grenze
Der Wochenend-Intent ist als strukturelle Lücke ausreichend belegt. Der Heute-Intent bleibt bis zur Query×Page-Auswertung unverändert, weil dort mögliche Kannibalisierung zwischen `/` und `/events/` zuerst gemessen werden muss.
