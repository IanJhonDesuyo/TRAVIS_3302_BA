<?php
declare(strict_types=1);

// Reuse the Admin payment workflow and presentation inside the Treasurer
// portal so both roles use the same payment and receipt workflow.
define('TRAVIS_PORTAL_LAYOUT', __DIR__ . '/layout.php');
define('TRAVIS_ADMIN_PARITY_UI', true);
require dirname(__DIR__) . '/Admin/payments.php';
