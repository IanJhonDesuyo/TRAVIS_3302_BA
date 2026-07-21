import cv2
import config
import time


class CameraSource:

    def __init__(self):
        self.cap = None

    def open(self):

        if config.VIDEO_SOURCE == "video":

            print("Source : Uploaded Video")

            self.cap = cv2.VideoCapture(
                config.VIDEO_PATH
            )

        elif config.VIDEO_SOURCE == "webcam":

            print("Source : Laptop Camera")

            self.cap = cv2.VideoCapture(
                config.CAMERA_INDEX
            )

        elif config.VIDEO_SOURCE == "tapo":

            print("Source : Tapo Camera")

            if not config.TAPO_RTSP:
                raise Exception("Tapo camera is not configured.")

            self.cap = cv2.VideoCapture(config.TAPO_RTSP)
            self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 2)

        else:

            raise Exception("Invalid Video Source")

        if not self.cap.isOpened():

            raise Exception("Cannot open video source.")

        return self.cap

    def reconnect(self, attempts=5, delay=2):
        """Reconnect a live camera after a temporary Wi-Fi/RTSP interruption."""
        if config.VIDEO_SOURCE != "tapo":
            return None

        if self.cap is not None:
            self.cap.release()

        for attempt in range(1, attempts + 1):
            print(f"Reconnecting to Tapo camera ({attempt}/{attempts})...")
            self.cap = cv2.VideoCapture(config.TAPO_RTSP)
            self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 2)
            if self.cap.isOpened():
                return self.cap
            self.cap.release()
            time.sleep(delay)

        return None
