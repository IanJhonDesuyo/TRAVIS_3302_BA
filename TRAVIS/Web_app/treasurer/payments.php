<?php
declare(strict_types=1);

// Reuse the Admin payment workflow and presentation inside the Treasurer
// portal. Treasurer payments additionally open the printable receipt as soon
// as a successful transaction has been recorded.
define('TRAVIS_PORTAL_LAYOUT', __DIR__ . '/layout.php');
define('TRAVIS_AUTO_PRINT_PAYMENT_RECEIPT', true);
require dirname(__DIR__) . '/Admin/payments.php';
