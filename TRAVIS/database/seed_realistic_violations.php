<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Web_app/traffic_rules.php';

date_default_timezone_set('Asia/Manila');

$dbHost = getenv('TRAVIS_DB_HOST') ?: 'localhost';
$dbName = getenv('TRAVIS_DB_NAME') ?: 'travis';
$dbUser = getenv('TRAVIS_DB_USER') ?: 'root';
$dbPass = getenv('TRAVIS_DB_PASS') ?: '';
$recordCount = 300;

$pdo = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$firstNames = [
    'Juan Miguel', 'Mark Anthony', 'John Paul', 'Carlo', 'Paolo', 'Jose', 'Ramon', 'Angelo',
    'Christian', 'Jerome', 'Daniel', 'Joshua', 'Michael', 'Francis', 'Gabriel', 'Marvin',
    'Roberto', 'Eduardo', 'Antonio', 'Renato', 'Maria Cristina', 'Ana Marie', 'Jessa Mae',
    'Rose Ann', 'Maricel', 'Joanna', 'Kathleen', 'Angelica', 'Michelle', 'Liza',
];
$lastNames = [
    'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos',
    'Flores', 'Aquino', 'Castillo', 'Navarro', 'Torres', 'Fernandez', 'Gonzales', 'Diaz',
    'Mercado', 'Pascual', 'Domingo', 'Manalo', 'Tolentino', 'Aguilar', 'Valdez', 'Salazar',
    'Evangelista', 'Soriano', 'De Guzman', 'Lorenzo', 'Francisco', 'Rivera',
];
$locations = [
    'J.P. Laurel Street corner C. Alvarez Street, Barangay 2',
    'J.P. Laurel Street corner Escalera Street, Barangay 3',
    'J.P. Laurel Street corner Concepcion Street, Barangay 5',
    'J.P. Laurel Street corner F. Alix Street, Barangay 6',
    'J.P. Laurel Street corner J.P. Rizal Street, Barangay 10',
    'J.P. Laurel Street corner Consuelo Street, Barangay 11',
    'R. Martinez Street, Poblacion',
    'Apacible Boulevard, Barangay Wawa',
    'Nasugbu-Tuy Road, Barangay Lumbangan',
    'Nasugbu-Tagaytay Highway, Barangay Kaylaway',
    'Palico-Balayan-Batangas Road, Barangay Palico',
    'Barangay Bucana Road near Public Market',
    'Barangay Pantalan Road near Fish Port',
    'Barangay Banilad Provincial Road',
    'Barangay Natipuan Road',
    'Barangay Aga Provincial Road',
    'Barangay Sagbat Road',
    'Barangay Bilaran Road',
    'Barangay Bunducan Road',
    'Barangay Calayo Road',
    'Barangay Tumalim Road',
    'Barangay Maugat Road',
    'Barangay Mataas na Pulo Road',
    'C. Alvarez Street near Nasugbu Public Market',
    'J.P. Laurel Street near Municipal Hall',
];
$weightedViolationTypes = [
    'Illegal Parking', 'Illegal Parking', 'Illegal Parking',
    'No Side Mirror', 'No Side Mirror',
    'OR / CR Not Carried', 'OR / CR Not Carried',
    'Failure to Carry Driver\'s License', 'Failure to Carry Driver\'s License',
    'Unregistered Motor Vehicle', 'Unregistered Motor Vehicle',
    'Disregarding Traffic Sign / Officer', 'Disregarding Traffic Sign / Officer',
    'Obstruction', 'Obstruction',
    'Coding Violation', 'Coding Violation',
    'Nuisance Muffler', 'Nuisance Muffler',
    'Colorum',
    'Reckless Driving',
    'Overloading',
    'Loading / Unloading in Prohibited Zone',
    'Operating Out of Line',
    'No Canvas Cover',
    'Driving with Sleeveless Shirt / Shorts',
    'Not Wearing Shoes',
    'Arrogant Driver',
    'Illegal Terminal',
];
$peakHours = [6, 7, 7, 8, 8, 9, 10, 11, 13, 14, 15, 16, 16, 17, 17, 18, 18, 19, 20];

function plate_letters(int $number): string
{
    $letters = '';
    for ($position = 0; $position < 3; $position++) {
        $letters = chr(65 + ($number % 26)) . $letters;
        $number = intdiv($number, 26);
    }
    return $letters;
}

function ordinance_for(string $violationType): string
{
    return match ($violationType) {
        "No Driver's License", "Failure to Carry Driver's License", "Invalid / Delinquent Driver's License" => 'RA 4136, Sections 19 and 22',
        'Nuisance Muffler' => 'RA 4136, Section 34 / Local Anti-Noise Ordinance',
        'Coding Violation' => 'Municipal Number Coding Ordinance',
        'Colorum', 'Operating Out of Line' => 'RA 4136 / LTFRB regulations',
        'Illegal Parking', 'Obstruction', 'Loading / Unloading in Prohibited Zone' => 'Nasugbu Municipal Traffic Ordinance',
        default => 'RA 4136 / Nasugbu Municipal Traffic Ordinance',
    };
}

