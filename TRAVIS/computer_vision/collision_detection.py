"""Isolated trajectory-based potential collision detector.

This module consumes existing ByteTrack results. It never runs YOLO itself and
can be disabled without affecting counting, congestion, streaming, or logging.
"""

from __future__ import annotations

from collections import deque
from dataclasses import dataclass
from itertools import combinations
from math import hypot
import time


@dataclass(frozen=True)
class CollisionResult:
    status: str = "none"
    track_ids: tuple[int, int] | None = None
    confidence: float = 0.0
    time_to_collision: float | None = None


def _bbox_iou(first, second) -> float:
    ax1, ay1, ax2, ay2 = first
    bx1, by1, bx2, by2 = second
    intersection_width = max(0, min(ax2, bx2) - max(ax1, bx1))
    intersection_height = max(0, min(ay2, by2) - max(ay1, by1))
    intersection = intersection_width * intersection_height
    first_area = max(0, ax2 - ax1) * max(0, ay2 - ay1)
    second_area = max(0, bx2 - bx1) * max(0, by2 - by1)
    union = first_area + second_area - intersection
    return intersection / union if union > 0 else 0.0


def _center_distance(first, second) -> float:
    return hypot(first[0] - second[0], first[1] - second[1])


def _bbox_diagonal(box) -> float:
    return hypot(box[2] - box[0], box[3] - box[1])


