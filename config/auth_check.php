<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {

    $_SESSION['auth_error'] = "Please sign in to access this page.";

    header("Location: /HostelConnect/modules/authentication/login.php");
    exit;
}