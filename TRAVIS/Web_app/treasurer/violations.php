<?php
declare(strict_types=1);

// Reuse the Admin violation workflow and presentation while rendering it
// inside the Treasurer portal layout. This keeps filters, actions, modals,
// and future design updates consistent between both portals.
define('TRAVIS_PORTAL_LAYOUT', __DIR__ . '/layout.php');
require dirname(__DIR__) . '/Admin/violations.php';
