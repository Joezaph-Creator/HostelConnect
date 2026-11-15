<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Basic Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$userId = (int) $_SESSION['user_id'];

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    http_response_code(403);
    exit('Invalid security token. Please try again.');
}

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    http_response_code(400);
    exit('Invalid booking reference.');
}

/*
|--------------------------------------------------------------------------
| Check cURL Availability
|--------------------------------------------------------------------------
*/

if (!function_exists('curl_init')) {
    error_log('PHP cURL extension is not enabled.');
    exit('Payment service is temporarily unavailable.');
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
    !is_string($baseUrl) ||
    $baseUrl !== 'https://api.paystack.co' ||
    $currency !== 'GHS'
) {
    error_log('Invalid Paystack test configuration.');
    exit('Payment service is not configured correctly.');
}

/*
|--------------------------------------------------------------------------
| Retrieve Booking and Prepare Payment
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
            b.booking_id,
            b.booking_status,
            r.price,
            u.email,
            u.full_name

        FROM bookings b

        INNER JOIN rooms r
            ON b.room_id = r.room_id

        INNER JOIN users u
            ON b.student_id = u.user_id

        WHERE b.booking_id = :booking_id
          AND b.student_id = :student_id

        LIMIT 1
        FOR UPDATE
    ");

    $bookingQuery->execute([
        ':booking_id' => $bookingId,
        ':student_id' => $userId
    ]);

    $booking = $bookingQuery->fetch();

    if (!$booking) {
        $pdo->rollBack();

        http_response_code(404);
        exit('Booking not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Check Booking Status
    |--------------------------------------------------------------------------
    */

    if ($booking['booking_status'] !== 'Pending') {
        $pdo->rollBack();
        exit('This booking is not available for payment.');
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Room Price
    |--------------------------------------------------------------------------
    | The room price represents one semester.
    | Convert GHS into pesewas without floating-point arithmetic.
    |--------------------------------------------------------------------------
    */

    $price = (string) $booking['price'];

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $price)) {
        $pdo->rollBack();
        exit('Invalid payment amount.');
    }

    $priceParts = explode('.', $price);

    $wholePart = $priceParts[0];

    $decimalPart = str_pad(
        $priceParts[1] ?? '0',
        2,
        '0'
    );

    $pesewasString = ltrim(
        $wholePart . $decimalPart,
        '0'
    );

    if ($pesewasString === '') {
        $pesewasString = '0';
    }

    if (
        strlen($pesewasString) > 10 ||
        (int) $pesewasString <= 0
    ) {
        $pdo->rollBack();
        exit('Invalid payment amount.');
    }

    $amountInPesewas = (int) $pesewasString;

    /*
    |--------------------------------------------------------------------------
    | Check Existing Successful Payments
    |--------------------------------------------------------------------------
    */

    $successfulPayment = $pdo->prepare("
        SELECT payment_id

        FROM payments

        WHERE booking_id = :booking_id
          AND payment_status = 'Successful'

        LIMIT 1
    ");

    $successfulPayment->execute([
        ':booking_id' => $bookingId
    ]);

    if ($successfulPayment->fetch()) {
        $pdo->rollBack();
        exit('This booking has already been paid for.');
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Pending Payments
    |--------------------------------------------------------------------------
    */

    $pendingPayment = $pdo->prepare("
        SELECT payment_id

        FROM payments

        WHERE booking_id = :booking_id
          AND payment_status = 'Pending'

        LIMIT 1
    ");

    $pendingPayment->execute([
        ':booking_id' => $bookingId
    ]);

    if ($pendingPayment->fetch()) {
        $pdo->rollBack();

        exit(
            'A payment attempt is already pending. ' .
            'Please check your payment history before trying again.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Unique Payment Reference
    |--------------------------------------------------------------------------
    */

    $reference = 'HC-' . $bookingId . '-' . bin2hex(random_bytes(12));

    /*
    |--------------------------------------------------------------------------
    | Create Pending Payment Record
    |--------------------------------------------------------------------------
    */

    $insertPayment = $pdo->prepare("
        INSERT INTO payments (
            booking_id,
            amount,
            payment_reference,
            payment_status
        )

        VALUES (
            :booking_id,
            :amount,
            :reference,
            'Pending'
        )
    ");

    $insertPayment->execute([
        ':booking_id' => $bookingId,
        ':amount' => $price,
        ':reference' => $reference
    ]);

    /*
    |--------------------------------------------------------------------------
    | Commit Before Contacting Paystack
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Payment preparation error: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to prepare your payment. Please try again later.');
}

/*
|--------------------------------------------------------------------------
| Prepare Paystack Transaction
|--------------------------------------------------------------------------
*/

$callbackUrl = 'http://localhost/HostelConnect/modules/payments/verify_payment.php';

$payload = [
    'email' => $booking['email'],
    'amount' => (string) $amountInPesewas,
    'currency' => $currency,
    'reference' => $reference,
    'callback_url' => $callbackUrl,

    'metadata' => [
        'booking_id' => (int) $bookingId,
        'student_id' => $userId,
        'project' => 'HostelConnect'
    ]
];

/*
|--------------------------------------------------------------------------
| Encode Request
|--------------------------------------------------------------------------
*/

try {

    $jsonPayload = json_encode(
        $payload,
        JSON_THROW_ON_ERROR
    );

} catch (JsonException $e) {

    error_log(
        'Payment JSON encoding error: ' . $e->getMessage()
    );

    exit('Unable to prepare payment request.');
}

/*
|--------------------------------------------------------------------------
| Initialize Paystack Transaction
|--------------------------------------------------------------------------
*/

$ch = curl_init($baseUrl . '/transaction/initialize');

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $jsonPayload,

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

/*
|--------------------------------------------------------------------------
| Handle Connection Failure
|--------------------------------------------------------------------------
| Do not mark the payment Failed automatically.
| Paystack may have received the request despite a timeout.
|--------------------------------------------------------------------------
*/

if ($response === false) {

    error_log(
        'Paystack connection error for reference ' .
        $reference . ': ' . $curlError
    );

    exit(
        'We could not confirm whether your payment request was received. ' .
        'Please do not start another payment yet. ' .
        'Check your payment history for the transaction status.'
    );
}

/*
|--------------------------------------------------------------------------
| Decode Paystack Response
|--------------------------------------------------------------------------
*/

$result = json_decode($response, true);

/*
|--------------------------------------------------------------------------
| Handle Definitive Initialization Rejection
|--------------------------------------------------------------------------
*/

if (
    is_array($result) &&
    ($result['status'] ?? null) === false &&
    $httpCode >= 400 &&
    $httpCode < 500
) {

    $updatePayment = $pdo->prepare("
        UPDATE payments

        SET payment_status = 'Failed'

        WHERE payment_reference = :reference
          AND payment_status = 'Pending'
    ");

    $updatePayment->execute([
        ':reference' => $reference
    ]);

    error_log(
        'Paystack rejected initialization. Reference: ' . $reference
    );

    exit('Paystack could not initialize your payment. Please try again.');
}

/*
|--------------------------------------------------------------------------
| Handle Uncertain Responses
|--------------------------------------------------------------------------
| Keep the payment Pending if the response is malformed,
| unsuccessful in an uncertain way, or indicates a server error.
|--------------------------------------------------------------------------
*/

if (
    $httpCode !== 200 ||
    !is_array($result) ||
    ($result['status'] ?? false) !== true ||
    !isset($result['data']['authorization_url'])
) {

    error_log(
        'Uncertain Paystack initialization response. Reference: ' .
        $reference . '. HTTP: ' . $httpCode
    );

    exit(
        'We could not confirm the payment initialization. ' .
        'Please check your payment history before trying again.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate Checkout URL
|--------------------------------------------------------------------------
*/

$authorizationUrl = $result['data']['authorization_url'];

$checkoutHost = is_string($authorizationUrl)
    ? parse_url($authorizationUrl, PHP_URL_HOST)
    : false;

if (
    !is_string($authorizationUrl) ||
    !filter_var($authorizationUrl, FILTER_VALIDATE_URL) ||
    $checkoutHost !== 'checkout.paystack.com' ||
    parse_url($authorizationUrl, PHP_URL_SCHEME) !== 'https'
) {

    error_log(
        'Invalid Paystack checkout URL. Reference: ' . $reference
    );

    exit(
        'The payment checkout could not be opened. ' .
        'Please check your payment history.'
    );
}

/*
|--------------------------------------------------------------------------
| Redirect Student to Paystack Checkout
|--------------------------------------------------------------------------
*/

header(
    'Location: ' . $authorizationUrl,
    true,
    303
);

exit;