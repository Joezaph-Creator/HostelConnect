<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve only hostels belonging to the logged-in owner
$stmt = $pdo->prepare("
    SELECT hostel_id, hostel_name
    FROM hostels
    WHERE owner_id = :owner_id
    ORDER BY hostel_name
");

$stmt->execute([
    ':owner_id' => $_SESSION['user_id']
]);

$hostels = $stmt->fetchAll();

$error = $_SESSION['room_error'] ?? '';
unset($_SESSION['room_error']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Room | HostelConnect</title>

    <link rel="stylesheet" href="../../public/css/dashboard.css">

    <style>
        .page-container {
            max-width: 850px;
            margin: 45px auto;
            padding: 0 20px;
        }

        .form-card {
            background: #fff;
            padding: 35px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .04);
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            font-size: 27px;
            color: #0f172a;
            margin-bottom: 9px;
        }

        .page-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .room-form .form-group {
            margin-bottom: 22px;
        }

        .room-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .room-form input,
        .room-form select,
        .room-form textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .room-form input:focus,
        .room-form select:focus,
        .room-form textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .room-form textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 28px;
        }

        .submit-btn {
            border: none;
            background: #2563eb;
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .back-btn {
            text-decoration: none;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
        }

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .empty-message {
            background: #fff7ed;
            color: #9a3412;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
        }

        @media(max-width: 600px) {
            .form-card {
                padding: 22px;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>

<header class="topbar">

    <a href="../../index.php" class="brand">
        Hostel<span>Connect.</span>
    </a>

    <div class="topbar-right">
        <div class="user-info">
            <span class="user-name">
                <?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="user-role">Hostel Owner</span>
        </div>

        <a href="../authentication/logout.php" class="logout-btn">
            Sign Out
        </a>
    </div>

</header>

<main class="page-container">

    <div class="page-heading">
        <h1>Add a New Room</h1>
        <p>Register a room under one of your hostel properties.</p>
    </div>

    <div class="form-card">

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($hostels)): ?>

            <div class="empty-message">
                You need to register a hostel before adding rooms.
                <a href="../hostels/add_hostel.php">Add your hostel here.</a>
            </div>

        <?php else: ?>

            <form class="room-form" action="process_room.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="hostel_id">Select Hostel</label>

                    <select id="hostel_id" name="hostel_id" required>
                        <option value="">Choose your hostel</option>

                        <?php foreach ($hostels as $hostel): ?>
                            <option value="<?= (int) $hostel['hostel_id'] ?>">
                                <?= htmlspecialchars($hostel['hostel_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="room_number">Room Number</label>

                    <input
                        type="text"
                        id="room_number"
                        name="room_number"
                        maxlength="30"
                        placeholder="e.g. Room 101"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="room_type">Room Type</label>

                    <select id="room_type" name="room_type" required>
                        <option value="">Select room type</option>
                        <option value="Single Room">Single Room</option>
                        <option value="Two in a Room">Two in a Room</option>
                        <option value="Three in a Room">Three in a Room</option>
                        <option value="Self-Contained">Self-Contained</option>
                        <option value="Shared Room">Shared Room</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="price">Price (GHS)</label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0.01"
                        step="0.01"
                        placeholder="e.g. 2500.00"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Room Description</label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe the room and its facilities..."
                    ></textarea>
                </div>

                <div class="form-actions">

                    <button type="submit" class="submit-btn">
                        Save Room
                    </button>

                    <a href="view_rooms.php" class="back-btn">
                        View Rooms
                    </a>

                </div>

            </form>

        <?php endif; ?>

    </div>

</main>

</body>
</html>