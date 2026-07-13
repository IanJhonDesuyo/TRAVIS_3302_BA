"""Phase 1 traffic-enforcer presence detection within a calibrated ROI."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class OfficerPresenceResult:
    status: str = "unknown"
    person_count: int = 0
    track_ids: tuple[int, ...] = ()


class OfficerPresenceDetector:
    """Debounce tracked-person presence inside the configured officer zone.

    Phase 1 intentionally does not classify uniforms or identity. A detection
    means only that a YOLO `person` is present in the camera's officer-post ROI.
    """

    def __init__(self, enabled: bool = False, settings=None):
        self.enabled = bool(enabled)
        self.settings = settings or {}
        self.presence_frames = max(1, int(self.settings.get("presence_frames", 3)))
        self.absence_frames = max(1, int(self.settings.get("absence_frames", 15)))
        self._present_streak = 0
        self._absent_streak = 0
        self._status = "unknown"

    def update(self, persons_in_zone) -> OfficerPresenceResult:
        if not self.enabled:
            return OfficerPresenceResult()

        track_ids = tuple(
            sorted({
                int(person.get("track_id", -1))
                for person in persons_in_zone
                if int(person.get("track_id", -1)) >= 0
            })
        )
        # Keep untracked person detections in the zone count. Track IDs improve
        # stability but must not be required for basic presence detection.
        person_count = len(persons_in_zone)

        if person_count > 0:
            self._present_streak += 1
            self._absent_streak = 0
            if self._present_streak >= self.presence_frames:
                self._status = "multiple" if person_count > 1 else "detected"
        else:
            self._absent_streak += 1
            self._present_streak = 0
            if self._absent_streak >= self.absence_frames:
                self._status = "none"

        return OfficerPresenceResult(
            status=self._status,
            person_count=person_count,
            track_ids=track_ids,
        )
