"""Offline OCR and coordinate-aware field extraction for TRAVIS tickets."""
from __future__ import annotations

import difflib
import json
import re
import sys
from datetime import date
from dataclasses import dataclass
from pathlib import Path

import cv2
import numpy as np
from rapidocr_onnxruntime import RapidOCR


LABELS = {
    "ticket_number": ["ticket no", "ticket number", "citation no", "citation number"],
    "driver_name": ["driver name", "driver's name", "drivers name", "name of driver"],
    "driver_address": ["address", "driver address"],
    "date_of_birth": ["date of birth", "birth date", "dob"],
    "license_number": ["license number", "driver license number", "driver's license number", "license no", "dl no"],
    "license_expiry_date": ["license expiry date", "expiry date", "expiration date"],
    "license_remarks": ["license remarks", "confiscated remarks"],
    "plate_number": ["plate number", "plate no"],
    "vehicle_owner": ["owner of vehicle", "vehicle owner"],
    "vehicle_registration_number": ["vehicle registration number", "registration number", "or cr number"],
    "vehicle_color": ["color of vehicle", "vehicle color"],
    "insurance_policy_number": ["insurance policy number", "insurance policy no"],
    "coding_sticker_number": ["coding sticker number", "coding sticker no"],
    "vehicle_toda": ["toda"],
    "vehicle_type": ["vehicle type", "type of vehicle"],
    "violation_type": ["violation type", "nature of violation", "offense"],
    "location": ["violation location", "place of violation", "place of violation", "location"],
    "violation_date": ["date and time of violation", "date of violation", "violation date"],
    "violation_time": ["time of violation", "violation time"],
    "penalty_amount": ["penalty amount", "amount due", "penalty fee", "fine"],
    "ticket_remarks": ["remarks"],
    # The official form prints a section heading followed by generic "Name"
    # and "Position" labels. Those are extracted within that section only so
    # they cannot be confused with the driver's name near the top of the form.
    "apprehending_officer_name": ["officer name"],
    "apprehending_officer_position": ["officer position"],
}

VEHICLE_TYPES = ["Motorcycle", "Car", "SUV", "Jeepney", "Tricycle", "Van", "Truck", "Bus", "Other"]
VEHICLE_TYPE_ALIASES = {
    "motor": "Motorcycle",
    "motors": "Motorcycle",
    "motor cycle": "Motorcycle",
    "motorbike": "Motorcycle",
}
VIOLATION_TYPES = [
    "No Driver's License", "Failure to Carry Driver's License", "Invalid / Delinquent Driver's License",
    "Unregistered Motor Vehicle", "Nuisance Muffler", "Disregarding Traffic Sign / Officer",
    "Reckless Driving", "Colorum", "Illegal Parking", "Illegal Terminal", "Obstruction", "OR / CR Not Carried",
    "No Canvas Cover", "Operating Out of Line", "Overloading", "Overcharging",
    "Loading / Unloading in Prohibited Zone", "Refusal to Convey Passenger",
    "Driving with Sleeveless Shirt / Shorts", "Not Wearing Shoes", "No Side Mirror", "Arrogant Driver",
    "Driving Under the Influence of Liquor", "Coding Violation", "Other Traffic Violation",
    "LOI 1482 Highway",
]
VIOLATION_ALIASES = {
    "no canvass cover": "No Canvas Cover",
    "loading unloading prohibited zone": "Loading / Unloading in Prohibited Zone",
    "driving under the influence of liquor": "Driving Under the Influence of Liquor",
    "driving under influence of liquor": "Driving Under the Influence of Liquor",
    "loi 1482 highway": "LOI 1482 Highway",
    "others specify": "Other Traffic Violation",
    "other specify": "Other Traffic Violation",
    "arrogant": "Arrogant Driver",
}
PENALTY_FEES = {100, 200, 300, 500, 750, 1000, 1500, 2000, 2500, 3000, 5000}


