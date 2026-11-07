"""OCR and coordinate-aware extraction for Philippine LTO driver's licenses."""
from __future__ import annotations

import difflib
import json
import re
import sys
from datetime import date
from pathlib import Path

import cv2
import numpy as np
from rapidocr_onnxruntime import RapidOCR


# Returned with every scan so the mobile client and deployment checks can
# prove which parser implementation produced the result.
LICENSE_OCR_VERSION = "2026.11.07.1"

from ticket_ocr import (
    OCRItem,
    clean,
    document_warp,
    normalized,
    order_document_corners,
    preprocess_variants,
    shadow_normalized_gray,
    to_item,
)


LICENSE_LABELS = {
    # Avoid the generic word "name": it also appears in the printed address
    # guide ("street name") and can shift extraction to the wrong section.
    "driver_name": ["last name first name middle name"],
    "driver_address": ["address"],
    "date_of_birth": ["date of birth", "birth date"],
    "license_number": ["license no", "license number", "driver s license no"],
    "license_expiry_date": ["expiration date", "expiry date"],
}

INLINE_FIELD_PATTERNS = {
    "driver_name": re.compile(
        r"last\s*name\s*[,./| -]*\s*first\s*name\s*[,./| -]*\s*middle\s*name\s*[:\-]?\s*(.+)$",
        re.I,
    ),
    "driver_address": re.compile(r"\baddress\s*[:\-]?\s*(.+)$", re.I),
    "date_of_birth": re.compile(r"\b(?:date\s+of\s+birth|birth\s+date)\s*[:\-]?\s*(.+)$", re.I),
    "license_number": re.compile(
        r"\b(?:driver'?s\s+)?license\s*(?:no\.?|number)\s*[:\-]?\s*(.+)$",
        re.I,
    ),
    "license_expiry_date": re.compile(
        r"\b(?:expiration|expiry)\s+date\s*[:\-]?\s*(.+)$",
        re.I,
    ),
}

NAME_FORBIDDEN_PHRASES = (
    "republic of the philippines", "department of transportation",
    "land transportation office", "driver s license", "assistant secretary",
    "secretary", "signature of licensee", "nationality", "date of birth",
    "weight kg", "height m", "agency code", "blood type", "eyes color",
    "restrictions", "conditions", "license no", "expiration date",
)

ADDRESS_FORBIDDEN_PHRASES = (
    "republic of the philippines", "department of transportation",
    "land transportation office", "driver s license", "assistant secretary",
    "signature of licensee", "last name first name middle name",
)


def _meaningful_containment(source: str, target: str) -> bool:
    """Accept a partial OCR label only when most of the real label remains."""
    if target in source:
        return True
    minimum = max(8, int(len(target) * 0.65))
    return len(source) >= minimum and source in target


def license_label_match(text: str) -> str | None:
    source = normalized(text.replace("!", "i").replace("|", "i"))
    if not source:
        return None

    source_tokens = set(source.split())
    best: tuple[float, str] | None = None
    for field, aliases in LICENSE_LABELS.items():
        # These mandatory words prevent generic footer/header fragments such as
        # "Name" or "DRIVER'S LICENSE" from becoming field labels.
        if field == "driver_name" and not {"last", "first", "name"}.issubset(source_tokens):
            continue
        if field == "driver_address" and "address" not in source_tokens:
            continue
        if field == "date_of_birth" and "birth" not in source_tokens:
            continue
        if field == "license_number" and not ({"no", "number"} & source_tokens):
            continue
        if field == "license_expiry_date" and not ({"expiration", "expiry"} & source_tokens):
            continue
        for alias in aliases:
            target = normalized(alias)
            similarity = difflib.SequenceMatcher(None, source, target).ratio()
            if _meaningful_containment(source, target):
                similarity = max(similarity, 0.93)
            threshold = 0.90 if len(target) < 8 else 0.72
            if similarity >= threshold and (best is None or similarity > best[0]):
                best = (similarity, field)
    return best[1] if best else None


