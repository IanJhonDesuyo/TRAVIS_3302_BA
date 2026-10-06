from ultralytics import YOLO
import argparse
import cv2
import json
import os
import time
from datetime import datetime
from pathlib import Path
import config
from api_client import get_cv_settings, send_monitoring_log, send_status_update, send_status_update_now
from camera_source import CameraSource
from stream_server import start_stream, update_frame
from congestion import get_congestion_level
from alert_engine import AlertEngine
from calibration import load_calibration
from collision_detection import CollisionDetector
from direction_counter import DirectionCounter
from officer_detection import OfficerPresenceDetector
from duty_schedule import is_enforcer_duty_active


def apply_selected_source():
    parser = argparse.ArgumentParser(description="TRAVIS AI video detection engine")
    parser.add_argument(
        "--source-type",
        choices=["uploaded_video", "tapo_camera", "phone_camera"],
        default=None,
    )
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
    elif args.source_type == "phone_camera":
        config.VIDEO_SOURCE = "phone"
        if args.source:
            config.PHONE_STREAM_URL = args.source

    return args


selected_source = apply_selected_source()
is_live_source = config.VIDEO_SOURCE in ("tapo", "phone")

runtime_settings = get_cv_settings(config.CV_SETTINGS_API_URL)
config.CONFIDENCE_THRESHOLD = float(runtime_settings.get("confidence_threshold", config.CONFIDENCE_THRESHOLD))
config.ENABLE_COLLISION_DETECTION = bool(int(runtime_settings.get("enable_collision_detection", int(config.ENABLE_COLLISION_DETECTION))))
config.ENABLE_OFFICER_DETECTION = bool(int(runtime_settings.get("enable_officer_detection", int(config.ENABLE_OFFICER_DETECTION))))
CONGESTION_LIGHT_MAX = int(runtime_settings.get("congestion_light_max", 5))
CONGESTION_HEAVY_MIN = int(runtime_settings.get("congestion_heavy_min", 13))
ALERT_COOLDOWN_SECONDS = int(runtime_settings.get("alert_cooldown_seconds", 300))

# ============================
# Load YOLO Model
# ============================
model = YOLO(config.MODEL_PATH)

# Avoid CPU oversubscription between PyTorch inference and the background
# OpenCV MJPEG encoder. PyTorch retains the available inference threads.
cv2.setNumThreads(2)

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


def report_startup_error(message):
    """Persist and relay a terminal live-camera failure immediately."""
    source_type = selected_source.source_type or (
        "uploaded_video" if config.VIDEO_SOURCE == "video" else f"{config.VIDEO_SOURCE}_camera"
    )
    send_status_update_now(config.STATUS_API_URL, {
        "vehicle_count": 0,
        "inbound_count": 0,
        "outbound_count": 0,
        "congestion_level": "Unknown",
        "officer_presence": "Unknown",
        "potential_collision": "None",
        "alert_status": "NORMAL",
        "ai_status": "Error",
        "analysis_status": "Error",
        "message": message,
        "source_type": source_type,
    })
    status_path = Path(__file__).resolve().parent.parent / "Web_app" / "api" / "analysis_status.json"
    status_payload = {
        "analysis_status": "Error",
        "ai_status": "Error",
        "message": message,
        "source_type": source_type,
        "stream_owner": "shared",
        "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "updated_at_epoch": int(time.time()),
    }
    temporary_status_path = status_path.with_suffix(".json.tmp")
    temporary_status_path.write_text(json.dumps(status_payload, indent=4), encoding="utf-8")
    os.replace(temporary_status_path, status_path)


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
elif config.VIDEO_SOURCE == "phone":
    print("Cellphone stream configured:", bool(config.PHONE_STREAM_URL))

# ============================
# Open Video
# ============================




start_stream()

camera = CameraSource()

alert_engine = AlertEngine()
alert_engine.cooldown = ALERT_COOLDOWN_SECONDS

try:
    cap = camera.open()
except Exception as error:
    failure_message = (
        "The Tapo RTSP stream could not be opened. Confirm that port 554 is enabled, "
        "verify the Camera Account credentials, and use Standard quality."
        if config.VIDEO_SOURCE == "tapo"
        else str(error)
    )
    report_startup_error(failure_message)
    raise

if not cap.isOpened():
    print("Cannot open video.")
    exit()

