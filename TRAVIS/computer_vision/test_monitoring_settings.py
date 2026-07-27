import unittest
from unittest.mock import patch
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from alert_engine import AlertEngine
from congestion import get_congestion_level


class MonitoringSettingsTests(unittest.TestCase):
    def test_custom_congestion_thresholds(self):
        self.assertEqual(get_congestion_level(2, light_max=2, heavy_min=5), "Light")
        self.assertEqual(get_congestion_level(3, light_max=2, heavy_min=5), "Moderate")
        self.assertEqual(get_congestion_level(5, light_max=2, heavy_min=5), "Heavy")

    @patch("alert_engine.time.time")
    def test_alert_cooldown_suppresses_duplicates(self, now):
        engine = AlertEngine()
        engine.alert_delay = 5
        engine.cooldown = 300
        now.return_value = 1000
        self.assertEqual(engine.update("Heavy"), "WARNING")
        now.return_value = 1006
        self.assertEqual(engine.update("Heavy"), "ALERT")
        now.return_value = 1010
        self.assertEqual(engine.update("Heavy"), "COOLDOWN")
        now.return_value = 1307
        self.assertEqual(engine.update("Heavy"), "ALERT")


if __name__ == "__main__":
    unittest.main()
