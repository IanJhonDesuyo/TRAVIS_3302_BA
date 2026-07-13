"""
TRAVIS Configuration File
Centralized settings for the whole AI engine.
"""

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

# Future Tapo Camera RTSP
TAPO_RTSP = ""

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
