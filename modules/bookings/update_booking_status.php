<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Accept POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token.');
}

/*
|--------------------------------------------------------------------------
| Validate Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = filter_var(
    $_POST['booking_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$bookingId || $bookingId < 1) {

    $_SESSION['booking_error'] = 'Invalid booking selected.';

    header('Location: owner_bookings.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Requested Status
|--------------------------------------------------------------------------
*/

$newStatus = $_POST['booking_status'] ?? '';

$ownerId = (int) $_SESSION['user_id'];

$allowedStatuses = ['Cancelled', 'Completed'];

if (
    !is_string($newStatus) ||
    !in_array($newStatus, $allowedStatuses, true)
) {

    $_SESSION['booking_error'] =
        'Invalid booking status. Owners cannot manually confirm unpaid bookings.';

    header('Location: owner_bookings.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Update Booking Status
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Retrieve and Lock Booking
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_status,
            b.student_id,
            h.owner_id

        FROM bookings b

        INNER JOIN rooms r
            ON b.room_id = r.room_id

        INNER JOIN hostels h
            ON r.hostel_id = h.hostel_id

        WHERE b.booking_id = :booking_id
          AND h.owner_id = :owner_id

        FOR UPDATE
    ");

    $stmt->execute([
        ':booking_id' => $bookingId,
        ':owner_id' => $ownerId
    ]);

    $booking = $stmt->fetch();

    if (!$booking) {

        $pdo->rollBack();

        $_SESSION['booking_error'] =
            'Booking not found or you do not have permission to manage it.';

        header('Location: owner_bookings.php');
        exit;
    }

    $currentStatus = $booking['booking_status'];

    /*
    |--------------------------------------------------------------------------
    | Define Permitted Status Transitions
    |--------------------------------------------------------------------------
    */

    $allowedTransitions = [

        'Pending' => ['Cancelled'],

        'Confirmed' => ['Completed'],

        'Cancelled' => [],

        'Completed' => []

    ];

    if (
        !in_array(
            $newStatus,
            $allowedTransitions[$currentStatus] ?? [],
            true
        )
    ) {

        $pdo->rollBack();

        $_SESSION['booking_error'] =
            'This booking cannot be changed to the selected status.';

        header('Location: owner_bookings.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Protect Bookings With Pending or Successful Payments
    |--------------------------------------------------------------------------
    */

    if ($newStatus === 'Cancelled') {

        $paymentCheck = $pdo->prepare("
            SELECT payment_status

            FROM payments

            WHERE booking_id = :booking_id

            FOR UPDATE
        ");

        $paymentCheck->execute([
            ':booking_id' => $bookingId
        ]);

        $payments = $paymentCheck->fetchAll();

        foreach ($payments as $payment) {

            if ($payment['payment_status'] === 'Pending') {

                $pdo->rollBack();

                $_SESSION['booking_error'] =
                    'This booking has a payment awaiting verification. Check the payment status before cancelling it.';

                header('Location: owner_bookings.php');
                exit;
            }

            if ($payment['payment_status'] === 'Successful') {

                $pdo->rollBack();

                $_SESSION['booking_error'] =
                    'This booking has already been paid for. Contact the administrator to handle cancellation and any required refund.';

                header('Location: owner_bookings.php');
                exit;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Booking Status
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE bookings

        SET booking_status = :new_status

        WHERE booking_id = :booking_id
          AND booking_status = :current_status
    ");

    $update->execute([

        ':new_status' => $newStatus,

        ':booking_id' => $bookingId,

        ':current_status' => $currentStatus

    ]);

    if ($update->rowCount() !== 1) {

        throw new RuntimeException(
            'Booking status could not be updated.'
        );
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $_SESSION['booking_success'] =
        'Booking successfully updated to ' . $newStatus . '.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Booking status update error: ' . $e->getMessage()
    );

    $_SESSION['booking_error'] =
        'Unable to update booking status. Please try again.';
}

/*
|--------------------------------------------------------------------------
| Return to Owner Bookings
|--------------------------------------------------------------------------
*/

header('Location: owner_bookings.php');
exit;