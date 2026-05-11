<?php
declare(strict_types=1);
/* === BEGIN FILE: intern/seo-dashboard/index.php | Zweck: internes, passwortgeschuetztes SEO-/Mehrwert-Dashboard fuer Betreiber; Umfang: komplette Datei === */

require __DIR__ . '/../../api/_bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

session_name('BE_INTERNAL_SEO_DASHBOARD');
session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errorMessage = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: /intern/seo-dashboard/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = trim((string)($_POST['password'] ?? ''));

    try {
        $expectedPassword = be_review_password();
        if ($submittedPassword !== '' && hash_equals($expectedPassword, $submittedPassword)) {
            $_SESSION['be_internal_seo_dashboard_unlocked'] = true;
            header('Location: /intern/seo-dashboard/');
            exit;
        }

        $errorMessage = 'Passwort nicht korrekt.';
    } catch (Throwable $error) {
        http_response_code(503);
        $errorMessage = 'Review-Passwort ist serverseitig nicht konfiguriert.';
    }
}

$isUnlocked = (bool)($_SESSION['be_internal_seo_dashboard_unlocked'] ?? false);
$checkedAt = date('d.m.Y H:i');
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow,noarchive" />
  <title>Internes SEO-Dashboard · Bocholt erleben</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f6f2ea;
      --surface: #fffaf2;
      --surface-2: #ffffff;
      --text: #221b14;
      --muted: #74685d;
      --line: rgba(46, 34, 24, .14);
      --good: #0f6b3d;
      --warn: #8a5a00;
      --bad: #9b1c1c;
      --soft-good: rgba(15, 107, 61, .10);
      --soft-warn: rgba(138, 90, 0, .12);
      --soft-bad: rgba(155, 28, 28, .10);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      line-height: 1.45;
    }

    a { color: inherit; }

    .wrap {
      width: min(1180px, calc(100% - 28px));
      margin: 0 auto;
      padding: 22px 0 42px;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: flex-start;
      margin-bottom: 18px;
    }

    .eyebrow {
      margin: 0 0 4px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
    }

    h1 {
      margin: 0;
      font-size: clamp(26px, 4vw, 38px);
      line-height: 1.08;
      letter-spacing: -.03em;
    }

    h2 {
      margin: 0 0 12px;
      font-size: 18px;
      line-height: 1.2;
    }

    p { margin: 0; }

    .muted { color: var(--muted); }
    .small { font-size: 13px; }

    .logout {
      display: inline-flex;
      align-items: center;
      min-height: 38px;
      padding: 8px 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: var(--surface);
      text-decoration: none;
      font-size: 14px;
      font-weight: 700;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 14px;
    }

    .card {
      grid-column: span 12;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 16px;
      box-shadow: 0 12px 32px rgba(45, 33, 20, .06);
    }

    .card--third { grid-column: span 4; }
    .card--half { grid-column: span 6; }
    .card--wide { grid-column: span 8; }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      min-height: 28px;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid var(--line);
      font-size: 13px;
      font-weight: 800;
      background: var(--surface-2);
    }

    .dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: var(--muted);
    }

    .status[data-state="good"] {
      color: var(--good);
      background: var(--soft-good);
      border-color: rgba(15,107,61,.22);
    }

    .status[data-state="warn"] {
      color: var(--warn);
      background: var(--soft-warn);
      border-color: rgba(138,90,0,.25);
    }

    .status[data-state="bad"] {
      color: var(--bad);
      background: var(--soft-bad);
      border-color: rgba(155,28,28,.22);
    }

    .status[data-state="good"] .dot { background: var(--good); }
    .status[data-state="warn"] .dot { background: var(--warn); }
    .status[data-state="bad"] .dot { background: var(--bad); }

    .checklist {
      display: grid;
      gap: 9px;
      margin-top: 10px;
    }

    .check {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 10px;
      padding: 10px;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: var(--surface-2);
    }

    .check code {
      font-size: 12px;
      color: var(--muted);
      overflow-wrap: anywhere;
    }

    .metricForm {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 12px;
    }

    label {
      display: grid;
      gap: 5px;
      font-size: 12px;
      font-weight: 800;
      color: var(--muted);
    }

    input {
      width: 100%;
      min-height: 40px;
      padding: 9px 11px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: var(--surface-2);
      font: inherit;
      color: var(--text);
    }

    button {
      min-height: 40px;
      padding: 9px 12px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: var(--text);
      color: #fff;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }

    button.secondary {
      background: var(--surface-2);
      color: var(--text);
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .kpi {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-top: 12px;
    }

    .kpiBox {
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 16px;
      background: var(--surface-2);
    }

    .kpiValue {
      font-size: 24px;
      font-weight: 900;
      letter-spacing: -.03em;
    }

    .linkList {
      display: grid;
      gap: 8px;
      margin-top: 10px;
    }

    .linkItem {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      padding: 10px;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: var(--surface-2);
      text-decoration: none;
    }

    .note {
      padding: 11px;
      border-radius: 14px;
      background: rgba(255,255,255,.56);
      border: 1px solid var(--line);
    }

    .login {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 22px;
    }

    .loginCard {
      width: min(440px, 100%);
      padding: 22px;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 22px;
      box-shadow: 0 18px 42px rgba(45, 33, 20, .10);
    }

    .loginCard form {
      display: grid;
      gap: 12px;
      margin-top: 16px;
    }

    .error {
      color: var(--bad);
      font-weight: 800;
    }

    @media (max-width: 860px) {
      .topbar { display: grid; }
      .card--third,
      .card--half,
      .card--wide { grid-column: span 12; }
      .metricForm,
      .kpi { grid-template-columns: 1fr; }
      .check,
      .linkItem {
        grid-template-columns: 1fr;
        align-items: start;
      }
    }
  </style>
</head>
<body>
<?php if (!$isUnlocked): ?>
  <main class="login">
    <section class="loginCard" aria-labelledby="login-title">
      <p class="eyebrow">Intern · nicht öffentlich</p>
      <h1 id="login-title">SEO-Dashboard</h1>
      <p class="muted small" style="margin-top:8px;">Diese Seite ist serverseitig geschützt und nutzt dasselbe Review-Passwort wie die interne Kuratier-Inbox.</p>

      <?php if ($errorMessage !== ''): ?>
        <p class="error small" style="margin-top:12px;"><?= h($errorMessage) ?></p>
      <?php endif; ?>

      <form method="post" action="/intern/seo-dashboard/">
        <label>
          Passwort
          <input name="password" type="password" autocomplete="current-password" required autofocus />
        </label>
        <button type="submit">Dashboard öffnen</button>
      </form>
    </section>
  </main>
<?php else: ?>
  <main class="wrap">
    <header class="topbar">
      <div>
        <p class="eyebrow">Internes Dashboard · Stand <?= h($checkedAt) ?></p>
        <h1>SEO- & Mehrwert-Status</h1>
        <p class="muted" style="margin-top:8px; max-width:760px;">Private Betreiberansicht für Live-Rollout, Indexierung und Mehrwert-Messung. Diese Seite ist nicht verlinkt, nicht in der Sitemap und per <code>noindex</code> gesperrt.</p>
      </div>
      <a class="logout" href="/intern/seo-dashboard/?logout=1">Abmelden</a>
    </header>

    <section class="grid">
      <article class="card card--third">
        <h2>Gesamtstatus</h2>
        <span id="overallStatus" class="status" data-state="warn"><span class="dot"></span><span>Prüfung läuft</span></span>
        <p id="overallText" class="muted small" style="margin-top:10px;">Live-Checks werden im Browser geprüft.</p>
      </article>

      <article class="card card--third">
        <h2>Messung</h2>
        <span id="measurementStatus" class="status" data-state="warn"><span class="dot"></span><span>noch nicht bewertet</span></span>
        <p class="muted small" style="margin-top:10px;">Google- und Bing-Suchdaten werden in V2 automatisch aus <code>/data/search-metrics.json</code> geladen. Outbound-Klicks und performende Events bleiben vorerst manuell.</p>
      </article>

      <article class="card card--third">
        <h2>Nächste Aktion</h2>
        <p class="small"><strong>Nach Deploy:</strong> Search-Metrics-JSON prüfen, Google-/Bing-Status kontrollieren und danach Outbound-/Event-Werte ergänzen.</p>
      </article>

      <article class="card card--wide">
        <h2>Live-Checks</h2>
        <p class="muted small">Diese Checks prüfen technische Erreichbarkeit, offensichtliche Konfiguration und vorbereitete Analytics-Hooks. Echte Suchdaten kommen automatisiert aus Google Search Console und Bing Webmaster Tools.</p>

        <div class="checklist" id="checklist">
          <div class="check" data-check="status">
            <span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span>
            <div><strong>Backend-Status</strong><br><code>/api/status.php</code></div>
            <a href="/api/status.php" target="_blank" rel="noopener">öffnen</a>
          </div>

          <div class="check" data-check="robots">
            <span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span>
            <div><strong>Robots</strong><br><code>/robots.txt</code></div>
            <a href="/robots.txt" target="_blank" rel="noopener">öffnen</a>
          </div>

          <div class="check" data-check="sitemap">
            <span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span>
            <div><strong>Sitemap</strong><br><code>/sitemap.xml</code></div>
            <a href="/sitemap.xml" target="_blank" rel="noopener">öffnen</a>
          </div>

          <div class="check" data-check="build">
            <span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span>
            <div><strong>Build-Version</strong><br><code>/meta/build.txt</code></div>
            <a href="/meta/build.txt" target="_blank" rel="noopener">öffnen</a>
          </div>

          <div class="check" data-check="analytics">
            <span class="status" data-state="warn"><span class="dot"></span><span>offen</span></span>
            <div><strong>GA4-Konfiguration</strong><br><code>/config.js</code></div>
            <a href="/config.js" target="_blank" rel="noopener">öffnen</a>
          </div>
        </div>
      </article>

      <article class="card card--third">
        <h2>Indexierbare Kernseiten</h2>
        <div id="indexedPages" class="linkList"></div>
      </article>

      <article class="card">
        <h2>Akquise-Ampel</h2>
        <p class="muted small">Google- und Bing-Werte werden automatisch aus dem Search-Metrics-Export geladen. Outbound-Klicks und performende Events bleiben bis zur GA4-API-Anbindung als manuelle Monatswerte steuerbar.</p>

        <form id="metricForm" class="metricForm">
          <label>Google Impressionen/Monat<input name="googleImpressions" inputmode="numeric" type="number" min="0" step="1" /></label>
          <label>Google Klicks/Monat<input name="googleClicks" inputmode="numeric" type="number" min="0" step="1" /></label>
          <label>Bing Impressionen/Monat<input name="bingImpressions" inputmode="numeric" type="number" min="0" step="1" /></label>
          <label>Bing Klicks/Monat<input name="bingClicks" inputmode="numeric" type="number" min="0" step="1" /></label>
          <label>Outbound-Klicks/Monat<input name="outboundClicks" inputmode="numeric" type="number" min="0" step="1" /></label>
          <label>performende Events<input name="performingEvents" inputmode="numeric" type="number" min="0" step="1" /></label>
        </form>

        <div class="actions">
          <button type="button" id="saveMetrics">Manuelle Werte speichern</button>
          <button type="button" id="resetMetrics" class="secondary">Lokale Werte löschen</button>
          <button type="button" id="reloadSearchMetrics" class="secondary">Search-Daten neu laden</button>
        </div>

        <div class="kpi">
          <div class="kpiBox"><div class="muted small">Impressionen gesamt</div><div id="kpiImpressions" class="kpiValue">0</div></div>
          <div class="kpiBox"><div class="muted small">Klicks gesamt</div><div id="kpiClicks" class="kpiValue">0</div></div>
          <div class="kpiBox"><div class="muted small">Outbound</div><div id="kpiOutbound" class="kpiValue">0</div></div>
          <div class="kpiBox"><div class="muted small">Ampel</div><div id="kpiStage" class="kpiValue">Rot</div></div>
        </div>

        <p id="kpiExplanation" class="note small" style="margin-top:12px;">Noch keine Werte geladen.</p>
      </article>

      <article class="card card--half">
        <h2>Search-Automatik</h2>
        <div id="searchMetricsStatus" class="note small">Search-Daten werden geladen.</div>
      </article>

      <article class="card card--half">
        <h2>Externe Messquellen</h2>
        <div class="linkList">
          <a class="linkItem" href="https://search.google.com/search-console" target="_blank" rel="noopener"><strong>Google Search Console</strong><span>Impressionen, Klicks, Seiten</span></a>
          <a class="linkItem" href="https://www.bing.com/webmasters" target="_blank" rel="noopener"><strong>Bing Webmaster Tools</strong><span>Bing-Sichtbarkeit</span></a>
          <a class="linkItem" href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener"><strong>Google Analytics 4</strong><span>Pageviews, outbound_click</span></a>
        </div>
      </article>
    </section>
  </main>

  <script>
    // === BEGIN BLOCK: INTERNAL_SEO_DASHBOARD_LOGIC_V2_SEARCH_AUTOMATION | Zweck: lädt Google-/Bing-Search-Metriken automatisch aus dem Deploy-Export und kombiniert sie mit manuellen GA4-/Event-Werten; Umfang: nur diese interne Dashboard-Seite ===
    const CHECKS = new Map();

    const CORE_PAGES = [
      "/",
      "/angebote/",
      "/events-veroeffentlichen/",
      "/events-veroeffentlichen/einreichen/",
      "/events-veroeffentlichen/anbindung/",
      "/fuer-veranstalter/",
      "/ueber/",
      "/impressum/",
      "/datenschutz/"
    ];

    const STORAGE_KEY = "be_internal_seo_metrics_v2";
    const SEARCH_METRICS_URL = "/data/search-metrics.json";

    const DEFAULT_METRICS = {
      googleImpressions: 0,
      googleClicks: 0,
      bingImpressions: 0,
      bingClicks: 0,
      outboundClicks: 0,
      performingEvents: 0
    };

    let currentMetrics = { ...DEFAULT_METRICS };

    function metricNumber(value) {
      const number = Number(value || 0);
      return Number.isFinite(number) ? Math.max(0, Math.round(number)) : 0;
    }

    function formatMetric(value) {
      return metricNumber(value).toLocaleString("de-DE");
    }

    function setCheck(name, state, label, detail = "") {
      const row = document.querySelector(`[data-check="${name}"]`);
      if (!row) return;

      const status = row.querySelector(".status");
      const statusLabel = status?.querySelector("span:last-child");
      const code = row.querySelector("code");

      if (status) status.dataset.state = state;
      if (statusLabel) statusLabel.textContent = label;
      if (detail && code) code.textContent = detail;

      CHECKS.set(name, state);
      updateOverallStatus();
    }

    function updateOverallStatus() {
      const status = document.getElementById("overallStatus");
      const text = document.getElementById("overallText");
      const values = Array.from(CHECKS.values());
      const known = values.length > 0;
      const hasBad = values.includes("bad");
      const hasWarn = values.includes("warn");

      let state = "warn";
      let label = "Prüfung läuft";
      let copy = "Live-Checks werden im Browser geprüft.";

      if (known && !hasBad && !hasWarn) {
        state = "good";
        label = "bereit";
        copy = "Robots, Sitemap, Build, Backend und GA4-Konfiguration wirken technisch erreichbar.";
      } else if (known && hasBad) {
        state = "bad";
        label = "prüfen";
        copy = "Mindestens ein technischer Check ist fehlgeschlagen.";
      } else if (known) {
        label = "teilweise offen";
        copy = "Mindestens ein technischer Check ist noch nicht eindeutig grün.";
      }

      status.dataset.state = state;
      status.querySelector("span:last-child").textContent = label;
      text.textContent = copy;
    }

    async function fetchText(path) {
      const res = await fetch(path, { cache: "no-store" });
      const text = await res.text();

      return {
        ok: res.ok,
        status: res.status,
        text
      };
    }

    async function runChecks() {
      try {
        const res = await fetch("/api/status.php", { cache: "no-store" });
        const json = await res.json();

        const ok = (
          res.ok &&
          json.status === "ok" &&
          json.checks?.config &&
          json.checks?.database
        );

        setCheck(
          "status",
          ok ? "good" : "bad",
          ok ? "ok" : "Fehler",
          `/api/status.php · ${json.app_env || "unknown"}`
        );
      } catch (_) {
        setCheck("status", "bad", "Fehler");
      }

      try {
        const robots = await fetchText("/robots.txt");
        const ok = (
          robots.ok &&
          /Sitemap:\s*https:\/\/bocholt-erleben\.de\/sitemap\.xml/i.test(robots.text) &&
          /User-agent:\s*GPTBot/i.test(robots.text)
        );
        const internalBlocked = /Disallow:\s*\/intern\//i.test(robots.text);

        setCheck(
          "robots",
          ok && internalBlocked ? "good" : "warn",
          ok && internalBlocked ? "ok" : "prüfen",
          internalBlocked ? "/robots.txt · intern gesperrt" : "/robots.txt · intern nicht gesperrt"
        );
      } catch (_) {
        setCheck("robots", "bad", "Fehler");
      }

      try {
        const sitemap = await fetchText("/sitemap.xml");
        const urls = Array.from(sitemap.text.matchAll(/<loc>(.*?)<\/loc>/g)).map((m) => m[1]);
        const missing = CORE_PAGES.filter((path) => !urls.some((url) => url.endsWith(path)));

        setCheck(
          "sitemap",
          sitemap.ok && missing.length === 0 ? "good" : "warn",
          missing.length === 0 ? `${urls.length} URLs` : `${missing.length} fehlt`,
          `/sitemap.xml · ${urls.length} URLs`
        );

        renderIndexedPages(urls);
      } catch (_) {
        setCheck("sitemap", "bad", "Fehler");
        renderIndexedPages([]);
      }

      try {
        const build = await fetchText("/meta/build.txt");
        const value = build.text.trim();

        setCheck(
          "build",
          build.ok && value ? "good" : "warn",
          value ? value : "nicht gefunden",
          `/meta/build.txt · ${value || "leer"}`
        );
      } catch (_) {
        setCheck("build", "bad", "Fehler");
      }

      try {
        const config = await fetchText("/config.js");
        const hasGa4 = /measurementId:\s*["']G-Y6QLCQ4HXT["']/.test(config.text);
        const liveOnly = /enabledHosts:\s*\[[^\]]*bocholt-erleben\.de[^\]]*\]/.test(config.text);
        const outbound = /trackOutboundClick/.test(config.text) && /outbound_click/.test(config.text);
        const ok = config.ok && hasGa4 && liveOnly && outbound;

        setCheck(
          "analytics",
          ok ? "good" : "warn",
          ok ? "ok" : "prüfen",
          ok ? "GA4 + outbound_click vorbereitet" : "GA4-Konfiguration prüfen"
        );
      } catch (_) {
        setCheck("analytics", "bad", "Fehler");
      }
    }

    function renderIndexedPages(urls) {
      const box = document.getElementById("indexedPages");
      const visibleUrls = urls.length ? urls : CORE_PAGES.map((path) => `${location.origin}${path}`);

      box.innerHTML = visibleUrls.map((url) => {
        const path = new URL(url, location.origin).pathname;

        return `
          <a class="linkItem" href="${path}" target="_blank" rel="noopener">
            <strong>${path}</strong>
            <span>öffnen</span>
          </a>
        `;
      }).join("");
    }

    function readMetricsFromForm() {
      const form = document.getElementById("metricForm");

      return Object.fromEntries(
        Array.from(new FormData(form).entries()).map(([key, value]) => [key, metricNumber(value)])
      );
    }

    function writeMetricsToForm(metrics) {
      const form = document.getElementById("metricForm");

      for (const [key, value] of Object.entries(metrics || {})) {
        if (form.elements[key]) {
          form.elements[key].value = value || "";
        }
      }
    }

    function classifyMetrics(metrics) {
      const impressions = metricNumber(metrics.googleImpressions) + metricNumber(metrics.bingImpressions);
      const clicks = metricNumber(metrics.googleClicks) + metricNumber(metrics.bingClicks);
      const outbound = metricNumber(metrics.outboundClicks);
      const performing = metricNumber(metrics.performingEvents);

      if (impressions >= 20000 && clicks >= 1000 && outbound >= 100 && performing >= 3) {
        return ["strong", "Stark", "Stark verkaufsfähig: Reichweite und Interaktion reichen für konkrete Veranstalter-Kommunikation."];
      }

      if (impressions >= 10000 && clicks >= 500 && outbound >= 30 && performing >= 15) {
        return ["good", "Grün", "Aktiv verkaufsfähig: mit Zahlen auf Veranstalter zugehen."];
      }

      if (impressions >= 3000 && clicks >= 150 && outbound >= 10 && performing >= 5) {
        return ["warn", "Gelb", "Erste gezielte Akquise-Tests sind plausibel. Noch keine breite Verkaufsoffensive."];
      }

      return ["bad", "Rot", "Noch nicht aktiv verkaufen. Erst Sichtbarkeit, Klicks und Beispiel-Events weiter aufbauen."];
    }

    function renderMetrics(metrics) {
      const impressions = metricNumber(metrics.googleImpressions) + metricNumber(metrics.bingImpressions);
      const clicks = metricNumber(metrics.googleClicks) + metricNumber(metrics.bingClicks);
      const [state, label, copy] = classifyMetrics(metrics);

      document.getElementById("kpiImpressions").textContent = formatMetric(impressions);
      document.getElementById("kpiClicks").textContent = formatMetric(clicks);
      document.getElementById("kpiOutbound").textContent = formatMetric(metrics.outboundClicks);
      document.getElementById("kpiStage").textContent = label;
      document.getElementById("kpiExplanation").textContent = copy;

      const measurementStatus = document.getElementById("measurementStatus");
      measurementStatus.dataset.state = state === "strong" ? "good" : state;
      measurementStatus.querySelector("span:last-child").textContent = label;
    }

    function applyMetrics(metrics) {
      currentMetrics = {
        ...DEFAULT_METRICS,
        ...(metrics || {})
      };

      writeMetricsToForm(currentMetrics);
      renderMetrics(currentMetrics);
    }

    function loadStoredMetrics() {
      try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");
      } catch (_) {
        return {};
      }
    }

    function sourceStatusLabel(status) {
      if (status === "ok") return "ok";
      if (status === "not_configured") return "nicht konfiguriert";
      return "Fehler";
    }

    function renderSearchMetricsStatus(payload) {
      const box = document.getElementById("searchMetricsStatus");
      if (!box) return;

      if (!payload) {
        box.textContent = "Search-Daten werden geladen.";
        return;
      }

      const google = payload.sources?.google || {};
      const bing = payload.sources?.bing || {};
      const period = payload.period || {};
      const generatedAt = payload.generated_at ? new Date(payload.generated_at) : null;
      const generatedLabel = generatedAt && !Number.isNaN(generatedAt.getTime())
        ? generatedAt.toLocaleString("de-DE")
        : "unbekannt";

      box.innerHTML = `
        <p><strong>Status:</strong> ${payload.status || "unbekannt"}</p>
        <p style="margin-top:6px;"><strong>Zeitraum:</strong> ${period.start_date || "?"} bis ${period.end_date || "?"}</p>
        <p style="margin-top:6px;"><strong>Aktualisiert:</strong> ${generatedLabel}</p>
        <p style="margin-top:10px;"><strong>Google:</strong> ${sourceStatusLabel(google.status)} · ${formatMetric(google.impressions)} Impressionen · ${formatMetric(google.clicks)} Klicks</p>
        <p style="margin-top:6px;"><strong>Bing:</strong> ${sourceStatusLabel(bing.status)} · ${formatMetric(bing.impressions)} Impressionen · ${formatMetric(bing.clicks)} Klicks</p>
        ${google.message ? `<p class="muted" style="margin-top:8px;">Google: ${google.message}</p>` : ""}
        ${bing.message ? `<p class="muted" style="margin-top:4px;">Bing: ${bing.message}</p>` : ""}
      `;
    }

    function extractSearchMetrics(payload) {
      const google = payload.sources?.google || {};
      const bing = payload.sources?.bing || {};
      const metrics = {};

      if (google.status === "ok") {
        metrics.googleImpressions = metricNumber(google.impressions);
        metrics.googleClicks = metricNumber(google.clicks);
      }

      if (bing.status === "ok") {
        metrics.bingImpressions = metricNumber(bing.impressions);
        metrics.bingClicks = metricNumber(bing.clicks);
      }

      return metrics;
    }

    async function loadSearchMetrics() {
      renderSearchMetricsStatus(null);

      try {
        const res = await fetch(SEARCH_METRICS_URL, { cache: "no-store" });
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const payload = await res.json();
        const searchMetrics = extractSearchMetrics(payload);

        applyMetrics({
          ...currentMetrics,
          ...searchMetrics
        });
        renderSearchMetricsStatus(payload);
      } catch (error) {
        renderSearchMetricsStatus({
          status: "error",
          generated_at: null,
          period: {},
          sources: {
            google: { status: "error", impressions: 0, clicks: 0, message: "Search-Metrics-JSON konnte nicht geladen werden." },
            bing: { status: "error", impressions: 0, clicks: 0, message: "Search-Metrics-JSON konnte nicht geladen werden." }
          }
        });
      }
    }

    document.getElementById("saveMetrics").addEventListener("click", () => {
      const metrics = readMetricsFromForm();

      localStorage.setItem(STORAGE_KEY, JSON.stringify(metrics));
      applyMetrics(metrics);
    });

    document.getElementById("resetMetrics").addEventListener("click", () => {
      localStorage.removeItem(STORAGE_KEY);
      applyMetrics(DEFAULT_METRICS);
      loadSearchMetrics();
    });

    document.getElementById("reloadSearchMetrics").addEventListener("click", () => {
      loadSearchMetrics();
    });

    applyMetrics(loadStoredMetrics());
    loadSearchMetrics();
    runChecks();
    // === END BLOCK: INTERNAL_SEO_DASHBOARD_LOGIC_V2_SEARCH_AUTOMATION ===
  </script>
  </script>
<?php endif; ?>
</body>
</html>
<?php /* === END FILE: intern/seo-dashboard/index.php === */ ?>