def clean(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip(" :-_|.,")


def normalized(value: str) -> str:
    value = value.lower().replace("’", "'")
    return re.sub(r"[^a-z0-9]+", " ", value).strip()


@dataclass(frozen=True)
class OCRItem:
    text: str
    confidence: float
    left: float
    top: float
    right: float
    bottom: float

    @property
    def center_x(self) -> float:
        return (self.left + self.right) / 2

    @property
    def center_y(self) -> float:
        return (self.top + self.bottom) / 2

    @property
    def height(self) -> float:
        return max(1, self.bottom - self.top)


def to_item(entry: list) -> OCRItem:
    points = entry[0]
    xs = [float(point[0]) for point in points]
    ys = [float(point[1]) for point in points]
    return OCRItem(clean(str(entry[1])), float(entry[2]), min(xs), min(ys), max(xs), max(ys))


def transformed_item(entry: list, scale: float, offset_x: float, offset_y: float) -> OCRItem:
    """Map OCR coordinates from an enlarged crop back to the full ticket."""

    item = to_item(entry)
    return OCRItem(
        item.text,
        item.confidence,
        item.left / scale + offset_x,
        item.top / scale + offset_y,
        item.right / scale + offset_x,
        item.bottom / scale + offset_y,
    )


def merge_items(groups: list[list[OCRItem]]) -> list[OCRItem]:
    """Merge repeat detections from preprocessing passes in reading order."""
    merged: list[OCRItem] = []
    for item in sorted((item for group in groups for item in group), key=lambda value: value.confidence, reverse=True):
        duplicate = next((existing for existing in merged
            if normalized(existing.text) == normalized(item.text)
            and abs(existing.center_x - item.center_x) <= max(existing.height, item.height) * 1.5
            and abs(existing.center_y - item.center_y) <= max(existing.height, item.height) * 1.5), None)
        if duplicate is None:
            merged.append(item)
    return sorted(merged, key=lambda value: (round(value.center_y / max(value.height, 1)), value.left))


def order_document_corners(points):
    """Return quadrilateral corners as top-left, top-right, bottom-right, bottom-left."""
    points = np.asarray(points, dtype="float32").reshape(4, 2)
    ordered = np.zeros((4, 2), dtype="float32")
    totals = points.sum(axis=1)
    differences = np.diff(points, axis=1).reshape(-1)
    ordered[0] = points[np.argmin(totals)]
    ordered[2] = points[np.argmax(totals)]
    ordered[1] = points[np.argmin(differences)]
    ordered[3] = points[np.argmax(differences)]
    return ordered


def document_warp(image):
    """Find the paper boundary and flatten a photographed ticket like camera document mode."""
    height, width = image.shape[:2]
    longest = max(height, width)
    scale = min(1.0, 1200.0 / longest)
    preview = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA) if scale < 1 else image.copy()
    gray = cv2.cvtColor(preview, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 45, 140)
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, np.ones((7, 7), np.uint8), iterations=2)
    contours = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)[0]
    image_area = float(preview.shape[0] * preview.shape[1])
    document = None
    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:15]:
        if cv2.contourArea(contour) < image_area * 0.20:
            break
        perimeter = cv2.arcLength(contour, True)
        polygon = cv2.approxPolyDP(contour, 0.02 * perimeter, True)
        if len(polygon) == 4 and cv2.isContourConvex(polygon):
            document = polygon.reshape(4, 2).astype("float32") / scale
            break
    if document is None:
        return image, False

    top_left, top_right, bottom_right, bottom_left = order_document_corners(document)
    target_width = int(max(np.linalg.norm(bottom_right - bottom_left), np.linalg.norm(top_right - top_left)))
    target_height = int(max(np.linalg.norm(top_right - bottom_right), np.linalg.norm(top_left - bottom_left)))
    if target_width < 400 or target_height < 400:
        return image, False
    destination = np.array([
        [0, 0], [target_width - 1, 0],
        [target_width - 1, target_height - 1], [0, target_height - 1],
    ], dtype="float32")
    transform = cv2.getPerspectiveTransform(
        np.array([top_left, top_right, bottom_right, bottom_left], dtype="float32"), destination
    )
    return cv2.warpPerspective(image, transform, (target_width, target_height)), True


def shadow_normalized_gray(image):
    """Flatten uneven phone-camera lighting without erasing ballpoint ink."""

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    background = cv2.GaussianBlur(gray, (0, 0), 35)
    normalized_gray = cv2.divide(gray, background, scale=255)
    return cv2.createCLAHE(2.3, (8, 8)).apply(normalized_gray)


def deskew_ticket(image):
    """Straighten the printed table lines after coarse 90-degree rotation.

    Official tickets contain many long horizontal rules. Their median angle is
    more reliable than handwriting for correcting the small camera tilt that
    remains after perspective correction. Large angles are intentionally
    ignored because coarse orientation is handled separately.
    """

    height, width = image.shape[:2]
    gray = shadow_normalized_gray(image)
    edges = cv2.Canny(gray, 50, 150)
    lines = cv2.HoughLinesP(
        edges,
        1,
        np.pi / 180,
        threshold=max(60, int(width * 0.12)),
        minLineLength=max(80, int(width * 0.28)),
        maxLineGap=max(12, int(width * 0.025)),
    )
    angles: list[float] = []
    for x1, y1, x2, y2 in (lines.reshape(-1, 4) if lines is not None else []):
        angle = float(np.degrees(np.arctan2(y2 - y1, x2 - x1)))
        if -10.0 <= angle <= 10.0:
            angles.append(angle)
    if len(angles) < 3:
        return image, 0.0

    angle = float(np.median(angles))
    if abs(angle) < 0.25:
        return image, 0.0

    center = (width / 2.0, height / 2.0)
    matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    cosine, sine = abs(matrix[0, 0]), abs(matrix[0, 1])
    target_width = int(height * sine + width * cosine)
    target_height = int(height * cosine + width * sine)
    matrix[0, 2] += target_width / 2.0 - center[0]
    matrix[1, 2] += target_height / 2.0 - center[1]
    corrected = cv2.warpAffine(
        image,
        matrix,
        (target_width, target_height),
        flags=cv2.INTER_CUBIC,
        borderMode=cv2.BORDER_REPLICATE,
    )
    return corrected, round(angle, 3)


