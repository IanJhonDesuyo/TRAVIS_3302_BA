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

# ==========================================
# YOLO
# ==========================================
MODEL_PATH = "models/yolov8n.pt"
CONFIDENCE_THRESHOLD = 0.50

# ==========================================
# Video Source
# video
# webcam
# tapo
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

# Live-camera latency controls. Intermediate RTSP frames are discarded so
# detection stays close to real time even when inference is slower than FPS.
TAPO_FRAMES_TO_GRAB = 2
LIVE_INFERENCE_SIZE = 320

# ==========================================
# Output
# ==========================================
OUTPUT_FOLDER = "results"
OUTPUT_VIDEO = "processed_video.mp4"

# ==========================================
# Monitoring API
# ==========================================
CAMERA_ID = 1
STATUS_API_URL = "http://localhost/TRAVIS/Web_app/api/update_status.php"
MONITORING_LOG_API_URL = "http://localhost/TRAVIS/Web_app/api/save_monitoring_log.php"
CV_SETTINGS_API_URL = "http://localhost/TRAVIS/Web_app/api/get_cv_settings.php"

# ==========================================
# Detection Classes
# ==========================================
PERSON_CLASS = 0

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
