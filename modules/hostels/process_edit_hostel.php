<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: view_hostels.php");
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    exit("Invalid security token.");
}

$hostelId = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);
$universityId = filter_input(INPUT_POST, 'university_id', FILTER_VALIDATE_INT);

$hostelName = trim($_POST['hostel_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$contact = trim($_POST['contact_number'] ?? '');
$description = trim($_POST['description'] ?? '');

if (
    !$hostelId || !$universityId ||
    $hostelName === '' || $location === '' ||
    strlen($hostelName) > 150 ||
    strlen($location) > 200 ||
    strlen($contact) > 20 ||
    strlen($description) > 5000
) {
    exit("Please provide valid hostel information.");
}

$check = $pdo->prepare(
    "SELECT hostel_id FROM hostels
     WHERE hostel_id = ? AND owner_id = ?"
);
$check->execute([$hostelId, $_SESSION['user_id']]);

if (!$check->fetch()) {
    http_response_code(404);
    exit("Hostel not found.");
}

$universityCheck = $pdo->prepare(
    "SELECT university_id FROM universities WHERE university_id = ?"
);
$universityCheck->execute([$universityId]);

if (!$universityCheck->fetch()) {
    exit("Invalid university selected.");
}

$stmt = $pdo->prepare(
    "UPDATE hostels
     SET university_id = ?, hostel_name = ?, location = ?,
         contact_number = ?, description = ?
     WHERE hostel_id = ? AND owner_id = ?"
);

$stmt->execute([
    $universityId,
    $hostelName,
    $location,
    $contact,
    $description,
    $hostelId,
    $_SESSION['user_id']
]);

$_SESSION['success'] = "Hostel updated successfully.";

header("Location: view_hostels.php");
exit;