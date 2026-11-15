<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: view_rooms.php");
    exit;
}

if (!hash_equals(
    $_SESSION['csrf_token'] ?? '',
    $_POST['csrf_token'] ?? ''
)) {
    exit("Invalid security token.");
}

$roomId = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
$hostelId = filter_input(INPUT_POST, 'hostel_id', FILTER_VALIDATE_INT);

$roomNumber = trim($_POST['room_number'] ?? '');
$roomType = trim($_POST['room_type'] ?? '');
$priceInput = trim($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');

$allowedTypes = [
    'Single Room',
    'Two in a Room',
    'Three in a Room',
    'Self-Contained',
    'Shared Room'
];

if (
    !$roomId ||
    !$hostelId ||
    $roomNumber === '' ||
    strlen($roomNumber) > 30 ||
    !in_array($roomType, $allowedTypes, true) ||
    !is_numeric($priceInput) ||
    !is_finite((float)$priceInput) ||
    (float)$priceInput <= 0 ||
    (float)$priceInput > 99999999.99 ||
    strlen($description) > 5000
) {
    $_SESSION['room_error'] = "Please provide valid room information.";
    header("Location: edit_room.php?id=" . (int)$roomId);
    exit;
}

// Confirm that the room belongs to this owner.
$stmt = $pdo->prepare("
    SELECT r.room_id
    FROM rooms r
    INNER JOIN hostels h ON r.hostel_id = h.hostel_id
    WHERE r.room_id = ? AND h.owner_id = ?
");

$stmt->execute([$roomId, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit("Room not found or access denied.");
}

// Confirm that the selected hostel belongs to this owner.
$stmt = $pdo->prepare("
    SELECT hostel_id
    FROM hostels
    WHERE hostel_id = ? AND owner_id = ?
");

$stmt->execute([$hostelId, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    exit("Invalid hostel selected.");
}

try {

    $sql = "
        UPDATE rooms
        SET hostel_id = :hostel_id,
            room_number = :room_number,
            room_type = :room_type,
            price = :price,
            description = :description
        WHERE room_id = :room_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':hostel_id' => $hostelId,
        ':room_number' => $roomNumber,
        ':room_type' => $roomType,
        ':price' => $priceInput,
        ':description' => $description,
        ':room_id' => $roomId
    ]);

    $_SESSION['room_success'] = "Room updated successfully.";

} catch (PDOException $e) {

    if ($e->getCode() === '23000') {
        $_SESSION['room_error'] = "That room number already exists in the selected hostel.";
    } else {
        throw $e;
    }
}

header("Location: view_rooms.php");
exit;