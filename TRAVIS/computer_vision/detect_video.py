from ultralytics import YOLO
import cv2
import os
import time
import config
from api_client import send_monitoring_log, send_status_update
from utils import crossed_line
from camera_source import CameraSource
from stream_server import start_stream, update_frame
from congestion import get_congestion_level
from alert_engine import AlertEngine


# ============================
# Load YOLO Model
# ============================
model = YOLO(config.MODEL_PATH)

# ============================
# Video Paths
# ============================


OUTPUT_FOLDER = config.OUTPUT_FOLDER
os.makedirs(OUTPUT_FOLDER, exist_ok=True)

OUTPUT_VIDEO = os.path.join(
    OUTPUT_FOLDER,
    config.OUTPUT_VIDEO
)

# Live Monitoring API

last_api_update = 0
last_log_save = 0
last_logged_congestion_level = None
last_logged_alert_status = None


# ============================
# Debug
# ============================
print("Current directory:", os.getcwd())
print("Video Source:", config.VIDEO_SOURCE)
if config.VIDEO_SOURCE == "video":
    print("Video path:", os.path.abspath(config.VIDEO_PATH))
    print("Video exists:", os.path.exists(config.VIDEO_PATH))

# ============================
# Open Video
# ============================




start_stream()

camera = CameraSource()

alert_engine = AlertEngine()

cap = camera.open()

if not cap.isOpened():
    print("Cannot open video.")
    exit()

width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
fps = cap.get(cv2.CAP_PROP_FPS)

if fps == 0:
    fps = 30

fourcc = cv2.VideoWriter_fourcc(*"mp4v")
out = cv2.VideoWriter(OUTPUT_VIDEO, fourcc, fps, (width, height))

# ============================
# Detection Classes
# 0 = person
# 2 = car
# 3 = motorcycle
# 5 = bus
# 7 = truck
# ============================


# ============================
# Direction Counting Lines
# Adjust these coordinates based on your video
# Green = Inbound
# Red = Outbound
# ============================
inbound_count = 0
outbound_count = 0

counted_inbound = set()
counted_outbound = set()

track_history = {}