function action_for(string $violationType): string
{
    return match ($violationType) {
        "No Driver's License" => 'Citation issued; licensed driver required before release',
        'Colorum', 'Operating Out of Line' => 'Citation issued; unit referred for franchise verification',
        'Unregistered Motor Vehicle' => 'Citation issued; registration documents required',
        'Nuisance Muffler' => 'Citation issued; defective accessory noted for correction',
        default => 'Citation issued; driver advised of applicable traffic rule',
    };
}

mt_srand(20260918);

$vehicleTypes = ['Motorcycle', 'Motorcycle', 'Motorcycle', 'Car', 'Car', 'SUV', 'Truck', 'Bus', 'Other'];
$profiles = [];
for ($index = 0; $index < 90; $index++) {
    $firstName = $firstNames[$index % count($firstNames)];
    $surnameGroup = intdiv($index, count($firstNames));
    $lastName = $lastNames[(($index * 7) + ($surnameGroup * 9) + 3) % count($lastNames)];
    $middleInitial = chr(65 + (($index * 5) % 26));
    $vehicleType = $vehicleTypes[$index % count($vehicleTypes)];
    $letters = plate_letters(1200 + $index);
    $plateNumber = $vehicleType === 'Motorcycle'
        ? str_pad((string)(120 + $index), 3, '0', STR_PAD_LEFT) . $letters
        : $letters . ' ' . str_pad((string)(1100 + ($index * 37) % 8800), 4, '0', STR_PAD_LEFT);
    $hasNoLicense = $index % 11 === 0;
    $profiles[] = [
        'name' => "{$firstName} {$middleInitial}. {$lastName}",
        'license' => $hasNoLicense ? 'NO LICENSE' : sprintf('N01-%02d-%06d', 10 + ($index % 15), 120000 + ($index * 791)),
        'has_no_license' => $hasNoLicense,
        'plate' => $plateNumber,
        'vehicle' => $vehicleType,
    ];
}

