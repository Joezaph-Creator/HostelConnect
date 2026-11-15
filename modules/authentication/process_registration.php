<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config/database.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
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
    header("Location: register.php");
    exit;
}

// Collect form information
$fullName = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phoneNumber = trim($_POST['phone_number'] ?? '');
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Preserve non-sensitive fields if validation fails
$_SESSION['old_input'] = [
    'full_name' => $fullName,
    'email' => $email,
    'phone_number' => $phoneNumber,
    'role' => $role
];

// Validate full name
if ($fullName === '' || strlen($fullName) > 150) {
    $_SESSION['auth_error'] = "Please enter a valid full name.";
    header("Location: register.php");
    exit;
}

// Validate email
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 150
) {
    $_SESSION['auth_error'] = "Please enter a valid email address.";
    header("Location: register.php");
    exit;
}

// Validate phone number
if (strlen($phoneNumber) > 20) {
    $_SESSION['auth_error'] = "Phone number must not exceed 20 characters.";
    header("Location: register.php");
    exit;
}

// Validate role
if (!in_array($role, ['Student', 'Owner'], true)) {
    $_SESSION['auth_error'] = "Please select a valid account type.";
    header("Location: register.php");
    exit;
}

// Validate password
if (strlen($password) < 8) {
    $_SESSION['auth_error'] = "Password must contain at least 8 characters.";
    header("Location: register.php");
    exit;
}

// Confirm passwords
if ($password !== $confirmPassword) {
    $_SESSION['auth_error'] = "Your passwords do not match.";
    header("Location: register.php");
    exit;
}

try {

    // Check whether email already exists
    $checkEmail = $pdo->prepare(
        "SELECT user_id FROM users WHERE email = :email LIMIT 1"
    );

    $checkEmail->execute([
        ':email' => $email
    ]);

    if ($checkEmail->fetch()) {
        $_SESSION['auth_error'] = "An account with this email already exists.";
        header("Location: register.php");
        exit;
    }

    // Securely hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new account
    $sql = "
        INSERT INTO users
        (full_name, email, phone_number, password, role)
        VALUES
        (:full_name, :email, :phone_number, :password, :role)
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
        ':password' => $hashedPassword,
        ':role' => $role
    ]);

    // Clear old form information
    unset($_SESSION['old_input']);

    // Generate a fresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Success message
    $_SESSION['auth_success'] = "Your account has been created successfully. Please sign in.";

    header("Location: login.php");
    exit;

} catch (PDOException $e) {

    error_log("Registration error: " . $e->getMessage());

    if ($e->getCode() === '23000') {
        $_SESSION['auth_error'] = "This email address may already be registered.";
    } else {
        $_SESSION['auth_error'] = "Registration could not be completed. Please try again.";
    }

    header("Location: register.php");
    exit;
}