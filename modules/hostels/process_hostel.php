<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_hostel.php");
    exit;
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    $_SESSION['hostel_error'] = "Invalid request. Please try again.";
    header("Location: add_hostel.php");
    exit;
}

// Collect form information
$hostelName = trim($_POST['hostel_name'] ?? '');
$universityId = filter_var(
    $_POST['university_id'] ?? null,
    FILTER_VALIDATE_INT
);
$location = trim($_POST['location'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$description = trim($_POST['description'] ?? '');

if (
    $hostelName === '' ||
    strlen($hostelName) > 150 ||
    !$universityId ||
    $location === '' ||
    strlen($location) > 200 ||
    strlen($contactNumber) > 20
) {
    $_SESSION['hostel_error'] = "Please provide valid hostel information.";
    header("Location: add_hostel.php");
    exit;
}

try {

    // Confirm that the selected university exists
    $checkUniversity = $pdo->prepare(
        "SELECT university_id FROM universities
         WHERE university_id = :university_id"
    );

    $checkUniversity->execute([
        ':university_id' => $universityId
    ]);

    if (!$checkUniversity->fetch()) {
        $_SESSION['hostel_error'] = "The selected university does not exist.";
        header("Location: add_hostel.php");
        exit;
    }

    // Save hostel information
    $sql = "
        INSERT INTO hostels
        (
            university_id,
            owner_id,
            hostel_name,
            location,
            description,
            contact_number
        )
        VALUES
        (
            :university_id,
            :owner_id,
            :hostel_name,
            :location,
            :description,
            :contact_number
        )
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':university_id' => $universityId,
        ':owner_id' => $_SESSION['user_id'],
        ':hostel_name' => $hostelName,
        ':location' => $location,
        ':description' => $description !== '' ? $description : null,
        ':contact_number' => $contactNumber !== '' ? $contactNumber : null
    ]);

    $_SESSION['hostel_success'] = "Your hostel has been added successfully.";

    header("Location: view_hostels.php");
    exit;

} catch (PDOException $e) {

    error_log("Hostel creation error: " . $e->getMessage());

    $_SESSION['hostel_error'] = "Unable to save the hostel. Please try again.";

    header("Location: add_hostel.php");
    exit;
}