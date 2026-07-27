from ticket_ocr import OCRItem, extract


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

    assert fields == {
        "driver_name": "CONRAD DELA CRUZ",
        "license_number": "NO LICENSE",
        "plate_number": "QWDQW123",
        "vehicle_type": "Motorcycle",
        "violation_type": "No Driver's License",
        "location": "BUCANA",
        "penalty_amount": "300",
    }
