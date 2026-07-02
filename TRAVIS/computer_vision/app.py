from flask import Flask, Response
import cv2
import time

app = Flask(__name__)

USERNAME = "travis"
PASSWORD = "Travis123"
CAMERA_IP = "192.168.1.23"

RTSP_URL = f"rtsp://{USERNAME}:{PASSWORD}@{CAMERA_IP}:554/stream2"


def generate_frames():
    cap = cv2.VideoCapture(RTSP_URL)

    if not cap.isOpened():
        print("Cannot connect to Tapo camera.")
        return

    while True:
        success, frame = cap.read()

        if not success:
            print("Cannot read frame. Reconnecting...")
            cap.release()
            time.sleep(2)
            cap = cv2.VideoCapture(RTSP_URL)
            continue

        frame = cv2.resize(frame, (960, 540))

        _, buffer = cv2.imencode(".jpg", frame)
        frame_bytes = buffer.tobytes()

        yield (
            b"--frame\r\n"
            b"Content-Type: image/jpeg\r\n\r\n" + frame_bytes + b"\r\n"
        )


@app.route("/")
def index():
    return """
    <h2>TRAVIS Tapo Camera Stream</h2>
    <img src="/video_feed" width="960">
    """


@app.route("/video_feed")
def video_feed():
    return Response(
        generate_frames(),
        mimetype="multipart/x-mixed-replace; boundary=frame"
    )


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)