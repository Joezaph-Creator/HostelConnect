<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

$hostelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$hostelId) {
    http_response_code(400);
    exit("Invalid hostel ID.");
}

// Fetch hostel details
$stmt = $pdo->prepare("
    SELECT
        h.hostel_id,
        h.hostel_name,
        h.location,
        h.description,
        h.contact_number,
        u.university_name
    FROM hostels h
    INNER JOIN universities u
        ON h.university_id = u.university_id
    WHERE h.hostel_id = :hostel_id
");

$stmt->execute([
    ':hostel_id' => $hostelId
]);

$hostel = $stmt->fetch();

if (!$hostel) {
    http_response_code(404);
    exit("Hostel not found.");
}

// Fetch rooms belonging to this hostel
$stmt = $pdo->prepare("
    SELECT
        room_id,
        room_number,
        room_type,
        price,
        description
    FROM rooms
    WHERE hostel_id = :hostel_id
    ORDER BY price ASC
");

$stmt->execute([
    ':hostel_id' => $hostelId
]);

$rooms = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($hostel['hostel_name'], ENT_QUOTES, 'UTF-8') ?>
        | HostelConnect
    </title>

    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>

        .page-container {
            max-width: 1100px;
            margin: 45px auto;
            padding: 0 22px;
        }

        .hostel-header {
            background: white;
            padding: 30px;
            border-radius: 13px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .hostel-header h1 {
            margin-bottom: 12px;
            font-size: 28px;
        }

        .hostel-header p {
            color: #64748b;
            line-height: 1.8;
            font-size: 14px;
        }

        .university-tag {
            display: inline-block;
            background: #eff6ff;
            color: #2563eb;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .section-heading {
            margin-bottom: 20px;
        }

        .room-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .room-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 24px;
            border-radius: 12px;
        }

        .room-card h3 {
            margin-bottom: 12px;
        }

        .room-card p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 9px;
        }

        .room-price {
            font-size: 20px;
            font-weight: 800;
            color: #2563eb;
            margin-top: 18px;
        }

        .book-btn {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.3s ease;
        }

        .book-btn:hover {
            background: #1d4ed8;
        }

        .empty-state {
            background: white;
            text-align: center;
            padding: 45px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        @media(max-width: 850px) {
            .room-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 600px) {
            .room-grid {
                grid-template-columns: 1fr;
            }
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

<main class="page-container">

    <a href="browse_hostels.php" class="back-link">
        ← Back to Explore Hostels
    </a>

    <section class="hostel-header">

        <span class="university-tag">
            <?= htmlspecialchars($hostel['university_name'], ENT_QUOTES, 'UTF-8') ?>
        </span>

        <h1>
            <?= htmlspecialchars($hostel['hostel_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p>
            <strong>Location:</strong>
            <?= htmlspecialchars($hostel['location'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <strong>Contact:</strong>
            <?= htmlspecialchars($hostel['contact_number'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            <?= htmlspecialchars(
                $hostel['description'] ?? 'No description provided.',
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

    </section>

    <div class="section-heading">

        <h2>Available Room Listings</h2>

        <p>
            Explore the rooms registered under this hostel.
        </p>

    </div>

    <?php if (count($rooms) > 0): ?>

        <section class="room-grid">

            <?php foreach ($rooms as $room): ?>

                <article class="room-card">

                    <h3>
                        <?= htmlspecialchars($room['room_type'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p>
                        <strong>Room Number:</strong>
                        <?= htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p>
                        <?= htmlspecialchars(
                            $room['description'] ?? 'No description provided.',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <div class="room-price">
                        GHS <?= number_format((float)$room['price'], 2) ?>
                    </div>

                    <!-- Room Booking Button -->
                    <a
                        href="../bookings/create_booking.php?room_id=<?= (int)$room['room_id'] ?>"
                        class="book-btn"
                    >
                        Book This Room
                    </a>

                </article>

            <?php endforeach; ?>

        </section>

    <?php else: ?>

        <div class="empty-state">

            <h2>No Rooms Listed</h2>

            <p>
                This hostel has not registered any rooms yet.
                Please check again later.
            </p>

        </div>

    <?php endif; ?>

</main>

</body>
</html>