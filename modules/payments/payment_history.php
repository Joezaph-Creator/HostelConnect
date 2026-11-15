<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Student']);

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Generate CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Escape Output
|--------------------------------------------------------------------------
*/

function escapeOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Retrieve Payment Messages
|--------------------------------------------------------------------------
*/

$successMessage = $_SESSION['payment_success'] ?? '';
$errorMessage = $_SESSION['payment_error'] ?? '';

unset(
    $_SESSION['payment_success'],
    $_SESSION['payment_error']
);

/*
|--------------------------------------------------------------------------
| Retrieve Student Payment History
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.booking_id,
            p.amount,
            p.payment_reference,
            p.payment_status,
            p.payment_date,

            b.booking_status,

            r.room_number,
            r.room_type,

            h.hostel_name

        FROM payments p

        INNER JOIN bookings b
            ON p.booking_id = b.booking_id

        INNER JOIN rooms r
            ON b.room_id = r.room_id

        INNER JOIN hostels h
            ON r.hostel_id = h.hostel_id

        WHERE b.student_id = :student_id

        ORDER BY p.payment_id DESC
    ");

    $stmt->execute([
        ':student_id' => $userId
    ]);

    $payments = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log(
        'Payment history error: ' . $e->getMessage()
    );

    exit('Unable to retrieve payment history.');
}

/*
|--------------------------------------------------------------------------
| Calculate Payment Summary
|--------------------------------------------------------------------------
*/

$totalSuccessful = 0;
$totalPending = 0;
$totalFailed = 0;

foreach ($payments as $payment) {

    if ($payment['payment_status'] === 'Successful') {
        $totalSuccessful += (float) $payment['amount'];
    }

    if ($payment['payment_status'] === 'Pending') {
        $totalPending++;
    }

    if ($payment['payment_status'] === 'Failed') {
        $totalFailed++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment History | HostelConnect</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #263238;
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            color: #64748b;
        }

        .back-button {
            display: inline-block;
            padding: 11px 18px;
            background: #263b80;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 14px;
        }

        .summary-card strong {
            font-size: 24px;
        }

        .success-text {
            color: #15803d;
        }

        .pending-text {
            color: #d97706;
        }

        .failed-text {
            color: #dc2626;
        }

        .message {
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            overflow-x: auto;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-successful {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-button {
            display: inline-block;
            padding: 9px 12px;
            border: none;
            border-radius: 6px;
            background: #263b80;
            color: white;
            cursor: pointer;
            font-size: 13px;
        }

        .action-button:hover {
            background: #1e2f68;
        }

        .retry-button {
            display: inline-block;
            padding: 9px 12px;
            border-radius: 6px;
            background: #15803d;
            color: white;
            text-decoration: none;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 35px;
            color: #64748b;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>Payment History</h1>

            <p>View and manage your HostelConnect transactions.</p>

        </div>

        <a href="../../index.php" class="back-button">
            Back to Dashboard
        </a>

    </div>

    <?php if ($successMessage): ?>

        <div class="message success-message">
            <?= escapeOutput((string) $successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage): ?>

        <div class="message error-message">
            <?= escapeOutput((string) $errorMessage) ?>
        </div>

    <?php endif; ?>

    <div class="summary">

        <div class="summary-card">

            <h3>Total Successful Payments</h3>

            <strong class="success-text">
                GHS <?= number_format($totalSuccessful, 2) ?>
            </strong>

        </div>

        <div class="summary-card">

            <h3>Pending Transactions</h3>

            <strong class="pending-text">
                <?= $totalPending ?>
            </strong>

        </div>

        <div class="summary-card">

            <h3>Failed Transactions</h3>

            <strong class="failed-text">
                <?= $totalFailed ?>
            </strong>

        </div>

    </div>

    <div class="table-container">

        <h2>Transaction Records</h2>

        <?php if (empty($payments)): ?>

            <div class="empty-state">

                <h3>No Payment Records</h3>

                <p>
                    You have not made any payment transactions yet.
                </p>

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>Payment ID</th>

                        <th>Hostel</th>

                        <th>Room</th>

                        <th>Amount</th>

                        <th>Reference</th>

                        <th>Status</th>

                        <th>Date</th>

                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($payments as $payment): ?>

                        <tr>

                            <td>
                                <?= (int) $payment['payment_id'] ?>
                            </td>

                            <td>
                                <?= escapeOutput($payment['hostel_name']) ?>
                            </td>

                            <td>
                                <?= escapeOutput($payment['room_number']) ?>
                            </td>

                            <td>
                                GHS <?= number_format((float) $payment['amount'], 2) ?>
                            </td>

                            <td>
                                <?= escapeOutput($payment['payment_reference']) ?>
                            </td>

                            <td>

                                <?php

                                $status = $payment['payment_status'];

                                $badgeClass = match ($status) {
                                    'Successful' => 'badge-successful',
                                    'Pending' => 'badge-pending',
                                    'Failed' => 'badge-failed',
                                    default => 'badge-pending'
                                };

                                ?>

                                <span class="badge <?= $badgeClass ?>">
                                    <?= escapeOutput($status) ?>
                                </span>

                            </td>

                            <td>

                                <?= $payment['payment_date']
                                    ? escapeOutput($payment['payment_date'])
                                    : 'Not completed' ?>

                            </td>

                            <td>

                                <?php if ($status === 'Pending'): ?>

                                    <form
                                        method="POST"
                                        action="verify_payment.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="reference"
                                            value="<?= escapeOutput($payment['payment_reference']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapeOutput($_SESSION['csrf_token']) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button"
                                        >
                                            Check Status
                                        </button>

                                    </form>

                                <?php elseif (
                                    $status === 'Failed' &&
                                    $payment['booking_status'] === 'Pending'
                                ): ?>

                                    <a
                                        class="retry-button"
                                        href="pay_now.php?booking_id=<?= (int) $payment['booking_id'] ?>"
                                    >
                                        Retry Payment
                                    </a>

                                <?php else: ?>

                                    <span>—</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>

</html>