class CollisionDetector:
    """Conservative pair-risk state machine for a single camera stream."""

    def __init__(self, frame_size, settings=None, enabled: bool = False):
        self.width, self.height = frame_size
        self.frame_diagonal = max(1.0, hypot(self.width, self.height))
        self.settings = settings or {}
        self.enabled = bool(enabled)
        # Balanced CCTV defaults. Low-resolution streams frequently lose a
        # track shortly after contact, so require approach + contact but use a
        # shorter confirmation window than the original conservative preset.
        self.ttc_warning_seconds = float(self.settings.get("ttc_warning_seconds", 2.5))
        self.maximum_pair_distance = float(self.settings.get("maximum_pair_distance", 2.6))
        self.contact_iou = float(self.settings.get("contact_iou", 0.08))
        self.confirmation_iou = float(self.settings.get("confirmation_iou", 0.04))
        self.possible_frames = int(self.settings.get("possible_frames", 3))
        self.confirmed_frames = int(self.settings.get("confirmed_frames", 4))
        self.stationary_speed = float(self.settings.get("stationary_speed", 0.012))
        self.post_contact_speed = float(self.settings.get("post_contact_speed", 0.025))
        self.risk_memory_seconds = float(self.settings.get("risk_memory_seconds", 4.0))
        self.minimum_track_confidence = float(self.settings.get("minimum_track_confidence", 0.45))
        self.minimum_motorcycle_confidence = float(self.settings.get("minimum_motorcycle_confidence", 0.25))
        self.occlusion_confirmation_frames = int(self.settings.get("occlusion_confirmation_frames", 3))
        self.occlusion_window_seconds = float(self.settings.get("occlusion_window_seconds", 2.0))
        self.occlusion_max_distance = float(self.settings.get("occlusion_max_distance", 1.5))
        self.cooldown_seconds = float(self.settings.get("cooldown_seconds", 30))
        self.histories = {}
        self.pair_states = {}
        self.last_confirmed = {}

    def _append_history(self, track, timestamp):
        track_id = int(track["track_id"])
        history = self.histories.setdefault(track_id, deque(maxlen=12))
        history.append((timestamp, track["center"], track["bbox"]))

    def _normalized_speed(self, track_id: int) -> float:
        history = self.histories.get(track_id)
        if not history or len(history) < 2:
            return 0.0
        start_index = max(0, len(history) - 5)
        start_time, start_point, _ = history[start_index]
        end_time, end_point, _ = history[-1]
        elapsed = max(1e-6, end_time - start_time)
        return _center_distance(start_point, end_point) / elapsed / self.frame_diagonal

    def _pair_metrics(self, first, second):
        first_history = self.histories[first["track_id"]]
        second_history = self.histories[second["track_id"]]
        current_distance = _center_distance(first["center"], second["center"])
        box_scale = max(
            1.0,
            (_bbox_diagonal(first["bbox"]) + _bbox_diagonal(second["bbox"])) / 2,
        )
        scaled_distance = current_distance / box_scale
        iou = _bbox_iou(first["bbox"], second["bbox"])

        closing_speed = 0.0
        ttc = None
        if len(first_history) >= 2 and len(second_history) >= 2:
            previous_time = min(first_history[-2][0], second_history[-2][0])
            current_time = max(first_history[-1][0], second_history[-1][0])
            elapsed = max(1e-6, current_time - previous_time)
            previous_distance = _center_distance(
                first_history[-2][1],
                second_history[-2][1],
            )
            closing_speed = (previous_distance - current_distance) / elapsed / self.frame_diagonal
            if closing_speed > 1e-6:
                ttc = (current_distance / self.frame_diagonal) / closing_speed

        return scaled_distance, iou, closing_speed, ttc

    def update(self, vehicle_tracks, timestamp=None) -> CollisionResult:
        if not self.enabled:
            return CollisionResult()

        now = float(timestamp if timestamp is not None else time.monotonic())
        valid_tracks = []
        for track in vehicle_tracks:
            class_id = int(track.get("class_id", -1))
            minimum_confidence = (
                self.minimum_motorcycle_confidence
                if class_id == 3
                else self.minimum_track_confidence
            )
            if (
                int(track.get("track_id", -1)) >= 0
                and bool(track.get("inside_road_roi", True))
                and float(track.get("confidence", 0.0)) >= minimum_confidence
            ):
                valid_tracks.append(track)

        for track in valid_tracks:
            self._append_history(track, now)

        best_result = CollisionResult()
        seen_pairs = set()

        for first, second in combinations(valid_tracks, 2):
            pair = tuple(sorted((int(first["track_id"]), int(second["track_id"]))))
            seen_pairs.add(pair)
            scaled_distance, iou, closing_speed, ttc = self._pair_metrics(first, second)
            state = self.pair_states.setdefault(pair, {
                "risk": 0,
                "contact": 0,
                "last_seen": now,
                "last_approach": 0.0,
                "qualified_risk_at": 0.0,
                "last_distance": float("inf"),
                "occlusion": 0,
            })
            state["last_seen"] = now
            state["last_distance"] = scaled_distance
            state["occlusion"] = 0

            approaching_risk = (
                ttc is not None
                and 0 < ttc <= self.ttc_warning_seconds
                and closing_speed > 0
                and scaled_distance <= self.maximum_pair_distance
            )
            state["risk"] = state["risk"] + 1 if approaching_risk else max(0, state["risk"] - 1)
            if approaching_risk:
                state["last_approach"] = now
            if state["risk"] >= self.possible_frames:
                state["qualified_risk_at"] = now

            first_speed = self._normalized_speed(pair[0])
            second_speed = self._normalized_speed(pair[1])
            recent_approach = now - state["last_approach"] <= self.risk_memory_seconds
            confirmed_contact = (
                recent_approach
                and iou >= self.confirmation_iou
            )
            post_impact_slow = max(first_speed, second_speed) <= self.post_contact_speed
            if confirmed_contact:
                # A stopped pair confirms faster, while a moving side-impact
                # still qualifies only after sustained box contact.
                state["contact"] += 2 if post_impact_slow else 1
            else:
                state["contact"] = max(0, state["contact"] - 2)

            status = "none"
            confidence = 0.0
            if state["risk"] >= self.possible_frames:
                status = "possible"
                confidence = min(0.85, 0.45 + state["risk"] * 0.04)

            cooldown_elapsed = now - self.last_confirmed.get(pair, 0) >= self.cooldown_seconds
            if state["contact"] >= self.confirmed_frames and cooldown_elapsed:
                status = "confirmed"
                confidence = min(0.98, 0.75 + state["contact"] * 0.02)
                self.last_confirmed[pair] = now

            rank = {"none": 0, "possible": 1, "confirmed": 2}
            if rank[status] > rank[best_result.status] or confidence > best_result.confidence:
                best_result = CollisionResult(status, pair, confidence, ttc)

        # Smoke, severe overlap, or an abrupt camera occlusion can make one or
        # both vehicle detections disappear at the exact impact frame. Retain
        # a short, conservative confirmation window only for pairs that had a
        # sustained approach risk and were already very close before loss.
        for pair, state in self.pair_states.items():
            if pair in seen_pairs:
                continue
            recently_qualified = now - state.get("qualified_risk_at", 0.0) <= self.occlusion_window_seconds
            recently_visible = now - state["last_seen"] <= self.occlusion_window_seconds
            was_close = state.get("last_distance", float("inf")) <= self.occlusion_max_distance
            if recently_qualified and recently_visible and was_close:
                state["occlusion"] = state.get("occlusion", 0) + 1
            else:
                state["occlusion"] = 0

            cooldown_elapsed = now - self.last_confirmed.get(pair, 0) >= self.cooldown_seconds
            if state["occlusion"] >= self.occlusion_confirmation_frames and cooldown_elapsed:
                self.last_confirmed[pair] = now
                candidate = CollisionResult("confirmed", pair, 0.90, None)
                if best_result.status != "confirmed" or candidate.confidence > best_result.confidence:
                    best_result = candidate

        stale_pairs = [
            pair for pair, state in self.pair_states.items()
            if pair not in seen_pairs and now - state["last_seen"] > max(2.0, self.occlusion_window_seconds)
        ]
        for pair in stale_pairs:
            self.pair_states.pop(pair, None)

        active_ids = {int(track["track_id"]) for track in valid_tracks}
        stale_ids = [
            track_id for track_id, history in self.histories.items()
            if track_id not in active_ids and history and now - history[-1][0] > 2.0
        ]
        for track_id in stale_ids:
            self.histories.pop(track_id, None)

        return best_result
