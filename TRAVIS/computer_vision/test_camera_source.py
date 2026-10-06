import unittest
from pathlib import Path
import sys
from unittest.mock import patch

import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parent))

from camera_source import CameraSource


class CameraSourceTests(unittest.TestCase):
    @patch("camera_source.config.VIDEO_SOURCE", "tapo")
    def test_tapo_uses_latest_frame_reader(self):
        self.assertTrue(CameraSource().uses_latest_frame_reader)

    @patch("camera_source.config.VIDEO_SOURCE", "video")
    def test_uploaded_video_remains_sequential(self):
        self.assertFalse(CameraSource().uses_latest_frame_reader)

    @patch("camera_source.config.VIDEO_SOURCE", "tapo")
    def test_read_latest_skips_stale_versions(self):
        camera = CameraSource()
        camera._latest_frame = np.zeros((2, 2, 3), dtype=np.uint8)
        camera._frame_version = 8

        success, frame, version = camera.read_latest(after_version=3, timeout=0)

        self.assertTrue(success)
        self.assertEqual(version, 8)
        self.assertEqual(frame.shape, (2, 2, 3))
        self.assertEqual(camera.metrics()["dropped_frames"], 4)


if __name__ == "__main__":
    unittest.main()
