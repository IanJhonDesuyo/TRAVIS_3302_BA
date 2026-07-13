"""Stateful inbound/outbound line counting for tracked vehicles."""

from __future__ import annotations

from collections import deque

from utils import crossed_line


class DirectionCounter:
    """Count each ByteTrack ID at most once per configured direction line."""

    def __init__(self, inbound_line, outbound_line, history_size: int = 10):
        self.inbound_line = inbound_line
        self.outbound_line = outbound_line
        self.history_size = max(2, int(history_size))
        self.inbound_count = 0
        self.outbound_count = 0
        self._counted_inbound: set[int] = set()
        self._counted_outbound: set[int] = set()
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

        previous_point = history[-2]

        if (
            track_id not in self._counted_inbound
            and crossed_line(previous_point, point, self.inbound_line)
        ):
            self._counted_inbound.add(track_id)
            self.inbound_count += 1

        if (
            track_id not in self._counted_outbound
            and crossed_line(previous_point, point, self.outbound_line)
        ):
            self._counted_outbound.add(track_id)
            self.outbound_count += 1

    def snapshot(self) -> tuple[int, int]:
        return self.inbound_count, self.outbound_count