def preprocess_variants(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(2.5, (8, 8)).apply(gray)
    sharpened = cv2.addWeighted(clahe, 1.8, cv2.GaussianBlur(clahe, (0, 0), 2), -0.8, 0)
    # Divide by a broad background estimate to remove phone/camera shadows
    # while retaining faint ballpoint handwriting.
    shadow_free = shadow_normalized_gray(image)
    threshold = cv2.adaptiveThreshold(
        shadow_free, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 35, 11
    )
    # Color helps preserve blue/black ballpoint ink that can disappear when a
    # bright paper ticket is reduced directly to binary pixels.
    return [image, clahe, sharpened, shadow_free, threshold]


def recognize_ticket_regions(image, engine) -> list[OCRItem]:
    """Re-read dense official-form sections at higher effective resolution.

    Full-page OCR is good at headings but often loses small handwriting after
    the tall ticket is resized. These overlapping crops preserve the original
    coordinate system while giving personal, violation, vehicle, and officer
    rows a second, enlarged reading pass.
    """

    height, width = image.shape[:2]
    if height <= width * 1.12:
        return []

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    background = cv2.GaussianBlur(gray, (0, 0), 35)
    normalized_page = cv2.divide(gray, background, scale=255)
    normalized_page = cv2.createCLAHE(2.3, (8, 8)).apply(normalized_page)
    regions = (
        (0.07, 0.29),  # driver and licence details
        (0.24, 0.61),  # two-column traffic-violation checklist
        (0.56, 0.75),  # vehicle, place, date, and offence rows
        (0.73, 0.97),  # narrative and apprehending-officer section
    )
    scale = 1.55
    detected: list[OCRItem] = []
    for top_ratio, bottom_ratio in regions:
        top = max(0, int(height * top_ratio))
        bottom = min(height, int(height * bottom_ratio))
        crop = normalized_page[top:bottom, :]
        enlarged = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        result, _ = engine(enlarged)
        detected.extend(
            transformed_item(entry, scale, 0, top)
            for entry in (result or [])
            if len(entry) >= 3 and float(entry[2]) >= 0.24
        )
    return detected


def orientation_score(items: list[OCRItem]) -> float:
    """Score a rotation using stable words printed on the official ticket."""
    anchors = (
        "traffic citation ticket", "traffic violation", "driver s name", "license no",
        "license expiry date", "owner of vehicle", "plate number", "date and time",
        "apprehending arresting officer", "remarks",
    )
    score = 0.0
    for item in items:
        text = normalized(item.text)
        for anchor in anchors:
            similarity = difflib.SequenceMatcher(None, text, anchor).ratio()
            if anchor in text or text in anchor:
                similarity = max(similarity, 0.9)
            if similarity >= 0.62:
                score += similarity * max(0.35, item.confidence)
    return score


def normalize_orientation(image, engine):
    """Rotate phone photos to the ticket's natural reading direction."""
    best_image = image
    best_items: list[OCRItem] = []
    best_score = -1.0
    candidates = [image, cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE),
                  cv2.rotate(image, cv2.ROTATE_180), cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE)]
    for candidate in candidates:
        gray = shadow_normalized_gray(candidate)
        result, _ = engine(gray)
        items = [to_item(entry) for entry in (result or []) if len(entry) >= 3 and float(entry[2]) >= 0.28]
        # The official ticket is portrait when upright. This small tie-breaker
        # helps dark/blurred photos where only one or two printed anchors are
        # recognized, while anchor text still decides between 0 and 180 degrees.
        portrait_bonus = 0.35 if candidate.shape[0] > candidate.shape[1] * 1.08 else 0.0
        score = orientation_score(items) + portrait_bonus
        if score > best_score:
            best_image, best_items, best_score = candidate, items, score
    return best_image, best_items


def label_match(text: str) -> tuple[str, str] | None:
    # OCR frequently substitutes vertical strokes in bold printed labels.
    source = text.replace("’", "'").replace("!", "I").replace("|", "I")
    for field, aliases in LABELS.items():
        for alias in sorted(aliases, key=len, reverse=True):
            match = re.match(rf"^\s*{re.escape(alias)}\s*(?:no\.?\s*)?[:\-]?\s*(.*)$", source, re.I)
            if match:
                return field, clean(match.group(1))

    # Printed labels on carbon-copy tickets are commonly read with one or two
    # damaged characters (for example "DR!VER'S NAME"). Match only the label
    # prefix and use a stricter threshold for short/generic labels to avoid
    # treating ordinary handwritten values as field names.
    source_normalized = normalized(source)
    source_tokens = source_normalized.split()
    best: tuple[float, str] | None = None
    for field, aliases in LABELS.items():
        for alias in aliases:
            alias_normalized = normalized(alias)
            alias_tokens = alias_normalized.split()
            if not alias_tokens or len(source_tokens) < len(alias_tokens):
                continue
            prefix = " ".join(source_tokens[:len(alias_tokens)])
            similarity = difflib.SequenceMatcher(None, prefix, alias_normalized).ratio()
            threshold = 0.88 if len(alias_normalized) < 9 else 0.78
            if similarity >= threshold and (best is None or similarity > best[0]):
                best = (similarity, field)
    if best:
        return best[1], ""
    return None


