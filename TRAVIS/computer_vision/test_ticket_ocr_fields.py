import cv2
import numpy as np

from ticket_ocr import (
    OCRItem, deskew_ticket, detect_checked_violations, document_warp, extract, label_match,
    normalize_date, normalize_time, violation_section_bounds,
)


def item(text, left, top, right, bottom, confidence=0.95):
    return OCRItem(text, confidence, left, top, right, bottom)


def test_two_column_ticket_does_not_cross_assign_values():
    fields = extract([
        item("Driver Name", 20, 10, 120, 30), item("CONRAD DELA CRUZ", 140, 10, 300, 30),
        item("License Number", 330, 10, 450, 30), item("NO LICENSE", 470, 10, 580, 30),
        item("Plate Number", 20, 50, 120, 70), item("QWDQW123", 140, 50, 260, 70),
        item("Vehicle Type", 330, 50, 440, 70), item("MOTORCYCLE", 470, 50, 580, 70),
        item("Violation Type", 20, 90, 130, 110), item("No Driver's License", 150, 90, 320, 110),
        item("Violation Location", 330, 90, 470, 110), item("BUCANA", 490, 90, 580, 110),
        item("Penalty Fee", 20, 130, 110, 150), item("300", 140, 130, 190, 150),
    ])

    assert fields["driver_name"] == "CONRAD DELA CRUZ"
    assert fields["license_number"] == "NO LICENSE"
    assert fields["plate_number"] == "QWDQW123"
    assert fields["vehicle_type"] == "Motorcycle"
    assert fields["violation_type"] == "No Driver's License"
    assert fields["location"] == "BUCANA"
    assert fields["penalty_amount"] == "300"


def test_official_driver_apostrophe_label_is_supported():
    assert label_match("DRIVER'S NAME: AJ RAMOS") == ("driver_name", "AJ RAMOS")


def test_damaged_printed_label_is_fuzzy_matched():
    assert label_match("DR!VER'S NAME") == ("driver_name", "")


def test_no_plate_is_preserved_as_an_explicit_value():
    fields = extract([
        item("Driver Name", 20, 10, 120, 30), item("MARIA SANTOS", 140, 10, 300, 30),
        item("License Number", 20, 50, 140, 70), item("N01-12-123456", 160, 50, 300, 70),
        item("Plate Number", 20, 90, 120, 110), item("NO PLATE", 140, 90, 260, 110),
    ])

    assert fields["plate_number"] == "NO PLATE"


def test_checkbox_analysis_is_bounded_to_violation_section():
    items = [
        item("Driver's License Number", 20, 40, 180, 60),
        item("TRAFFIC VIOLATION", 100, 200, 300, 225),
        item("No Driver's License", 100, 250, 300, 270),
        item("Date and Time of Violation", 20, 600, 260, 625),
    ]
    assert violation_section_bounds(items, 900) == (225, 600)


def test_only_marked_official_checkbox_is_returned():
    items = [
        item("TRAFFIC VIOLATION", 90, 80, 300, 105),
        item("No Driver's License", 100, 150, 300, 170),
        item("Date and Time of Violation", 20, 260, 270, 285),
    ]
    blank = np.full((340, 420, 3), 255, dtype=np.uint8)
    cv2.rectangle(blank, (62, 144), (88, 172), (0, 0, 0), 2)
    marked = blank.copy()
    cv2.line(marked, (68, 151), (82, 166), (0, 0, 0), 3)
    cv2.line(marked, (82, 151), (68, 166), (0, 0, 0), 3)

    assert detect_checked_violations(blank, items) == []
    detected = detect_checked_violations(marked, items)
    assert detected and detected[0]["violation_type"] == "No Driver's License"


def test_shadow_noise_does_not_turn_empty_checkboxes_into_violations():
    labels = [
        "No Driver's License", "Failure to Carry Driver's License", "Invalid / Delinquent Driver's License",
        "Unregistered Motor Vehicle", "Nuisance Muffler", "Reckless Driving",
    ]
    items = [item("TRAFFIC VIOLATION", 90, 50, 300, 75)]
    image = np.full((500, 430, 3), 235, dtype=np.uint8)
    for row, label in enumerate(labels):
        top = 105 + row * 48
        items.append(item(label, 105, top, 340, top + 20))
        cv2.rectangle(image, (66, top - 5), (93, top + 23), (30, 30, 30), 2)
        cv2.circle(image, (79, top + 9), 1 + row % 2, (105, 105, 105), -1)
    items.append(item("Date and Time of Violation", 20, 410, 270, 435))
    cv2.line(image, (69, 108), (89, 124), (0, 0, 0), 4)

    detected = detect_checked_violations(image, items)
    assert [entry["violation_type"] for entry in detected] == ["No Driver's License"]


