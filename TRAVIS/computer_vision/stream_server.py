"""
TRAVIS Live Stream Server
Streams the latest AI frame saved by detect_video.py
"""

from flask import Flask, Response
import cv2
import os
import time

app = Flask(__name__)

SNAPSHOT_PATH = "snapshots/current.jpg"


def generate_frames():

    while True:

        if os.path.exists(SNAPSHOT_PATH):

            frame = cv2.imread(SNAPSHOT_PATH)

            if frame is not None:

                ret, buffer = cv2.imencode(".jpg", frame)

                if ret:

                    yield (
                        b'--frame\r\n'
                        b'Content-Type: image/jpeg\r\n\r\n' +
                        buffer.tobytes() +
                        b'\r\n'
                    )

        else:

            blank = 255 * __import__("numpy").ones((480, 640, 3), dtype="uint8")

            cv2.putText(
                blank,
                "Waiting for AI frames...",
                (120, 240),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (0, 0, 0),
                2
            )

            ret, buffer = cv2.imencode(".jpg", blank)

            if ret:

                yield (
                    b'--frame\r\n'
                    b'Content-Type: image/jpeg\r\n\r\n' +
                    buffer.tobytes() +
                    b'\r\n'
                )

        # ~30 FPS
        time.sleep(0.10)


@app.route("/video_feed")
def video_feed():

    return Response(
        generate_frames(),
        mimetype="multipart/x-mixed-replace; boundary=frame"
    )


if __name__ == "__main__":

    print("------------------------------------")
    print("TRAVIS Live Stream Server")
    print("http://localhost:5000/video_feed")
    print("------------------------------------")

    app.run(
        host="0.0.0.0",
        port=5000,
        debug=False,
        threaded=True
    )