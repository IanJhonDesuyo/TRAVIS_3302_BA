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
    "violation_date": ["date of violation", "violation date"],
    "violation_time": ["time of violation", "violation time"],
    "penalty_amount": ["penalty amount", "amount due", "penalty fee", "fine"],
    "ticket_remarks": ["remarks"],
    "apprehending_officer_name": ["apprehending arresting officer", "apprehending officer", "arresting officer"],
    "apprehending_officer_position": ["position"],
}

VEHICLE_TYPES = ["Motorcycle", "Car", "SUV", "Jeepney", "Tricycle", "Van", "Truck", "Bus", "Other"]
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


def preprocess_variants(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(2.5, (8, 8)).apply(gray)
    sharpened = cv2.addWeighted(clahe, 1.8, cv2.GaussianBlur(clahe, (0, 0), 2), -0.8, 0)
    # Divide by a broad background estimate to remove phone/camera shadows
    # while retaining faint ballpoint handwriting.
    background = cv2.GaussianBlur(gray, (0, 0), 35)
    shadow_free = cv2.divide(gray, background, scale=255)
    shadow_free = cv2.createCLAHE(2.0, (8, 8)).apply(shadow_free)
    threshold = cv2.adaptiveThreshold(
        shadow_free, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 35, 11
    )
    # Color helps preserve blue/black ballpoint ink that can disappear when a
    # bright paper ticket is reduced directly to binary pixels.
    return [image, clahe, sharpened, shadow_free, threshold]


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
        gray = cv2.cvtColor(candidate, cv2.COLOR_BGR2GRAY)
        result, _ = engine(gray)
        items = [to_item(entry) for entry in (result or []) if len(entry) >= 3 and float(entry[2]) >= 0.28]
        score = orientation_score(items)
        if score > best_score:
            best_image, best_items, best_score = candidate, items, score
    return best_image, best_items


def label_match(text: str) -> tuple[str, str] | None:
    source = text.replace("’", "'")
    for field, aliases in LABELS.items():
        for alias in sorted(aliases, key=len, reverse=True):
            match = re.match(rf"^\s*{re.escape(alias)}\s*(?:no\.?\s*)?[:\-]?\s*(.*)$", source, re.I)
            if match:
                return field, clean(match.group(1))
    return None


def spatial_value(label: OCRItem, items: list[OCRItem], label_indexes: set[int]) -> str:
    choices: list[tuple[float, OCRItem]] = []
    for index, candidate in enumerate(items):
        if index in label_indexes or candidate is label or not candidate.text:
            continue
        row_tolerance = max(label.height, candidate.height) * 0.85
        if candidate.left >= label.right - 8 and abs(candidate.center_y - label.center_y) <= row_tolerance:
            choices.append(((candidate.left - label.right) + abs(candidate.center_y - label.center_y) * 2, candidate))
            continue
        horizontal_alignment = abs(candidate.center_x - label.center_x)
        if candidate.top >= label.bottom - 4 and horizontal_alignment <= max(90, (label.right - label.left) * 0.8):
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
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    section = violation_section_bounds(items, binary.shape[0])
    if section is None:
        return []
    section_top, section_bottom = section
    detected: dict[str, float] = {}
    for item in items:
        if not section_top <= item.center_y <= section_bottom:
            continue
        violation = closest_violation(item.text, 0.60)
        if not violation:
            continue
        h = max(12, int(item.height))
        # Official tickets place a square roughly 1.0-1.8 text heights left of the label.
        x2 = max(1, int(item.left - h * 0.12))
        x1 = max(0, int(item.left - h * 1.9))
        y1 = max(0, int(item.center_y - h * 0.72))
        y2 = min(binary.shape[0], int(item.center_y + h * 0.72))
        region = binary[y1:y2, x1:x2]
        if region.size == 0 or region.shape[0] < 6 or region.shape[1] < 6:
            continue
        # Ignore the printed square border; a tick/cross leaves ink in its interior.
        margin_y = max(2, int(region.shape[0] * 0.20))
        margin_x = max(2, int(region.shape[1] * 0.20))
        inner = region[margin_y:-margin_y, margin_x:-margin_x]
        if inner.size == 0:
            continue
        ink_ratio = float(cv2.countNonZero(inner)) / float(inner.size)
        components, _, stats, _ = cv2.connectedComponentsWithStats(inner, 8)
        largest_mark = int(stats[1:, cv2.CC_STAT_AREA].max()) if components > 1 else 0
        # A printed empty box has an almost blank interior. Requiring both ink
        # density and a connected pen stroke prevents every printed option from
        # being returned merely because its checkbox border was detected.
        min_mark_area = max(3, int(inner.size * 0.025))
        if ink_ratio >= 0.08 and largest_mark >= min_mark_area:
            confidence = min(0.99, 0.58 + (ink_ratio - 0.08) * 2.8)
            detected[violation] = max(detected.get(violation, 0.0), confidence)
    return [
        {"violation_type": violation, "confidence": round(confidence, 3)}
        for violation, confidence in detected.items()
    ]


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

    for index, match in matches.items():
        if match is None:
            continue
        field, inline_value = match
        if not fields[field]:
            fields[field] = inline_value or spatial_value(items[index], items, label_indexes)

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
    fields["vehicle_type"] = closest_allowed(fields["vehicle_type"], VEHICLE_TYPES, 0.68)
    fields["violation_type"] = closest_violation(fields["violation_type"], 0.70)

    for date_field in ("date_of_birth", "license_expiry_date", "violation_date"):
        if fields[date_field]:
            fields[date_field] = normalize_date(fields[date_field])

    amount_match = re.search(r"(?:PHP|P|₱)?\s*([0-9]{2,6}(?:[,.][0-9]{2})?)", fields["penalty_amount"], re.I)
    amount = float(amount_match.group(1).replace(",", "")) if amount_match else 0
    fields["penalty_amount"] = str(int(amount)) if amount in PENALTY_FEES else ""

    if label_match(fields["driver_name"]) or normalized(fields["driver_name"]) in {normalized(v) for v in VEHICLE_TYPES}:
        fields["driver_name"] = ""
    return fields


def main() -> None:
    image_path = Path(sys.argv[1]).resolve()
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

    engine = RapidOCR()
    image, orientation_items = normalize_orientation(image, engine)
    detection_groups: list[list[OCRItem]] = [orientation_items]
    for variant in preprocess_variants(image):
        result, _ = engine(variant)
        detection_groups.append([
            to_item(entry) for entry in (result or [])
            if len(entry) >= 3 and float(entry[2]) >= 0.28
        ])
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

    print(json.dumps({
        "success": True,
        "fields": fields,
        "field_confidences": per_field_confidence,
        "checked_violations": checked_violations,
        "confidence": round(sum(confidences) / len(confidences), 3) if confidences else 0,
        "recognized_fields": found,
        "document_detected": document_detected,
        "missing_fields": missing_fields,
        "raw_text": "\n".join(item.text for item in items),
        "warning": warning,
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"success": False, "error": str(exc)}))
        sys.exit(1)
