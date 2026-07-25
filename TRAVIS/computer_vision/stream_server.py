"""
TRAVIS Live Stream Server
Shared-memory MJPEG stream
"""

from flask import Flask, Response
import cv2
import threading

app = Flask(__name__)

# ==========================================
# Shared Frame Buffer
# ==========================================

latest_jpeg = None
frame_version = 0
frame_condition = threading.Condition()


def update_frame(frame):
    """
    Receives the latest AI frame from detect_video.py
    """

    global latest_jpeg, frame_version

    success, buffer = cv2.imencode(
        ".jpg",
        frame,
        [int(cv2.IMWRITE_JPEG_QUALITY), 75],
    )
    if not success:
        return

    with frame_condition:
        latest_jpeg = buffer.tobytes()
        frame_version += 1
        frame_condition.notify_all()


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
