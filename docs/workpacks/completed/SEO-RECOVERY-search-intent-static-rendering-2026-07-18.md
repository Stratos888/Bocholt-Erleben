# Completed Workpack: SEO Recovery – Search Intent und statische Renderingbasis

Stand: 2026-07-22  
Status: technisch und fachlich auf Staging abgeschlossen  
Risikoklasse: R2

## Ausgangslage

Die Today-Home bot guten Produktnutzen, lieferte im initialen HTML aber zu wenig belastbare Suchsemantik und crawlbare Inhalte. Gleichzeitig meldete Google Search Console bei einer Eventdetailseite die nicht kritische Warnung `Feld "offers" fehlt`.

Grundlagen:

- `docs/forensics/seo-visibility-rueckgang-homepage-intent-2026-07-18.md`;
- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`;
- Google-Search-Console-Befund für die Kinderzaubershow mit fehlendem `offers`-Feld.

## Erreichter Zielzustand

```text
/               -> kuratierter Heute-Einstieg
/events/         -> vollständige Veranstaltungen
/aktivitaeten/   -> dauerhafte Aktivitäten
```

```text
kanonische Daten
-> deterministische neutrale Grundauswahl
-> statisches initiales HTML
-> atomare Browser-Anreicherung
```

Die drei Hauptseiten besitzen einen echten initialen Inhaltskern. Build und Browser verwenden dieselbe kleine neutrale Auswahlgrenze. Wetter, Präferenzen, Impressionen und Rotation bleiben ausschließlich Browser-Anreicherung.

## Technische Umsetzung

- `js/neutral-selection.js` ist der gemeinsame Owner der neutralen Grundauswahl.
- Vergangene und ungültige Events werden ausgeschlossen; Mehrtagesevents werden über `endDate` korrekt behandelt.
- Auswahl und Sortierung sind stabil und verwenden keine Zufalls-, Wetter-, Präferenz- oder Rotationswerte.
- `scripts/render-static-content.mjs` rendert die tatsächlichen Deploydaten in `/`, `/events/` und `/aktivitaeten/`.
- Der Renderer bricht bei leerem Event- oder Activitykern fail-closed ab.
- Der Startseitenkern enthält im tatsächlichen Renderer-Output direkte crawlbare Links zu `/events/` und `/aktivitaeten/` im bestehenden unteren `today-more`-Muster.
- Zusätzliche sichtbare Hero-Links wurden vollständig entfernt; der ursprüngliche Seitencharakter bleibt erhalten.
- Sammelseiten geben keine Event-Entitäten aus; Event-JSON-LD liegt ausschließlich auf geeigneten eindeutigen Detailseiten.
- DB-Submissions bleiben im Browserfeed sichtbar, sind aber ohne vollständigen statischen Detailseitenvertrag `schema_eligible: false`.

## Event-/Offer-Vertrag

Der öffentliche Vertrag trennt insbesondere:

- Quellen-URL;
- Eintrittsstatus;
- Preis und Währung;
- Ticket-URL;
- Verfügbarkeit;
- `validFrom`;
- Organizer und Performer einschließlich explizitem Typ;
- mehrere belegte Ticketangebote.

Wahrheitsregeln:

- eine allgemeine Quellen-URL wird nie automatisch zur Ticket-URL;
- `admission_status=free` erzeugt `price: 0` und `priceCurrency: EUR`;
- kostenpflichtige Offers benötigen gleichzeitig belegten Preis, gültige Währung und zulässige Ticket-URL;
- `availability` und `validFrom` erscheinen nur bei gültigen gelieferten Werten;
- unbekannte Eintrittslage erzeugt kein synthetisches Offer und kein Event-JSON-LD;
- Organizer und Performer erscheinen nur bei explizitem zulässigem Schema-Typ.

Der konkrete Zaubershow-Fall bleibt damit eine indexierbare HTML-Detailseite, ohne erfundene Angebotsdaten und ohne erneute `offers`-Warnung aus unvollständigem Event-Markup.

## Evidence

### E1

- Implementierung über PR #151 einschließlich technischer Nachschärfung;
- visuelle Regressionen über PR #153 und #154 untersucht und anschließend mit PR #156 vollständig zurückgebaut;
- finale No-JS-Hauptlinklücke mit PR #158 geschlossen;
- finaler Staging-SHA: `2ee2990bb06ee03ac8248e47150bb12de8a1c74e`.

### E2

Im zentralen `PR Gate` dauerhaft enthalten:

- Syntaxprüfungen für JavaScript, Python und PHP;
- `tests/neutral-selection.test.mjs`;
- `tests/static-render-fixture.test.mjs`;
- `tests/test_event_offer_contract.py`;
- `tests/test_event_detail_schema_contract.py`;
- `tests/test_seo_static_contract.py`;
- vollständiges `bash scripts/validate-repo.sh`.

Abgedeckt sind unter anderem Berliner Kalendertag, Mehrtagesevents, abgelaufene und ungültige Events, stabile Auswahl, leere Datenkerne, kostenlose und kostenpflichtige Offers, mehrere Ticketarten, ungültige URLs/Preise/Verfügbarkeiten sowie unbekannte Eintrittslagen.

### E3

- PR Gate #219 für den finalen technischen Fix: grün;
- normaler Deploy des Staging-SHA `2ee2990bb06ee03ac8248e47150bb12de8a1c74e`: grün;
- Startseite bei 327 × 779 Pixeln visuell geprüft;
- keine zusätzlichen Hero-Zeilen;
- keine CTA-/Kartenregression und kein horizontaler Overflow;
- normaler Browserzustand zeigt keine doppelten Karten oder Links.

## Abschlussbewertung

| Kriterium | Ergebnis |
|---|---|
| Gate A und Ownergrenzen geklärt | erfüllt |
| sinnvoller No-JS-Inhaltskern | erfüllt |
| gemeinsame neutrale Build-/Browserauswahl | erfüllt |
| Today-Home funktional erhalten | erfüllt |
| `/events/` und `/aktivitaeten/` eindeutig abgegrenzt | erfüllt |
| wahrheitsgetreuer Event-/Offer-Vertrag | erfüllt |
| E1, E2 und Staging-E3 | erfüllt |
| genau eine visuelle Korrekturrunde | nicht erfüllt |
| keine Try-and-Error-Schleife nach Staging-Merge | nicht erfüllt |
| finale Live-E6 und Search-Console-Validierung | getrennte Nacharbeit |
| 14-/28-Tage-Wirkungsmessung | zeitversetzte Nacharbeit |

Das Produktziel ist erreicht. Die nicht erfüllten Effizienzkriterien betreffen den gleichzeitig durchgeführten Werkzeugsteuerungs-Pilot und verhindern keine technische Workpack-Schließung. Sie werden nicht beschönigt und lösen keine neue allgemeine Prozessrunde aus.

## Getrennte Nacharbeiten

Nach dem regulären Release nach `main`:

1. read-only Live-E6 für HTML, Hauptlinks, Sitemap, Robots und repräsentative Detailseiten;
2. Search-Console-Validierung für `Feld "offers" fehlt` starten und Status zeitversetzt dokumentieren;
3. erste Suchwirkung nach mindestens 14 Tagen, führende Bewertung nach 28 Tagen;
4. Impressionen, Klicks, CTR und Position getrennt bewerten.

Rankingverbesserung und Search-Console-Neubewertung sind keine rückwirkenden technischen Postconditions. Ohne konkreten neuen Befund erfolgt keine weitere SEO-Strukturänderung.

## Rollback

Der SEO-Release kann über den jeweiligen Main-Merge-Commit revertiert und anschließend über den normalen `Deploy to STRATO`-Pfad erneut veröffentlicht werden. Sheet-, DB- und externe Daten wurden durch diesen Workpack nicht geschrieben.
