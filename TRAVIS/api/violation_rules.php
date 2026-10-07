<?php
declare(strict_types=1);

require_once __DIR__ . '/../Web_app/traffic_rules.php';

function travis_offense_analysis(PDO $pdo, string $driverName, string $violationType, string $licenseNumber = '', ?string $dateOfBirth = null): array
{
    $category = travis_violation_category($violationType);
    $driverName = preg_replace('/\s+/', ' ', trim($driverName)) ?? '';
    $licenseNumber = strtoupper(trim($licenseNumber));
    $hasLicense = $licenseNumber !== '' && !in_array($licenseNumber, ['NO LICENSE', 'NONE', 'N/A', 'NA'], true);
    $dateOfBirth = trim((string)$dateOfBirth);
    $matching = [];
    $matchedBy = null;
    $select = "SELECT item.violation_type, record.violation_date, record.ticket_number
            FROM violations record JOIN violation_items item ON item.violation_id = record.violation_id
            WHERE %s AND record.status <> 'cancelled'
            ORDER BY record.violation_date DESC, record.violation_id DESC";
    if ($hasLicense) {
        $statement = $pdo->prepare(sprintf($select, 'UPPER(TRIM(record.license_number)) = ? AND record.has_no_license = 0'));
        $statement->execute([$licenseNumber]);
        $matchedBy = 'license number';
    } elseif ($driverName !== '' && $dateOfBirth !== '') {
        $statement = $pdo->prepare(sprintf($select, 'UPPER(TRIM(record.driver_name)) = UPPER(?) AND record.date_of_birth = ?'));
        $statement->execute([$driverName, $dateOfBirth]);
        $matchedBy = 'full name and date of birth';
    } elseif ($driverName !== '') {
        $statement = $pdo->prepare(sprintf($select, 'UPPER(TRIM(record.driver_name)) = UPPER(?)'));
        $statement->execute([$driverName]);
        $matchedBy = 'driver name (fallback)';
    }
    if (isset($statement)) {
        $matching = array_values(array_filter($statement->fetchAll(), static fn(array $row): bool => travis_violation_category((string)$row['violation_type']) === $category));
    }

    $previous = count($matching);
    $maximum = 4;
    $suggestedOffense = min($maximum, $previous + 1);
    return [
        'category' => $category,
        'previous_offenses' => $previous,
        'suggested_offense' => $suggestedOffense,
        'maximum_offense' => $maximum,
        'at_maximum' => $suggestedOffense >= $maximum,
        'matched_by' => $matchedBy,
        'last_violation_date' => $matching[0]['violation_date'] ?? null,
        'last_ticket_number' => $matching[0]['ticket_number'] ?? null,
        'suggested_penalty' => travis_suggested_penalty($violationType, $suggestedOffense),
        'penalty_schedule' => travis_penalty_schedule($violationType),
    ];
}
