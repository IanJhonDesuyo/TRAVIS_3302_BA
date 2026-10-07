ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS official_receipt_number VARCHAR(120) NULL AFTER receipt_reference;

CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_official_receipt_number
    ON payments (official_receipt_number);