def plausible_license_name(value: str) -> bool:
    source = normalized(value)
    if not source or any(phrase in source for phrase in NAME_FORBIDDEN_PHRASES):
        return False
    if re.search(r"\d", source):
        return False
    words = source.split()
    return 2 <= len(words) <= 8 and all(len(word) >= 2 for word in words)


def plausible_license_address(value: str) -> bool:
    source = normalized(value)
    if not source or any(phrase in source for phrase in ADDRESS_FORBIDDEN_PHRASES):
        return False
    words = source.split()
    return 2 <= len(words) <= 20 and len(clean(value)) <= 180


def collapse_repeated_words(value: str) -> str:
    """Remove an exactly repeated address sequence without inventing text."""
    words = clean(value).split()
    if len(words) < 4:
        return clean(value)
    normalized_words = [normalized(word) for word in words]
    for start in range(len(words) // 2 + 1):
        tail = normalized_words[start:]
        if len(tail) >= 4 and len(tail) % 2 == 0:
            midpoint = len(tail) // 2
            if tail[:midpoint] == tail[midpoint:]:
                return " ".join(words[:start + midpoint])
    return clean(value)


def inline_license_value(text: str, field: str) -> str:
    """Return a value when OCR combines a printed label and its value."""
    pattern = INLINE_FIELD_PATTERNS.get(field)
    if pattern is None:
        return ""
    match = pattern.search(clean(text))
    return clean(match.group(1)).strip(" :,-") if match else ""


def license_document_warp(image):
    """Flatten a rounded LTO card when the generic document contour misses it."""
    height, width = image.shape[:2]
    longest = max(height, width)
    scale = min(1.0, 1400.0 / longest)
    preview = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA) if scale < 1 else image.copy()
    gray = cv2.cvtColor(preview, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 35, 125)
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, np.ones((11, 11), np.uint8), iterations=2)
    contours = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)[0]
    image_area = float(preview.shape[0] * preview.shape[1])
    candidates = []
    for contour in contours:
        contour_area = float(cv2.contourArea(contour))
        if contour_area < image_area * 0.12:
            continue
        rectangle = cv2.minAreaRect(contour)
        rect_width, rect_height = rectangle[1]
        if min(rect_width, rect_height) < 1:
            continue
        aspect = max(rect_width, rect_height) / min(rect_width, rect_height)
        rectangle_area = rect_width * rect_height
        fill_ratio = contour_area / max(rectangle_area, 1.0)
        if 1.35 <= aspect <= 1.90 and fill_ratio >= 0.45:
            score = contour_area * (1.0 - min(abs(aspect - 1.586), 0.5))
            candidates.append((score, rectangle))

    if not candidates:
        return document_warp(image)

    rectangle = max(candidates, key=lambda candidate: candidate[0])[1]
    points = cv2.boxPoints(rectangle).astype("float32") / scale
    top_left, top_right, bottom_right, bottom_left = order_document_corners(points)
    target_width = int(max(np.linalg.norm(bottom_right - bottom_left), np.linalg.norm(top_right - top_left)))
    target_height = int(max(np.linalg.norm(top_right - bottom_right), np.linalg.norm(top_left - bottom_left)))
    if target_width < 400 or target_height < 250:
        return document_warp(image)
    destination = np.array([
        [0, 0], [target_width - 1, 0], [target_width - 1, target_height - 1], [0, target_height - 1],
    ], dtype="float32")
    transform = cv2.getPerspectiveTransform(
        np.array([top_left, top_right, bottom_right, bottom_left], dtype="float32"), destination
    )
    return cv2.warpPerspective(image, transform, (target_width, target_height)), True


