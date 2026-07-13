from ultralytics import YOLO
import argparse
import cv2
import os
import time
import config
from api_client import send_monitoring_log, send_status_update
from camera_source import CameraSource
from stream_server import start_stream, update_frame
from congestion import get_congestion_level
from alert_engine import AlertEngine
from calibration import load_calibration
from collision_detection import CollisionDetector
from direction_counter import DirectionCounter
from officer_detection import OfficerPresenceDetector


def apply_selected_source():
    parser = argparse.ArgumentParser(description="TRAVIS AI video detection engine")
    parser.add_argument("--source-type", choices=["uploaded_video", "tapo_camera"], default=None)
    parser.add_argument("--source", default=None)
    parser.add_argument("--calibration-profile", default=None)
    parser.add_argument("--enable-collision", action="store_true")
    parser.add_argument("--enable-officer-detection", action="store_true")
    args = parser.parse_args()

    if args.source_type == "uploaded_video":
        config.VIDEO_SOURCE = "video"
        if args.source:
            config.VIDEO_PATH = args.source
    elif args.source_type == "tapo_camera":
        config.VIDEO_SOURCE = "tapo"
        if args.source:
            config.TAPO_RTSP = args.source

    return args


selected_source = apply_selected_source()

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
last_logged_collision_status = None
last_logged_officer_status = None


# ============================
# Debug
# ============================
print("Current directory:", os.getcwd())
print("Video Source:", config.VIDEO_SOURCE)
if config.VIDEO_SOURCE == "video":
    print("Video path:", os.path.abspath(config.VIDEO_PATH))
    print("Video exists:", os.path.exists(config.VIDEO_PATH))
elif config.VIDEO_SOURCE == "tapo":
    print("RTSP source configured:", bool(config.TAPO_RTSP))

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
print(f"Capture Resolution: {width} x {height}")
fps = cap.get(cv2.CAP_PROP_FPS)
total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT)) if config.VIDEO_SOURCE == "video" else 0
current_frame = 0
source_started_at = time.time()

calibration = load_calibration(
    width=width,
    height=height,
    legacy_inbound_line=config.INBOUND_LINE_NORMALIZED,
    legacy_outbound_line=config.OUTBOUND_LINE_NORMALIZED,
    profile_path=selected_source.calibration_profile or config.CALIBRATION_PROFILE,
)
INBOUND_LINE = calibration.inbound_line
OUTBOUND_LINE = calibration.outbound_line

direction_counter = DirectionCounter(INBOUND_LINE, OUTBOUND_LINE)
collision_enabled = bool(
    selected_source.enable_collision
    or config.ENABLE_COLLISION_DETECTION
    or calibration.collision_enabled
)
collision_detector = CollisionDetector(
    frame_size=(width, height),
    settings=calibration.collision,
    enabled=collision_enabled,
)
officer_detection_enabled = bool(
    calibration.officer_zone
    and (
        selected_source.enable_officer_detection
        or config.ENABLE_OFFICER_DETECTION
        or calibration.officer_enabled
    )
)
officer_detector = OfficerPresenceDetector(
    enabled=officer_detection_enabled,
    settings=calibration.officer,
)

