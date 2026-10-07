import cv2
import numpy as np

from ticket_ocr import OCRItem, document_warp, extract, label_match, normalize_date, violation_section_bounds


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


def test_document_photo_is_detected_and_perspective_corrected():
    photo = np.full((900, 1200, 3), 35, dtype=np.uint8)
    corners = np.array([[180, 90], [1050, 150], [980, 800], [120, 730]], dtype=np.int32)
    cv2.fillConvexPoly(photo, corners, (245, 245, 245))
    cv2.polylines(photo, [corners], True, (255, 255, 255), 10)
    warped, detected = document_warp(photo)

    assert detected is True
    assert warped.shape[0] >= 600
    assert warped.shape[1] >= 800
