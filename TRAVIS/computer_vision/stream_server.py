"""
TRAVIS Live Stream Server
Shared-memory MJPEG stream
"""

from flask import Flask, Response
import base64
import cv2
import hashlib
import hmac
import json
from pathlib import Path
import requests
import subprocess
import threading
import time

app = Flask(__name__)

# ==========================================
# Shared Frame Buffer
# ==========================================

latest_jpeg = None
frame_version = 0
frame_condition = threading.Condition()
encode_condition = threading.Condition()
pending_frame = None
encoder_started = False
relay_condition = threading.Condition()
relay_jpeg = None
relay_started = False
last_relay_queued_at = 0.0
webrtc_condition = threading.Condition()
webrtc_frame = None
webrtc_started = False
# The AI still receives the camera's full-resolution frame. Only the browser
# copy is reduced so each hosted upload finishes quickly and the viewer stays
# close to real time instead of waiting on large JPEG transfers.
STREAM_MAX_WIDTH = 720
STREAM_JPEG_QUALITY = 55
# Match the hosted browser's low-latency snapshot cadence. The relay always
# replaces its single pending JPEG, so slow uploads drop old frames instead of
# building a latency-producing queue.
HOSTED_RELAY_INTERVAL_SECONDS = 0.10
WEBRTC_PUBLISH_FPS = 12


def _webrtc_publish_url():
    try:
        config_path = Path(__file__).resolve().parent / "edge_config.json"
        config = json.loads(config_path.read_text(encoding="utf-8"))
        url = str(config.get("webrtc_publish_url", "")).strip()
    except (OSError, ValueError):
        return ""
    return url if url.startswith("rtsp://") else ""


def _webrtc_publisher_loop():
    """Publish the newest processed frame to MediaMTX at a stable cadence."""
    global webrtc_frame
    publish_url = _webrtc_publish_url()
    if not publish_url:
        return

    try:
        import imageio_ffmpeg
        ffmpeg_executable = imageio_ffmpeg.get_ffmpeg_exe()
    except (ImportError, RuntimeError):
        return

    log_path = Path(__file__).resolve().parent.parent / "storage" / "webrtc-publisher.log"
    log_path.parent.mkdir(parents=True, exist_ok=True)

    process = None
    frame_size = None
    frame_interval = 1.0 / WEBRTC_PUBLISH_FPS
    next_frame_at = time.monotonic()

    while True:
        with webrtc_condition:
            webrtc_condition.wait_for(lambda: webrtc_frame is not None, timeout=2)
            if webrtc_frame is None:
                continue
            frame = webrtc_frame.copy()

        height, width = frame.shape[:2]
        current_size = (width, height)
        if process is None or process.poll() is not None or frame_size != current_size:
            if process is not None:
                try:
                    process.kill()
                except OSError:
                    pass
            command = [
                ffmpeg_executable,
                "-hide_banner", "-loglevel", "error",
                "-f", "rawvideo", "-pix_fmt", "bgr24",
                "-video_size", f"{width}x{height}",
                "-framerate", str(WEBRTC_PUBLISH_FPS), "-i", "pipe:0",
                "-an", "-c:v", "libx264", "-preset", "ultrafast",
                "-tune", "zerolatency", "-pix_fmt", "yuv420p",
                "-profile:v", "baseline", "-g", str(WEBRTC_PUBLISH_FPS),
                "-bf", "0", "-f", "rtsp", "-rtsp_transport", "tcp",
                publish_url,
            ]
            log_handle = log_path.open("ab", buffering=0)
            process = subprocess.Popen(
                command,
                stdin=subprocess.PIPE,
                stdout=subprocess.DEVNULL,
                stderr=log_handle,
            )
            frame_size = current_size

        try:
            process.stdin.write(frame.tobytes())
        except (BrokenPipeError, OSError, AttributeError):
            try:
                process.kill()
            except OSError:
                pass
            process = None
            time.sleep(2)
            continue

        next_frame_at += frame_interval
        remaining = next_frame_at - time.monotonic()
        if remaining > 0:
            time.sleep(remaining)
        else:
            next_frame_at = time.monotonic()


def _relay_settings():
    base_dir = Path(__file__).resolve().parent
    config_path = base_dir / "edge_config.json"
    key_path = base_dir.parent / "storage" / "service_api.key"
    try:
        config = json.loads(config_path.read_text(encoding="utf-8"))
        base_url = str(config.get("web_base_url", "")).rstrip("/")
        secret = key_path.read_text(encoding="utf-8").strip()
    except (OSError, ValueError):
        return "", ""
    if base_url.startswith("http://localhost") or len(secret) < 32:
        return "", ""
    return base_url + "/api/worker_snapshot.php", secret


def _snapshot_relay_loop():
    endpoint, secret = _relay_settings()
    if not endpoint:
        return
    session = requests.Session()
    while True:
        global relay_jpeg
        with relay_condition:
            relay_condition.wait_for(lambda: relay_jpeg is not None, timeout=2)
            if relay_jpeg is None:
                continue
            jpeg = relay_jpeg
            relay_jpeg = None
        payload = {"image_base64": base64.b64encode(jpeg).decode("ascii")}
        raw = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        timestamp = str(int(time.time()))
        signature = hmac.new(secret.encode(), timestamp.encode() + b"." + raw, hashlib.sha256).hexdigest()
        try:
            session.post(
                endpoint,
                data=raw,
                headers={"Content-Type": "application/json", "X-TRAVIS-Timestamp": timestamp, "X-TRAVIS-Signature": signature},
                timeout=5,
            ).raise_for_status()
        except requests.RequestException:
            # Frame delivery is best-effort and must never stop CV processing.
            pass