def crop_to_camera_guide(image, crop_frame: dict | None):
    """Map the centered mobile preview guide to the captured image.

    Expo's camera preview uses a cover-style fill. The uploaded frame is kept
    optional: invalid metadata returns the unmodified image instead of making
    the scan fail.
    """
    if not isinstance(crop_frame, dict):
        return image, False
    try:
        preview_width = float(crop_frame["preview_width"])
        preview_height = float(crop_frame["preview_height"])
        left = float(crop_frame["left"])
        top = float(crop_frame["top"])
        width = float(crop_frame["width"])
        height = float(crop_frame["height"])
        padding_ratio = min(0.05, max(0.03, float(crop_frame.get("padding_ratio", 0.04))))
    except (KeyError, TypeError, ValueError):
        return image, False
    if min(preview_width, preview_height, width, height) <= 0:
        return image, False

    working = image
    image_height, image_width = working.shape[:2]
    if preview_width > preview_height and image_height > image_width:
        working = cv2.rotate(working, cv2.ROTATE_90_CLOCKWISE)
        image_height, image_width = working.shape[:2]

    scale = max(preview_width / image_width, preview_height / image_height)
    displayed_width = image_width * scale
    displayed_height = image_height * scale
    offset_x = (displayed_width - preview_width) / 2.0
    offset_y = (displayed_height - preview_height) / 2.0
    padding_x = width * padding_ratio
    padding_y = height * padding_ratio
    x1 = int(max(0, (left - padding_x + offset_x) / scale))
    y1 = int(max(0, (top - padding_y + offset_y) / scale))
    x2 = int(min(image_width, (left + width + padding_x + offset_x) / scale))
    y2 = int(min(image_height, (top + height + padding_y + offset_y) / scale))
    if x2 - x1 < 320 or y2 - y1 < 190:
        return working, False
    aspect = (x2 - x1) / max(1, y2 - y1)
    if not 1.25 <= aspect <= 2.05:
        return working, False
    return working[y1:y2, x1:x2].copy(), True


def license_orientation_score(items: list[OCRItem], landscape: bool) -> float:
    score = 0.35 if landscape else 0.0
    anchors = (
        "republic of the philippines", "land transportation office",
        "driver s license", "last name first name middle name",
        "license no", "expiration date", "date of birth",
    )
    for item in items:
        source = normalized(item.text)
        for anchor in anchors:
            similarity = difflib.SequenceMatcher(None, source, anchor).ratio()
            if _meaningful_containment(source, anchor):
                similarity = max(similarity, 0.93)
            if similarity >= 0.62:
                score += similarity * max(0.35, item.confidence)
    return score


def normalize_license_orientation(image, engine):
    best_image = image
    best_items: list[OCRItem] = []
    best_score = -1.0
    candidates = [
        image,
        cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE),
        cv2.rotate(image, cv2.ROTATE_180),
        cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE),
    ]
    for candidate in candidates:
        result, _ = engine(shadow_normalized_gray(candidate))
        items = [to_item(entry) for entry in (result or []) if len(entry) >= 3 and float(entry[2]) >= 0.28]
        score = license_orientation_score(items, candidate.shape[1] > candidate.shape[0] * 1.25)
        if score > best_score:
            best_image, best_items, best_score = candidate, items, score
    return best_image, best_items


def normalize_license_date(value: str) -> str:
    source = clean(value)
    year_first = re.search(r"\b(19\d{2}|20\d{2})\s*[./-]\s*(\d{1,2})\s*[./-]\s*(\d{1,2})\b", source)
    month_first = re.search(r"\b(\d{1,2})\s*[./-]\s*(\d{1,2})\s*[./-]\s*(\d{2,4})\b", source)
    if year_first:
        year, month, day = (int(part) for part in year_first.groups())
    elif month_first:
        month, day, year = (int(part) for part in month_first.groups())
        year += 2000 if year < 50 else (1900 if year < 100 else 0)
    else:
        return ""
    try:
        return date(year, month, day).isoformat()
    except ValueError:
        return ""


def normalize_license_number(value: str) -> str:
    """Normalize common OCR spacing and digit confusions in an LTO number."""
    source = clean(value).upper().replace("–", "-").replace("—", "-")
    match = re.search(
        r"\b([A-Z])\s*([0-9OIL]{2})\s*[- ]?\s*([0-9OIL]{2})\s*[- ]?\s*([0-9OIL]{5,7})\b",
        source,
    )
    if not match:
        return ""
    translate_digits = str.maketrans({"O": "0", "I": "1", "L": "1"})
    prefix, office, year, serial = match.groups()
    return f"{prefix}{office.translate(translate_digits)}-{year.translate(translate_digits)}-{serial.translate(translate_digits)}"


