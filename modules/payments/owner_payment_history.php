<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/role_check.php';
requireRole(['Owner']);

require_once __DIR__ . '/../../config/database.php';

$ownerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Escape Output
|--------------------------------------------------------------------------
*/

function escapeOwnerPaymentOutput($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Retrieve Owner Payment Records
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

            b.start_date,
            b.end_date,
            b.booking_status,

            r.room_number,
            r.room_type,

            h.hostel_name,

            u.full_name AS student_name,
            u.email AS student_email,
            u.phone_number AS student_phone

        FROM payments p

        INNER JOIN bookings b
            ON p.booking_id = b.booking_id

        INNER JOIN rooms r
            ON b.room_id = r.room_id

        INNER JOIN hostels h
            ON r.hostel_id = h.hostel_id

        INNER JOIN users u
            ON b.student_id = u.user_id

        WHERE h.owner_id = :owner_id

        ORDER BY p.payment_id DESC
    ");

    $stmt->execute([
        ':owner_id' => $ownerId
    ]);

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Owner payment history error: ' . $e->getMessage()
    );

    exit('Unable to retrieve payment records. Please try again later.');
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
    } elseif ($payment['payment_status'] === 'Pending') {
        $totalPending++;
    } elseif ($payment['payment_status'] === 'Failed') {
        $totalFailed++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Owner Payments | HostelConnect</title>

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
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
        }

        .back-button {
            background: #263b80;
            color: white;
            padding: 11px 18px;
            text-decoration: none;
            border-radius: 7px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            color: #64748b;
            font-size: 14px;
        }

        .summary-card strong {
            font-size: 25px;
        }

        .successful {
            color: #15803d;
        }

        .pending {
            color: #d97706;
        }

        .failed {
            color: #dc2626;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
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

<main class="container">

    <header class="header">

        <div>

            <h1>Payment Management</h1>

            <p>
                Monitor payments associated with bookings in your hostels.
            </p>

        </div>

        <a href="../../index.php" class="back-button">
            ← Dashboard
        </a>

    </header>


    <!-- PAYMENT SUMMARY -->

    <section class="summary">

        <div class="summary-card">

            <h3>Total Successful Payments</h3>

            <strong class="successful">
                GHS <?= number_format($totalSuccessful, 2) ?>
            </strong>

        </div>

        <div class="summary-card">

            <h3>Pending Transactions</h3>

            <strong class="pending">
                <?= $totalPending ?>
            </strong>

        </div>

        <div class="summary-card">

            <h3>Failed Transactions</h3>

            <strong class="failed">
                <?= $totalFailed ?>
            </strong>

        </div>

    </section>


    <!-- PAYMENT RECORDS -->

    <section class="table-container">

        <h2>Payment Records</h2>

        <?php if (empty($payments)): ?>

            <div class="empty-state">

                <h3>No Payment Records Found</h3>

                <p>
                    Payments for your hostel bookings will appear here.
                </p>

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>Payment ID</th>
                        <th>Student</th>
                        <th>Hostel</th>
                        <th>Room</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($payments as $payment): ?>

                        <?php

                        $status = $payment['payment_status'];

                        $badgeClass = match ($status) {
                            'Successful' => 'badge-successful',
                            'Pending' => 'badge-pending',
                            'Failed' => 'badge-failed',
                            default => 'badge-pending'
                        };

                        ?>

                        <tr>

                            <td>
                                <?= (int) $payment['payment_id'] ?>
                            </td>

                            <td>
                                <?= escapeOwnerPaymentOutput($payment['student_name']) ?>
                                <br>
                                <small>
                                    <?= escapeOwnerPaymentOutput($payment['student_email']) ?>
                                </small>
                            </td>

                            <td>
                                <?= escapeOwnerPaymentOutput($payment['hostel_name']) ?>
                            </td>

                            <td>
                                <?= escapeOwnerPaymentOutput($payment['room_number']) ?>
                            </td>

                            <td>
                                GHS <?= number_format((float) $payment['amount'], 2) ?>
                            </td>

                            <td>
                                <?= escapeOwnerPaymentOutput($payment['payment_reference']) ?>
                            </td>

                            <td>
                                <?= $payment['payment_date']
                                    ? escapeOwnerPaymentOutput($payment['payment_date'])
                                    : 'Not completed' ?>
                            </td>

                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= escapeOwnerPaymentOutput($status) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </section>

</main>

</body>

</html>