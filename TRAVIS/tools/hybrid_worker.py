"""TRAVIS laptop worker for hosted ML and computer-vision commands."""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import logging
import socket
import subprocess
import sys
import time
from pathlib import Path
from typing import Any

import requests


PROJECT_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_BASE_URL = "https://travis-nasugbu.site/TRAVIS"
DEFAULT_KEY_FILE = PROJECT_ROOT / "storage" / "service_api.key"
LOCAL_WEB_BASE = "http://127.0.0.1/TRAVIS/Web_app/api"
ML_BASE = "http://127.0.0.1:5001"
LOG_PATH = PROJECT_ROOT / "storage" / "hybrid-worker.log"

logger = logging.getLogger("travis_hybrid_worker")
logger.setLevel(logging.INFO)
LOG_PATH.parent.mkdir(parents=True, exist_ok=True)
_file_handler = logging.FileHandler(LOG_PATH, encoding="utf-8")
_file_handler.setFormatter(logging.Formatter("%(asctime)s | %(levelname)s | %(message)s"))
logger.addHandler(_file_handler)
if sys.stdout is not None:
    _stream_handler = logging.StreamHandler(sys.stdout)
    _stream_handler.setFormatter(logging.Formatter("%(message)s"))
    logger.addHandler(_stream_handler)


class WorkerClient:
    def __init__(self, base_url: str, secret: str, worker_id: str) -> None:
        self.endpoint = base_url.rstrip("/") + "/api/worker_jobs.php"
        self.secret = secret.encode("utf-8")
        self.worker_id = worker_id
        self.session = requests.Session()

    def post(self, payload: dict[str, Any], timeout: int = 20) -> dict[str, Any]:
        raw = json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
        timestamp = str(int(time.time()))
        signature = hmac.new(self.secret, timestamp.encode() + b"." + raw, hashlib.sha256).hexdigest()
        response = self.session.post(
            self.endpoint,
            data=raw,
            headers={
                "Content-Type": "application/json",
                "X-TRAVIS-Timestamp": timestamp,
                "X-TRAVIS-Signature": signature,
            },
            timeout=timeout,
        )
        response.raise_for_status()
        result = response.json()
        if not result.get("success"):
            raise RuntimeError(result.get("message", "Worker API request failed."))
        return result

    def download_latest_video(self, destination: Path) -> None:
        endpoint = self.endpoint.rsplit("/", 1)[0] + "/worker_video.php"
        raw = b'{"action":"download_latest"}'
        timestamp = str(int(time.time()))
        signature = hmac.new(self.secret, timestamp.encode() + b"." + raw, hashlib.sha256).hexdigest()
        destination.parent.mkdir(parents=True, exist_ok=True)
        temporary = destination.with_suffix(destination.suffix + ".part")
        with self.session.post(
            endpoint,
            data=raw,
            headers={"Content-Type": "application/json", "X-TRAVIS-Timestamp": timestamp, "X-TRAVIS-Signature": signature},
            timeout=180,
            stream=True,
        ) as response:
            if not response.ok:
                try:
                    detail = response.json().get("message", response.text)
                except ValueError:
                    detail = response.text
                raise RuntimeError(f"Hosted video download failed ({response.status_code}): {detail or response.reason}")
            with temporary.open("wb") as output:
                for chunk in response.iter_content(1024 * 1024):
                    if chunk:
                        output.write(chunk)
        temporary.replace(destination)


def ml_ready() -> bool:
    try:
        return requests.get(f"{ML_BASE}/health", timeout=2).status_code == 200
    except requests.RequestException:
        return False


