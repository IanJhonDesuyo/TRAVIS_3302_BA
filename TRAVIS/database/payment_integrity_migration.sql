-- Apply once to an existing TRAVIS database after confirming the duplicate
-- query returns no rows. The base schema already includes this constraint.

SELECT violation_id, COUNT(*) AS payment_count
FROM payments
GROUP BY violation_id
HAVING COUNT(*) > 1;

ALTER TABLE payments
    DROP INDEX idx_payments_violation_id,
    ADD UNIQUE KEY uq_payments_violation_id (violation_id);
