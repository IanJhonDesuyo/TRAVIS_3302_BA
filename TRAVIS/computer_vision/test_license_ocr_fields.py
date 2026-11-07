import numpy as np

from license_ocr import (
    LICENSE_OCR_VERSION,
    OCRItem,
    collapse_repeated_words,
    crop_to_camera_guide,
    extract_license_fields,
    inline_license_value,
    license_label_match,
    normalize_license_date,
    normalize_license_number,
    parse_lto_name,
    select_best_license_fields,
)


def test_license_ocr_version_is_exposed_for_deployment_verification():
    assert LICENSE_OCR_VERSION == "2026.11.07.1"


def item(text, left, top, right, bottom, confidence=0.95):
    return OCRItem(text, confidence, left, top, right, bottom)


def test_lto_name_order_supports_multiple_first_names():
    parsed = parse_lto_name("DELA CRUZ, JUAN PEDRO GARCIA")
    assert parsed["driver_last_name"] == "Dela Cruz"
    assert parsed["driver_first_name"] == "Juan Pedro"
    assert parsed["driver_middle_name"] == "Garcia"


def test_lto_year_first_dates_are_normalized():
    assert normalize_license_date("1987/10/04") == "1987-10-04"
    assert normalize_license_date("2022/10/04") == "2022-10-04"


def test_lto_license_number_spacing_and_ocr_digits_are_normalized():
    assert normalize_license_number("N 19-69-004197") == "N19-69-004197"
    assert normalize_license_number("N19-69-OO4197") == "N19-69-004197"


def test_inline_label_values_are_preserved():
    assert inline_license_value(
        "Last Name, First Name, Middle Name RAMOS, APPLE SHANE CONSIGO",
        "driver_name",
    ) == "RAMOS, APPLE SHANE CONSIGO"
    assert inline_license_value("License No. D18-25-005144", "license_number") == "D18-25-005144"
    assert inline_license_value("Expiration Date 2030/02/09", "license_expiry_date") == "2030/02/09"


def test_generic_footer_and_header_text_are_not_license_field_labels():
    assert license_label_match("Name") is None
    assert license_label_match("DRIVER'S LICENSE") is None
    assert license_label_match("Last Name, First Name, Middle Name") == "driver_name"
    assert license_label_match("License No.") == "license_number"


def test_standard_lto_license_fields_are_extracted():
    fields, confidence = extract_license_fields([
        item("Last Name, First Name, Middle Name", 220, 90, 510, 110),
        item("DELA CRUZ, JUAN PEDRO GARCIA", 220, 115, 540, 140),
        item("Date of Birth", 330, 155, 420, 175),
        item("1987/10/04", 330, 180, 420, 202),
        item("Address", 220, 215, 290, 235),
        item("UNIT/HOUSE NO. BUILDING, STREET NAME,", 220, 240, 570, 260),
        item("BARANGAY, CITY/MUNICIPALITY", 220, 265, 500, 285),
        item("License No.", 220, 310, 310, 330),
        item("N03-12-123456", 220, 335, 350, 357),
        item("Expiration Date", 365, 310, 480, 330),
        item("2022/10/04", 365, 335, 460, 357),
        item("Agency Code", 500, 310, 590, 330),
        item("N32", 510, 335, 550, 357),
    ])
    assert fields["driver_last_name"] == "Dela Cruz"
    assert fields["driver_first_name"] == "Juan Pedro"
    assert fields["driver_middle_name"] == "Garcia"
    assert fields["date_of_birth"] == "1987-10-04"
    assert fields["license_number"] == "N03-12-123456"
    assert fields["license_expiry_date"] == "2022-10-04"
    assert "CITY/MUNICIPALITY" in fields["driver_address"]
    assert confidence["license_number"] > 0.8


def test_government_header_and_signatory_text_are_rejected_as_values():
    fields, confidence = extract_license_fields([
        item("Last Name, First Name, Middle Name", 220, 90, 510, 110),
        item("Assistant Secretary Atty. Mendoza Weight Kg", 220, 115, 620, 140),
        item("Address", 220, 215, 290, 235),
        item("REPUBLIC OF THE PHILIPPINES LAND TRANSPORTATION OFFICE", 220, 240, 650, 265),
    ])
    assert "driver_name" not in fields
    assert "driver_last_name" not in fields
    assert "driver_address" not in fields
    assert confidence["driver_name"] == 0.0
    assert confidence["driver_address"] == 0.0


def test_duplicate_preprocessing_address_is_not_concatenated():
    fields, _ = extract_license_fields([
        item("Address", 220, 215, 290, 235),
        item("BUCAL CALATAGAN BATANGAS", 220, 240, 520, 265, 0.96),
        item("ALATAGAN BATANGAS", 225, 241, 515, 265, 0.71),
    ])
    assert fields["driver_address"] == "BUCAL CALATAGAN BATANGAS"


def test_full_name_overrides_mixed_component_guesses():
    fields, _ = extract_license_fields([
        item("Last Name, First Name, Middle Name", 220, 90, 510, 110),
        item("JONSON, FRANCISCO JR SANTOS", 220, 115, 540, 140),
        item("Middle Name", 460, 80, 580, 100),
        item("Jonson.Franciscojrsantos", 460, 108, 620, 130, 0.70),
    ])
    assert fields["driver_last_name"] == "Jonson"
    assert fields["driver_first_name"] == "Francisco"
    assert fields["driver_middle_name"] == "Santos"
    assert fields["driver_suffix"] == "Jr"


def test_real_card_name_recovers_period_ir_suffix_and_duplicate_tail():
    parsed = parse_lto_name("JONSON. FRANCISCO IR SANTOS JONSONFRANCISCOJRSANTOS")
    assert parsed["driver_last_name"] == "Jonson"
    assert parsed["driver_first_name"] == "Francisco"
    assert parsed["driver_middle_name"] == "Santos"
    assert parsed["driver_suffix"] == "Jr"


def test_repeated_address_sequence_is_collapsed_once():
    assert collapse_repeated_words(
        "BUCAL CALATAGAN BATANGAS BUCAL CALATAGAN BATANGAS"
    ) == "BUCAL CALATAGAN BATANGAS"


def test_variant_results_are_selected_per_field_without_merging_text_lines():
    clean_pass = [
        item("Last Name, First Name, Middle Name", 220, 90, 510, 110),
        item("JONSON, FRANCISCO JR SANTOS", 220, 115, 540, 140, 0.94),
    ]
    address_pass = [
        item("Address", 220, 215, 290, 235),
        item("BUCAL CALATAGAN BATANGAS", 220, 240, 520, 265, 0.96),
    ]
    fields, _ = select_best_license_fields([clean_pass, address_pass])
    assert fields["driver_first_name"] == "Francisco"
    assert fields["driver_middle_name"] == "Santos"
    assert fields["driver_address"] == "BUCAL CALATAGAN BATANGAS"


def test_camera_guide_becomes_a_real_padded_crop():
    image = np.zeros((600, 1000, 3), dtype=np.uint8)
    crop, applied = crop_to_camera_guide(image, {
        "preview_width": 800,
        "preview_height": 500,
        "left": 80,
        "top": 50,
        "width": 640,
        "height": 400,
        "padding_ratio": 0.04,
    })
    assert applied is True
    assert crop.shape[1] < image.shape[1]
    assert crop.shape[0] < image.shape[0]
    assert 1.45 < crop.shape[1] / crop.shape[0] < 1.75