def _frame_encoder_loop():
    """Encode only the newest rendered frame without blocking inference."""
    global pending_frame, latest_jpeg, frame_version, relay_jpeg, last_relay_queued_at, webrtc_frame

    while True:
        with encode_condition:
            encode_condition.wait_for(lambda: pending_frame is not None)
            frame = pending_frame
            pending_frame = None

        # The detector keeps the source resolution for tracking and counting,
        # but browsers do not need a 1080p/4K MJPEG frame. Scaling only the
        # displayed copy greatly reduces encoding and transfer stalls.
        stream_frame = frame
        if frame.shape[1] > STREAM_MAX_WIDTH:
            ratio = STREAM_MAX_WIDTH / frame.shape[1]
            scaled_height = max(2, int(frame.shape[0] * ratio))
            # H.264 yuv420p requires even frame dimensions.
            scaled_height -= scaled_height % 2
            stream_frame = cv2.resize(
                frame,
                (STREAM_MAX_WIDTH, scaled_height),
                interpolation=cv2.INTER_AREA,
            )

        success, buffer = cv2.imencode(
            ".jpg",
            stream_frame,
            [int(cv2.IMWRITE_JPEG_QUALITY), STREAM_JPEG_QUALITY],
        )
        if not success:
            continue

        with webrtc_condition:
            webrtc_frame = stream_frame
            webrtc_condition.notify()

        with frame_condition:
            latest_jpeg = buffer.tobytes()
            frame_version += 1
            frame_condition.notify_all()

        now = time.monotonic()
        if now - last_relay_queued_at < HOSTED_RELAY_INTERVAL_SECONDS:
            continue

        # Reuse the already encoded balanced frame for the hosted relay. A
        # second resize/JPEG pass here previously caused periodic CPU spikes.
        with relay_condition:
            relay_jpeg = buffer.tobytes()
            relay_condition.notify()
        last_relay_queued_at = now


def update_frame(frame):
    """Queue the newest AI frame and return immediately to detection."""
    global pending_frame

    with encode_condition:
        # detect_video creates a fresh annotated frame on every iteration, so
        # ownership can be handed to the encoder without another full-frame
        # memory copy. Replacing a pending item keeps latency bounded.
        pending_frame = frame
        encode_condition.notify()


# ==========================================
# MJPEG Generator
# ==========================================

def generate_frames():

    global latest_jpeg, frame_version
    last_version = -1

    while True:
        with frame_condition:
            frame_condition.wait_for(
                lambda: latest_jpeg is not None and frame_version != last_version,
                timeout=1,
            )
            if latest_jpeg is None or frame_version == last_version:
                continue
            frame_bytes = latest_jpeg
            last_version = frame_version

        yield (
            b'--frame\r\n'
            b'Content-Type: image/jpeg\r\n\r\n'
            + frame_bytes +
            b'\r\n'
        )


# ==========================================
# Flask Route
# ==========================================

@app.route("/video_feed")
def video_feed():
    response = Response(
        generate_frames(),
        mimetype="multipart/x-mixed-replace; boundary=frame"
    )
    response.headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0"
    response.headers["Pragma"] = "no-cache"
    response.headers["Expires"] = "0"
    response.headers["X-Accel-Buffering"] = "no"
    return response


@app.route("/snapshot")
def snapshot():
    """Single processed JPEG frame for mobile clients that cannot render MJPEG."""
    with frame_condition:
        frame_bytes = latest_jpeg
    if frame_bytes is None:
        return Response(status=503)
    response = Response(frame_bytes, mimetype="image/jpeg")
    response.headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0"
    response.headers["Access-Control-Allow-Origin"] = "*"
    return response


# ==========================================
# Start Server
# ==========================================

def start_stream():

    global relay_started, encoder_started, webrtc_started
    if not encoder_started:
        threading.Thread(target=_frame_encoder_loop, daemon=True, name="mjpeg-frame-encoder").start()
        encoder_started = True
    if not relay_started:
        threading.Thread(target=_snapshot_relay_loop, daemon=True).start()
        relay_started = True
    if not webrtc_started:
        threading.Thread(
            target=_webrtc_publisher_loop,
            daemon=True,
            name="mediamtx-webrtc-publisher",
        ).start()
        webrtc_started = True

    threading.Thread(
        target=lambda: app.run(
            host="0.0.0.0",
            port=5000,
            threaded=True,
            debug=False,
            use_reloader=False
        ),
        daemon=True
    ).start()


# ==========================================
# Standalone Run
# ==========================================

if __name__ == "__main__":

    print("--------------------------------")
    print("TRAVIS Live Stream Server")
    print("http://localhost:5000/video_feed")
    print("--------------------------------")

    app.run(
        host="0.0.0.0",
        port=5000,
        threaded=True,
        debug=False
    )