def spatial_value(label: OCRItem, items: list[OCRItem], label_indexes: set[int]) -> str:
    choices: list[tuple[float, OCRItem]] = []
    next_labels = [
        candidate for index, candidate in enumerate(items)
        if index in label_indexes and candidate is not label
        and candidate.left > label.right
        and abs(candidate.center_y - label.center_y) <= max(label.height, candidate.height) * 0.9
    ]
    row_boundary = min((candidate.left for candidate in next_labels), default=float("inf"))
    lower_labels = [
        candidate for index, candidate in enumerate(items)
        if index in label_indexes and candidate is not label
        and candidate.top > label.bottom
    ]
    next_row_boundary = min((candidate.top for candidate in lower_labels), default=float("inf"))
    max_vertical_gap = max(80.0, label.height * 4.0)
    for index, candidate in enumerate(items):
        if index in label_indexes or candidate is label or not candidate.text:
            continue
        row_tolerance = max(label.height, candidate.height) * 0.85
        if (candidate.left >= label.right - 8 and candidate.left < row_boundary
                and abs(candidate.center_y - label.center_y) <= row_tolerance):
            choices.append(((candidate.left - label.right) + abs(candidate.center_y - label.center_y) * 2, candidate))
            continue
        horizontal_alignment = abs(candidate.center_x - label.center_x)
        if (candidate.top >= label.bottom - 4
                and candidate.top < next_row_boundary
                and candidate.top - label.bottom <= max_vertical_gap
                and horizontal_alignment <= max(90, (label.right - label.left) * 0.8)):
            choices.append(((candidate.top - label.bottom) * 2 + horizontal_alignment, candidate))
    return min(choices, key=lambda choice: choice[0])[1].text if choices else ""


def closest_allowed(value: str, allowed: list[str], cutoff: float = 0.74) -> str:
    target = normalized(value)
    if not target:
        return ""
    for option in allowed:
        option_normalized = normalized(option)
        if target == option_normalized or (len(target) >= 5 and target in option_normalized):
            return option
    matches = difflib.get_close_matches(target, [normalized(option) for option in allowed], n=1, cutoff=cutoff)
    if not matches:
        return ""
    return next(option for option in allowed if normalized(option) == matches[0])


def closest_violation(value: str, cutoff: float = 0.60) -> str:
    target = normalized(value)
    if target in VIOLATION_ALIASES:
        return VIOLATION_ALIASES[target]
    alias_matches = difflib.get_close_matches(target, VIOLATION_ALIASES.keys(), n=1, cutoff=cutoff)
    if alias_matches:
        return VIOLATION_ALIASES[alias_matches[0]]
    return closest_allowed(value, VIOLATION_TYPES, cutoff)


MONTHS = {
    "jan": 1, "january": 1, "feb": 2, "february": 2, "mar": 3, "march": 3,
    "apr": 4, "april": 4, "may": 5, "jun": 6, "june": 6, "jul": 7,
    "july": 7, "aug": 8, "august": 8, "sep": 9, "sept": 9, "september": 9,
    "oct": 10, "october": 10, "nov": 11, "november": 11, "dec": 12, "december": 12,
}


def normalize_date(value: str) -> str:
    """Accept handwritten-style numeric dates and dates containing month words."""
    source = clean(value).lower().replace(",", " ")
    numeric = re.search(r"\b(\d{1,2})\s*[./-]\s*(\d{1,2})\s*[./-]\s*(\d{2,4})\b", source)
    month, day, year = (0, 0, 0)
    if numeric:
        month, day, year = (int(part) for part in numeric.groups())
    else:
        month_pattern = "|".join(sorted(MONTHS, key=len, reverse=True))
        month_first = re.search(rf"\b({month_pattern})\.?\s+(\d{{1,2}})(?:st|nd|rd|th)?\s+(\d{{2,4}})\b", source)
        day_first = re.search(rf"\b(\d{{1,2}})(?:st|nd|rd|th)?\s+({month_pattern})\.?\s+(\d{{2,4}})\b", source)
        if month_first:
            month, day, year = MONTHS[month_first.group(1)], int(month_first.group(2)), int(month_first.group(3))
        elif day_first:
            day, month, year = int(day_first.group(1)), MONTHS[day_first.group(2)], int(day_first.group(3))
        else:
            return ""
    year += 2000 if year < 50 else (1900 if year < 100 else 0)
    try:
        return date(year, month, day).isoformat()
    except ValueError:
        return ""