def ensure_ml_service() -> None:
    if ml_ready():
        return
    python = PROJECT_ROOT / ".venv" / "Scripts" / "python.exe"
    script = PROJECT_ROOT / "Machine_Learning" / "api.py"
    if not python.is_file() or not script.is_file():
        raise RuntimeError("Local ML Python runtime or Machine_Learning/api.py is missing.")
    subprocess.Popen(
        [str(python), str(script)],
        cwd=str(PROJECT_ROOT),
        creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    deadline = time.time() + 30
    while time.time() < deadline:
        if ml_ready():
            return
        time.sleep(1)
    raise RuntimeError("Local machine-learning API did not become ready.")


def local_web_ready() -> bool:
    try:
        with socket.create_connection(("127.0.0.1", 80), timeout=1):
            return True
    except OSError:
        return False


def ensure_local_web_service() -> None:
    """Start the bundled XAMPP Apache server when a CV command needs it."""
    if local_web_ready():
        return

    xampp_root = PROJECT_ROOT.parents[1]
    apache = xampp_root / "apache" / "bin" / "httpd.exe"
    if not apache.is_file():
        raise RuntimeError(
            "Local Apache is offline and its executable was not found. "
            "Start Apache in the XAMPP Control Panel."
        )

    logger.info("Local Apache is offline; starting %s", apache)
    subprocess.Popen(
        [str(apache)],
        cwd=str(apache.parent),
        creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    deadline = time.time() + 20
    while time.time() < deadline:
        if local_web_ready():
            logger.info("Local Apache is ready.")
            return
        time.sleep(0.5)

    raise RuntimeError(
        "Local Apache could not start. Open the XAMPP Control Panel and check "
        "whether port 80 is being used by another application."
    )


def json_request(method: str, url: str, **kwargs: Any) -> dict[str, Any]:
    response = requests.request(method, url, timeout=35, **kwargs)
    try:
        payload = response.json()
    except ValueError as error:
        raise RuntimeError(f"Local service returned invalid JSON ({response.status_code}).") from error
    if not response.ok or payload.get("success") is False:
        raise RuntimeError(payload.get("message") or f"Local service failed ({response.status_code}).")
    return payload


def execute_job(client: WorkerClient, job_type: str, payload: dict[str, Any]) -> dict[str, Any]:
    if job_type == "ml_monthly":
        ensure_ml_service()
        return json_request("POST", f"{ML_BASE}/predict/monthly", json=payload)
    if job_type == "ml_hotspots":
        ensure_ml_service()
        risk = str(payload.get("risk", "")).strip()
        suffix = "/" + requests.utils.quote(risk, safe="") if risk else ""
        return json_request("GET", f"{ML_BASE}/hotspots{suffix}")
    if job_type == "cv_start":
        ensure_local_web_service()
        if str(payload.get("source_type", "uploaded_video")) == "uploaded_video":
            client.download_latest_video(PROJECT_ROOT / "computer_vision" / "uploads" / "videos" / "test.mp4")
        return json_request("POST", f"{LOCAL_WEB_BASE}/start_analysis.php", json=payload)
    if job_type == "cv_stop":
        ensure_local_web_service()
        return json_request("POST", f"{LOCAL_WEB_BASE}/stop_analysis.php", json=payload)
    raise RuntimeError(f"Unsupported job type: {job_type}")


def run(client: WorkerClient, interval: float, supported: list[str]) -> None:
    logger.info("TRAVIS worker online: %s", client.worker_id)
    logger.info("Polling %s", client.endpoint)
    connection_failures = 0
    while True:
        try:
            response = client.post({
                "action": "claim",
                "worker_id": client.worker_id,
                "supported_types": supported,
            })
            job = response.get("job")
            connection_failures = 0
            if not job:
                time.sleep(interval)
                continue
            job_id = int(job["job_id"])
            job_type = str(job["job_type"])
            logger.info("Claimed job %s: %s", job_id, job_type)
            try:
                result = execute_job(client, job_type, job.get("payload") or {})
                client.post({"action": "complete", "worker_id": client.worker_id, "job_id": job_id, "result": result})
                logger.info("Completed job %s", job_id)
            except Exception as error:
                client.post({"action": "fail", "worker_id": client.worker_id, "job_id": job_id, "error": str(error)})
                logger.error("Failed job %s: %s", job_id, error)
        except KeyboardInterrupt:
            logger.info("Worker stopped.")
            return
        except Exception as error:
            logger.error("Worker connection error: %s", error)
            connection_failures += 1
            # Shared hosting may rate-limit frequent empty queue polls. Back
            # off progressively so the worker recovers instead of sustaining
            # a 429 loop with multiple scheduled workers.
            time.sleep(min(60.0, max(interval, 3.0) * (2 ** min(connection_failures - 1, 4))))


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL)
    parser.add_argument("--key-file", type=Path, default=DEFAULT_KEY_FILE)
    parser.add_argument("--worker-id", default=f"{socket.gethostname()}-edge")
    parser.add_argument("--interval", type=float, default=5.0)
    parser.add_argument(
        "--types",
        default="ml_monthly,ml_hotspots,cv_start,cv_stop",
        help="Comma-separated job types handled by this worker.",
    )
    args = parser.parse_args()
    secret = args.key_file.read_text(encoding="utf-8").strip()
    if len(secret) < 32:
        raise SystemExit("The service API key must contain at least 32 characters.")
    allowed = {"ml_monthly", "ml_hotspots", "cv_start", "cv_stop"}
    supported = [item.strip() for item in args.types.split(",") if item.strip() in allowed]
    if not supported:
        raise SystemExit("At least one valid worker job type is required.")
    run(WorkerClient(args.base_url, secret, args.worker_id), max(0.5, args.interval), supported)


if __name__ == "__main__":
    main()
