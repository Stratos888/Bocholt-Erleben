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


def wait_events_visible(page, timeout_ms: int = 60000):
    # Do NOT wait for networkidle (bocholt.de keeps background requests open)
    page.wait_for_timeout(1200)
    page.wait_for_function(
        "() => Array.from(document.querySelectorAll('a[href]')).some(a => (a.getAttribute('href')||'').startsWith('/veranstaltungskalender/'))",
        timeout=timeout_ms,
    )


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
            ),
            locale="de-DE",
        )

        page.goto(URL, wait_until="domcontentloaded", timeout=60000)
        wait_events_visible(page)

        all_links = set()

        for n in [1, 2, 3]:
            html = page.content()
            links = extract_detail_links(html)
            new_links = [u for u in links if u not in all_links]
            all_links.update(links)

            print(f"PAGE {n}: links={len(links)} new={len(new_links)} sample={new_links[:5]}")

            if n < 3:
                locator = page.locator(f"text=\"{n+1}\"").first
                if locator.count() == 0:
                    print(f"Pagination button '{n+1}' not found; stop.")
                    break
                locator.click()
                wait_events_visible(page)

        browser.close()


if __name__ == "__main__":
    main()
