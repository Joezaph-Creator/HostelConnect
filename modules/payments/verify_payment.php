<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function escapeOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectToPaymentHistory(string $message, string $type = 'error'): never
{
    $_SESSION['payment_' . $type] = $message;

    header(
        'Location: /HostelConnect/modules/payments/payment_history.php'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Identify Request Type
|--------------------------------------------------------------------------
| GET  = Paystack callback
| POST = Student manually checking payment status
|--------------------------------------------------------------------------
*/

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

$isManualCheck = $requestMethod === 'POST';

/*
|--------------------------------------------------------------------------
| Validate Manual Status Check
|--------------------------------------------------------------------------
*/

if ($isManualCheck) {

    require_once __DIR__ . '/../../config/role_check.php';
    requireRole(['Student']);

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    $userId = (int) $_SESSION['user_id'];

} else {

    $userId = isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

/*
|--------------------------------------------------------------------------
| Validate Payment Reference
|--------------------------------------------------------------------------
*/

$reference = $isManualCheck
    ? ($_POST['reference'] ?? '')
    : ($_GET['reference'] ?? '');

if (
    !is_string($reference) ||
    !preg_match('/^HC-\d+-[a-f0-9]{24}$/', $reference)
) {
    http_response_code(400);
    exit('Invalid payment reference.');
}

/*
|--------------------------------------------------------------------------
| Load Paystack Configuration
|--------------------------------------------------------------------------
*/

$paystackConfig = require __DIR__ . '/../../config/paystack.php';

$secretKey = $paystackConfig['secret_key'] ?? '';
$baseUrl = $paystackConfig['base_url'] ?? '';
$currency = $paystackConfig['currency'] ?? '';

if (
    !is_string($secretKey) ||
    !str_starts_with($secretKey, 'sk_test_') ||
    $baseUrl !== 'https://api.paystack.co' ||
    $currency !== 'GHS'
) {
    error_log('Invalid Paystack verification configuration.');

    http_response_code(500);
    exit('Payment verification is unavailable.');
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('Payment verification is unavailable.');
}

/*
|--------------------------------------------------------------------------
| Find Local Payment
|--------------------------------------------------------------------------
*/

try {

    $paymentQuery = $pdo->prepare("
        SELECT
            p.payment_id,
            p.booking_id,
            p.amount,
            p.payment_reference,
            p.payment_status,
            b.student_id,
            b.booking_status

        FROM payments p

        INNER JOIN bookings b
            ON p.booking_id = b.booking_id

        WHERE p.payment_reference = :reference

        LIMIT 1
    ");

    $paymentQuery->execute([
        ':reference' => $reference
    ]);

    $payment = $paymentQuery->fetch();

    if (!$payment) {
        http_response_code(404);
        exit('Payment record not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Manual Check Ownership
    |--------------------------------------------------------------------------
    */

    if (
        $isManualCheck &&
        (int) $payment['student_id'] !== $userId
    ) {
        http_response_code(403);
        exit('You are not authorized to check this payment.');
    }

    /*
    |--------------------------------------------------------------------------
    | Verify With Paystack
    |--------------------------------------------------------------------------
    */

    $ch = curl_init(
        $baseUrl . '/transaction/verify/' . rawurlencode($reference)
    );

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPGET => true,

        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ],

        CURLOPT_CONNECTTIMEOUT => 15,

        CURLOPT_TIMEOUT => 45,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS

    ]);

    $response = curl_exec($ch);

    $curlError = curl_error($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($response === false) {

        error_log(
            'Paystack verification connection error: ' .
            $curlError
        );

        redirectToPaymentHistory(
            'We could not contact Paystack. Please check again later.'
        );
    }

    $result = json_decode($response, true);

    if (
        $httpCode !== 200 ||
        !is_array($result) ||
        ($result['status'] ?? false) !== true ||
        !isset($result['data']) ||
        !is_array($result['data'])
    ) {

        error_log(
            'Paystack verification response was invalid. Reference: ' .
            $reference
        );

        redirectToPaymentHistory(
            'Payment status could not be verified. Please try again later.'
        );
    }

    $transaction = $result['data'];

    /*
    |--------------------------------------------------------------------------
    | Validate Transaction Details
    |--------------------------------------------------------------------------
    */

    $verifiedReference = $transaction['reference'] ?? '';
    $verifiedCurrency = $transaction['currency'] ?? '';
    $verifiedAmount = $transaction['amount'] ?? null;
    $transactionStatus = $transaction['status'] ?? '';

    $expectedAmount = (string) $payment['amount'];

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $expectedAmount)) {
        throw new RuntimeException('Invalid local payment amount.');
    }

    $amountParts = explode('.', $expectedAmount);

    $wholePart = $amountParts[0];

    $decimalPart = str_pad(
        $amountParts[1] ?? '0',
        2,
        '0'
    );

    $expectedPesewas = (int) (
        $wholePart . $decimalPart
    );

    if (
        !is_int($verifiedAmount) &&
        !(is_string($verifiedAmount) && ctype_digit($verifiedAmount))
    ) {
        throw new RuntimeException('Invalid Paystack transaction amount.');
    }

    if (
        !hash_equals($reference, (string) $verifiedReference) ||
        $verifiedCurrency !== 'GHS' ||
        (int) $verifiedAmount !== $expectedPesewas
    ) {
        throw new RuntimeException(
            'Paystack transaction details do not match local payment.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Local Payment
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
     * Lock the booking first, then the payment.
     */

    $bookingLock = $pdo->prepare("
        SELECT booking_id, booking_status

        FROM bookings

        WHERE booking_id = :booking_id

        LIMIT 1
        FOR UPDATE
    ");

    $bookingLock->execute([
        ':booking_id' => $payment['booking_id']
    ]);

    $booking = $bookingLock->fetch();

    if (!$booking) {
        throw new RuntimeException('Associated booking not found.');
    }

    $paymentLock = $pdo->prepare("
        SELECT payment_id, payment_status

        FROM payments

        WHERE payment_id = :payment_id

        LIMIT 1
        FOR UPDATE
    ");

    $paymentLock->execute([
        ':payment_id' => $payment['payment_id']
    ]);

    $lockedPayment = $paymentLock->fetch();

    if (!$lockedPayment) {
        throw new RuntimeException('Payment record not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Successful Transaction
    |--------------------------------------------------------------------------
    */

    if ($transactionStatus === 'success') {

        $updatePayment = $pdo->prepare("
            UPDATE payments

            SET payment_status = 'Successful',
                payment_date = NOW()

            WHERE payment_id = :payment_id
        ");

        $updatePayment->execute([
            ':payment_id' => $payment['payment_id']
        ]);

        /*
         * Confirm only a booking that is still Pending.
         */

        if ($booking['booking_status'] === 'Pending') {

            $updateBooking = $pdo->prepare("
                UPDATE bookings

                SET booking_status = 'Confirmed'

                WHERE booking_id = :booking_id
                  AND booking_status = 'Pending'
            ");

            $updateBooking->execute([
                ':booking_id' => $booking['booking_id']
            ]);

        } elseif ($booking['booking_status'] !== 'Confirmed') {

            error_log(
                'Payment succeeded for a booking that is no longer ' .
                'Pending or Confirmed. Booking ID: ' .
                $booking['booking_id'] .
                '. Reference: ' . $reference
            );
        }

        $pdo->commit();

        redirectToPaymentHistory(
            'Payment successfully verified. Reference: ' . $reference,
            'success'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Failed, Abandoned or Reversed Transaction
    |--------------------------------------------------------------------------
    */

    if (in_array(
        $transactionStatus,
        ['failed', 'abandoned', 'reversed'],
        true
    )) {

        $updatePayment = $pdo->prepare("
            UPDATE payments

            SET payment_status = 'Failed'

            WHERE payment_id = :payment_id
              AND payment_status = 'Pending'
        ");

        $updatePayment->execute([
            ':payment_id' => $payment['payment_id']
        ]);

        $pdo->commit();

        redirectToPaymentHistory(
            'The payment was not successful. You may try again if your booking is still pending.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Other / Pending Paystack Status
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    redirectToPaymentHistory(
        'Your payment is still being processed. Please check again later.'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Payment verification error: ' . $e->getMessage()
    );

    redirectToPaymentHistory(
        'An error occurred while verifying your payment. Please try again later.'
    );
}