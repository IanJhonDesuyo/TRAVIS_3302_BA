<?php
declare(strict_types=1);

/**
 * Shared traffic-ticket choices and penalty rules.
 *
 * Tickets track up to a fourth offense. The available penalty schedule remains
 * capped at its highest tier, so a fourth offense keeps the third-tier fee.
 */
function travis_violation_types(): array
{
    return [
        "No Driver's License", "Failure to Carry Driver's License", "Invalid / Delinquent Driver's License",
        'Unregistered Motor Vehicle', 'Nuisance Muffler', 'Disregarding Traffic Sign / Officer',
        'Reckless Driving', 'Colorum', 'Illegal Parking', 'Illegal Terminal', 'Obstruction',
        'OR / CR Not Carried', 'No Canvas Cover', 'Operating Out of Line', 'Overloading',
        'Overcharging', 'Loading / Unloading in Prohibited Zone', 'Refusal to Convey Passenger',
        'Driving with Sleeveless Shirt / Shorts', 'Not Wearing Shoes', 'No Side Mirror',
        'Arrogant Driver', 'Driving Under the Influence of Liquor', 'Coding Violation',
        'LOI 1482 Highway', 'Other Traffic Violation',
    ];
}

function travis_penalty_fees(): array
{
    return [100, 200, 300, 500, 750, 1000, 1500, 2000, 2500, 3000, 5000];
}

function travis_vehicle_types(): array
{
    return ['Motorcycle', 'Tricycle', 'Car', 'SUV', 'Truck', 'Bus', 'Other'];
}

function travis_violation_category(string $type): string
{
    if (in_array($type, ["No Driver's License", "Failure to Carry Driver's License", "Invalid / Delinquent Driver's License"], true)) {
        return 'driver-license';
    }
    if (in_array($type, ['Unregistered Motor Vehicle', 'OR / CR Not Carried'], true)) {
        return 'vehicle-registration';
    }
    if ($type === 'Coding Violation') {
        return 'coding';
    }
    return strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $type), '-'));
}

function travis_penalty_schedule(string $violationType): array
{
    return match (travis_violation_category($violationType)) {
        'coding', 'colorum' => [500.0, 1000.0, 2500.0],
        'nuisance-muffler' => [500.0, 750.0, 2500.0],
        default => [200.0, 500.0, 1000.0],
    };
}

function travis_suggested_penalty(string $violationType, int $offenseNumber): float
{
    $schedule = travis_penalty_schedule($violationType);
    $index = min(count($schedule), max(1, $offenseNumber)) - 1;
    return $schedule[$index];
}

function travis_mysqli_offense_analysis(mysqli $conn, string $driverName, string $violationType, string $licenseNumber = '', ?string $dateOfBirth = null): array
{
    $category = travis_violation_category($violationType);
    $driverName = preg_replace('/\s+/', ' ', trim($driverName)) ?? '';
    $licenseNumber = strtoupper(trim($licenseNumber));
    $hasLicense = $licenseNumber !== '' && !in_array($licenseNumber, ['NO LICENSE', 'NONE', 'N/A', 'NA'], true);
    $dateOfBirth = trim((string)$dateOfBirth);
    $matching = [];
    $matchedBy = null;

    $select = "SELECT item.violation_type, record.violation_date, record.ticket_number
            FROM violations record
            JOIN violation_items item ON item.violation_id = record.violation_id
            WHERE %s AND record.status <> 'cancelled'
            ORDER BY record.violation_date DESC, record.violation_id DESC";
    if ($hasLicense) {
        $stmt = $conn->prepare(sprintf($select, "UPPER(TRIM(record.license_number)) = ? AND record.has_no_license = 0"));
        $stmt->bind_param('s', $licenseNumber);
        $matchedBy = 'license number';
    } elseif ($driverName !== '' && $dateOfBirth !== '') {
        $stmt = $conn->prepare(sprintf($select, "UPPER(TRIM(record.driver_name)) = UPPER(?) AND record.date_of_birth = ?"));
        $stmt->bind_param('ss', $driverName, $dateOfBirth);
        $matchedBy = 'full name and date of birth';
    } elseif ($driverName !== '') {
        $stmt = $conn->prepare(sprintf($select, "UPPER(TRIM(record.driver_name)) = UPPER(?)"));
        $stmt->bind_param('s', $driverName);
        $matchedBy = 'driver name (fallback)';
    }
    if (isset($stmt)) {
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $matching = array_values(array_filter($rows, static fn(array $row): bool => travis_violation_category((string)$row['violation_type']) === $category));
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
