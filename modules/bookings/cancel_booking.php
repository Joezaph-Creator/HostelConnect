<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

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
| Validate CSRF Token
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

$studentId = (int) $_SESSION['user_id'];

if (!$bookingId || $bookingId < 1) {

    $_SESSION['booking_error'] = 'Invalid booking selected.';

    header('Location: my_bookings.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Process Booking Cancellation
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock Booking Record
    |--------------------------------------------------------------------------
    */

    $bookingQuery = $pdo->prepare("
        SELECT
            booking_id,
            booking_status

        FROM bookings

        WHERE booking_id = :booking_id

        AND student_id = :student_id

        FOR UPDATE
    ");

    $bookingQuery->execute([

        ':booking_id' => $bookingId,

        ':student_id' => $studentId

    ]);

    $booking = $bookingQuery->fetch();

    if (!$booking) {

        $pdo->rollBack();

        $_SESSION['booking_error'] =
            'Booking not found or you do not have permission to cancel it.';

        header('Location: my_bookings.php');

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Only Pending Bookings Can Be Cancelled
    |--------------------------------------------------------------------------
    */

    if ($booking['booking_status'] !== 'Pending') {

        $pdo->rollBack();

        $_SESSION['booking_error'] =
            'Only Pending bookings can be cancelled.';

        header('Location: my_bookings.php');

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Payment Attempts
    |--------------------------------------------------------------------------
    | A Pending payment may still be processing at Paystack.
    | A Successful payment must not be cancelled without a refund process.
    |--------------------------------------------------------------------------
    */

    $paymentQuery = $pdo->prepare("
        SELECT payment_status

        FROM payments

        WHERE booking_id = :booking_id

        FOR UPDATE
    ");

    $paymentQuery->execute([
        ':booking_id' => $bookingId
    ]);

    $paymentRecords = $paymentQuery->fetchAll();

    foreach ($paymentRecords as $payment) {

        if ($payment['payment_status'] === 'Pending') {

            $pdo->rollBack();

            $_SESSION['booking_error'] =
                'This booking has a payment awaiting verification. Check your payment status before attempting cancellation.';

            header('Location: my_bookings.php');

            exit;
        }

        if ($payment['payment_status'] === 'Successful') {

            $pdo->rollBack();

            $_SESSION['booking_error'] =
                'This booking has already been paid for. Cancellation requires an administrator to process the refund.';

            header('Location: my_bookings.php');

            exit;
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Booking
    |--------------------------------------------------------------------------
    */

    $cancelQuery = $pdo->prepare("
        UPDATE bookings

        SET booking_status = 'Cancelled'

        WHERE booking_id = :booking_id

        AND student_id = :student_id

        AND booking_status = 'Pending'
    ");

    $cancelQuery->execute([

        ':booking_id' => $bookingId,

        ':student_id' => $studentId

    ]);

    if ($cancelQuery->rowCount() !== 1) {

        $pdo->rollBack();

        $_SESSION['booking_error'] =
            'The booking could not be cancelled. Please refresh and try again.';

        header('Location: my_bookings.php');

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    $_SESSION['booking_success'] =
        'Your booking was successfully cancelled.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Booking cancellation error: ' . $e->getMessage()
    );

    $_SESSION['booking_error'] =
        'Unable to cancel your booking at this time. Please try again later.';
}

/*
|--------------------------------------------------------------------------
| Return to My Bookings
|--------------------------------------------------------------------------
*/

header('Location: my_bookings.php');

exit;