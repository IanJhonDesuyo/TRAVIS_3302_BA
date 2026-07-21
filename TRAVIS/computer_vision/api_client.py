"""
TRAVIS API Client
Handles requests from the AI engine to the PHP monitoring APIs.
"""

import requests


def get_cv_settings(api_url):
    try:
        response = requests.get(api_url, timeout=2)
        response.raise_for_status()
        payload = response.json()
        return payload.get("data", {}) if payload.get("success") else {}
    except Exception:
        return {}


def send_status_update(api_url, payload):
    try:
        requests.post(api_url, json=payload, timeout=1)
    except Exception:
        pass


def send_monitoring_log(api_url, payload):
    try:
        requests.post(api_url, json=payload, timeout=1)
    except Exception:
        pass
