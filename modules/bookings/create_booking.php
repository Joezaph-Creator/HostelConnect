<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);

if (!$roomId || $roomId < 1) {
    exit("Invalid room selected.");
}

$stmt = $pdo->prepare("
    SELECT
        r.room_id,
        r.room_number,
        r.room_type,
        r.price,
        r.description,
        h.hostel_name,
        h.location,
        u.university_name
    FROM rooms r
    INNER JOIN hostels h ON r.hostel_id = h.hostel_id
    INNER JOIN universities u ON h.university_id = u.university_id
    WHERE r.room_id = :room_id
");

$stmt->execute(['room_id' => $roomId]);

$room = $stmt->fetch();

if (!$room) {
    http_response_code(404);
    exit("Room not found.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book Room | HostelConnect</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        }

        h2 {
            color: #1d4ed8;
            margin-top: 0;
        }

        .room-info {
            padding: 18px;
            margin-bottom: 25px;
            background: #eff6ff;
            border-radius: 10px;
            line-height: 1.7;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        button {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .back {
            display: inline-block;
            margin-top: 18px;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Book Your Room</h2>

    <div class="room-info">
        <strong>Hostel:</strong>
        <?= escape($room['hostel_name']) ?><br>

        <strong>University:</strong>
        <?= escape($room['university_name']) ?><br>

        <strong>Location:</strong>
        <?= escape($room['location']) ?><br>

        <strong>Room Number:</strong>
        <?= escape($room['room_number']) ?><br>

        <strong>Room Type:</strong>
        <?= escape($room['room_type']) ?><br>

        <strong>Price:</strong>
        GH₵ <?= number_format((float)$room['price'], 2) ?>
    </div>

    <form action="process_booking.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['csrf_token']) ?>"
        >

        <input
            type="hidden"
            name="room_id"
            value="<?= (int)$room['room_id'] ?>"
        >

        <label for="start_date">Check-in Date</label>

        <input
            type="date"
            id="start_date"
            name="start_date"
            min="<?= date('Y-m-d') ?>"
            required
        >

        <label for="end_date">Check-out Date</label>

        <input
            type="date"
            id="end_date"
            name="end_date"
            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
            required
        >

        <button type="submit">Submit Booking Request</button>

    </form>

    <a class="back" href="../hostels/browse_hostels.php">
        ← Back to Hostels
    </a>

</div>

</body>
</html>