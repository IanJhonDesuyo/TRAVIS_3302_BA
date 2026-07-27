ALTER TABLE violations
    ADD COLUMN has_no_license TINYINT(1) NOT NULL DEFAULT 0 AFTER license_number;

UPDATE violations
SET has_no_license = 1
WHERE UPPER(TRIM(license_number)) = 'NO LICENSE';