$adminIds = array_map('intval', $pdo->query("SELECT user_id FROM users WHERE status = 'active' AND role = 'Administrator' ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN));
$treasuryIds = array_map('intval', $pdo->query("SELECT user_id FROM users WHERE status = 'active' AND role = 'Treasury Personnel' ORDER BY user_id")->fetchAll(PDO::FETCH_COLUMN));

$insertViolation = $pdo->prepare("INSERT INTO violations (
    ticket_number, driver_name, license_number, has_no_license, plate_number,
    vehicle_type, violation_type, violation_location, violation_date, violation_time,
    offense_number, penalty_amount, ordinance_reference, enforcement_action,
    input_method, encoded_by, status, created_at, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)");
$insertItem = $pdo->prepare("INSERT INTO violation_items
    (violation_id, violation_type, penalty_amount, ocr_confidence, created_at)
    VALUES (?, ?, ?, ?, ?)");
$insertPayment = $pdo->prepare("INSERT INTO payments
    (violation_id, amount_paid, payment_status, payment_date, received_by,
     payment_method, receipt_reference, created_at)
    VALUES (?, ?, 'completed', ?, ?, 'cash', ?, ?)");
$ticketExists = $pdo->prepare('SELECT 1 FROM violations WHERE ticket_number = ?');

$offenseCounts = [];
$startDate = (new DateTimeImmutable('today'))->modify('-365 days');
$now = new DateTimeImmutable('now');
$insertedViolationIds = [];

$pdo->beginTransaction();
try {
    for ($index = 0; $index < $recordCount; $index++) {
        $profileIndex = $index < 210 ? (($index * 17) % 60) : mt_rand(0, count($profiles) - 1);
        $profile = $profiles[$profileIndex];
        $eventDate = $startDate->modify('+' . (int)floor(($index * 360) / ($recordCount - 1)) . ' days');
        $hour = $peakHours[array_rand($peakHours)];
        $minute = mt_rand(0, 59);
        $eventDateTime = $eventDate->setTime($hour, $minute, mt_rand(0, 59));

        $itemTypes = [];
        if ($profile['has_no_license']) {
            $itemTypes[] = "No Driver's License";
            if (mt_rand(1, 100) <= 42) {
                $itemTypes[] = $weightedViolationTypes[array_rand($weightedViolationTypes)];
            }
        } else {
            $itemTypes[] = $weightedViolationTypes[array_rand($weightedViolationTypes)];
            if (mt_rand(1, 100) <= 18) {
                do {
                    $additionalType = $weightedViolationTypes[array_rand($weightedViolationTypes)];
                } while (in_array($additionalType, $itemTypes, true));
                $itemTypes[] = $additionalType;
            }
        }
        $itemTypes = array_values(array_unique($itemTypes));

        $items = [];
        $recordOffenseNumber = 1;
        foreach ($itemTypes as $itemType) {
            $countKey = mb_strtoupper($profile['name']) . '|' . travis_violation_category($itemType);
            $offenseNumber = min(3, ($offenseCounts[$countKey] ?? 0) + 1);
            $offenseCounts[$countKey] = ($offenseCounts[$countKey] ?? 0) + 1;
            $recordOffenseNumber = max($recordOffenseNumber, $offenseNumber);
            $items[] = [
                'type' => $itemType,
                'offense' => $offenseNumber,
                'penalty' => travis_suggested_penalty($itemType, $offenseNumber),
            ];
        }

        $penaltyTotal = array_sum(array_column($items, 'penalty'));
        $typeSummary = implode(', ', array_column($items, 'type'));
        $primaryType = $items[0]['type'];
        $location = $locations[array_rand($locations)];
        $inputMethod = mt_rand(1, 100) <= 28 ? 'ocr' : 'manual';
        $encodedBy = $adminIds ? $adminIds[array_rand($adminIds)] : null;

        $ticketSequence = 700001 + $index;
        do {
            $ticketNumber = 'TRV-' . $eventDateTime->format('Ymd') . '-' . str_pad((string)$ticketSequence, 6, '0', STR_PAD_LEFT);
            $ticketExists->execute([$ticketNumber]);
            $ticketSequence++;
        } while ($ticketExists->fetchColumn());

        $createdAt = $eventDateTime->modify('+' . mt_rand(2, 25) . ' minutes');
        $insertViolation->execute([
            $ticketNumber,
            $profile['name'],
            $profile['license'],
            $profile['has_no_license'] ? 1 : 0,
            $profile['plate'],
            $profile['vehicle'],
            $typeSummary,
            $location,
            $eventDateTime->format('Y-m-d'),
            $eventDateTime->format('H:i:s'),
            $recordOffenseNumber,
            $penaltyTotal,
            ordinance_for($primaryType),
            action_for($primaryType),
            $inputMethod,
            $encodedBy,
            $createdAt->format('Y-m-d H:i:s'),
            $createdAt->format('Y-m-d H:i:s'),
        ]);
        $violationId = (int)$pdo->lastInsertId();
        $insertedViolationIds[] = $violationId;

        foreach ($items as $item) {
            $confidence = $inputMethod === 'ocr' ? mt_rand(8200, 9900) / 10000 : null;
            $insertItem->execute([
                $violationId,
                $item['type'],
                $item['penalty'],
                $confidence,
                $createdAt->format('Y-m-d H:i:s'),
            ]);
        }

        $paymentDelay = mt_rand(0, 5);
        $paymentDateTime = $paymentDelay === 0
            ? $eventDateTime->modify('+' . mt_rand(30, 360) . ' minutes')
            : $eventDateTime->modify("+{$paymentDelay} days")->setTime(mt_rand(8, 16), mt_rand(0, 59), mt_rand(0, 59));
        if ($paymentDateTime > $now) {
            $paymentDateTime = $now->modify('-' . mt_rand(10, 180) . ' minutes');
        }
        $receivedBy = $treasuryIds ? $treasuryIds[array_rand($treasuryIds)] : null;
        $receiptReference = sprintf('OR-NAS-%s-%06d', $paymentDateTime->format('Y'), $violationId);
        $insertPayment->execute([
            $violationId,
            $penaltyTotal,
            $paymentDateTime->format('Y-m-d H:i:s'),
            $receivedBy,
            $receiptReference,
            $paymentDateTime->format('Y-m-d H:i:s'),
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

$placeholders = implode(',', array_fill(0, count($insertedViolationIds), '?'));
$summary = $pdo->prepare("SELECT
    COUNT(*) AS violations,
    COUNT(DISTINCT driver_name) AS distinct_drivers,
    SUM(has_no_license) AS no_license_records,
    SUM(offense_number >= 2) AS repeat_offense_records,
    SUM(penalty_amount) AS total_penalties,
    MIN(violation_date) AS first_date,
    MAX(violation_date) AS last_date
    FROM violations WHERE violation_id IN ({$placeholders})");
$summary->execute($insertedViolationIds);
$itemCount = $pdo->prepare("SELECT COUNT(*) FROM violation_items WHERE violation_id IN ({$placeholders})");
$itemCount->execute($insertedViolationIds);
$paymentCount = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE violation_id IN ({$placeholders})");
$paymentCount->execute($insertedViolationIds);

echo json_encode([
    'success' => true,
    'inserted' => array_merge($summary->fetch(), [
        'violation_items' => (int)$itemCount->fetchColumn(),
        'payments' => (int)$paymentCount->fetchColumn(),
    ]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
