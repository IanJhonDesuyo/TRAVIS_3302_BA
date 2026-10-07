"""
TRAVIS API Client
Handles requests from the AI engine to the PHP monitoring APIs.
"""

import copy
import requests
import threading
import time


_pending_updates = {}
_pending_condition = threading.Condition()
_worker_started = False


def _update_worker():
    """Send only the newest queued payload without blocking video inference."""
    session = requests.Session()
    while True:
        with _pending_condition:
            _pending_condition.wait_for(lambda: bool(_pending_updates))
            _, (api_url, payload) = _pending_updates.popitem()
        # Shared hosting can briefly answer 429 while the dashboard and edge
        # workers are polling. Retry the newest heartbeat so a transient rate
        # limit cannot make a healthy live camera look stopped to the browser.
        for attempt in range(4):
            try:
                response = session.post(api_url, json=payload, timeout=3)
                response.raise_for_status()
                break
            except requests.RequestException:
                if attempt < 3:
                    time.sleep(1.5 * (attempt + 1))


def _queue_update(kind, api_url, payload):
    global _worker_started
    with _pending_condition:
        _pending_updates[kind] = (api_url, copy.deepcopy(payload))
        if not _worker_started:
            threading.Thread(target=_update_worker, name="travis-api-updates", daemon=True).start()
            _worker_started = True
        _pending_condition.notify()


def get_cv_settings(api_url):
    try:
        response = requests.get(api_url, timeout=2)
        response.raise_for_status()
        payload = response.json()
        return payload.get("data", {}) if payload.get("success") else {}
    except Exception:
        return {}


def send_status_update(api_url, payload):
    _queue_update("status", api_url, payload)


def send_status_update_now(api_url, payload):
    """Send a terminal status before the short-lived worker exits."""
    for attempt in range(4):
        try:
            response = requests.post(api_url, json=payload, timeout=4)
            response.raise_for_status()
            return True
        except requests.RequestException:
            if attempt < 3:
                time.sleep(1.5 * (attempt + 1))
    return False


def send_monitoring_log(api_url, payload):
    _queue_update("monitoring-log", api_url, payload)