def parse_lto_name(value: str) -> dict[str, str]:
    """Parse the LTO order: LAST NAME, FIRST NAME(S), MIDDLE NAME."""
    raw = re.sub(r"\s+", " ", clean(value)).strip(" ,")
    output = {
        "driver_name": raw,
        "driver_last_name": "",
        "driver_first_name": "",
        "driver_middle_name": "",
        "driver_suffix": "",
    }
    if not raw:
        return output

    # A faint comma after the surname is commonly read as a period. Normalize
    # only the first surname separator so initials elsewhere remain untouched.
    if "," not in raw:
        separator = re.match(r"^([A-Z][A-Z '\-]{1,38})[.;:]\s+(.+)$", raw, re.I)
        if separator:
            raw = f"{separator.group(1).strip()}, {separator.group(2).strip()}"

    # Alternate preprocessing passes can append a second, whitespace-free copy
    # of the same name. The first surname occurrence is authoritative on an LTO
    # card, so discard a repeated tail before splitting the components.
    surname_seed = normalized(raw.split(",", 1)[0])
    if surname_seed:
        repeated = re.search(rf"\b{re.escape(surname_seed)}(?=[a-z]|\b)", raw[len(raw.split(",", 1)[0]) + 1:], re.I)
        if repeated:
            tail_offset = len(raw.split(",", 1)[0]) + 1
            raw = raw[:tail_offset + repeated.start()].strip(" ,")
    output["driver_name"] = raw

    # On low-contrast cards RapidOCR sometimes reads the J in JR as I.
    suffix_pattern = re.compile(r"\b(JR\.?|IR\.?|SR\.?|II|III|IV|V)\b", re.I)
    suffix = suffix_pattern.search(raw)
    if suffix:
        suffix_value = suffix.group(1).rstrip(".")
        output["driver_suffix"] = ("Jr" if suffix_value.upper() == "IR" else suffix_value.title())
        raw_without_suffix = clean(raw[:suffix.start()] + " " + raw[suffix.end():])
    else:
        raw_without_suffix = raw

    if "," in raw_without_suffix:
        surname, given = (part.strip() for part in raw_without_suffix.split(",", 1))
        given_parts = given.split()
        output["driver_last_name"] = surname.title()
        if len(given_parts) == 1:
            output["driver_first_name"] = given_parts[0].title()
        elif given_parts:
            output["driver_first_name"] = " ".join(given_parts[:-1]).title()
            output["driver_middle_name"] = given_parts[-1].title()
    else:
        parts = raw_without_suffix.split()
        if len(parts) >= 3:
            output["driver_last_name"] = parts[0].title()
            output["driver_first_name"] = " ".join(parts[1:-1]).title()
            output["driver_middle_name"] = parts[-1].title()
        elif len(parts) == 2:
            output["driver_last_name"] = parts[0].title()
            output["driver_first_name"] = parts[1].title()
        elif parts:
            output["driver_first_name"] = parts[0].title()
    return output


def _same_row_values(label: OCRItem, items: list[OCRItem], labels: dict[int, str]) -> list[OCRItem]:
    row_labels = [
        item for index, item in enumerate(items)
        if index in labels and item is not label and abs(item.center_y - label.center_y) <= max(item.height, label.height)
    ]
    left = max((item.center_x for item in row_labels if item.center_x < label.center_x), default=-1.0)
    right = min((item.center_x for item in row_labels if item.center_x > label.center_x), default=float("inf"))
    left_boundary = (left + label.center_x) / 2 if left >= 0 else max(0.0, label.left - label.height * 2)
    right_boundary = (right + label.center_x) / 2 if right != float("inf") else float("inf")
    below = [
        item for index, item in enumerate(items)
        if index not in labels
        and item.center_y > label.bottom
        and item.center_y <= label.bottom + label.height * 5.5
        and left_boundary <= item.center_x < right_boundary
    ]
    if not below:
        return []
    nearest_y = min(item.center_y for item in below)
    tolerance = max(label.height * 1.35, 14.0)
    return sorted([item for item in below if abs(item.center_y - nearest_y) <= tolerance], key=lambda item: item.left)


