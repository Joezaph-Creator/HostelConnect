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

if (!$roomId) {
    $_SESSION['room_error'] = "Invalid room ID.";
    header("Location: view_rooms.php");
    exit;
}

try {

    $sql = "
        DELETE r
        FROM rooms r
        INNER JOIN hostels h ON r.hostel_id = h.hostel_id
        WHERE r.room_id = :room_id
        AND h.owner_id = :owner_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':room_id' => $roomId,
        ':owner_id' => $_SESSION['user_id']
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['room_success'] = "Room deleted successfully.";
    } else {
        $_SESSION['room_error'] = "Room not found or you do not own it.";
    }

} catch (PDOException $e) {

    if ($e->getCode() === '23000') {
        $_SESSION['room_error'] = "This room cannot be deleted because it has related booking or payment records.";
    } else {
        throw $e;
    }
}

header("Location: view_rooms.php");
exit;