"""
TRAVIS Live Stream Server
Shared-memory MJPEG stream
"""

from flask import Flask, Response
import cv2
import threading
import numpy as np

app = Flask(__name__)

# ==========================================
# Shared Frame Buffer
# ==========================================

latest_frame = None
frame_lock = threading.Lock()


def update_frame(frame):
    """
    Receives the latest AI frame from detect_video.py
    """

    global latest_frame

    with frame_lock:
        latest_frame = frame.copy()


# ==========================================
# MJPEG Generator
# ==========================================

def generate_frames():

    global latest_frame

    while True:

        with frame_lock:

            if latest_frame is None:

                frame = np.ones((480, 640, 3), dtype=np.uint8) * 255

                cv2.putText(
                    frame,
                    "Waiting for AI...",
                    (170, 240),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    1,
                    (0, 0, 0),
                    2
                )

            else:

                frame = latest_frame.copy()

        success, buffer = cv2.imencode(".jpg", frame)

        if not success:
            continue

        frame_bytes = buffer.tobytes()

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

    return Response(
        generate_frames(),
        mimetype="multipart/x-mixed-replace; boundary=frame"
    )


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