width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
print(f"Capture Resolution: {width} x {height}")
fps = cap.get(cv2.CAP_PROP_FPS)
total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT)) if config.VIDEO_SOURCE == "video" else 0
current_frame = 0
live_frame_version = -1
live_reconnect_cycles = 0
live_connection_error = None
source_started_at = time.time()
processing_fps_ema = 0.0

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

print("Inbound Line:", INBOUND_LINE)
print("Outbound Line:", OUTBOUND_LINE)
print("Calibration Profile:", calibration.name)
print("Collision Detection:", "Enabled" if collision_enabled else "Disabled")
print("Officer Presence Detection:", "Enabled" if officer_detection_enabled else "Disabled")

if fps == 0:
    fps = 30

out = None
if config.VIDEO_SOURCE == "video" and config.SAVE_PROCESSED_VIDEO:
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
    frame_started_at = time.perf_counter()
    enforcer_duty_active = is_enforcer_duty_active(runtime_settings)
    if is_live_source:
        ret, frame, live_frame_version = camera.read_latest(
            after_version=live_frame_version,
        )
    else:
        # Preserve sequential frames so ByteTrack IDs remain stable and a
        # vehicle cannot jump over a counting line between detections.
        ret, frame = cap.read()

    if not ret:
        if is_live_source:
            live_reconnect_cycles += 1
            if live_reconnect_cycles > 3:
                live_connection_error = (
                    "The Tapo camera stopped delivering usable RTSP frames after three reconnect attempts. "
                    "Check Wi-Fi stability, Camera Account/RTSP settings, and restart the camera."
                    if config.VIDEO_SOURCE == "tapo"
                    else "The live camera stopped delivering usable frames."
                )
                report_startup_error(live_connection_error)
                print(live_connection_error)
                break
            cap = camera.reconnect(attempts=1, delay=1)
            if cap is not None:
                live_frame_version = -1
                continue
            live_connection_error = "Live camera connection lost and could not be restored."
            report_startup_error(live_connection_error)
            print(live_connection_error)
        break

    live_reconnect_cycles = 0

    current_frame = (
        int(cap.get(cv2.CAP_PROP_POS_FRAMES))
        if config.VIDEO_SOURCE == "video"
        else current_frame + 1
    )
   

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
        tracker=config.TRACKER_CONFIG,
        imgsz=config.LIVE_INFERENCE_SIZE if is_live_source else config.UPLOADED_INFERENCE_SIZE,
        # Preserve low-confidence small-object candidates for class-specific
        # filtering below. Without this, YOLO may discard the motorcycle body
        # while retaining only its higher-confidence rider/person box.
        conf=0.15,
        classes=config.ALLOWED_CLASSES,
        verbose=False
    )

    for result in results:
        boxes = result.boxes

        for box in boxes:
            cls = int(box.cls[0])
            conf = float(box.conf[0])

            detection_threshold = (
                min(config.CONFIDENCE_THRESHOLD, config.MOTORCYCLE_CONFIDENCE_THRESHOLD)
                if cls == config.MOTORCYCLE_CLASS
                else (
                    min(config.CONFIDENCE_THRESHOLD, config.VEHICLE_CONFIDENCE_THRESHOLD)
                    if cls in config.VEHICLE_CLASSES
                    else config.CONFIDENCE_THRESHOLD
                )
            )
            if conf < detection_threshold:
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
                if enforcer_duty_active and in_officer_zone:
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
                # An unconfirmed detection has no stable identity yet. Show
                # it immediately, but do not merge multiple anonymous objects
                # into the shared -1 track used by counting/collision logic.
                if track_id >= 0:
                    # The bottom-center follows the vehicle's road contact
                    # point. It crosses a painted/calibrated road line more
                    # reliably than the bounding-box center, especially for
                    # tall trucks and perspective CCTV views.
                    counting_point = (center_x, y2)
                    direction_counter.update(track_id, counting_point)
                    vehicle_tracks.append({
                        "track_id": track_id,
                        "class_id": cls,
                        "confidence": conf,
                        "bbox": (x1, y1, x2, y2),
                        "center": current_point,
                        "inside_road_roi": calibration.contains_road_point(current_point),
                    })

                    cv2.circle(
                        annotated_frame,
                        counting_point,
                        max(3, POINT_RADIUS - 1),
                        (0, 255, 255),
                        -1,
                    )

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
    frame_processing_seconds = max(1e-6, time.perf_counter() - frame_started_at)
    instantaneous_processing_fps = 1.0 / frame_processing_seconds
    processing_fps_ema = (
        instantaneous_processing_fps
        if processing_fps_ema <= 0
        else (processing_fps_ema * 0.90) + (instantaneous_processing_fps * 0.10)
    )
    collision_result = collision_detector.update(vehicle_tracks, time.monotonic())
    potential_collision = collision_result.status
    officer_result = officer_detector.update(persons_in_officer_zone) if enforcer_duty_active else None
    officer_presence = officer_result.status if officer_result else "unknown"

    congestion_level = get_congestion_level(
        visible_vehicle_count,
        light_max=CONGESTION_LIGHT_MAX,
        heavy_min=CONGESTION_HEAVY_MIN,
    )

    alert_status = alert_engine.update(congestion_level)

    # Send status to PHP every second
    if time.time() - last_api_update >= 1:
        capture_metrics = camera.metrics() if is_live_source else {}
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
            "calibration_profile": calibration.name,
            "current_frame": current_frame,
            "total_frames": total_frames,
            "progress_percent": progress_percent,
            "running_time_seconds": int(time.time() - source_started_at),
            "processing_fps": round(processing_fps_ema, 2),
            "source_fps": round(float(fps), 2),
            **capture_metrics,
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
            collision_label = (
                "Confirmed collision detected"
                if potential_collision == "confirmed"
                else "Potential collision detected"
            )
            collision_note = (
                f"{collision_label} between tracks "
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
            # A possible trajectory conflict is monitoring data only. It must
            # not raise an operator alert until the detector confirms it.
            "alert_generated": 1 if alert_status == "ALERT" or potential_collision == "confirmed" else 0,
            "incident_notes": collision_note
        }

        send_monitoring_log(config.MONITORING_LOG_API_URL, log_payload)

        last_log_save = current_time
        last_logged_congestion_level = congestion_level
        last_logged_alert_status = alert_status
        last_logged_collision_status = potential_collision
        last_logged_officer_status = officer_presence

    update_frame(annotated_frame)

    # Pace uploaded footage at its recorded frame rate so mobile viewers have
    # time to connect instead of losing the stream when processing finishes.
    if config.VIDEO_SOURCE == "video" and fps > 0:
        target_elapsed = current_frame / fps
        remaining = target_elapsed - (time.time() - source_started_at)
        if remaining > 0:
            time.sleep(remaining)

    if out is not None:
        out.write(annotated_frame)

    if not is_live_source and config.SHOW_DEBUG_WINDOW:
        cv2.imshow("TRAVIS AI Direction-Based Counting", annotated_frame)

    if not is_live_source and config.SHOW_DEBUG_WINDOW and cv2.waitKey(1) & 0xFF == ord("q"):
        break

