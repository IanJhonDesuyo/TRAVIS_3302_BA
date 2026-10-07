ALTER TABLE violations
    ADD COLUMN IF NOT EXISTS has_no_plate TINYINT(1) NOT NULL DEFAULT 0 AFTER plate_number;

UPDATE violations
SET has_no_plate = 1,
    plate_number = 'NO PLATE'
WHERE UPPER(TRIM(plate_number)) IN ('NO PLATE', 'NOPLATE', 'NONE', 'N/A', 'NA');

