<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Display Booking Result
|--------------------------------------------------------------------------
*/

function showResult(
    string $title,
    string $message,
    bool $success
): void {

    $color = $success ? '#15803d' : '#dc2626';

    $title = htmlspecialchars(
        $title,
        ENT_QUOTES,
        'UTF-8'
    );

    $message = htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    );

    $link = $success
        ? 'my_bookings.php'
        : '../hostels/browse_hostels.php';

    $linkText = $success
        ? 'View My Bookings'
        : 'Explore Hostels';

    echo '<!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>' . $title . ' | HostelConnect</title>

        <style>

            body {
                font-family: Arial, sans-serif;
                background: #f1f5f9;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }

            .box {
                background: white;
                padding: 35px;
                border-radius: 12px;
                max-width: 450px;
                width: 90%;
                text-align: center;
                box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            }

            h2 {
                color: ' . $color . ';
            }

            p {
                color: #475569;
                line-height: 1.7;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 20px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 7px;
            }

            a:hover {
                background: #1d4ed8;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h2>' . $title . '</h2>

            <p>' . $message . '</p>

            <a href="' . $link . '">' . $linkText . '</a>

        </div>

    </body>

    </html>';

    exit;
}

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

    exit(
        'Invalid security token. Please return to the booking form and try again.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate Student Session
|--------------------------------------------------------------------------
*/

$studentId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {

    http_response_code(403);

    exit('Invalid student session. Please sign in again.');
}

/*
|--------------------------------------------------------------------------
| Validate Room ID
|--------------------------------------------------------------------------
*/

$roomId = filter_var(
    $_POST['room_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$roomId || $roomId < 1) {

    showResult(
        'Invalid Room',
        'Please select a valid room.',
        false
    );
}

/*
|--------------------------------------------------------------------------
| Validate Submitted Dates
|--------------------------------------------------------------------------
*/

$startDate = $_POST['start_date'] ?? '';

$endDate = $_POST['end_date'] ?? '';

if (
    !is_string($startDate) ||
    !is_string($endDate) ||
    $startDate === '' ||
    $endDate === ''
) {

    showResult(
        'Invalid Dates',
        'Please provide both check-in and check-out dates.',
        false
    );
}

/*
|--------------------------------------------------------------------------
| Validate Date Format
|--------------------------------------------------------------------------
*/

function validateDate(string $date): ?DateTimeImmutable
{
    $parsedDate = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        $parsedDate === false ||
        (
            $errors !== false &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        ) ||
        $parsedDate->format('Y-m-d') !== $date
    ) {

        return null;
    }

    return $parsedDate;
}

$start = validateDate($startDate);

$end = validateDate($endDate);

if (!$start || !$end) {

    showResult(
        'Invalid Dates',
        'Please provide valid check-in and check-out dates.',
        false
    );
}

/*
|--------------------------------------------------------------------------
| Check Booking Dates
|--------------------------------------------------------------------------
*/

$today = new DateTimeImmutable('today');

if ($start < $today) {

    showResult(
        'Invalid Check-in Date',
        'Your check-in date cannot be in the past.',
        false
    );
}

if ($end <= $start) {

    showResult(
        'Invalid Check-out Date',
        'Your check-out date must be after your check-in date.',
        false
    );
}

/*
|--------------------------------------------------------------------------
| Process Booking
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock Selected Room
    |--------------------------------------------------------------------------
    | This prevents simultaneous booking transactions from creating
    | overlapping reservations for the same room.
    |--------------------------------------------------------------------------
    */

    $roomQuery = $pdo->prepare("
        SELECT room_id

        FROM rooms

        WHERE room_id = :room_id

        FOR UPDATE
    ");

    $roomQuery->execute([
        ':room_id' => $roomId
    ]);

    $room = $roomQuery->fetch();

    if (!$room) {

        $pdo->rollBack();

        showResult(
            'Room Not Found',
            'The selected room does not exist or is unavailable.',
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Reservations
    |--------------------------------------------------------------------------
    | Pending and Confirmed bookings temporarily block overlapping dates.
    |--------------------------------------------------------------------------
    */

    $availabilityQuery = $pdo->prepare("
        SELECT booking_id

        FROM bookings

        WHERE room_id = :room_id

          AND booking_status IN ('Pending', 'Confirmed')

          AND start_date < :end_date

          AND end_date > :start_date

        LIMIT 1
    ");

    $availabilityQuery->execute([

        ':room_id' => $roomId,

        ':start_date' => $startDate,

        ':end_date' => $endDate

    ]);

    $existingBooking = $availabilityQuery->fetch();

    if ($existingBooking) {

        $pdo->rollBack();

        showResult(
            'Room Not Available',
            'Sorry, this room is already reserved for some or all of your selected dates. Please choose different dates or another room.',
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Pending Booking
    |--------------------------------------------------------------------------
    */

    $insertBooking = $pdo->prepare("
        INSERT INTO bookings (

            student_id,
            room_id,
            start_date,
            end_date,
            booking_status

        )

        VALUES (

            :student_id,
            :room_id,
            :start_date,
            :end_date,
            'Pending'

        )
    ");

    $insertBooking->execute([

        ':student_id' => $studentId,

        ':room_id' => $roomId,

        ':start_date' => $startDate,

        ':end_date' => $endDate

    ]);

    $bookingId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Display Successful Booking
    |--------------------------------------------------------------------------
    */

    showResult(

        'Booking Request Submitted!',

        'Your booking request was successfully submitted. Your booking reference is #' .
        $bookingId .
        '. Your booking is currently Pending. Proceed to My Bookings to continue with payment.',

        true

    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Booking processing error: ' . $e->getMessage()
    );

    showResult(
        'Booking Error',
        'We could not process your booking at this time. Please try again later.',
        false
    );
}