camera.release()
if out is not None:
    out.release()
cv2.destroyAllWindows()

print("--------------------------------")
print("Processing Finished")
if config.SAVE_PROCESSED_VIDEO:
    print("Saved to:", OUTPUT_VIDEO)
print("Inbound:", inbound_count)
print("Outbound:", outbound_count)
print("--------------------------------")

# The Flask stream lives inside this worker, so it ends with uploaded-video
# processing. Persist the terminal state to prevent browsers from trying to
# join a stream that no longer exists.
if config.VIDEO_SOURCE == "video":
    send_status_update(config.STATUS_API_URL, {
        "vehicle_count": visible_vehicle_count,
        "inbound_count": inbound_count,
        "outbound_count": outbound_count,
        "congestion_level": congestion_level,
        "officer_presence": officer_presence,
        "potential_collision": potential_collision,
        "alert_status": alert_status,
        "ai_status": "Completed",
        "source_type": "uploaded_video",
        "calibration_profile": calibration.name,
        "current_frame": current_frame,
        "total_frames": total_frames,
        "progress_percent": 100,
        "running_time_seconds": int(time.time() - source_started_at),
    })
    status_path = Path(__file__).resolve().parent.parent / "Web_app" / "api" / "analysis_status.json"
    status_payload = {
        "analysis_status": "Completed",
        "ai_status": "Completed",
        "message": "Uploaded video analysis completed. Upload or start the footage again to open a new live feed.",
        "source_type": "uploaded_video",
        "stream_owner": "shared",
        "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "updated_at_epoch": int(time.time()),
    }
    temporary_status_path = status_path.with_suffix(".json.tmp")
    temporary_status_path.write_text(json.dumps(status_payload, indent=4), encoding="utf-8")
    os.replace(temporary_status_path, status_path)
