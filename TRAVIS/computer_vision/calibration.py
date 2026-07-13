"""Per-source calibration profiles with behavior-compatible defaults."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from utils import scale_line


class CalibrationProfile:
    def __init__(
        self,
        width: int,
        height: int,
        inbound_line,
        outbound_line,
        road_roi=None,
        collision: dict[str, Any] | None = None,
        officer_zone=None,
        officer: dict[str, Any] | None = None,
        name: str = "Legacy config fallback",
        source_path: Path | None = None,
    ):
        self.width = width
        self.height = height
        self.name = name
        self.source_path = source_path
        self.inbound_line = scale_line(inbound_line, width, height)
        self.outbound_line = scale_line(outbound_line, width, height)
        self.road_roi = self._scale_polygon(
            road_roi or [(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)]
        )
        self.collision = collision or {}
        self.officer_zone = self._scale_polygon(officer_zone) if officer_zone else []
        self.officer = officer or {}

    def _scale_polygon(self, points):
        return [
            (int(float(x) * self.width), int(float(y) * self.height))
            for x, y in points
        ]

    @property
    def collision_enabled(self) -> bool:
        return bool(self.collision.get("enabled", False))

    @property
    def officer_enabled(self) -> bool:
        return bool(self.officer.get("enabled", False) and self.officer_zone)

    def contains_road_point(self, point: tuple[int, int]) -> bool:
        """Return whether a point lies inside the calibrated road polygon."""
        return self._contains_point(point, self.road_roi)

    def contains_officer_point(self, point: tuple[int, int]) -> bool:
        """Return whether a person anchor lies inside the officer-post ROI."""
        return self._contains_point(point, self.officer_zone) if self.officer_zone else False

    @staticmethod
    def _contains_point(point: tuple[int, int], points) -> bool:
        x, y = point
        inside = False
        previous = points[-1]
        for current in points:
            x1, y1 = previous
            x2, y2 = current
            crosses_y = (y1 > y) != (y2 > y)
            if crosses_y:
                boundary_x = (x2 - x1) * (y - y1) / (y2 - y1) + x1
                if x < boundary_x:
                    inside = not inside
            previous = current
        return inside


def load_calibration(
    width: int,
    height: int,
    legacy_inbound_line,
    legacy_outbound_line,
    profile_path: str | None = None,
) -> CalibrationProfile:
    """Load JSON calibration or retain the exact legacy lines as fallback."""

    if not profile_path:
        return CalibrationProfile(
            width,
            height,
            legacy_inbound_line,
            legacy_outbound_line,
        )

    path = Path(profile_path).expanduser()
    if not path.is_absolute() and not path.exists():
        path = Path(__file__).resolve().parent / path
    path = path.resolve()
    with path.open("r", encoding="utf-8") as profile_file:
        data = json.load(profile_file)

    inbound_line = data.get("inbound_line", legacy_inbound_line)
    outbound_line = data.get("outbound_line", legacy_outbound_line)

    return CalibrationProfile(
        width=width,
        height=height,
        inbound_line=inbound_line,
        outbound_line=outbound_line,
        road_roi=data.get("road_roi"),
        collision=data.get("collision"),
        officer_zone=data.get("officer_zone"),
        officer=data.get("officer"),
        name=str(data.get("profile_name", path.stem)),
        source_path=path,
    )