# ============================
# Main Loop
# ============================
while True:
    ret, frame = cap.read()

    if not ret:
        break

    visible_vehicle_count = 0
    visible_person_count = 0

    annotated_frame = frame.copy()

    # Draw inbound line
    cv2.line(
        annotated_frame,
        config.INBOUND_LINE[0],
        config.INBOUND_LINE[1],
        (0, 255, 0),
        3
    )

    cv2.putText(
        annotated_frame,
        "INBOUND",
        (config.INBOUND_LINE[0][0], config.INBOUND_LINE[0][1] - 10),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 255, 0),
        2
    )

    # Draw outbound line
    cv2.line(
        annotated_frame,
        config.OUTBOUND_LINE[0],
        config.OUTBOUND_LINE[1],
        (0, 0, 255),
        3
    )

    cv2.putText(
        annotated_frame,
        "OUTBOUND",
        (config.OUTBOUND_LINE[0][0], config.OUTBOUND_LINE[0][1] - 10),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 0, 255),
        2
    )

    # YOLO + ByteTrack
    results = model.track(
        frame,
        persist=True,
        tracker="bytetrack.yaml",
        verbose=False
    )

    for result in results:
        boxes = result.boxes

        for box in boxes:
            cls = int(box.cls[0])
            conf = float(box.conf[0])

            if conf < config.CONFIDENCE_THRESHOLD:
                continue

            if cls not in config.ALLOWED_CLASSES:
                continue

            track_id = -1
            if box.id is not None:
                track_id = int(box.id.item())

            x1, y1, x2, y2 = map(int, box.xyxy[0])

            center_x = (x1 + x2) // 2
            center_y = (y1 + y2) // 2
            current_point = (center_x, center_y)

            if cls == config.PERSON_CLASS:
                visible_person_count += 1
                label = f"Person #{track_id}"
                box_color = (255, 255, 0)

            else:
                visible_vehicle_count += 1
                label = f"Vehicle #{track_id}"
                box_color = (0, 255, 0)

                if track_id != -1:
                    if track_id not in track_history:
                        track_history[track_id] = []

                    track_history[track_id].append(current_point)

                    if len(track_history[track_id]) > 10:
                        track_history[track_id].pop(0)

                    if len(track_history[track_id]) >= 2:
                        previous_point = track_history[track_id][-2]

                        # Inbound count
                        if crossed_line(previous_point, current_point, config.INBOUND_LINE):
                            if track_id not in counted_inbound:
                                counted_inbound.add(track_id)
                                inbound_count += 1

                        # Outbound count
                        if crossed_line(previous_point, current_point, config.OUTBOUND_LINE):
                            if track_id not in counted_outbound:
                                counted_outbound.add(track_id)
                                outbound_count += 1

            # Draw bounding box
            cv2.rectangle(
                annotated_frame,
                (x1, y1),
                (x2, y2),
                box_color,
                2
            )

            # Draw label
            cv2.putText(
                annotated_frame,
                label,
                (x1, y1 - 10),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.6,
                box_color,
                2
            )

            # Draw center point
            cv2.circle(
                annotated_frame,
                current_point,
                4,
                (0, 0, 255),
                -1
            )

    congestion_level = get_congestion_level(visible_vehicle_count)

    alert_status = alert_engine.update(congestion_level)

    # ============================
    # Dashboard Overlay
    # ============================
    cv2.rectangle(
        annotated_frame,
        (10, 10),
        (410, 280),
        (0, 0, 0),
        -1
    )

    cv2.putText(
        annotated_frame,
        "TRAVIS AI MONITOR",
        (20, 35),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 255, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Visible Vehicles : {visible_vehicle_count}",
        (20, 70),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (0, 255, 0),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Inbound          : {inbound_count}",
        (20, 105),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (0, 255, 0),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Outbound         : {outbound_count}",
        (20, 140),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (0, 0, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Visible Persons  : {visible_person_count}",
        (20, 175),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 255, 0),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Congestion : {congestion_level}",
        (20, 205),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 255, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Alert Status : {alert_status}",
        (20, 235),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (0, 165, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        "Tracking : ByteTrack",
        (20, 265),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 255, 255),
        2
    )

    # Send status to PHP every second
    if time.time() - last_api_update >= 1:
        payload = {
            "vehicle_count": visible_vehicle_count,
            "inbound_count": inbound_count,
            "outbound_count": outbound_count,
            "officer_presence": "Unknown",
            "congestion_level": congestion_level,
            "alert_status": alert_status,
            "potential_collision": "None",
            "ai_status": "Running"
        }

        send_status_update(config.STATUS_API_URL, payload)
        last_api_update = time.time()

    current_time = time.time()
    log_due = current_time - last_log_save >= 30
    congestion_changed = congestion_level != last_logged_congestion_level
    alert_changed = alert_status != last_logged_alert_status

    if log_due or congestion_changed or alert_changed:
        log_payload = {
            "camera_id": config.CAMERA_ID,
            "vehicle_count": visible_vehicle_count,
            "inbound_count": inbound_count,
            "outbound_count": outbound_count,
            "congestion_level": congestion_level,
            "officer_presence": "Unknown",
            "potential_collision": "None",
            "alert_generated": 1 if alert_status == "ALERT" else 0,
            "incident_notes": None
        }

        send_monitoring_log(config.MONITORING_LOG_API_URL, log_payload)

        last_log_save = current_time
        last_logged_congestion_level = congestion_level
        last_logged_alert_status = alert_status

    update_frame(annotated_frame)

    out.write(annotated_frame)

    cv2.imshow("TRAVIS AI Direction-Based Counting", annotated_frame)

    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

cap.release()
out.release()
cv2.destroyAllWindows()

print("--------------------------------")
print("Processing Finished")
print("Saved to:", OUTPUT_VIDEO)
print("Inbound:", inbound_count)
print("Outbound:", outbound_count)
print("--------------------------------")