def normalize_time(value: str) -> str:
    """Return a 24-hour time from a handwritten date/time field."""

    source = clean(value).lower()
    matches = list(re.finditer(r"\b(\d{1,2})\s*[:.]\s*(\d{2})\s*(a\.?m\.?|p\.?m\.?)?\b", source))
    if not matches:
        return ""
    hour, minute = int(matches[-1].group(1)), int(matches[-1].group(2))
    meridiem = re.sub(r"[^apm]", "", matches[-1].group(3) or "")
    if minute > 59 or hour > (12 if meridiem else 23) or hour == 0 and meridiem:
        return ""
    if meridiem.startswith("p") and hour < 12:
        hour += 12
    elif meridiem.startswith("a") and hour == 12:
        hour = 0
    return f"{hour:02d}:{minute:02d}"


def violation_section_bounds(items: list[OCRItem], image_height: int) -> tuple[float, float] | None:
    """Return the printed violation-list bounds, failing closed when its heading is unreadable."""
    headings = [
        item for item in items
        if normalized(item.text) in {"traffic violation", "traffic violations"}
    ]
    if not headings:
        return None
    heading = max(headings, key=lambda item: item.center_y)
    end_markers = [
        item.top for item in items
        if item.top > heading.bottom
        and (
            normalized(item.text).startswith("date and time of violation")
            or normalized(item.text).startswith("owner of vehicle")
            or normalized(item.text) == "remarks"
        )
    ]
    bottom = min(end_markers) if end_markers else min(float(image_height), heading.bottom + image_height * 0.48)
    return heading.bottom, bottom


def detect_checked_violations(image, items: list[OCRItem]) -> list[dict]:
    """Detect ink inside the printed checkbox immediately left of each offence label."""
    gray = shadow_normalized_gray(image)
    binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    section = violation_section_bounds(items, binary.shape[0])
    if section is None:
        return []
    section_top, section_bottom = section
    # Measure every checkbox before deciding. Carbon-copy shadows and printed
    # rules can leave a little ink in all empty squares, so relative comparison
    # is safer than classifying every square independently.
    measurements: dict[str, tuple[float, float]] = {}
    for item in items:
        if not section_top <= item.center_y <= section_bottom:
            continue
        violation = closest_violation(item.text, 0.60)
        if not violation:
            continue
        h = max(12, int(item.height))
        # On the supplied official ticket, the printed square is often around
        # 1.5-2.5 text heights left of its label. The wider range also survives
        # perspective correction and imperfect OCR text boxes.
        x2 = max(1, int(item.left - h * 0.12))
        x1 = max(0, int(item.left - h * 2.8))
        y1 = max(0, int(item.center_y - h * 1.0))
        y2 = min(binary.shape[0], int(item.center_y + h * 1.0))
        region = binary[y1:y2, x1:x2]
        if region.size == 0 or region.shape[0] < 6 or region.shape[1] < 6:
            continue
        # Ignore the printed square border; a tick/cross leaves ink in its interior.
        # Use the rightmost square-like contour, which is the checkbox nearest
        # the label. This avoids measuring neighboring table rules as ink.
        contours = cv2.findContours(region, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)[0]
        boxes: list[tuple[int, int, int, int]] = []
        for contour in contours:
            box_x, box_y, box_w, box_h = cv2.boundingRect(contour)
            if (h * 0.65 <= box_w <= h * 2.4 and h * 0.65 <= box_h <= h * 2.4
                    and 0.55 <= box_w / max(1, box_h) <= 1.55):
                boxes.append((box_x, box_y, box_w, box_h))
        if boxes:
            box_x, box_y, box_w, box_h = max(boxes, key=lambda box: box[0] + box[2])
            margin_y = max(2, int(box_h * 0.25))
            margin_x = max(2, int(box_w * 0.25))
            inner = region[
                box_y + margin_y:box_y + box_h - margin_y,
                box_x + margin_x:box_x + box_w - margin_x,
            ]
        else:
            # A missing square contour means the crop is probably a table
            # rule or nearby printed text. Never infer a checked offence from
            # that ambiguous ink.
            continue
        if inner.size == 0:
            continue
        ink_ratio = float(cv2.countNonZero(inner)) / float(inner.size)
        components, _, stats, _ = cv2.connectedComponentsWithStats(inner, 8)
        largest_mark = int(stats[1:, cv2.CC_STAT_AREA].max()) if components > 1 else 0
        # A printed empty box has an almost blank interior. Requiring both ink
        # density and a connected pen stroke prevents every printed option from
        # being returned merely because its checkbox border was detected.
        component_ratio = largest_mark / float(inner.size)
        previous = measurements.get(violation)
        if previous is None or (ink_ratio + component_ratio) > sum(previous):
            measurements[violation] = (ink_ratio, component_ratio)

    if not measurements:
        return []

    densities = np.array([value[0] for value in measurements.values()], dtype=float)
    components = np.array([value[1] for value in measurements.values()], dtype=float)
    density_median = float(np.median(densities))
    component_median = float(np.median(components))
    density_mad = float(np.median(np.abs(densities - density_median)))
    component_mad = float(np.median(np.abs(components - component_median)))
    density_floor = 0.085
    component_floor = 0.035
    if len(measurements) >= 3:
        density_floor = max(density_floor, density_median + max(0.035, density_mad * 3.0))
        component_floor = max(component_floor, component_median + max(0.025, component_mad * 2.5))

    detected: dict[str, float] = {}
    for violation, (ink_ratio, component_ratio) in measurements.items():
        if ink_ratio >= density_floor and component_ratio >= component_floor:
            separation = max(0.0, ink_ratio - density_median) + max(0.0, component_ratio - component_median)
            detected[violation] = min(0.99, 0.64 + separation * 2.2)
    return [
        {"violation_type": violation, "confidence": round(confidence, 3)}
        for violation, confidence in detected.items()
    ]


