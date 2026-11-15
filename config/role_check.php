<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

/**
 * Restrict access to specified user roles.
 */
function requireRole(array $allowedRoles): void
{
    $userRole = $_SESSION['role'] ?? '';

    if (!in_array($userRole, $allowedRoles, true)) {

        http_response_code(403);

        exit("403 Forbidden: You do not have permission to access this page.");
    }
}