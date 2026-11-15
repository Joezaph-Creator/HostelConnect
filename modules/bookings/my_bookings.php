<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

$studentId = (int) $_SESSION['user_id'];

// Ensure this page has a CSRF token for booking actions and payment status checks.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$stmt = $pdo->prepare("
    SELECT
        b.booking_id,
        b.start_date,
        b.end_date,
        b.booking_status,
        b.booking_date,
        r.room_number,
        r.room_type,
        r.price,
        h.hostel_name,
        h.location,
        u.university_name,
        (
            SELECT p.payment_status
            FROM payments p
            WHERE p.booking_id = b.booking_id
            ORDER BY p.payment_id DESC
            LIMIT 1
        ) AS payment_status,
        (
            SELECT p.payment_reference
            FROM payments p
            WHERE p.booking_id = b.booking_id
            ORDER BY p.payment_id DESC
            LIMIT 1
        ) AS payment_reference
    FROM bookings b
    INNER JOIN rooms r ON b.room_id = r.room_id
    INNER JOIN hostels h ON r.hostel_id = h.hostel_id
    INNER JOIN universities u ON h.university_id = u.university_id
    WHERE b.student_id = :student_id
    ORDER BY b.booking_date DESC
");
$stmt->execute([':student_id' => $studentId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash messages
$success = $_SESSION['booking_success'] ?? '';
$error = $_SESSION['booking_error'] ?? '';
unset($_SESSION['booking_success'], $_SESSION['booking_error']);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusColor(string $status): string
{
    return match ($status) {
        'Confirmed' => '#15803d',
        'Pending' => '#d97706',
        'Cancelled' => '#dc2626',
        'Completed' => '#2563eb',
        default => '#64748b'
    };
}

function paymentStatusColor(?string $status): string
{
    return match ($status) {
        'Successful' => '#15803d',
        'Pending' => '#d97706',
        'Failed' => '#dc2626',
        default => '#64748b'
    };
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | HostelConnect</title>
    <link rel="stylesheet" href="../../public/css/dashboard.css">
    <style>
        body { background: #f1f5f9; }
        .container { max-width: 1100px; margin: 40px auto; padding: 20px; }
        .page-heading {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px; margin-bottom: 25px;
        }
        .booking-card {
            background: white; padding: 24px; border-radius: 12px;
            border: 1px solid #e2e8f0; margin-bottom: 20px;
        }
        .booking-card h3 { margin-top: 0; color: #1e293b; }
        .booking-card p { color: #64748b; line-height: 1.7; }
        .status {
            display: inline-block; padding: 7px 13px; border-radius: 20px;
            color: white; font-size: 12px; font-weight: bold;
        }
        .btn {
            display: inline-block; padding: 10px 16px; margin: 8px 5px 0 0;
            border-radius: 6px; text-decoration: none; color: white;
            background: #2563eb; border: none; cursor: pointer; font-size: 14px;
        }
        .btn:hover { opacity: 0.9; }
        .cancel-btn { background: #dc2626; }
        .pay-btn { background: #15803d; }
        .history-btn { background: #475569; }
        .check-btn { background: #d97706; }
        .message { padding: 13px; border-radius: 7px; margin-bottom: 18px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .empty { background: white; padding: 40px; text-align: center; border-radius: 12px; }
        .payment-information {
            background: #f8fafc; padding: 12px 15px; border-radius: 8px; margin-top: 15px;
        }
        .payment-information p { margin: 5px 0; }
        @media (max-width: 600px) {
            .container { margin: 20px auto; }
            .booking-card { padding: 18px; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a href="../../index.php" class="brand">Hostel<span>Connect.</span></a>
    <a href="../authentication/logout.php" class="logout-btn">Sign Out</a>
</header>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>My Bookings</h1>
            <p>View and manage your hostel reservations.</p>
        </div>
        <a href="../payments/payment_history.php" class="btn history-btn">Payment History</a>
    </div>

    <?php if ($success): ?>
        <div class="message success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($bookings): ?>
        <?php foreach ($bookings as $booking): ?>
            <?php
                $bookingStatus = (string) $booking['booking_status'];
                $paymentStatus = $booking['payment_status'] !== null
                    ? (string) $booking['payment_status']
                    : null;
                $bookingId = (int) $booking['booking_id'];
            ?>

            <article class="booking-card">
                <h3><?= e($booking['hostel_name']) ?></h3>

                <p><strong>University:</strong> <?= e($booking['university_name']) ?></p>
                <p><strong>Location:</strong> <?= e($booking['location']) ?></p>
                <p>
                    <strong>Room:</strong>
                    <?= e($booking['room_number']) ?> (<?= e($booking['room_type']) ?>)
                </p>
                <p>
                    <strong>Semester Price:</strong>
                    GH₵ <?= number_format((float) $booking['price'], 2) ?>
                </p>
                <p><strong>Check-in:</strong> <?= e($booking['start_date']) ?></p>
                <p><strong>Check-out:</strong> <?= e($booking['end_date']) ?></p>

                <p>
                    <strong>Booking Status:</strong>
                    <span class="status" style="background: <?= statusColor($bookingStatus) ?>">
                        <?= e($bookingStatus) ?>
                    </span>
                </p>

                <div class="payment-information">
                    <p>
                        <strong>Payment Status:</strong>
                        <span class="status" style="background: <?= paymentStatusColor($paymentStatus) ?>">
                            <?= e($paymentStatus ?? 'Not Started') ?>
                        </span>
                    </p>
                    <p><strong>Payment Period:</strong> One Semester</p>
                </div>

                <a class="btn" href="booking_details.php?id=<?= $bookingId ?>">
                    View Details
                </a>

                <?php if ($bookingStatus === 'Pending' && $paymentStatus === 'Pending'): ?>
                    <form action="../payments/verify_payment.php" method="POST" style="display:inline;">
                        <input type="hidden" name="reference" value="<?= e($booking['payment_reference']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="btn check-btn">Check Payment Status</button>
                    </form>

                <?php elseif (
                    $bookingStatus === 'Pending' &&
                    ($paymentStatus === null || $paymentStatus === 'Failed')
                ): ?>
                    <a class="btn pay-btn" href="../payments/pay_now.php?booking_id=<?= $bookingId ?>">
                        <?= $paymentStatus === 'Failed' ? 'Retry Payment' : 'Pay Now' ?>
                    </a>
                <?php endif; ?>

                <?php
                    // Cancellation is available only when there is no unresolved or successful payment.
                    $canCancel = $bookingStatus === 'Pending'
                        && ($paymentStatus === null || $paymentStatus === 'Failed');
                ?>

                <?php if ($canCancel): ?>
                    <form
                        action="cancel_booking.php"
                        method="POST"
                        style="display:inline;"
                        onsubmit="return confirm('Are you sure you want to cancel this booking?');"
                    >
                        <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button type="submit" class="btn cancel-btn">Cancel Booking</button>
                    </form>
                <?php endif; ?>

                <?php if ($paymentStatus === 'Successful'): ?>
                    <p style="color:#15803d;font-weight:bold;margin-top:15px;">
                        ✓ Payment successfully verified.
                    </p>
                <?php elseif ($paymentStatus === 'Pending'): ?>
                    <p style="color:#a16207;font-weight:bold;margin-top:15px;">
                        Your payment is being processed. Check its status before attempting another payment.
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="empty">
            <h2>No Bookings Yet</h2>
            <p>You have not made any hostel reservations.</p>
            <a href="../hostels/browse_hostels.php" class="btn">Explore Hostels</a>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