def test_official_ticket_extended_fields_and_dates_are_extracted():
    fields = extract([
        item("DRIVER'S NAME:", 20, 10, 150, 30), item("JUAN DELA CRUZ", 170, 10, 330, 30),
        item("ADDRESS:", 20, 40, 120, 60), item("NASUGBU, BATANGAS", 140, 40, 330, 60),
        item("Date of Birth:", 350, 40, 470, 60), item("04/21/1990", 490, 40, 590, 60),
        item("Owner of Vehicle:", 20, 80, 160, 100), item("JUAN DELA CRUZ", 180, 80, 330, 100),
        item("Vehicle Registration Number:", 20, 110, 230, 130), item("OR-12345", 250, 110, 340, 130),
        item("Color of Vehicle:", 20, 140, 150, 160), item("BLUE", 170, 140, 230, 160),
        item("Insurance Policy Number:", 20, 170, 210, 190), item("POL-7788", 230, 170, 330, 190),
    ])

    assert fields["driver_address"] == "NASUGBU, BATANGAS"
    assert fields["date_of_birth"] == "1990-04-21"
    assert fields["vehicle_owner"] == "JUAN DELA CRUZ"
    assert fields["vehicle_registration_number"] == "OR-12345"
    assert fields["vehicle_color"] == "BLUE"
    assert fields["insurance_policy_number"] == "POL-7788"


def test_dates_accept_numeric_and_written_months_case_insensitively():
    assert normalize_date("4/21/1990") == "1990-04-21"
    assert normalize_date("April 21, 1990") == "1990-04-21"
    assert normalize_date("21 april 1990") == "1990-04-21"
    assert normalize_date("SEP. 7 24") == "2024-09-07"
    assert normalize_date("February 31, 2024") == ""


def test_combined_violation_datetime_populates_both_fields():
    fields = extract([
        item("Date and Time of Violation:", 20, 10, 230, 30),
        item("10/29/2026 7:35 PM", 250, 10, 430, 30),
    ])

    assert fields["violation_date"] == "2026-10-29"
    assert fields["violation_time"] == "19:35"
    assert normalize_time("October 29, 2026 07:05 a.m.") == "07:05"


def test_officer_name_and_position_are_limited_to_officer_section():
    fields = extract([
        item("DRIVER'S NAME:", 20, 10, 150, 30), item("JUAN DELA CRUZ", 170, 10, 330, 30),
        item("Apprehending / Arresting Officer", 20, 500, 300, 525),
        item("Name:", 20, 550, 80, 570), item("PEDRO SANTOS", 100, 550, 240, 570),
        item("Position:", 20, 590, 100, 610), item("TMO II", 120, 590, 200, 610),
    ])

    assert fields["driver_name"] == "JUAN DELA CRUZ"
    assert fields["apprehending_officer_name"] == "PEDRO SANTOS"
    assert fields["apprehending_officer_position"] == "TMO II"


def test_blank_remarks_does_not_capture_the_printed_legal_instruction():
    fields = extract([
        item("TRAFFIC VIOLATION", 100, 100, 300, 125),
        item("REMARKS:", 20, 500, 120, 520),
        item("You are hereby directed to report to Municipal Treasurer's Office", 20, 550, 620, 575),
        item("within 72 hours from the date hereof", 20, 580, 380, 605),
    ])
    assert fields["ticket_remarks"] == ""


def test_officer_rows_do_not_capture_the_apprehension_date_and_time():
    fields = extract([
        item("Apprehending / Arresting Officer", 20, 500, 300, 525),
        item("Name:", 20, 550, 80, 570), item("Ian Desuyo", 100, 550, 220, 570),
        item("Position:", 20, 590, 100, 610), item("Patrolman", 120, 590, 220, 610),
        item("Date and Time:", 20, 630, 150, 650), item("09-04-2026 2:00PM", 170, 630, 350, 650),
    ])
    assert fields["apprehending_officer_name"] == "Ian Desuyo"
    assert fields["apprehending_officer_position"] == "Patrolman"


