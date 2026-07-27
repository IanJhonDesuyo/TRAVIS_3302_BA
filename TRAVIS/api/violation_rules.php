<?php
declare(strict_types=1);

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
        'Other Traffic Violation',
    ];
}

function travis_penalty_fees(): array
{
    return [100, 200, 300, 500, 1000, 1500, 2000, 2500, 3000, 5000];
}

function travis_vehicle_types(): array
{
    return ['Motorcycle', 'Car', 'SUV', 'Truck', 'Bus', 'Other'];
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

function travis_offense_analysis(PDO $pdo, string $license, string $plate, string $violationType): array
{
    $license = strtoupper(trim($license));
    $plate = strtoupper(trim($plate));
    if ($license === '' && $plate === '') {
        $category = travis_violation_category($violationType);
        return ['category' => $category, 'previous_offenses' => 0, 'suggested_offense' => 1, 'maximum_offense' => $category === 'coding' ? 4 : 3, 'at_maximum' => false, 'matched_by' => null, 'last_violation_date' => null, 'last_ticket_number' => null];
    }

    if ($license !== '' && $license !== 'NO LICENSE') {
        $statement = $pdo->prepare("SELECT item.violation_type, record.violation_date, record.ticket_number
            FROM violations record JOIN violation_items item ON item.violation_id = record.violation_id
            WHERE UPPER(record.license_number) = ? AND record.status <> 'cancelled'
            ORDER BY record.violation_date DESC, record.violation_id DESC");
        $statement->execute([$license]);
        $matchedBy = 'license number';
    } else {
        $statement = $pdo->prepare("SELECT item.violation_type, record.violation_date, record.ticket_number
            FROM violations record JOIN violation_items item ON item.violation_id = record.violation_id
            WHERE UPPER(record.plate_number) = ? AND record.status <> 'cancelled'
            ORDER BY record.violation_date DESC, record.violation_id DESC");
        $statement->execute([$plate]);
        $matchedBy = 'plate number';
    }

    $category = travis_violation_category($violationType);
    $matching = array_values(array_filter($statement->fetchAll(), static fn(array $row): bool => travis_violation_category((string)$row['violation_type']) === $category));
    $previous = count($matching);
    $maximum = $category === 'coding' ? 4 : 3;
    return [
        'category' => $category,
        'previous_offenses' => $previous,
        'suggested_offense' => min($maximum, $previous + 1),
        'maximum_offense' => $maximum,
        'at_maximum' => $previous >= $maximum,
        'matched_by' => $matchedBy,
        'last_violation_date' => $matching[0]['violation_date'] ?? null,
        'last_ticket_number' => $matching[0]['ticket_number'] ?? null,
    ];
}
