"""Stateful inbound/outbound line counting for tracked vehicles."""

from __future__ import annotations

from collections import deque

from utils import crossed_line


class DirectionCounter:
    """Count a track once, using horizontal travel to validate its direction.

    For the current camera orientation, leftward travel is inbound/northbound
    and rightward travel is outbound/southbound. The two configured lines are
    trigger zones; crossing the wrong line in the wrong direction never adds a
    count.
    """

    def __init__(
        self,
        inbound_line,
        outbound_line,
        history_size: int = 45,
        minimum_horizontal_travel: int = 6,
    ):
        self.inbound_line = inbound_line
        self.outbound_line = outbound_line
        self.history_size = max(2, int(history_size))
        self.minimum_horizontal_travel = max(1, int(minimum_horizontal_travel))
        self.inbound_count = 0
        self.outbound_count = 0
        self._counted_tracks: set[int] = set()
        self._history: dict[int, deque[tuple[int, int]]] = {}

    def update(self, track_id: int, point: tuple[int, int]) -> None:
        if track_id < 0:
            return

        history = self._history.setdefault(
            track_id,
            deque(maxlen=self.history_size),
        )
        history.append(point)

        if len(history) < 2:
            return

        # A vehicle is counted in exactly one direction. This also prevents a
        # vehicle that physically reaches both configured lines from being
        # added to both totals.
        if track_id in self._counted_tracks:
            return

        # Use the short tracked trajectory instead of only the last frame
        # pair. This still detects a crossing when inference skips frames or a
        # vehicle moves several pixels between processed frames.
        trajectory_start = history[0]
        horizontal_travel = point[0] - history[0][0]
        moving_left = horizontal_travel <= -self.minimum_horizontal_travel
        moving_right = horizontal_travel >= self.minimum_horizontal_travel

        # Both calibrated lines are counting gates. Direction is determined by
        # horizontal travel, not by which colored line is crossed first.
        crossed_counting_gate = (
            crossed_line(trajectory_start, point, self.inbound_line)
            or crossed_line(trajectory_start, point, self.outbound_line)
        )

        if moving_left and crossed_counting_gate:
            self._counted_tracks.add(track_id)
            self.inbound_count += 1
        elif moving_right and crossed_counting_gate:
            self._counted_tracks.add(track_id)
            self.outbound_count += 1

    def snapshot(self) -> tuple[int, int]:
        return self.inbound_count, self.outbound_count
