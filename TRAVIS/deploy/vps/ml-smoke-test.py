"""Smoke-test the internal TRAVIS ML API from inside its Docker network."""

import json
import urllib.request


BASE_URL = "http://127.0.0.1:5001"


def get_json(path: str) -> dict:
    with urllib.request.urlopen(BASE_URL + path, timeout=10) as response:
        return json.load(response)


health = get_json("/health")
print("health:", health.get("success"), health.get("status"))

request = urllib.request.Request(
    BASE_URL + "/predict/monthly",
    data=json.dumps({"year": 2026, "month": 11}).encode("utf-8"),
    headers={"Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(request, timeout=10) as response:
    prediction = json.load(response)
prediction_data = prediction.get("data", {})
print(
    "prediction:",
    prediction.get("success"),
    prediction_data.get("model"),
    prediction_data.get("risk_level"),
)

hotspots = get_json("/hotspots")
hotspot_data = hotspots.get("data", {})
print(
    "hotspots:",
    hotspots.get("success"),
    hotspot_data.get("algorithm"),
    hotspot_data.get("count"),
)

if not health.get("success") or not prediction.get("success") or not hotspots.get("success"):
    raise SystemExit(1)