def _block_below(label: OCRItem, items: list[OCRItem], labels: dict[int, str], max_rows: int) -> list[OCRItem]:
    next_label_top = min(
        (item.top for index, item in enumerate(items) if index in labels and item.top > label.bottom + label.height),
        default=label.bottom + label.height * (4.5 + max_rows * 2),
    )
    candidates = [
        item for index, item in enumerate(items)
        if index not in labels and label.bottom < item.center_y < next_label_top
        and item.left >= max(0.0, label.left - label.height * 3)
    ]
    if not candidates:
        return []
    rows: list[list[OCRItem]] = []
    for item in sorted(candidates, key=lambda value: (value.center_y, value.left)):
        row = next((row for row in rows if abs(row[0].center_y - item.center_y) <= max(row[0].height, item.height) * 0.8), None)
        if row is None:
            rows.append([item])
        else:
            row.append(item)
    return [item for row in rows[:max_rows] for item in sorted(row, key=lambda value: value.left)]


def _deduplicate_spatial_values(values: list[OCRItem]) -> list[OCRItem]:
    """Keep the best OCR reading when preprocessing passes overlap."""
    selected: list[OCRItem] = []
    for item in sorted(values, key=lambda value: value.confidence, reverse=True):
        item_width = max(1.0, item.right - item.left)
        overlaps_existing = False
        for existing in selected:
            existing_width = max(1.0, existing.right - existing.left)
            horizontal_overlap = max(0.0, min(item.right, existing.right) - max(item.left, existing.left))
            vertical_overlap = max(0.0, min(item.bottom, existing.bottom) - max(item.top, existing.top))
            if (
                horizontal_overlap / min(item_width, existing_width) >= 0.45
                and vertical_overlap / min(item.height, existing.height) >= 0.50
            ):
                overlaps_existing = True
                break
        if not overlaps_existing:
            selected.append(item)
    return sorted(selected, key=lambda value: (value.center_y, value.left))


