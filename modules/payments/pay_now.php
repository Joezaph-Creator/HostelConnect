<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Validate Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    http_response_code(400);
    exit('Invalid booking reference.');
}

/*
|--------------------------------------------------------------------------
| Retrieve Booking Information
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.booking_id,
        b.booking_status,
        b.start_date,
        b.end_date,
        r.room_number,
        r.room_type,
        r.price,
        h.hostel_name,
        h.location,
        u.email,
        u.full_name
    FROM bookings b
    INNER JOIN rooms r
        ON b.room_id = r.room_id
    INNER JOIN hostels h
        ON r.hostel_id = h.hostel_id
    INNER JOIN users u
        ON b.student_id = u.user_id
    WHERE b.booking_id = :booking_id
      AND b.student_id = :student_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':booking_id' => $bookingId,
    ':student_id' => $userId
]);

$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

/*
|--------------------------------------------------------------------------
| Check Booking Status
|--------------------------------------------------------------------------
*/

if ($booking['booking_status'] !== 'Pending') {
    exit('Payment is only available for pending bookings.');
}

/*
|--------------------------------------------------------------------------
| Validate Semester Price
|--------------------------------------------------------------------------
*/

$amount = (string) $booking['price'];

if (
    !preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) ||
    (float) $amount <= 0
) {
    exit('Invalid booking amount.');
}

$formattedAmount = number_format((float) $amount, 2);

/*
|--------------------------------------------------------------------------
| Check Existing Payment Records
|--------------------------------------------------------------------------
*/

$paymentQuery = $pdo->prepare("
    SELECT
        payment_id,
        payment_reference,
        payment_status
    FROM payments
    WHERE booking_id = :booking_id
    ORDER BY payment_id DESC
");

$paymentQuery->execute([
    ':booking_id' => $bookingId
]);

$payments = $paymentQuery->fetchAll();

$hasPendingPayment = false;
$hasSuccessfulPayment = false;

foreach ($payments as $payment) {

    if ($payment['payment_status'] === 'Pending') {
        $hasPendingPayment = true;
    }

    if ($payment['payment_status'] === 'Successful') {
        $hasSuccessfulPayment = true;
    }
}

/*
|--------------------------------------------------------------------------
| Helper for Safe HTML Output
|--------------------------------------------------------------------------
*/

function escapeOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Make Payment | HostelConnect</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .payment-container {
            max-width: 650px;
            margin: 60px auto;
            padding: 20px;
        }

        .payment-card {
            background: #ffffff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.07);
        }

        h1 {
            margin-top: 0;
            color: #1d4ed8;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
        }

        .booking-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;
        }

        .detail-value {
            font-weight: 600;
            text-align: right;
        }

        .amount-box {
            text-align: center;
            background: #eff6ff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .amount-box p {
            margin: 0 0 8px;
            color: #64748b;
        }

        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #1d4ed8;
        }

        .pay-button {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .pay-button:hover {
            background: #1d4ed8;
        }

        .notice {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .notice.warning {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }

        .notice.success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .notice.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .notice-small {
            margin-top: 20px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

    </style>

</head>

<body>

<div class="payment-container">

    <div class="payment-card">

        <h1>Complete Your Payment</h1>

        <p class="subtitle">
            Review your hostel booking details before proceeding.
        </p>

        <div class="booking-details">

            <div class="detail-row">
                <span class="detail-label">Booking ID</span>
                <span class="detail-value">
                    #<?= (int) $booking['booking_id'] ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Student</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['full_name']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Hostel</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['hostel_name']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Location</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['location']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Room</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['room_number']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Room Type</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['room_type']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Booking Period</span>
                <span class="detail-value">
                    <?= escapeOutput($booking['start_date']) ?>
                    to
                    <?= escapeOutput($booking['end_date']) ?>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Payment Period</span>
                <span class="detail-value">One Semester</span>
            </div>

        </div>

        <div class="amount-box">

            <p>Total Amount to Pay</p>

            <div class="amount">
                GH₵ <?= $formattedAmount ?>
            </div>

        </div>

        <?php if ($hasSuccessfulPayment): ?>

            <div class="notice success">
                A successful payment already exists for this booking.
                You cannot make another payment.
                Please check your payment history or contact the administrator
                if your booking has not been confirmed.
            </div>

        <?php elseif ($hasPendingPayment): ?>

            <div class="notice warning">
                A payment attempt is already awaiting verification.
                Please check your payment history before attempting another payment.
                Do not make another payment while this transaction is unresolved.
            </div>

        <?php else: ?>

            <form action="initialize_payment.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeOutput($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?= (int) $booking['booking_id'] ?>"
                >

                <button type="submit" class="pay-button">
                    Proceed to Payment
                </button>

            </form>

        <?php endif; ?>

        <a href="../bookings/my_bookings.php" class="back-link">
            ← Back to My Bookings
        </a>

        <p class="notice-small">
            Secure test payment powered by Paystack.
            No real money will be collected.
        </p>

    </div>

</div>

</body>
</html>