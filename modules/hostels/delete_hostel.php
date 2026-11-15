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

if (!$hostelId) {
    exit("Invalid hostel ID.");
}

$stmt = $pdo->prepare(
    "DELETE FROM hostels
     WHERE hostel_id = ? AND owner_id = ?"
);

try {
    $stmt->execute([$hostelId, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Hostel deleted successfully.";
    } else {
        $_SESSION['error'] = "Hostel not found or you do not own it.";
    }

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $_SESSION['error'] = "This hostel cannot be deleted because it has rooms or related records. Remove or manage those records first.";
    } else {
        throw $e;
    }
}

header("Location: view_hostels.php");
exit;