import re
from urllib.parse import urljoin
from playwright.sync_api import sync_playwright

BASE = "https://www.bocholt.de"
URL = "https://www.bocholt.de/veranstaltungskalender"

DETAIL_RE = re.compile(r"^/veranstaltungskalender/[^?#]+$")


def extract_detail_links(html: str):
    hrefs = set(re.findall(r'href="([^"]+)"', html))
    out = set()
    for h in hrefs:
        if DETAIL_RE.match(h):
            out.add(urljoin(BASE, h))
    return sorted(out)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
            ),
            locale="de-DE",
        )
        page = context.new_page()

        # Log XHR/fetch to discover real event endpoints
        def on_response(resp):
            try:
                req = resp.request
                if req.resource_type in ("xhr", "fetch"):
                    print(f"XHR {resp.status} {req.method} {resp.url}")
            except Exception:
                pass

        page.on("response", on_response)

        page.goto(URL, wait_until="domcontentloaded", timeout=60000)
        page.wait_for_timeout(2500)

        final_url = page.url
        title = page.title()
        html = page.content()

        print(f"FINAL_URL: {final_url}")
        print(f"TITLE: {title}")
        print(f"HTML_LEN: {len(html)}")

        # quick block diagnostics
        for needle in [
            "Just a moment",
            "cf-",
            "Cloudflare",
            "Bitte aktivieren Sie JavaScript",
            "Cookie",
            "Zustimmen",
            "Consent",
            "veranstaltung anlegen",
            "veranstaltungskalender",
        ]:
            if needle.lower() in html.lower():
                print(f"HIT: {needle}")

        links = extract_detail_links(html)
        print(f"DETAIL_LINKS: {len(links)} sample={links[:10]}")

        # screenshot + html dump for inspection (artifacts in Actions logs are limited, but file helps)
        page.screenshot(path="proof_bocholt.png", full_page=True)
        with open("proof_bocholt.html", "w", encoding="utf-8") as f:
            f.write(html)

        print("WROTE: proof_bocholt.png and proof_bocholt.html")

        context.close()
        browser.close()


if __name__ == "__main__":
    main()
