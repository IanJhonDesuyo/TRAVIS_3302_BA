"""
TRAVIS Configuration File
Centralized settings for the whole AI engine.
"""

import json
from pathlib import Path
from urllib.parse import quote


def _load_camera_config():
    path = Path(__file__).resolve().parent / "camera_config.json"
    if not path.exists():
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


_camera_config = _load_camera_config()


def _load_phone_camera_config():
    path = Path(__file__).resolve().parent / "phone_camera_config.json"
    if not path.exists():
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


_phone_camera_config = _load_phone_camera_config()


def _load_edge_config():
    path = Path(__file__).resolve().parent / "edge_config.json"
    if not path.exists():
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


_edge_config = _load_edge_config()

# ==========================================
# YOLO
# ==========================================
MODEL_PATH = "models/yolov8n.pt"
CONFIDENCE_THRESHOLD = 0.50
# Vehicles benefit from a slightly lower threshold than people because small
# and distant road users otherwise appear several frames late. The tracker
# provides the temporal stability needed to keep this conservative.
VEHICLE_CONFIDENCE_THRESHOLD = 0.40

# ==========================================
# Video Source
# video
# webcam
# tapo
# phone
# ==========================================
VIDEO_SOURCE = "video"

# Uploaded Video
VIDEO_PATH = "uploads/videos/test.mp4"

# Laptop Webcam
CAMERA_INDEX = 0

# Tapo camera credentials are written by the local web app to the ignored
# camera_config.json file. URL encoding keeps special characters valid in RTSP.
TAPO_HOST = str(_camera_config.get("host", "")).strip()
TAPO_USERNAME = str(_camera_config.get("username", "")).strip()
TAPO_PASSWORD = str(_camera_config.get("password", ""))
TAPO_STREAM = str(_camera_config.get("stream", "stream2"))
TAPO_RTSP = (
    f"rtsp://{quote(TAPO_USERNAME, safe='')}:{quote(TAPO_PASSWORD, safe='')}"
    f"@{TAPO_HOST}:554/{TAPO_STREAM}"
    if TAPO_HOST and TAPO_USERNAME and TAPO_PASSWORD
    else ""
)

# Generic cellphone camera stream. The web app accepts RTSP/RTSPS or an
# HTTP/HTTPS MJPEG feed on the local network and stores it outside version control.
PHONE_STREAM_URL = str(_phone_camera_config.get("stream_url", "")).strip()

# Live-camera latency control. Tapo capture drops buffered frames between
# inference passes, while HTTP/MJPEG phone sources expose their newest frame.
LIVE_INFERENCE_SIZE = 320
# 640 keeps CPU-only processing close to real time while retaining materially
# more detail than the live-camera preset. At 960, the reference workstation
# processes only about half of a 30 FPS video's frames per second.
# Balanced CPU preset: 448 is still large enough for small road users while
# requiring materially less work than 480/640 on every processed frame.
UPLOADED_INFERENCE_SIZE = 448

# ==========================================
# Output
# ==========================================
OUTPUT_FOLDER = "results"
OUTPUT_VIDEO = "processed_video.mp4"
SAVE_PROCESSED_VIDEO = False
SHOW_DEBUG_WINDOW = False

# ==========================================
# Monitoring API
# ==========================================
CAMERA_ID = 1
_web_base_url = str(_edge_config.get("web_base_url", "http://localhost/TRAVIS")).rstrip("/")
STATUS_API_URL = f"{_web_base_url}/Web_app/api/update_status.php"
MONITORING_LOG_API_URL = f"{_web_base_url}/Web_app/api/save_monitoring_log.php"
CV_SETTINGS_API_URL = f"{_web_base_url}/Web_app/api/get_cv_settings.php"

# ==========================================
# Detection Classes
# ==========================================
PERSON_CLASS = 0
MOTORCYCLE_CLASS = 3
# Small motorcycles often score lower than their rider/person box. Keep the
# final motorcycle threshold below the general object threshold, then rely on
# stable ByteTrack IDs and the conservative collision confirmation rules.
MOTORCYCLE_CONFIDENCE_THRESHOLD = 0.25

# Project-owned tracker settings tuned for traffic footage. Keeping this file
# outside site-packages makes deployments reproducible after package updates.
TRACKER_CONFIG = "trackers/travis_bytetrack.yaml"

VEHICLE_CLASSES = [
    2,  # car
    3,  # motorcycle
    5,  # bus
    7   # truck
]

ALLOWED_CLASSES = [PERSON_CLASS] + VEHICLE_CLASSES

# ==========================================
# Direction Lines - Normalized Coordinates
# Works with any video resolution
# Values are based on 1280x720 reference
# ==========================================

INBOUND_LINE_NORMALIZED = (
    (330 / 1280, 305 / 720),
    (560 / 1280, 285 / 720)
)

OUTBOUND_LINE_NORMALIZED = (
    (420 / 1280, 735 / 720),
    (760 / 1280, 630 / 720)
)

# ==========================================
# Optional modular collision detection
# Remains off unless this flag or a selected
# calibration profile explicitly enables it.
# ==========================================
ENABLE_COLLISION_DETECTION = False
CALIBRATION_PROFILE = "calibration_profiles/example.json"
ENABLE_OFFICER_DETECTION = True
