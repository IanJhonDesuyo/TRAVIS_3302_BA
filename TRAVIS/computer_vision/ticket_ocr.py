"""Offline OCR and coordinate-aware field extraction for TRAVIS tickets."""
from __future__ import annotations

import difflib
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path

import cv2
from rapidocr_onnxruntime import RapidOCR


LABELS = {
    "driver_name": ["driver name", "name of driver"],
    "license_number": ["license number", "driver license number", "driver's license number", "license no", "dl no"],
    "plate_number": ["plate number", "plate no"],
    "vehicle_type": ["vehicle type", "type of vehicle"],
    "violation_type": ["violation type", "nature of violation", "offense"],
    "location": ["violation location", "place of violation", "location"],
    "penalty_amount": ["penalty amount", "amount due", "penalty fee", "fine"],
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
]
PENALTY_FEES = {100, 200, 300, 500, 1000, 1500, 2000, 2500, 3000, 5000}


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


def preprocess_variants(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(2.5, (8, 8)).apply(gray)
    sharpened = cv2.addWeighted(clahe, 1.8, cv2.GaussianBlur(clahe, (0, 0), 2), -0.8, 0)
    threshold = cv2.adaptiveThreshold(
        clahe, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 35, 11
    )
    # Color helps preserve blue/black ballpoint ink that can disappear when a
    # bright paper ticket is reduced directly to binary pixels.
    return [image, clahe, sharpened, threshold]


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

    license_text = normalized(fields["license_number"])
    if license_text in {"no license", "none", "n a", "na"}:
        fields["license_number"] = "NO LICENSE"
    else:
        fields["license_number"] = re.sub(r"\s+", "", fields["license_number"]).upper()
    fields["plate_number"] = re.sub(r"\s+", "", fields["plate_number"]).upper()
    fields["vehicle_type"] = closest_allowed(fields["vehicle_type"], VEHICLE_TYPES, 0.68)
    fields["violation_type"] = closest_allowed(fields["violation_type"], VIOLATION_TYPES, 0.70)

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

    height, width = image.shape[:2]
    if max(height, width) > 2600:
        scale = 2600 / max(height, width)
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    elif max(height, width) < 1800:
        scale = 1800 / max(height, width)
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

    engine = RapidOCR()
    detection_groups: list[list[OCRItem]] = []
    for variant in preprocess_variants(image):
        result, _ = engine(variant)
        detection_groups.append([
            to_item(entry) for entry in (result or [])
            if len(entry) >= 3 and float(entry[2]) >= 0.28
        ])
    items = merge_items(detection_groups)
    fields = extract(items)
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
        "confidence": round(sum(confidences) / len(confidences), 3) if confidences else 0,
        "recognized_fields": found,
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
