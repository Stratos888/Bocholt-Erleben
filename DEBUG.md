<!-- === BEGIN FILE: DEBUG.md ===
Zweck:
- Kanonische, KI-optimierte Debug-Proofs für UI/Layout/Positioning/Regression.
- Reduziert “Verschlimmbesserungen” durch Root-Cause Proof vor Patches.
- Dient ausschließlich als Arbeitsinstrument für die Assistenz (nicht für Endnutzer).
Umfang:
- DevTools Console Snippets + Erwartungswerte + Patch-Entscheidungslogik.
=== -->

# DEBUG.md — Kanonische Proof-Snippets (KI-optimiert)

## 0) Regeln für diesen Debug-Flow (kurz)
- Dieses Dokument ist die **einzige** Quelle für Debug-Proofs im Projekt.
- Bei UI/Layout/Positioning: **erst Proof**, dann Patch (außer Root-Cause ist bereits bewiesen).
- **Nachher-Proof nur**, wenn “gelöst/DONE” behauptet wird.
- Immer den aktuellen Stand benennen: “Datei X ist Wahrheit”.

---

## 1) Standard-Proof: DETAILPANEL (Sheet / Body / Actionbar / Header)
**Wann nutzen:** Actionbar weg/verschoben, Content hinter Actionbar, Header/Close wirkt off, Scroll-Bugs.
**Wie nutzen:** Detailpanel öffnen → DevTools Console → Snippet ausführen → komplette Ausgabe posten.

### DP_PROOF_ALL
```js
(() => {
  const q = (s) => document.querySelector(s);
  const panel = q("#event-detail-panel");
  const sheet = q("#event-detail-panel .detail-panel-content");
  const body  = q("#event-detail-panel .detail-panel-body");
  const content = q("#event-detail-panel #detail-content");
  const slot  = q("#detail-actionbar-slot");
  const header= q("#event-detail-panel .detail-header");
  const close = q("#event-detail-panel .detail-panel-close");
  const titleRow = q("#event-detail-panel .detail-title-row");

  const rect = (el) => el ? (() => {
    const r = el.getBoundingClientRect();
    return { top:+r.top.toFixed(2), bottom:+r.bottom.toFixed(2), left:+r.left.toFixed(2), right:+r.right.toFixed(2), w:+r.width.toFixed(2), h:+r.height.toFixed(2) };
  })() : null;

  const pick = (el, keys) => el ? (() => {
    const s = getComputedStyle(el);
    const o = {};
    keys.forEach(k => o[k] = s[k]);
    return o;
  })() : null;

  const chain = (el) => {
    const out = [];
    let cur = el;
    while (cur && out.length < 14) {
      out.push(cur.id ? `#${cur.id}` : (cur.className ? `.${String(cur.className).trim().split(/\s+/)[0]}` : cur.tagName));
      cur = cur.parentElement;
    }
    return out;
  };

  const slotBtn = slot?.querySelector(".detail-actionbar-btn");
  const slotBtnTag = slotBtn?.tagName || null;

  return {
    meta: {
      href: location.href,
      viewport: { w: window.innerWidth, h: window.innerHeight, dpr: window.devicePixelRatio },
      now: new Date().toISOString()
    },
    exists: {
      panel: !!panel, sheet: !!sheet, body: !!body, content: !!content,
      slot: !!slot, header: !!header, close: !!close, titleRow: !!titleRow
    },
    rects: {
      sheet: rect(sheet),
      body: rect(body),
      content: rect(content),
      slot: rect(slot),
      header: rect(header),
      close: rect(close),
      titleRow: rect(titleRow)
    },
    css: {
      sheet: pick(sheet, ["position","overflow","transform","zIndex","height","maxHeight"]),
      body:  pick(body,  ["position","overflow","zIndex","paddingBottom","minHeight"]),
      slot:  pick(slot,  ["position","bottom","zIndex","display","height"]),
      header:pick(header,["paddingTop","paddingBottom","marginTop","marginBottom"]),
      close: pick(close, ["position","top","right","width","height","zIndex"])
    },
    derived: {
      // Actionbar "weg" Diagnose:
      slotOutsideViewport: slot ? (rect(slot).top > window.innerHeight || rect(slot).bottom < 0) : null,
      // Content unter Actionbar Diagnose (nur grob):
      contentBelowSlot: (content && slot) ? (rect(content).bottom > rect(slot).top) : null,
      // Header/Close "nicht auf gleicher Höhe":
      headerCloseDeltaTop: (header && close) ? +(rect(close).top - rect(header).top).toFixed(2) : null,
      // Stacking:
      slotOffsetParent: slot?.offsetParent ? (slot.offsetParent.id || slot.offsetParent.className || slot.offsetParent.tagName) : null,
      slotParentChain: slot ? chain(slot) : null,
      slotBtnTag
    },
    loadedCSS: [...document.styleSheets].map(s => s.href).filter(Boolean)
  };
})();
