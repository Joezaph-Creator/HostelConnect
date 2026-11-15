<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config/database.php';

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    $_SESSION['auth_error'] = "Invalid request. Please try again.";
    header("Location: login.php");
    exit;
}

// Collect login details
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Validate input
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $password === ''
) {
    $_SESSION['auth_error'] = "Invalid email address or password.";
    header("Location: login.php");
    exit;
}

try {

    // Find user by email
    $sql = "
        SELECT user_id, full_name, email, password, role
        FROM users
        WHERE email = :email
        LIMIT 1
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':email' => $email
    ]);

    $user = $statement->fetch();

    // Verify password
    if (!$user || !password_verify($password, $user['password'])) {

        $_SESSION['auth_error'] = "Invalid email address or password.";

        header("Location: login.php");
        exit;
    }

    // Prevent session fixation
    session_regenerate_id(true);

    // Store authenticated user information
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    // Create a fresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Redirect according to user role
    switch ($user['role']) {

        case 'Admin':
            header("Location: ../../index.php");
            break;

        case 'Owner':
            header("Location: ../../index.php");
            break;

        case 'Student':
            header("Location: ../../index.php");
            break;

        default:
            session_unset();
            session_destroy();

            header("Location: login.php");
            break;
    }

    exit;

} catch (PDOException $e) {

    error_log("Login error: " . $e->getMessage());

    $_SESSION['auth_error'] = "Unable to sign in at the moment. Please try again.";

    header("Location: login.php");
    exit;
}