def test_missing_officer_handwriting_does_not_use_date_as_name_or_position():
    fields = extract([
        item("Apprehending / Arresting Officer", 20, 500, 300, 525),
        item("Name:", 20, 550, 80, 570),
        item("Position:", 20, 590, 100, 610),
        item("Date and Time:", 20, 630, 150, 650), item("09-04-2026 2:00PM", 170, 630, 350, 650),
    ])
    assert fields["apprehending_officer_name"] == ""
    assert fields["apprehending_officer_position"] == ""


def test_document_photo_is_detected_and_perspective_corrected():
    photo = np.full((900, 1200, 3), 35, dtype=np.uint8)
    corners = np.array([[180, 90], [1050, 150], [980, 800], [120, 730]], dtype=np.int32)
    cv2.fillConvexPoly(photo, corners, (245, 245, 245))
    cv2.polylines(photo, [corners], True, (255, 255, 255), 10)
    warped, detected = document_warp(photo)

    assert detected is True
    assert warped.shape[0] >= 600
    assert warped.shape[1] >= 800


def test_motor_handwriting_maps_to_motorcycle():
    fields = extract([
        item("Type of Vehicle:", 20, 20, 150, 40),
        item("Motor", 170, 20, 240, 40),
    ])

    assert fields["vehicle_type"] == "Motorcycle"


def test_na_license_and_plate_are_preserved_as_explicit_missing_values():
    fields = extract([
        item("License No:", 20, 20, 130, 40), item("N/A", 150, 20, 200, 40),
        item("Plate Number:", 20, 60, 140, 80), item("N/A", 160, 60, 210, 80),
    ])

    assert fields["license_number"] == "NO LICENSE"
    assert fields["plate_number"] == "NO PLATE"


def test_table_lines_are_deskewed_after_sideways_photo_is_oriented():
    ticket = np.full((700, 480, 3), 235, dtype=np.uint8)
    for y in range(100, 620, 55):
        cv2.line(ticket, (35, y), (445, y), (35, 35, 35), 3)
    matrix = cv2.getRotationMatrix2D((240, 350), -4.0, 1.0)
    tilted = cv2.warpAffine(ticket, matrix, (480, 700), borderValue=(235, 235, 235))

    corrected, angle = deskew_ticket(tilted)
    _, remaining_angle = deskew_ticket(corrected)

    assert abs(angle) >= 2.5
    assert abs(remaining_angle) < 0.5
    assert corrected.shape[0] >= tilted.shape[0]


def test_supplied_nasugbu_ticket_layout_keeps_each_value_in_its_own_field():
    """Regression layout based on the photographed ticket supplied by the user."""
    fields = extract([
        item("DRIVER'S NAME:", 20, 40, 150, 60), item("Dave Masambal", 170, 40, 330, 60),
        item("ADDRESS:", 20, 70, 110, 90), item("BUCANA, NASUGBU", 130, 70, 310, 90),
        item("License No.", 20, 100, 120, 120), item("N/A", 140, 100, 180, 120),
        item("Date of Birth:", 330, 100, 450, 120), item("January 22, 2002", 470, 100, 620, 120),
        item("Date and Time of Violation:", 20, 500, 230, 520), item("Aug. 21, 2026", 250, 500, 370, 520),
        item("Place of Violation:", 390, 500, 530, 520), item("JP Laurel St.", 550, 500, 670, 520),
        item("Owner of Vehicle:", 20, 530, 160, 550), item("Dave Masambal", 180, 530, 320, 550),
        item("Plate Number:", 390, 530, 500, 550), item("25MA2", 520, 530, 590, 550),
        item("Color of Vehicle:", 390, 560, 520, 580), item("Blue", 540, 560, 590, 580),
        item("Type of Vehicle:", 20, 590, 150, 610), item("Motor", 170, 590, 230, 610),
    ])

    assert fields["driver_name"] == "Dave Masambal"
    assert fields["driver_address"] == "BUCANA, NASUGBU"
    assert fields["license_number"] == "NO LICENSE"
    assert fields["date_of_birth"] == "2002-01-22"
    assert fields["violation_date"] == "2026-08-21"
    assert fields["location"] == "JP Laurel St."
    assert fields["vehicle_owner"] == "Dave Masambal"
    assert fields["plate_number"] == "25MA2"
    assert fields["vehicle_color"] == "Blue"
    assert fields["vehicle_type"] == "Motorcycle"