def is_instruction_text(value: str) -> bool:
    """Identify the fixed legal instructions printed below the remarks row."""
    text = normalized(value)
    phrases = (
        "you are hereby", "hereby directed", "municipal treasurer", "within 72 hours",
        "date hereof", "municipal trial court", "issuance of this", "proper investigation",
        "appears to report", "resulted in an accident",
    )
    return any(phrase in text for phrase in phrases)


def ticket_remarks_value(label: OCRItem, items: list[OCRItem], label_indexes: set[int]) -> str:
    """Read only the ruled remarks row, never the legal paragraph below it."""
    match = re.match(r"^\s*remarks\s*[:\-]?\s*(.*)$", label.text, re.I)
    inline = clean(match.group(1)) if match else ""
    if inline and not is_instruction_text(inline):
        return inline
    candidates = []
    for index, candidate in enumerate(items):
        if index in label_indexes or candidate is label or not candidate.text:
            continue
        tolerance = max(label.height, candidate.height) * 0.85
        same_row = candidate.left >= label.right - 8 and abs(candidate.center_y - label.center_y) <= tolerance
        immediately_below = (
            candidate.top >= label.bottom - 2
            and candidate.top - label.bottom <= max(18.0, label.height * 1.15)
            and abs(candidate.left - label.left) <= max(100.0, label.height * 5.0)
        )
        if (same_row or immediately_below) and not is_instruction_text(candidate.text):
            candidates.append(candidate)
    if not candidates:
        return ""
    return min(candidates, key=lambda value: abs(value.center_y - label.center_y) + max(0, value.left - label.right)).text


def looks_like_date_or_time(value: str) -> bool:
    text = clean(value)
    return bool(normalize_date(text) or normalize_time(text)
                or re.search(r"\b\d{1,2}\s*[-/.]\s*\d{1,2}\s*[-/.]\s*\d{2,4}\b", text))


def officer_row_value(label: OCRItem, next_label_top: float, items: list[OCRItem], excluded: set[int]) -> str:
    """Extract one officer row without drifting into its date/time row."""
    prefix = re.match(r"^\s*(?:name|position)\s*[:\-]?\s*(.*)$", label.text, re.I)
    inline = clean(prefix.group(1)) if prefix else ""
    if inline and not looks_like_date_or_time(inline) and not is_instruction_text(inline):
        return inline
    choices: list[tuple[float, OCRItem]] = []
    for index, candidate in enumerate(items):
        if index in excluded or candidate is label or not candidate.text:
            continue
        if candidate.top >= next_label_top or looks_like_date_or_time(candidate.text) or is_instruction_text(candidate.text):
            continue
        tolerance = max(label.height, candidate.height) * 0.9
        same_row = candidate.left >= label.right - 8 and abs(candidate.center_y - label.center_y) <= tolerance
        just_below = (
            candidate.top >= label.bottom - 2
            and candidate.top - label.bottom <= max(25.0, label.height * 1.35)
            and abs(candidate.left - label.left) <= max(100.0, label.height * 5.0)
        )
        if same_row or just_below:
            choices.append((abs(candidate.center_y - label.center_y) * 2 + max(0, candidate.left - label.right), candidate))
    return min(choices, key=lambda choice: choice[0])[1].text if choices else ""


def field_confidences(fields: dict[str, str], items: list[OCRItem]) -> dict[str, float]:
    """Estimate confidence for each populated field from the OCR token that supplied it."""
    result: dict[str, float] = {}
    for field, value in fields.items():
        target = normalized(value)
        if not target:
            result[field] = 0.0
            continue
        candidates: list[tuple[float, float]] = []
        for item in items:
            source = normalized(item.text)
            if not source:
                continue
            similarity = difflib.SequenceMatcher(None, target, source).ratio()
            if target in source or source in target:
                similarity = max(similarity, 0.92)
            candidates.append((similarity, item.confidence))
        best_similarity, best_ocr_confidence = max(candidates, default=(0.0, 0.0))
        result[field] = round(best_ocr_confidence * best_similarity, 3) if best_similarity >= 0.48 else 0.0
    return result


