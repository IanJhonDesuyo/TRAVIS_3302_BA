"""
TRAVIS Alert Engine
Handles congestion alert timing and cooldown.
"""

import time


class AlertEngine:

    def __init__(self):

        self.heavy_started = None
        self.last_alert_time = 0

        self.alert_delay = 5          # seconds
        self.cooldown = 300           # 5 minutes

    def update(self, congestion_level):

        current_time = time.time()

        # -------------------------
        # NORMAL
        # -------------------------
        if congestion_level != "Heavy":

            self.heavy_started = None

            return "NORMAL"

        # -------------------------
        # First Heavy Detection
        # -------------------------
        if self.heavy_started is None:

            self.heavy_started = current_time

            return "WARNING"

        # -------------------------
        # Still counting...
        # -------------------------
        elapsed = current_time - self.heavy_started

        if elapsed < self.alert_delay:

            return "WARNING"

        # -------------------------
        # Cooldown
        # -------------------------
        if current_time - self.last_alert_time >= self.cooldown:

            self.last_alert_time = current_time

            return "ALERT"

        # -------------------------
        # Heavy but already alerted. Do not report another ALERT until the
        # configured cooldown expires; the API uses this state to decide
        # whether a new notification may be created.
        # -------------------------
        return "COOLDOWN"