def extract_license_fields(items: list[OCRItem]) -> tuple[dict[str, str], dict[str, float]]:
    matches = {index: license_label_match(item.text) for index, item in enumerate(items)}
    labels = {index: field for index, field in matches.items() if field is not None}
    fields = {field: "" for field in LICENSE_LABELS}
    confidences = {field: 0.0 for field in LICENSE_LABELS}

    for index, field in labels.items():
        label = items[index]
        inline_value = inline_license_value(label.text, field)
        if inline_value and not fields[field]:
            fields[field] = inline_value
            confidences[field] = round(label.confidence, 3)
            continue
        if field in {"driver_name", "driver_address"}:
            values = _block_below(label, items, labels, 2 if field == "driver_address" else 1)
        else:
            values = _same_row_values(label, items, labels)
        if not values:
            continue
        values = _deduplicate_spatial_values(values)
        value = clean(" ".join(item.text for item in values))
        if value and not fields[field]:
            fields[field] = value
            confidences[field] = round(sum(item.confidence for item in values) / len(values), 3)

    joined = "\n".join(item.text for item in items)
    normalized_number = normalize_license_number(fields["license_number"])
    if not normalized_number:
        normalized_number = normalize_license_number(joined)
        if normalized_number:
            confidences["license_number"] = max(confidences["license_number"], 0.72)
    fields["license_number"] = normalized_number
    if not normalized_number:
        confidences["license_number"] = 0.0

    fields["date_of_birth"] = normalize_license_date(fields["date_of_birth"])
    fields["license_expiry_date"] = normalize_license_date(fields["license_expiry_date"])
    dated_items = [
        (normalize_license_date(item.text), item.confidence)
        for item in items
        if normalize_license_date(item.text)
    ]
    unique_dates = sorted({value for value, _ in dated_items})
    if unique_dates and not fields["date_of_birth"]:
        fields["date_of_birth"] = unique_dates[0]
        confidences["date_of_birth"] = max(confidence for value, confidence in dated_items if value == unique_dates[0])
    if len(unique_dates) >= 2 and not fields["license_expiry_date"]:
        fields["license_expiry_date"] = unique_dates[-1]
        confidences["license_expiry_date"] = max(confidence for value, confidence in dated_items if value == unique_dates[-1])
    if fields["driver_name"] and not plausible_license_name(fields["driver_name"]):
        fields["driver_name"] = ""
        confidences["driver_name"] = 0.0
    if fields["driver_address"] and not plausible_license_address(fields["driver_address"]):
        fields["driver_address"] = ""
        confidences["driver_address"] = 0.0
    elif fields["driver_address"]:
        fields["driver_address"] = collapse_repeated_words(fields["driver_address"])
    component_fields = ("driver_last_name", "driver_first_name", "driver_middle_name")
    if fields["driver_name"]:
        parsed_name = parse_lto_name(fields["driver_name"])
        for field, value in parsed_name.items():
            if value:
                fields[field] = value
        name_confidence = confidences["driver_name"]
        for field in (*component_fields, "driver_suffix"):
            if fields.get(field) and not confidences.get(field):
                confidences[field] = name_confidence
    else:
        valid_components = [
            fields[field] for field in component_fields
            if fields.get(field) and plausible_license_name(fields[field] + " Placeholder")
        ]
        if len(valid_components) >= 2:
            fields["driver_name"] = clean(" ".join(valid_components))
            confidences["driver_name"] = round(sum(
                confidences[field] for field in component_fields if fields.get(field)
            ) / len(valid_components), 3)
    return {key: value for key, value in fields.items() if value}, confidences


def _field_candidate_score(field: str, value: str, confidence: float) -> float:
    score = float(confidence)
    if field == "driver_name" and plausible_license_name(value):
        score += 0.30 if "," in value else 0.15
    elif field == "driver_address" and plausible_license_address(value):
        score += min(0.20, len(value.split()) * 0.02)
    elif field in {"date_of_birth", "license_expiry_date"} and normalize_license_date(value):
        score += 0.25
    elif field == "license_number" and normalize_license_number(value):
        score += 0.35
    return score


def select_best_license_fields(groups: list[list[OCRItem]]):
    """Extract each OCR pass separately, then select fields independently.

    This deliberately avoids joining text from color, grayscale, thresholded,
    and sharpened images. A complete name always comes from one pass so name
    components cannot be mixed across preprocessing variants.
    """
    passes = [extract_license_fields(group) for group in groups if group]
    selected_fields: dict[str, str] = {}
    selected_confidences = {field: 0.0 for field in LICENSE_LABELS}

    name_candidates = []
    for pass_index, (fields, confidences) in enumerate(passes):
        value = fields.get("driver_name", "")
        if value and plausible_license_name(value):
            score = _field_candidate_score("driver_name", value, confidences.get("driver_name", 0.0)) - pass_index * 0.005
            name_candidates.append((score, fields, confidences))
    if name_candidates:
        _, fields, confidences = max(name_candidates, key=lambda candidate: candidate[0])
        for field in ("driver_name", "driver_last_name", "driver_first_name", "driver_middle_name", "driver_suffix"):
            if fields.get(field):
                selected_fields[field] = fields[field]
                selected_confidences[field] = confidences.get(field, confidences.get("driver_name", 0.0))

    for field in ("driver_address", "date_of_birth", "license_number", "license_expiry_date"):
        candidates = []
        for pass_index, (fields, confidences) in enumerate(passes):
            value = fields.get(field, "")
            if not value:
                continue
            score = _field_candidate_score(field, value, confidences.get(field, 0.0)) - pass_index * 0.005
            candidates.append((score, value, confidences.get(field, 0.0)))
        if candidates:
            _, value, confidence = max(candidates, key=lambda candidate: candidate[0])
            selected_fields[field] = collapse_repeated_words(value) if field == "driver_address" else value
            selected_confidences[field] = round(float(confidence), 3)
    return selected_fields, selected_confidences


