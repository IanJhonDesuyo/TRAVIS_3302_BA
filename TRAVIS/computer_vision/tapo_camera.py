"""Dedicated, validated Tapo RTSP capture for TRAVIS."""

from __future__ import annotations

import os
import socket
import threading
import time
from urllib.parse import urlparse

import cv2


OPEN_TIMEOUT_MS = 7000
READ_TIMEOUT_MS = 7000


class TapoCamera:
    """Keep exactly one RTSP/TCP session and expose only its newest frame."""

    def __init__(self, stream_url: str):
        self.stream_url = stream_url
        self.cap = None
        self._reader_thread = None
        self._stop_event = threading.Event()
        self._condition = threading.Condition()
        self._latest_frame = None
        self._frame_version = 0
        self._reader_failed = False
        self._last_error = ""
        self._captured_at = None
        self._started_at = None
        self._captured_frames = 0
        self._dropped_frames = 0
        self._reconnect_count = 0
        self._width = 0
        self._height = 0
        self._fps = 0.0

    def _endpoint(self) -> tuple[str, int]:
        parsed = urlparse(self.stream_url)
        if parsed.scheme.lower() not in {"rtsp", "rtsps"} or not parsed.hostname:
            raise RuntimeError("The Tapo RTSP URL is invalid.")
        return parsed.hostname, parsed.port or 554

    def _check_port(self) -> None:
        host, port = self._endpoint()
        try:
            with socket.create_connection((host, port), timeout=2):
                return
        except OSError as error:
            raise RuntimeError(
                f"The Tapo camera is online, but RTSP port {port} is unavailable. "
                "Enable Camera Account/RTSP in the Tapo app and restart the camera."
            ) from error

    def _new_capture(self):
        self._check_port()
        # TCP is much more reliable than UDP on Wi-Fi and prevents partial-frame
        # loss from being mistaken for a dead camera.
        os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
            "rtsp_transport;tcp|stimeout;7000000|max_delay;500000"
        )
        parameters = [
            cv2.CAP_PROP_OPEN_TIMEOUT_MSEC,
            OPEN_TIMEOUT_MS,
            cv2.CAP_PROP_READ_TIMEOUT_MSEC,
            READ_TIMEOUT_MS,
        ]
        try:
            capture = cv2.VideoCapture(self.stream_url, cv2.CAP_FFMPEG, parameters)
        except (TypeError, cv2.error):
            capture = cv2.VideoCapture(self.stream_url, cv2.CAP_FFMPEG)

        if not capture.isOpened():
            capture.release()
            raise RuntimeError(
                "Tapo accepted the network connection but rejected the RTSP stream. "
                "Verify the separate Camera Account username and password."
            )

        # Opening an RTSP URL is not proof that video is usable. Decode one
        # complete frame before allowing the application to report Starting.
        success, frame = capture.read()
        if not success or frame is None or frame.size == 0:
            capture.release()
            raise RuntimeError(
                "Tapo connected but did not send a decodable video frame. "
                "Use Standard quality (stream2), close other camera viewers, and retry."
            )
        capture.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        return capture, frame

    def open(self):
        self.release()
        capture, first_frame = self._new_capture()
        self.cap = capture
        self._width = int(first_frame.shape[1])
        self._height = int(first_frame.shape[0])
        self._fps = max(0.0, float(capture.get(cv2.CAP_PROP_FPS)))
        self._stop_event = threading.Event()
        self._reader_failed = False
        self._last_error = ""
        self._latest_frame = first_frame
        self._frame_version = 1
        self._captured_at = time.monotonic()
        self._started_at = self._captured_at
        self._captured_frames = 1
        self._reader_thread = threading.Thread(
            target=self._reader_loop,
            args=(capture, self._stop_event),
            name="tapo-rtsp-reader",
            daemon=True,
        )
        self._reader_thread.start()
        return self

    def _reader_loop(self, capture, stop_event):
        while not stop_event.is_set():
            success, frame = capture.read()
            if not success or frame is None:
                with self._condition:
                    if stop_event is self._stop_event:
                        self._reader_failed = True
                        self._last_error = "The RTSP stream stopped sending frames."
                        self._condition.notify_all()
                return
            with self._condition:
                if stop_event is not self._stop_event:
                    return
                self._latest_frame = frame
                self._frame_version += 1
                self._captured_frames += 1
                self._captured_at = time.monotonic()
                self._condition.notify_all()

    def read_latest(self, after_version=-1, timeout=7):
        deadline = time.monotonic() + timeout
        with self._condition:
            while self._frame_version == after_version and not self._reader_failed:
                remaining = deadline - time.monotonic()
                if remaining <= 0 or self._stop_event.is_set():
                    break
                self._condition.wait(remaining)
            if self._latest_frame is None or self._reader_failed:
                return False, None, after_version
            version = self._frame_version
            if after_version >= 0:
                self._dropped_frames += max(0, version - after_version - 1)
            return True, self._latest_frame.copy(), version

    def reconnect(self, attempts=3, delay=2):
        last_error = None
        for attempt in range(1, attempts + 1):
            print(f"Reconnecting to Tapo camera ({attempt}/{attempts})...")
            try:
                self.open()
                self._reconnect_count += 1
                return self
            except RuntimeError as error:
                last_error = error
                self._last_error = str(error)
                if attempt < attempts:
                    time.sleep(delay * attempt)
        if last_error:
            print(f"Tapo reconnect failed: {last_error}")
        return None

    def release(self):
        self._stop_event.set()
        with self._condition:
            self._condition.notify_all()
        thread = self._reader_thread
        if thread is not None and thread.is_alive() and thread is not threading.current_thread():
            thread.join(timeout=(READ_TIMEOUT_MS / 1000) + 1)
        if self.cap is not None and (thread is None or not thread.is_alive()):
            self.cap.release()
        self.cap = None
        self._reader_thread = None

    def isOpened(self):
        return self.cap is not None and self.cap.isOpened() and not self._reader_failed

    def get(self, property_id):
        if property_id == cv2.CAP_PROP_FRAME_WIDTH:
            return self._width
        if property_id == cv2.CAP_PROP_FRAME_HEIGHT:
            return self._height
        if property_id == cv2.CAP_PROP_FPS:
            return self._fps
        if property_id == cv2.CAP_PROP_FRAME_COUNT:
            return 0
        return self.cap.get(property_id) if self.cap is not None else 0

    def metrics(self):
        with self._condition:
            now = time.monotonic()
            elapsed = max(1e-6, now - self._started_at) if self._started_at else 0
            age = max(0.0, now - self._captured_at) * 1000 if self._captured_at else None
            return {
                "capture_fps": round(self._captured_frames / elapsed, 2) if elapsed else 0.0,
                "dropped_frames": self._dropped_frames,
                "frame_age_ms": round(age, 1) if age is not None else None,
                "reconnect_count": self._reconnect_count,
                "reader_failed": self._reader_failed,
                "capture_error": self._last_error,
            }
