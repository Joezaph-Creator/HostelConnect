<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

$roomId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$roomId) {
    exit("Invalid room ID.");
}

// Fetch the room only if it belongs to the logged-in owner.
$sql = "
    SELECT r.*
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

$room = $stmt->fetch();

if (!$room) {
    http_response_code(404);
    exit("Room not found.");
}

// Fetch hostels belonging to this owner.
$stmt = $pdo->prepare("
    SELECT hostel_id, hostel_name
    FROM hostels
    WHERE owner_id = ?
    ORDER BY hostel_name
");

$stmt->execute([$_SESSION['user_id']]);
$hostels = $stmt->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$roomTypes = [
    'Single Room',
    'Two in a Room',
    'Three in a Room',
    'Self-Contained',
    'Shared Room'
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Room | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>
        .form-container {
            max-width: 700px;
            margin: 45px auto;
            padding: 30px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
        }

        .form-container h1 {
            margin-bottom: 10px;
        }

        .form-container p {
            color: #64748b;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .submit-btn {
            background: #2563eb;
            color: white;
            padding: 13px 20px;
            border: none;
            border-radius: 7px;
            font-weight: 700;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>

<body>

<header class="topbar">
    <a href="../../index.php" class="brand">
        Hostel<span>Connect.</span>
    </a>

    <a href="../authentication/logout.php" class="logout-btn">
        Sign Out
    </a>
</header>

<main class="form-container">

    <h1>Edit Room</h1>
    <p>Update your room information below.</p>

    <form action="process_edit_room.php" method="POST">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="room_id"
               value="<?= (int)$room['room_id'] ?>">

        <div class="form-group">
            <label>Hostel</label>

            <select name="hostel_id" required>
                <?php foreach ($hostels as $hostel): ?>
                    <option value="<?= (int)$hostel['hostel_id'] ?>"
                        <?= (int)$room['hostel_id'] === (int)$hostel['hostel_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($hostel['hostel_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Room Number</label>
            <input type="text" name="room_number" maxlength="30"
                   value="<?= htmlspecialchars($room['room_number']) ?>" required>
        </div>

        <div class="form-group">
            <label>Room Type</label>

            <select name="room_type" required>
                <?php foreach ($roomTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"
                        <?= $room['room_type'] === $type ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Price (GHS)</label>
            <input type="number" name="price" min="0.01"
                   step="0.01" max="99999999.99"
                   value="<?= htmlspecialchars((string)$room['price']) ?>" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" maxlength="5000"><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="submit-btn">
            Update Room
        </button>

    </form>

    <a href="view_rooms.php" class="back-link">← Back to My Rooms</a>

</main>

</body>
</html>