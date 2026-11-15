<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_room.php");
    exit;
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    $_SESSION['room_error'] = "Invalid request. Please try again.";
    header("Location: add_room.php");
    exit;
}

// Collect form information
$hostelId = filter_var(
    $_POST['hostel_id'] ?? null,
    FILTER_VALIDATE_INT
);

$roomNumber = trim($_POST['room_number'] ?? '');
$roomType = trim($_POST['room_type'] ?? '');
$priceInput = trim($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');

$allowedRoomTypes = [
    'Single Room',
    'Two in a Room',
    'Three in a Room',
    'Self-Contained',
    'Shared Room'
];

// Validate inputs
if (
    !$hostelId ||
    $roomNumber === '' ||
    strlen($roomNumber) > 30 ||
    !in_array($roomType, $allowedRoomTypes, true) ||
    !is_numeric($priceInput) ||
    !is_finite((float) $priceInput) ||
    (float) $priceInput <= 0 ||
    (float) $priceInput > 99999999.99
) {
    $_SESSION['room_error'] = "Please provide valid room information.";
    header("Location: add_room.php");
    exit;
}

if (strlen($description) > 5000) {
    $_SESSION['room_error'] = "The room description is too long.";
    header("Location: add_room.php");
    exit;
}

try {

    // Verify hostel ownership
    $checkHostel = $pdo->prepare("
        SELECT hostel_id
        FROM hostels
        WHERE hostel_id = :hostel_id
        AND owner_id = :owner_id
    ");

    $checkHostel->execute([
        ':hostel_id' => $hostelId,
        ':owner_id' => $_SESSION['user_id']
    ]);

    if (!$checkHostel->fetch()) {
        $_SESSION['room_error'] = "You cannot add a room to this hostel.";
        header("Location: add_room.php");
        exit;
    }

    // Insert room
    $sql = "
        INSERT INTO rooms
        (
            hostel_id,
            room_number,
            room_type,
            price,
            description
        )
        VALUES
        (
            :hostel_id,
            :room_number,
            :room_type,
            :price,
            :description
        )
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':hostel_id' => $hostelId,
        ':room_number' => $roomNumber,
        ':room_type' => $roomType,
        ':price' => $priceInput,
        ':description' => $description !== '' ? $description : null
    ]);

    $_SESSION['room_success'] = "Room added successfully.";

    header("Location: view_rooms.php");
    exit;

} catch (PDOException $e) {

    error_log("Room creation error: " . $e->getMessage());

    if ($e->getCode() === '23000') {
        $_SESSION['room_error'] = "This room number already exists in the selected hostel.";
    } else {
        $_SESSION['room_error'] = "Unable to save the room. Please try again.";
    }

    header("Location: add_room.php");
    exit;
}