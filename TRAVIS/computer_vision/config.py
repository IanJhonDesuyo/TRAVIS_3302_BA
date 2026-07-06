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
# Direction Counting Lines
# Extended versions of your original lines
# ==========================================

# INBOUND
# Original:
# (390,300) -> (495,290)

INBOUND_LINE = (
    (330, 305),
    (560, 285)
)

# OUTBOUND
# Original:
# (500,700) -> (660,650)

OUTBOUND_LINE = (
    (420, 735),
    (760, 630)
) 
