"""Offline OCR and conservative field extraction for TRAVIS paper tickets."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import cv2
from rapidocr_onnxruntime import RapidOCR


LABELS = {
    "driver_name": ["driver name", "name of driver", "driver"],
    "license_number": ["license number", "driver license", "license no", "dl no"],
    "plate_number": ["plate number", "plate no", "plate"],
    "vehicle_type": ["vehicle type", "type of vehicle", "vehicle"],
    "violation_type": ["violation type", "nature of violation", "violation", "offense"],
    "location": ["violation location", "place of violation", "location", "place"],
    "penalty_amount": ["penalty amount", "amount due", "penalty", "fine"],
}


def clean(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip(" :-_|.,")


def labeled_value(lines: list[str], aliases: list[str]) -> str:
    for index, line in enumerate(lines):
        lowered = line.lower()
        for alias in aliases:
            match = re.search(rf"\b{re.escape(alias)}\b\s*(?:no\.?\s*)?[:\-]?\s*(.*)$", lowered)
            if not match:
                continue
            original_tail = line[len(line) - len(match.group(1)) :] if match.group(1) else ""
            value = clean(original_tail)
            if value and value.lower() != alias:
                return value
            if index + 1 < len(lines):
                return clean(lines[index + 1])
    return ""


def extract(lines: list[str]) -> dict[str, str]:
    fields = {name: labeled_value(lines, aliases) for name, aliases in LABELS.items()}
    joined = "\n".join(lines)

    if not fields["license_number"]:
        match = re.search(r"\b[A-Z]\d{2}[- ]?\d{2}[- ]?\d{5,7}\b", joined, re.I)
        if match:
            fields["license_number"] = match.group(0).upper()
    if not fields["plate_number"]:
        candidates = re.findall(r"\b[A-Z]{2,4}[- ]?\d{3,4}\b", joined, re.I)
        if candidates:
            fields["plate_number"] = candidates[0].replace(" ", "").upper()
    amount_match = re.search(r"(?:PHP|P|₱)?\s*([0-9]{2,6}(?:[,.][0-9]{2})?)", fields["penalty_amount"], re.I)
    fields["penalty_amount"] = amount_match.group(1).replace(",", "") if amount_match else ""

    vehicle = fields["vehicle_type"].lower()
    allowed = {"motorcycle": "Motorcycle", "car": "Car", "suv": "SUV", "truck": "Truck", "bus": "Bus"}
    fields["vehicle_type"] = next((label for key, label in allowed.items() if key in vehicle), "Car")
    fields["plate_number"] = fields["plate_number"].upper()
    return fields


def main() -> None:
    image_path = Path(sys.argv[1]).resolve()
    image = cv2.imread(str(image_path))
    if image is None:
        raise ValueError("The uploaded ticket is not a readable image.")

    height, width = image.shape[:2]
    if max(height, width) > 1800:
        scale = 1800 / max(height, width)
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.createCLAHE(2.0, (8, 8)).apply(gray)

    result, _ = RapidOCR()(gray)
    entries = result or []
    lines = [clean(str(entry[1])) for entry in entries if len(entry) >= 3 and float(entry[2]) >= 0.35]
    confidences = [float(entry[2]) for entry in entries if len(entry) >= 3]
    fields = extract(lines)
    found = sum(bool(value) for key, value in fields.items() if key != "vehicle_type")
    print(json.dumps({
        "success": True,
        "fields": fields,
        "confidence": round(sum(confidences) / len(confidences), 3) if confidences else 0,
        "recognized_fields": found,
        "raw_text": "\n".join(lines),
        "warning": "Review every field before saving; OCR can misread handwriting." if found else "No labeled ticket fields were recognized.",
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(json.dumps({"success": False, "error": str(exc)}))
        sys.exit(1)
