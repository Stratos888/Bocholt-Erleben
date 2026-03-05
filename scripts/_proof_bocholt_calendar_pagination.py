# scripts/_proof_event_source_extraction.py
# === BEGIN PROOF SCRIPT: Event source extraction smoke test | Scope: CF/JS detection + 10-item sample ===
import re
import sys
from urllib.parse import urljoin, urlparse
from playwright.sync_api import sync_playwright

DEFAULT_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)

BLOCK_NEEDLES = [
    "just a moment",
    "cloudflare",
    "cdn-cgi/challenge",
    "cf-",
    "verify you are human",
    "access denied",
]

def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())

def is_probably_cf_block(html: str) -> bool:
    low = (html or "").lower()
    return any(n in low for n in BLOCK_NEEDLES)

def extract_links(html: str):
    return set(re.findall(r'href="([^"]+)"', html or ""))

def sample_eventlike_items(url: str, html: str, max_items: int = 10):
    """
    Very light heuristic:
    - find anchors that look like event/detail links
    - then derive 'title' from anchor text and try to find a nearby date-like token
    """
    base = f"{urlparse(url).scheme}://{urlparse(url).netloc}"
    hrefs = extract_links(html)
    out = []

    # domain-specific candidate patterns (loose, but good enough for proof)
    host = (urlparse(url).netloc or "").lower()
    patterns = []
    if "unser-ferienprogramm.de" in host:
        patterns = [re.compile(r"programm\.php\?aktion=show.*", re.I), re.compile(r"details\.php.*", re.I)]
    elif "textilwerk.lwl.org" in host:
        patterns = [re.compile(r".*veranstalt.*", re.I), re.compile(r".*event.*", re.I)]
    elif "muensterland.com" in host:
        patterns = [re.compile(r".*veranstalt.*", re.I), re.compile(r".*event.*", re.I)]
    else:
        patterns = [re.compile(r".*veranstalt.*", re.I), re.compile(r".*event.*", re.I)]

    # build anchor text map by regexing simplisticly
    # (proof-only; not production parsing)
    anchor_texts = {}
    for m in re.finditer(r'<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>', html or "", flags=re.I | re.S):
        href = m.group(1)
        txt = re.sub(r"<[^>]+>", " ", m.group(2))
        txt = norm(txt)
        if txt:
            anchor_texts[href] = txt

    def looks_like_date(s: str) -> str:
        s = s or ""
        # ISO or DMY
        m = re.search(r"\b\d{4}-\d{2}-\d{2}\b", s)
        if m:
            return m.group(0)
        m = re.search(r"\b\d{1,2}\.\d{1,2}\.\d{2,4}\b", s)
        if m:
            return m.group(0)
        # "Sa, 12.04." etc
        m = re.search(r"\b\d{1,2}\.\d{1,2}\.\b", s)
        if m:
            return m.group(0)
        return ""

    candidates = []
    for h in hrefs:
        if h.startswith("#") or h.startswith("mailto:") or h.startswith("tel:"):
            continue
        for pat in patterns:
            if pat.search(h):
                candidates.append(h)
                break

    seen = set()
    for h in candidates:
        if h in seen:
            continue
        seen.add(h)
        abs_url = urljoin(base, h)

        title = anchor_texts.get(h, "") or anchor_texts.get(abs_url, "")
        if not title:
            continue
        if len(title) < 6:
            continue

        # try to find a date in a small window after the anchor
        idx = (html or "").find(f'href="{h}"')
        window = ""
        if idx != -1:
            window = (html or "")[idx: idx + 800]
        date = looks_like_date(window)

        out.append({"title": title[:120], "date_hint": date, "url": abs_url})
        if len(out) >= max_items:
            break

    return out

def run(url: str):
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(user_agent=DEFAULT_UA, locale="de-DE")
        page = context.new_page()

        print(f"\n=== PROOF START: {url} ===")
        page.goto(url, wait_until="domcontentloaded", timeout=60000)
        page.wait_for_timeout(2500)

        final_url = page.url
        title = page.title()
        html = page.content()

        print(f"FINAL_URL: {final_url}")
        print(f"TITLE: {title}")
        print(f"HTML_LEN: {len(html)}")
        print(f"CF_BLOCK: {'YES' if is_probably_cf_block(html) else 'NO'}")

        # quick content sanity: count obvious date tokens
        iso_dates = len(re.findall(r"\b\d{4}-\d{2}-\d{2}\b", html))
        dmy_dates = len(re.findall(r"\b\d{1,2}\.\d{1,2}\.\d{2,4}\b", html))
        print(f"DATE_TOKENS: iso={iso_dates} dmy={dmy_dates}")

        sample = sample_eventlike_items(url, html, max_items=10)
        print(f"SAMPLE_COUNT: {len(sample)}")
        for i, it in enumerate(sample, 1):
            print(f"  {i:02d}. title={it['title']!r} date_hint={it['date_hint']!r} url={it['url']}")

        # helpful for debugging if sample is 0
        if len(sample) == 0:
            needles = ["veranstaltung", "kalender", "event", "programm", "termine"]
            hits = [n for n in needles if n in (html or "").lower()]
            print(f"NEEDLE_HITS: {hits}")

        context.close()
        browser.close()
        print(f"=== PROOF END: {url} ===\n")

if __name__ == "__main__":
    urls = sys.argv[1:]
    if not urls:
        urls = [
            "https://www.unser-ferienprogramm.de/bocholt/programm.php",
            "https://textilwerk.lwl.org/",
            "https://www.muensterland.com/tourismus/orte-muensterland/orte-staedte-im-muensterland/bocholt-tourismus/veranstaltungen-in-bocholt/",
        ]
    for u in urls:
        run(u)
# === END PROOF SCRIPT: Event source extraction smoke test | Scope: CF/JS detection + 10-item sample ===