def _prepare_and_extract(image, engine):
    image, document_detected = license_document_warp(image)
    longest = max(image.shape[:2])
    if longest < 1800:
        scale = 1800 / longest
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
    elif longest > 2600:
        scale = 2600 / longest
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)

    image, orientation_items = normalize_license_orientation(image, engine)
    groups: list[list[OCRItem]] = [orientation_items]
    # orientation_items already represent the normalized grayscale pass. Keep
    # the original-color pass and the other variants, without merging lines.
    for variant_index, variant in enumerate(preprocess_variants(image)):
        if variant_index == 3:  # already represented by orientation_items
            continue
        result, _ = engine(variant)
        groups.append([to_item(entry) for entry in (result or []) if len(entry) >= 3 and float(entry[2]) >= 0.28])
    fields, field_confidences = select_best_license_fields(groups)
    confidence_values = [value for field, value in field_confidences.items() if fields.get(field)]
    return {
        "fields": fields,
        "field_confidences": field_confidences,
        "document_detected": document_detected,
        "confidence": round(sum(confidence_values) / len(confidence_values), 3) if confidence_values else 0.0,
    }


def _result_quality(result: dict) -> float:
    fields = result["fields"]
    confidence = result["field_confidences"]
    weighted = {
        "driver_name": 2.0, "license_number": 2.0, "driver_address": 1.0,
        "date_of_birth": 1.0, "license_expiry_date": 1.0,
    }
    return sum(weight * (1.0 + float(confidence.get(field, 0.0))) for field, weight in weighted.items() if fields.get(field))


def analyze_license_image(image_path: str | Path, engine=None, crop_frame: dict | None = None) -> dict:
    original = cv2.imread(str(Path(image_path).resolve()))
    if original is None:
        raise ValueError("The uploaded driver's license is not a readable image.")
    engine = engine or RapidOCR()
    cropped, crop_applied = crop_to_camera_guide(original, crop_frame)
    selected = _prepare_and_extract(cropped, engine)
    fallback_used = False

    # The guide is a preferred region, not a hard requirement. If essential
    # fields are missing, process the retained full frame and keep whichever
    # result has the stronger validated field set.
    crop_fields = selected["fields"]
    if crop_applied and (not crop_fields.get("driver_name") or not crop_fields.get("license_number")):
        fallback = _prepare_and_extract(original, engine)
        if _result_quality(fallback) > _result_quality(selected):
            selected = fallback
            fallback_used = True

    fields = selected["fields"]
    field_confidences = selected["field_confidences"]
    critical = ["driver_name", "license_number"]
    missing = [field for field in critical if not fields.get(field)]
    warning = "Review all extracted license details before saving."
    if missing:
        warning = "Could not confidently read: " + ", ".join(field.replace("_", " ") for field in missing) + ". Enter these fields manually."
    return {
        "success": True,
        "ocr_version": LICENSE_OCR_VERSION,
        "fields": fields,
        "field_confidences": field_confidences,
        "confidence": selected["confidence"],
        "recognized_fields": len([value for value in fields.values() if value]),
        "document_detected": selected["document_detected"],
        "guide_crop_applied": crop_applied,
        "full_frame_fallback_used": fallback_used,
        "processing_variants": ["color", "grayscale_clahe", "sharpened", "shadow_normalized", "adaptive_bw"],
        "missing_fields": missing,
        "warning": warning,
    }


def main() -> None:
    crop_frame = json.loads(sys.argv[2]) if len(sys.argv) > 2 and sys.argv[2] else None
    print(json.dumps(analyze_license_image(sys.argv[1], crop_frame=crop_frame), ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(json.dumps({"success": False, "error": str(error)}))
        sys.exit(1)