def extract(items: list[OCRItem]) -> dict[str, str]:
    fields = {name: "" for name in LABELS}
    matches = {index: label_match(item.text) for index, item in enumerate(items)}
    label_indexes = {index for index, match in matches.items() if match is not None}

    violation_headings = [
        item for item in items
        if normalized(item.text) in {"traffic violation", "traffic violations"}
    ]
    violation_heading_y = max((item.center_y for item in violation_headings), default=float("inf"))

    for index, match in matches.items():
        if match is None:
            continue
        field, inline_value = match
        # The form contains two generic Remarks labels. The one above the
        # violation checklist belongs to the confiscated-license row.
        if field == "ticket_remarks" and normalized(items[index].text) == "remarks":
            field = "license_remarks" if items[index].center_y < violation_heading_y else "ticket_remarks"
        if not fields[field]:
            if field == "ticket_remarks":
                fields[field] = ticket_remarks_value(items[index], items, label_indexes)
            else:
                fields[field] = inline_value or spatial_value(items[index], items, label_indexes)

    # Read the generic Name and Position rows only after the printed officer
    # section heading. This prevents the driver's name from being reused as
    # the apprehending officer.
    officer_headings = [
        item for item in items
        if "apprehending" in normalized(item.text)
        and ("officer" in normalized(item.text) or "arresting" in normalized(item.text))
    ]
    if officer_headings:
        officer_heading = max(officer_headings, key=lambda item: item.center_y)
        officer_labels = [
            (index, item) for index, item in enumerate(items)
            if item.top > officer_heading.bottom
            and item.top - officer_heading.bottom <= max(500, officer_heading.height * 25)
            and (normalized(item.text) in {"name", "position"}
                 or normalized(item.text).startswith("name ")
                 or normalized(item.text).startswith("position "))
        ]
        officer_label_indexes = label_indexes | {index for index, _ in officer_labels}
        date_time_labels = [
            item for item in items
            if item.top > officer_heading.bottom
            and normalized(item.text).startswith("date and time")
        ]
        for _, label in officer_labels:
            label_text = normalized(label.text)
            target = "apprehending_officer_name" if label_text == "name" or label_text.startswith("name ") else "apprehending_officer_position"
            if not fields[target]:
                following = [other.top for _, other in officer_labels
                             if other.top > label.top + max(2.0, label.height * 0.25)]
                following += [other.top for other in date_time_labels if other.top > label.top]
                next_top = min(following, default=label.bottom + max(55.0, label.height * 2.5))
                fields[target] = officer_row_value(label, next_top, items, officer_label_indexes)

    joined = "\n".join(item.text for item in items)
    if not fields["license_number"]:
        match = re.search(r"\b[A-Z]\d{2}[- ]?\d{2}[- ]?\d{5,7}\b", joined, re.I)
        if match:
            fields["license_number"] = match.group(0)
    if not fields["plate_number"]:
        candidates = re.findall(r"\b[A-Z]{2,4}[- ]?\d{3,4}\b", joined, re.I)
        if candidates:
            fields["plate_number"] = candidates[0]
    if not fields["ticket_number"]:
        # The official form prints a large serial beside "No." at the foot.
        number_labels = [item for item in items if normalized(item.text) in {"no", "no ticket"}]
        for label in number_labels:
            candidates = [item for item in items if item is not label and re.fullmatch(r"\d{4,8}", clean(item.text))
                          and abs(item.center_y - label.center_y) <= max(label.height, item.height) * 2.5]
            if candidates:
                fields["ticket_number"] = min(candidates, key=lambda item: abs(item.center_x - label.center_x)).text
                break

    license_text = normalized(fields["license_number"])
    if license_text in {"no license", "none", "n a", "na"}:
        fields["license_number"] = "NO LICENSE"
    else:
        fields["license_number"] = re.sub(r"\s+", "", fields["license_number"]).upper()
    plate_text = normalized(fields["plate_number"])
    if plate_text in {"no plate", "noplate", "none", "n a", "na"}:
        fields["plate_number"] = "NO PLATE"
    else:
        fields["plate_number"] = re.sub(r"\s+", "", fields["plate_number"]).upper()

    # Reject values that are structurally implausible for these identifiers.
    # It is safer to request manual confirmation than to put an address or a
    # nearby form number into the license/plate fields.
    if fields["license_number"] != "NO LICENSE":
        license_value = fields["license_number"]
        if (len(license_value) < 5 or not re.search(r"\d", license_value)
                or any(word in normalized(license_value) for word in ("nasugbu", "batangas", "bucana"))):
            fields["license_number"] = ""
    if fields["plate_number"] != "NO PLATE":
        plate_value = re.sub(r"[^A-Z0-9]", "", fields["plate_number"])
        if len(plate_value) < 4 or not re.search(r"[A-Z]", plate_value) or not re.search(r"\d", plate_value):
            fields["plate_number"] = ""
    vehicle_type = normalized(fields["vehicle_type"])
    fields["vehicle_type"] = VEHICLE_TYPE_ALIASES.get(
        vehicle_type,
        closest_allowed(fields["vehicle_type"], VEHICLE_TYPES, 0.62),
    )
    fields["violation_type"] = closest_violation(fields["violation_type"], 0.70)

    raw_violation_datetime = fields["violation_date"]
    if not fields["violation_time"] and raw_violation_datetime:
        fields["violation_time"] = normalize_time(raw_violation_datetime)
    else:
        fields["violation_time"] = normalize_time(fields["violation_time"])

    for date_field in ("date_of_birth", "license_expiry_date", "violation_date"):
        if fields[date_field]:
            fields[date_field] = normalize_date(fields[date_field])

    amount_match = re.search(r"(?:PHP|P|₱)?\s*([0-9]{2,6}(?:[,.][0-9]{2})?)", fields["penalty_amount"], re.I)
    amount = float(amount_match.group(1).replace(",", "")) if amount_match else 0
    fields["penalty_amount"] = str(int(amount)) if amount in PENALTY_FEES else ""

    if label_match(fields["driver_name"]) or normalized(fields["driver_name"]) in {normalized(v) for v in VEHICLE_TYPES}:
        fields["driver_name"] = ""
    for officer_field in ("apprehending_officer_name", "apprehending_officer_position"):
        if looks_like_date_or_time(fields[officer_field]) or is_instruction_text(fields[officer_field]):
            fields[officer_field] = ""
    if is_instruction_text(fields["ticket_remarks"]):
        fields["ticket_remarks"] = ""
    return fields


