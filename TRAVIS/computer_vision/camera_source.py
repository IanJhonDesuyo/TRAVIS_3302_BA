import cv2
import config


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

            self.cap = cv2.VideoCapture(
                config.TAPO_RTSP
            )

        else:

            raise Exception("Invalid Video Source")

        if not self.cap.isOpened():

            raise Exception("Cannot open video source.")

        return self.cap