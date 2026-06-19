#!/usr/bin/env python3
# === BEGIN FILE: tools/smoke-check-deploy.py | Zweck: prueft nach einem STRATO-Deploy zentrale Live-/Staging-Endpunkte als Fail-Fast-Smoke-Test; Umfang: komplette Datei ===
from __future__ import annotations

import argparse
import json
import sys
import time
from dataclasses import dataclass
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin
from urllib.request import Request, urlopen


@dataclass(frozen=True)
class HttpResult:
    url: str
    status: int
    headers: dict[str, str]
    body: str


def fail(message: str) -> None:
    print(f"❌ {message}", file=sys.stderr)
    raise SystemExit(1)


def normalize_base_url(value: str) -> str:
    base_url = value.strip()
    if not base_url:
        fail("--base-url fehlt.")
    if not base_url.startswith(("https://", "http://")):
        fail("--base-url muss mit https:// oder http:// beginnen.")
    return base_url.rstrip("/") + "/"


def build_url(base_url: str, path: str) -> str:
    return urljoin(base_url, path.lstrip("/"))


def request_url(
    url: str,
    *,
    method: str = "GET",
    body: bytes | None = None,
    headers: dict[str, str] | None = None,
    timeout: int = 20,
) -> HttpResult:
    request_headers = {
        "User-Agent": "Bocholt-Erleben-Deploy-Smoke/1.0",
        "Accept": "application/json,text/html,text/plain,*/*",
    }
    if headers:
        request_headers.update(headers)

    request = Request(url, data=body, headers=request_headers, method=method)

    try:
        with urlopen(request, timeout=timeout) as response:
            raw = response.read()
            return HttpResult(
                url=url,
                status=int(response.status),
                headers={k.lower(): v for k, v in response.headers.items()},
                body=raw.decode("utf-8", errors="replace"),
            )
    except HTTPError as error:
        raw = error.read()
        return HttpResult(
            url=url,
            status=int(error.code),
            headers={k.lower(): v for k, v in error.headers.items()},
            body=raw.decode("utf-8", errors="replace"),
        )
    except URLError as error:
        raise RuntimeError(f"{url} ist nicht erreichbar: {error.reason}") from error


def retry(
    label: str,
    callback: Callable[[], Any],
    *,
    attempts: int = 5,
    wait_seconds: int = 5,
) -> Any:
    last_error: Exception | None = None

    for attempt in range(1, attempts + 1):
        try:
            return callback()
        except Exception as error:  # noqa: BLE001 - Smoke-Check soll robuste Diagnose liefern.
            last_error = error
            if attempt < attempts:
                print(f"⚠️  Retry {attempt}/{attempts} für {label}: {error}")
                time.sleep(wait_seconds)

    if last_error is not None:
        raise last_error
    raise RuntimeError(f"{label} konnte nicht geprüft werden.")


def request_with_retries(url: str, **kwargs: Any) -> HttpResult:
    def do_request() -> HttpResult:
        result = request_url(url, **kwargs)
        if result.status >= 500:
            raise RuntimeError(f"{url} liefert HTTP {result.status}.")
        return result

    return retry(url, do_request)


def parse_json(result: HttpResult) -> dict[str, Any]:
    try:
        payload = json.loads(result.body)
    except json.JSONDecodeError as error:
        raise AssertionError(f"{result.url} liefert kein gültiges JSON: {error}") from error

    if not isinstance(payload, dict):
        raise AssertionError(f"{result.url} liefert kein JSON-Objekt.")

    return payload


def require_status(result: HttpResult, expected: set[int], label: str) -> None:
    if result.status not in expected:
        excerpt = result.body.strip().replace("\n", " ")[:300]
        raise AssertionError(
            f"{label}: erwarteter HTTP-Status {sorted(expected)}, erhalten {result.status}. Antwort: {excerpt}"
        )


def check_html_page(base_url: str, path: str, label: str) -> None:
    result = request_with_retries(build_url(base_url, path))
    require_status(result, {200}, label)

    content_type = result.headers.get("content-type", "")
    body_lower = result.body.lower()
    if "text/html" not in content_type.lower() and "<!doctype html" not in body_lower and "<html" not in body_lower:
        raise AssertionError(f"{label}: Antwort sieht nicht wie HTML aus. Content-Type: {content_type}")

    print(f"✅ {label}: HTTP 200 HTML")


def check_build_file(base_url: str, expected_build: str | None) -> None:
    expected = (expected_build or "").strip()
    if not expected:
        return

    label = "Build-Datei"

    def do_check() -> str:
        result = request_url(build_url(base_url, "/meta/build.txt"), timeout=20)
        require_status(result, {200}, label)
        deployed_build = result.body.strip()
        if deployed_build != expected:
            raise AssertionError(f"{label}: erwarteter Build {expected}, deployt ist {deployed_build!r}.")
        return deployed_build

    deployed_build = retry(label, do_check)
    print(f"✅ {label}: {deployed_build}")


