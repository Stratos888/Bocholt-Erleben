# Bocholt erleben – Projektstand (verbindlich & vollständig)

Dieses Dokument ist die EINZIGE Wahrheitsquelle für Folgechats.
Der Assistent darf NIEMALS Annahmen treffen, sondern MUSS ausschließlich auf Basis dieses Dokuments arbeiten.

Alle Regeln hier sind verbindlich.

=====================================================================
=====================================================================

# 🎯 PRODUKTZIEL

bocholt-erleben.de ist:
→ eine Eventseite (keine Stadt-App)

Ziel:
- aktuelle Veranstaltungen in Bocholt + 20km
- mobile-first PWA
- ruhige moderne UI
- fair für Locations

WICHTIG:
Locations sind potenzielle Kunden → dürfen NICHT gescrapt werden.

Nur:
- öffentliche
- kommunale
- institutionelle
- rechtlich unkritische Quellen

=====================================================================

# 🧠 GESCHÄFTSREGELN (verbindlich)

- kein kostenloser Tarif
- keine Hervorhebungen
- keine Werbung
- keine Bevorzugung
- alle Locations gleich
- Einnahmen nur über Mitgliedschaften
- daher: Locations NICHT automatisch scrapen

=====================================================================

# ⚙️ ARBEITSMODUS & ENTWICKLUNGSREGELN (KRITISCH)

Diese Regeln sind absolut verbindlich:

1. Konsolidierungs-Modus
   → zuletzt gepostete Datei ist vollständig korrekt
   → keine Änderungen ohne aktuellen Stand

2. Diff statt Snippet
   → nur Replace-/Delete-Anweisungen
   → keine kompletten neuen Snippets anhängen

3. Datei-fokussiert
   → immer nur 1 Datei gleichzeitig

4. Codeblock-Markierungen
   → BEGIN/END Marker Pflicht

5. Keine Annahmen
   → erst Proof, dann Fix

6. 100%-Regel
   → nur sichere Aussagen, kein „vielleicht“

7. UI-Polish nur CSS

8. Overlay-Root direkt unter <body>

9. Deploy Fail-Fast
   → Pipeline MUSS bei kaputten Assets abbrechen

=====================================================================

# 🚀 DEPLOY ARCHITEKTUR

Hosting: STRATO
Deploy: GitHub Actions → SFTP

Mechanik:
- build.json enthält Commit SHA
- Service Worker liest build.json
- Cache Busting via ?v=BUILD_ID
- harte Guards für Asset Links

Ziel:
→ keine Cache-/Offline-Probleme

=====================================================================

# 📊 EVENT DISCOVERY SYSTEM (NEU)

Pipeline:

Sources / Sources_Adapters
      ↓
Daily Discovery
      ↓
Inbox
      ↓
Import → Events
      ↓
Deploy
      ↓
Live
      ↓
Cleanup → Archive

=====================================================================

# Google Sheets Tabs

Events
Inbox
Inbox_Archive
Sources (rss/ical)
Sources_Adapters (html)
Source_Seeds
Source_Candidates
Source_Blocklist

=====================================================================

# Discovery unterstützt

ical
rss
html_regex (facts-only)

Facts-only:
- title
- date
- time
- location
- url
- description leer

keine Texte übernehmen

=====================================================================

# DEDUPE & ID

slug(title)-yyyymmdd-hash

canonical URL (ohne utm/fbclid/?v)

Regeln:
1. URL
2. slug+date

=====================================================================

# GITHUB WORKFLOWS

Daily Discovery
Inbox Import
Inbox Cleanup
Source Scout (weekly)

=====================================================================

# CODING REGELN (wichtig)

- nur Spaces (4)
- keine Tabs
- nur ein try/except/else Block
- vor Run: python -m py_compile

=====================================================================

# AKTUELLER STATUS

✅ Discovery läuft
✅ HTML Adapter aktiv
✅ ID + Dedupe aktiv
✅ Source Scout aktiv
✅ Blocklist aktiv
⚠️ Sources Tab fast leer → daher evtl. keine Events

=====================================================================

# NÄCHSTE SCHRITTE

- reale kommunale Sources sammeln
- Seeds erweitern
- HTML Adapter erweitern
- Reichweite erhöhen

KEINE weitere Infrastruktur nötig

=====================================================================
Ende
