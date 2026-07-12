"""
TRAVIS API Client
Handles requests from the AI engine to the PHP monitoring APIs.
"""

import requests


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
