"""
TRAVIS Alert Engine
Handles congestion alert timing and cooldown.
"""

import time


class AlertEngine:

    def __init__(self):

        self.heavy_started = None
        self.last_alert_time = 0
        self.alert_active = False
        self.recovery_started = None

        self.alert_delay = 5          # seconds
        self.cooldown = 300           # 5 minutes

    def update(self, congestion_level):

        current_time = time.time()

        # -------------------------
        # NORMAL
        # -------------------------
        if congestion_level != "Heavy":
            if not self.alert_active:
                self.heavy_started = None
                return "NORMAL"
            if self.recovery_started is None:
                self.recovery_started = current_time
            if current_time - self.recovery_started < 10:
                return "ALERT"
            self.alert_active = False
            self.heavy_started = None
            self.recovery_started = None
            return "NORMAL"

        self.recovery_started = None

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

        # Keep the live state visible for as long as congestion remains heavy.
        # The monitoring-log API owns notification deduplication/cooldown. A
        # one-frame ALERT pulse can be missed by the one-second browser poll.
        self.alert_active = True
        return "ALERT"
