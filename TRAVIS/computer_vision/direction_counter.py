"""Stateful inbound/outbound line counting for tracked vehicles."""

from __future__ import annotations

from collections import deque

from utils import crossed_line


class DirectionCounter:
    """Count a track once when it crosses its direction-specific line.

    Screen coordinates increase downwards. A vehicle travelling from the
    bottom of the image to the top is inbound and must cross the inbound line.
    A vehicle travelling from the top to the bottom is outbound and must cross
    the outbound line. Crossing the other line never adds to that direction.
    """

    def __init__(
        self,
        inbound_line,
        outbound_line,
        history_size: int = 45,
        minimum_vertical_travel: int = 6,
    ):
        self.inbound_line = inbound_line
        self.outbound_line = outbound_line
        self.history_size = max(2, int(history_size))
        self.minimum_vertical_travel = max(1, int(minimum_vertical_travel))
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

        # Look back through the short trajectory for a point on the required
        # origin side. This retains crossings when inference skips frames and
        # avoids losing a valid crossing when a one-frame displacement is less
        # than the jitter threshold.
        inbound_origin = self._find_origin(history, self.inbound_line, "below")
        outbound_origin = self._find_origin(history, self.outbound_line, "above")

        inbound_crossing = (
            inbound_origin is not None
            and self._side_of_line(point, self.inbound_line) == "above"
            and inbound_origin[1] - point[1] >= self.minimum_vertical_travel
            and crossed_line(inbound_origin, point, self.inbound_line)
        )
        outbound_crossing = (
            outbound_origin is not None
            and self._side_of_line(point, self.outbound_line) == "below"
            and point[1] - outbound_origin[1] >= self.minimum_vertical_travel
            and crossed_line(outbound_origin, point, self.outbound_line)
        )

        if inbound_crossing:
            self._counted_tracks.add(track_id)
            self.inbound_count += 1
        elif outbound_crossing:
            self._counted_tracks.add(track_id)
            self.outbound_count += 1

    @classmethod
    def _find_origin(cls, history, line, required_side: str):
        """Return the most recent earlier point on the requested line side."""

        for candidate in reversed(list(history)[:-1]):
            if cls._side_of_line(candidate, line) == required_side:
                return candidate
        return None

    @staticmethod
    def _side_of_line(point, line) -> str:
        """Classify a point as visually above or below a non-vertical line.

        The result is independent of which endpoint was saved first. A
        vertical line cannot define screen-top and screen-bottom regions, so
        it intentionally returns ``on`` and cannot produce a directional
        count until its calibration is corrected.
        """

        (x1, y1), (x2, y2) = line
        horizontal_span = x2 - x1
        if horizontal_span == 0:
            return "on"

        cross_product = horizontal_span * (point[1] - y1) - (y2 - y1) * (point[0] - x1)
        normalized = cross_product if horizontal_span > 0 else -cross_product
        if abs(normalized) < 1e-6:
            return "on"
        return "below" if normalized > 0 else "above"

    def snapshot(self) -> tuple[int, int]:
        return self.inbound_count, self.outbound_count