def check_status_api(base_url: str) -> None:
    label = "Status-API"
    result = request_with_retries(build_url(base_url, "/api/status.php"))
    require_status(result, {200}, label)
    payload = parse_json(result)

    if payload.get("status") != "ok":
        raise AssertionError(f"{label}: status ist nicht ok: {payload}")

    checks = payload.get("checks")
    if not isinstance(checks, dict) or checks.get("config") is not True or checks.get("database") is not True:
        raise AssertionError(f"{label}: config/database nicht beide ok: {payload}")

    print("✅ Status-API: status=ok, config=ok, database=ok")


def check_public_events_api(base_url: str) -> None:
    label = "Public-Events-API"
    result = request_with_retries(build_url(base_url, "/api/events/public.php"))
    require_status(result, {200}, label)
    payload = parse_json(result)

    data = payload.get("data")
    if payload.get("status") != "ok" or not isinstance(data, dict) or not isinstance(data.get("events"), list):
        raise AssertionError(f"{label}: unerwartete Struktur: {payload}")

    print(f"✅ Public-Events-API: {len(data.get('events', []))} DB-Events")


def check_checkout_validation(base_url: str) -> None:
    label = "Checkout-API Validierung"
    result = request_with_retries(
        build_url(base_url, "/api/stripe/create-checkout-session.php"),
        method="POST",
        body=b"{}",
        headers={"Content-Type": "application/json"},
    )
    require_status(result, {422}, label)
    payload = parse_json(result)

    if payload.get("status") != "error":
        raise AssertionError(f"{label}: erwarteter validierter Fehler, erhalten: {payload}")

    print("✅ Checkout-API Validierung: kontrollierter HTTP 422 statt 500")


def check_protected_json_endpoint(
    base_url: str,
    *,
    path: str,
    label: str,
    method: str = "GET",
    body: bytes | None = None,
    expected_status: int = 401,
) -> None:
    headers = {"Content-Type": "application/json"} if body is not None else None
    result = request_with_retries(
        build_url(base_url, path),
        method=method,
        body=body,
        headers=headers,
    )
    require_status(result, {expected_status}, label)
    payload = parse_json(result)

    if payload.get("status") != "error":
        raise AssertionError(f"{label}: unerwartete JSON-Struktur: {payload}")

    print(f"✅ {label}: ohne Berechtigung nicht öffentlich erreichbar")


def check_review_endpoint_protected(base_url: str) -> None:
    check_protected_json_endpoint(
        base_url,
        path="/api/submissions/review-list.php",
        label="Review-API Zugriffsschutz",
    )


def check_push_endpoints_protected(base_url: str) -> None:
    check_protected_json_endpoint(
        base_url,
        path="/api/push/config.php",
        label="Push-Config Zugriffsschutz",
    )
    check_protected_json_endpoint(
        base_url,
        path="/api/push/subscribe.php",
        label="Push-Subscribe Zugriffsschutz",
        method="POST",
        body=b"{}",
    )
    check_protected_json_endpoint(
        base_url,
        path="/api/push/test.php",
        label="Push-Test Zugriffsschutz",
        method="POST",
        body=b"{}",
    )
    check_protected_json_endpoint(
        base_url,
        path="/api/push/notify-inbox.php",
        label="Push-Notify Zugriffsschutz",
        method="POST",
        body=b"{}",
    )


def run(args: argparse.Namespace) -> None:
    base_url = normalize_base_url(args.base_url)

    checks = [
        lambda: check_build_file(base_url, args.expected_build),
        lambda: check_html_page(base_url, "/", "Startseite"),
        lambda: check_html_page(base_url, "/events/", "Events-Suchseite"),
        lambda: check_html_page(base_url, "/aktivitaeten/", "Aktivitäten-Seite"),
        lambda: check_html_page(base_url, "/bildnachweise/", "Bildnachweise-Seite"),
        lambda: check_html_page(base_url, "/events-veroeffentlichen/einreichen/", "Event-Einreichen-Seite"),
        lambda: check_status_api(base_url),
        lambda: check_public_events_api(base_url),
        lambda: check_checkout_validation(base_url),
        lambda: check_review_endpoint_protected(base_url),
        lambda: check_push_endpoints_protected(base_url),
    ]

    print(f"=== Deploy-Smoke-Check: {base_url} ===")

    for check in checks:
        check()

    print("✅ Deploy-Smoke-Check abgeschlossen.")


def main() -> None:
    parser = argparse.ArgumentParser(description="Prueft zentrale Bocholt-Erleben-Endpunkte nach einem STRATO-Deploy.")
    parser.add_argument("--base-url", required=True, help="Deploy-Basis-URL, z. B. https://staging.bocholt-erleben.de")
    parser.add_argument("--expected-build", default="", help="Optional: erwarteter Inhalt von /meta/build.txt")
    args = parser.parse_args()

    try:
        run(args)
    except Exception as error:  # noqa: BLE001 - Klare Fail-Fast-Ausgabe fuer GitHub Actions.
        fail(str(error))


if __name__ == "__main__":
    main()
# === END FILE: tools/smoke-check-deploy.py ===