def analyze_ticket_image(image_path: str | Path, engine=None) -> dict:
    """Read one official ticket image and return the API-safe OCR result."""

    image_path = Path(image_path).resolve()
    image = cv2.imread(str(image_path))
    if image is None:
        raise ValueError("The uploaded ticket is not a readable image.")

    image, document_detected = document_warp(image)

    height, width = image.shape[:2]
    if max(height, width) > 2600:
        scale = 2600 / max(height, width)
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    elif max(height, width) < 1800:
        scale = 1800 / max(height, width)
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

    engine = engine or RapidOCR()
    image, orientation_items = normalize_orientation(image, engine)
    # A sideways, curved carbon-copy ticket can hide one or more paper edges.
    # Retry boundary detection after orientation so the portrait form receives
    # the same coordinate layout as a normal phone document scan.
    if not document_detected:
        retry_image, retry_detected = document_warp(image)
        if retry_detected:
            image, document_detected = retry_image, True
            orientation_items = []

    image, deskew_angle = deskew_ticket(image)
    if deskew_angle:
        # Coordinates gathered before affine deskewing no longer align with the
        # corrected page. Fresh full-page passes below replace them.
        orientation_items = []
    detection_groups: list[list[OCRItem]] = [orientation_items]
    for variant in preprocess_variants(image):
        result, _ = engine(variant)
        detection_groups.append([
            to_item(entry) for entry in (result or [])
            if len(entry) >= 3 and float(entry[2]) >= 0.28
        ])
    detection_groups.append(recognize_ticket_regions(image, engine))
    items = merge_items(detection_groups)
    fields = extract(items)
    per_field_confidence = field_confidences(fields, items)
    checked_violations = detect_checked_violations(image, items)
    if checked_violations:
        fields["violation_type"] = checked_violations[0]["violation_type"]
        per_field_confidence["violation_type"] = checked_violations[0]["confidence"]
    critical_fields = ["driver_name", "license_number", "plate_number"]
    missing_fields = [field for field in critical_fields if not fields[field]]
    confidences = [item.confidence for item in items]
    found = sum(bool(value) for value in fields.values())
    warning = "Review every field before saving; OCR can misread handwriting."
    if missing_fields:
        friendly = {"driver_name": "driver name", "license_number": "license number", "plate_number": "plate number"}
        warning = "Could not confidently read: " + ", ".join(friendly[field] for field in missing_fields) + ". Enter these fields manually."

    return {
        "success": True,
        "fields": fields,
        "field_confidences": per_field_confidence,
        "checked_violations": checked_violations,
        "confidence": round(sum(confidences) / len(confidences), 3) if confidences else 0,
        "recognized_fields": found,
        "document_detected": document_detected,
        "deskew_angle": deskew_angle,
        "missing_fields": missing_fields,
        "raw_text": "\n".join(item.text for item in items),
        "warning": warning,
    }


def main() -> None:
    result = analyze_ticket_image(sys.argv[1])
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"success": False, "error": str(exc)}))
        sys.exit(1)
