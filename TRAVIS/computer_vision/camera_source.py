import cv2
import config
import threading
import time


LIVE_OPEN_TIMEOUT_MS = 5000
LIVE_READ_TIMEOUT_MS = 5000


class CameraSource:

    def __init__(self):
        self.cap = None
        self._reader_thread = None
        self._reader_stop = threading.Event()
        self._frame_condition = threading.Condition()
        self._latest_frame = None
        self._frame_version = 0
        self._reader_failed = False
        self._latest_frame_captured_at = None
        self._capture_started_at = None
        self._captured_frames = 0
        self._dropped_frames = 0
        self._reconnect_count = 0

    @property
    def is_live(self):
        return config.VIDEO_SOURCE in ("tapo", "phone")

    @property
    def uses_latest_frame_reader(self):
        # Drain every live source independently from inference. Otherwise
        # FFmpeg's internal queue grows whenever YOLO is slower than the camera.
        return self.is_live

    def _start_latest_frame_reader(self):
        """Drain a live stream continuously so inference never uses stale frames."""
        self._reader_stop = threading.Event()
        self._reader_failed = False
        self._latest_frame = None
        self._frame_version = 0
        self._latest_frame_captured_at = None
        self._capture_started_at = time.monotonic()
        self._captured_frames = 0
        capture = self.cap
        stop_event = self._reader_stop
        self._reader_thread = threading.Thread(
            target=self._read_live_frames,
            args=(capture, stop_event),
            name="camera-latest-frame-reader",
            daemon=True,
        )
        self._reader_thread.start()

    def _read_live_frames(self, capture, stop_event):
        while not stop_event.is_set():
            success, frame = capture.read()
            if not success:
                with self._frame_condition:
                    if stop_event is self._reader_stop:
                        self._reader_failed = True
                        self._frame_condition.notify_all()
                return

            with self._frame_condition:
                if stop_event is not self._reader_stop:
                    return
                self._latest_frame = frame
                self._frame_version += 1
                self._captured_frames += 1
                self._latest_frame_captured_at = time.monotonic()
                self._frame_condition.notify_all()

    @staticmethod
    def _open_rtsp_capture(stream_url):
        """Open RTSP through FFmpeg with bounded connection/read waits."""
        parameters = [
            cv2.CAP_PROP_OPEN_TIMEOUT_MSEC,
            LIVE_OPEN_TIMEOUT_MS,
            cv2.CAP_PROP_READ_TIMEOUT_MSEC,
            LIVE_READ_TIMEOUT_MS,
        ]
        try:
            return cv2.VideoCapture(stream_url, cv2.CAP_FFMPEG, parameters)
        except (TypeError, cv2.error):
            return cv2.VideoCapture(stream_url, cv2.CAP_FFMPEG)

    @classmethod
    def _open_live_capture(cls, stream_url):
        lowered = str(stream_url).lower()
        if lowered.startswith(("rtsp://", "rtsps://", "rtmp://", "rtmps://")):
            capture = cls._open_rtsp_capture(stream_url)
        else:
            capture = cv2.VideoCapture(stream_url)
        capture.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        return capture

    def read_latest(self, after_version=-1, timeout=5):
        """Return the newest live frame, waiting briefly for a newer one."""
        if not self.uses_latest_frame_reader:
            success, frame = self.cap.read()
            return success, frame, after_version + 1

        deadline = time.monotonic() + timeout
        with self._frame_condition:
            while (
                self._frame_version == after_version
                and not self._reader_failed
                and not self._reader_stop.is_set()
            ):
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    break
                self._frame_condition.wait(remaining)

            if self._latest_frame is None or self._reader_failed:
                return False, None, after_version

            newest_version = self._frame_version
            if after_version >= 0:
                self._dropped_frames += max(0, newest_version - after_version - 1)
            return True, self._latest_frame.copy(), newest_version

    def metrics(self):
        """Return live-capture health without exposing mutable reader state."""
        with self._frame_condition:
            now = time.monotonic()
            elapsed = max(1e-6, now - self._capture_started_at) if self._capture_started_at else 0
            capture_fps = self._captured_frames / elapsed if elapsed else 0.0
            frame_age_ms = (
                max(0.0, now - self._latest_frame_captured_at) * 1000
                if self._latest_frame_captured_at is not None
                else None
            )
            return {
                "capture_fps": round(capture_fps, 2),
                "dropped_frames": self._dropped_frames,
                "frame_age_ms": round(frame_age_ms, 1) if frame_age_ms is not None else None,
                "reconnect_count": self._reconnect_count,
                "reader_failed": self._reader_failed,
            }

    def release(self):
        self._reader_stop.set()
        with self._frame_condition:
            self._frame_condition.notify_all()
        if (
            self._reader_thread is not None
            and self._reader_thread.is_alive()
            and self._reader_thread is not threading.current_thread()
        ):
            # Reads are bounded to five seconds. Let the owner thread leave
            # read() before releasing FFmpeg to avoid fctx->async_lock races.
            self._reader_thread.join(timeout=(LIVE_READ_TIMEOUT_MS / 1000) + 1)
        # Do not release FFmpeg while its reader thread owns the decoder. That
        # race triggers libavcodec's fctx->async_lock assertion on reconnect.
        reader_still_running = self._reader_thread is not None and self._reader_thread.is_alive()
        if self.cap is not None and not reader_still_running:
            self.cap.release()
        self._reader_thread = None

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

            self.cap = self._open_live_capture(config.TAPO_RTSP)

        elif config.VIDEO_SOURCE == "phone":

            print("Source : Cellphone Camera")

            if not config.PHONE_STREAM_URL:
                raise Exception("Cellphone camera stream is not configured.")

            self.cap = self._open_live_capture(config.PHONE_STREAM_URL)

        else:

            raise Exception("Invalid Video Source")

        if not self.cap.isOpened():

            raise Exception("Cannot open video source.")

        if self.uses_latest_frame_reader:
            self._start_latest_frame_reader()

        return self.cap

    def reconnect(self, attempts=3, delay=1):
        """Reconnect a live camera after a temporary Wi-Fi interruption."""
        if config.VIDEO_SOURCE not in ("tapo", "phone"):
            return None

        stream_url = (
            config.TAPO_RTSP
            if config.VIDEO_SOURCE == "tapo"
            else config.PHONE_STREAM_URL
        )
        source_label = "Tapo" if config.VIDEO_SOURCE == "tapo" else "cellphone"

        self.release()

        for attempt in range(1, attempts + 1):
            print(f"Reconnecting to {source_label} camera ({attempt}/{attempts})...")
            self.cap = self._open_live_capture(stream_url)
            if self.cap.isOpened():
                self._reconnect_count += 1
                if self.uses_latest_frame_reader:
                    self._start_latest_frame_reader()
                return self.cap
            self.cap.release()
            time.sleep(delay)

        return None
