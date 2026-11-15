<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$studentId = (int) $_SESSION['user_id'];

if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    exit('Invalid booking ID.');
}

$stmt = $pdo->prepare("
    SELECT
        b.*,
        r.room_number,
        r.room_type,
        r.price,
        h.hostel_name,
        h.location,
        h.contact_number,
        u.university_name
    FROM bookings b
    INNER JOIN rooms r ON b.room_id = r.room_id
    INNER JOIN hostels h ON r.hostel_id = h.hostel_id
    INNER JOIN universities u ON h.university_id = u.university_id
    WHERE b.booking_id = :booking_id
    AND b.student_id = :student_id
");

$stmt->execute([
    ':booking_id' => $bookingId,
    ':student_id' => $studentId
]);

$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Booking Details | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>
        body {
            background: #f1f5f9;
        }

        .container {
            max-width: 750px;
            margin: 45px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
        }

        .detail {
            padding: 13px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail strong {
            display: inline-block;
            min-width: 170px;
        }

        .back {
            display: inline-block;
            margin-top: 25px;
            color: #2563eb;
            text-decoration: none;
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

<main class="container">

    <h1>Booking Details</h1>

    <div class="detail">
        <strong>Booking Reference:</strong>
        #<?= (int)$booking['booking_id'] ?>
    </div>

    <div class="detail">
        <strong>Hostel:</strong>
        <?= e($booking['hostel_name']) ?>
    </div>

    <div class="detail">
        <strong>University:</strong>
        <?= e($booking['university_name']) ?>
    </div>

    <div class="detail">
        <strong>Location:</strong>
        <?= e($booking['location']) ?>
    </div>

    <div class="detail">
        <strong>Contact:</strong>
        <?= e($booking['contact_number']) ?>
    </div>

    <div class="detail">
        <strong>Room Number:</strong>
        <?= e($booking['room_number']) ?>
    </div>

    <div class="detail">
        <strong>Room Type:</strong>
        <?= e($booking['room_type']) ?>
    </div>

    <div class="detail">
        <strong>Room Price:</strong>
        GH₵ <?= number_format((float)$booking['price'], 2) ?>
    </div>

    <div class="detail">
        <strong>Check-in:</strong>
        <?= e($booking['start_date']) ?>
    </div>

    <div class="detail">
        <strong>Check-out:</strong>
        <?= e($booking['end_date']) ?>
    </div>

    <div class="detail">
        <strong>Booking Status:</strong>
        <?= e($booking['booking_status']) ?>
    </div>

    <div class="detail">
        <strong>Booking Date:</strong>
        <?= e($booking['booking_date']) ?>
    </div>

    <a href="my_bookings.php" class="back">
        ← Back to My Bookings
    </a>

</main>

</body>
</html>