FONT_SCALE = max(0.45, width / 1800)
TITLE_SCALE = max(0.55, width / 1600)
LINE_THICKNESS = max(2, width // 500)
BOX_THICKNESS = max(2, width // 600)
POINT_RADIUS = max(3, width // 350)

DASHBOARD_X = int(width * 0.01)
DASHBOARD_Y = int(height * 0.02)
DASHBOARD_W = int(width * 0.34)
DASHBOARD_H = int(height * 0.50)

print("Inbound Line:", INBOUND_LINE)
print("Outbound Line:", OUTBOUND_LINE)
print("Calibration Profile:", calibration.name)
print("Collision Detection:", "Enabled" if collision_enabled else "Disabled")
print("Officer Presence Detection:", "Enabled" if officer_detection_enabled else "Disabled")

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
# Main Loop
# ============================
while True:
    ret, frame = cap.read()

    if not ret:
        break

    current_frame += 1
   

    visible_vehicle_count = 0
    visible_person_count = 0
    vehicle_tracks = []
    persons_in_officer_zone = []

    annotated_frame = frame.copy()

    # Draw inbound line
    cv2.line(
        annotated_frame,
        INBOUND_LINE[0],
        INBOUND_LINE[1],
        (0, 255, 0),
        3
    )

    cv2.putText(
        annotated_frame,
        "INBOUND",
        (INBOUND_LINE[0][0], INBOUND_LINE[0][1] - 10),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 255, 0),
        2
    )

    # Draw outbound line
    cv2.line(
        annotated_frame,
        OUTBOUND_LINE[0],
        OUTBOUND_LINE[1],
        (0, 0, 255),
        3
    )

    cv2.putText(
        annotated_frame,
        "OUTBOUND",
        (OUTBOUND_LINE[0][0], OUTBOUND_LINE[0][1] - 10),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 0, 255),
        2
    )

    if officer_detection_enabled and calibration.officer_zone:
        for index, start_point in enumerate(calibration.officer_zone):
            end_point = calibration.officer_zone[(index + 1) % len(calibration.officer_zone)]
            cv2.line(annotated_frame, start_point, end_point, (255, 165, 0), 2)
        zone_label_point = calibration.officer_zone[0]
        cv2.putText(
            annotated_frame,
            "OFFICER ZONE",
            (zone_label_point[0], max(20, zone_label_point[1] - 8)),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.55,
            (255, 165, 0),
            2,
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
                person_anchor = (center_x, y2)
                in_officer_zone = calibration.contains_officer_point(person_anchor)
                if in_officer_zone:
                    persons_in_officer_zone.append({
                        "track_id": track_id,
                        "confidence": conf,
                        "bbox": (x1, y1, x2, y2),
                        "anchor": person_anchor,
                    })
                label = f"Zone Person #{track_id}" if in_officer_zone else f"Person #{track_id}"
                box_color = (255, 165, 0) if in_officer_zone else (255, 255, 0)

            else:
                visible_vehicle_count += 1
                label = f"Vehicle #{track_id}"
                box_color = (0, 255, 0)
                direction_counter.update(track_id, current_point)
                vehicle_tracks.append({
                    "track_id": track_id,
                    "class_id": cls,
                    "confidence": conf,
                    "bbox": (x1, y1, x2, y2),
                    "center": current_point,
                    "inside_road_roi": calibration.contains_road_point(current_point),
                })

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

    inbound_count, outbound_count = direction_counter.snapshot()
    collision_result = collision_detector.update(vehicle_tracks, time.monotonic())
    potential_collision = collision_result.status
    officer_result = officer_detector.update(persons_in_officer_zone)
    officer_presence = officer_result.status

    congestion_level = get_congestion_level(visible_vehicle_count)

    alert_status = alert_engine.update(congestion_level)

    # ============================
    # Dashboard Overlay
    # ============================
    cv2.rectangle(
    annotated_frame,
    (DASHBOARD_X, DASHBOARD_Y),
    (DASHBOARD_X + DASHBOARD_W, DASHBOARD_Y + DASHBOARD_H),
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
        f"Collision : {potential_collision.upper()}",
        (20, 265),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 255, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        "Tracking : ByteTrack",
        (20, 295),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 255, 255),
        2
    )

    cv2.putText(
        annotated_frame,
        f"Officer Zone : {officer_presence.upper()}",
        (20, 325),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (255, 165, 0),
        2
    )

    # Send status to PHP every second
    if time.time() - last_api_update >= 1:
        progress_percent = 0
        if total_frames > 0:
            progress_percent = min(100, round((current_frame / total_frames) * 100, 2))

        payload = {
            "vehicle_count": visible_vehicle_count,
            "inbound_count": inbound_count,
            "outbound_count": outbound_count,
            "officer_presence": officer_presence,
            "congestion_level": congestion_level,
            "alert_status": alert_status,
            "potential_collision": potential_collision,
            "ai_status": "Running",
            "source_type": selected_source.source_type or ("uploaded_video" if config.VIDEO_SOURCE == "video" else config.VIDEO_SOURCE),
            "current_frame": current_frame,
            "total_frames": total_frames,
            "progress_percent": progress_percent,
            "running_time_seconds": int(time.time() - source_started_at)
        }

        send_status_update(config.STATUS_API_URL, payload)
        last_api_update = time.time()

    current_time = time.time()
    log_due = current_time - last_log_save >= 30
    congestion_changed = congestion_level != last_logged_congestion_level
    alert_changed = alert_status != last_logged_alert_status
    collision_changed = potential_collision != last_logged_collision_status
    officer_changed = officer_presence != last_logged_officer_status

    if log_due or congestion_changed or alert_changed or collision_changed or officer_changed:
        collision_note = None
        if collision_result.track_ids:
            collision_note = (
                f"Potential collision {potential_collision} between tracks "
                f"{collision_result.track_ids[0]} and {collision_result.track_ids[1]} "
                f"(confidence {collision_result.confidence:.2f})."
            )
        log_payload = {
            "camera_id": config.CAMERA_ID,
            "vehicle_count": visible_vehicle_count,
            "inbound_count": inbound_count,
            "outbound_count": outbound_count,
            "congestion_level": congestion_level,
            "officer_presence": officer_presence,
            "potential_collision": potential_collision,
            "alert_generated": 1 if alert_status == "ALERT" or potential_collision != "none" else 0,
            "incident_notes": collision_note
        }

        send_monitoring_log(config.MONITORING_LOG_API_URL, log_payload)

        last_log_save = current_time
        last_logged_congestion_level = congestion_level
        last_logged_alert_status = alert_status
        last_logged_collision_status = potential_collision
        last_logged_officer_status = officer_presence

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
