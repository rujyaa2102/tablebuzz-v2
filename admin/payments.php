<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        p.id,
        p.provider,
        p.payment_id,
        p.order_id,
        p.amount,
        p.currency,
        p.status,
        p.paid_at,
        p.created_at,

        c.id AS cafe_id,
        c.name AS cafe_name,
        c.slug AS cafe_slug,

        s.id AS subscription_id,
        s.status AS subscription_status,

        pl.name AS plan_name,
        pl.code AS plan_code

    FROM payments p

    INNER JOIN cafes c
        ON c.id = p.cafe_id

    LEFT JOIN subscriptions s
        ON s.id = p.subscription_id

    LEFT JOIN plans pl
        ON pl.id = s.plan_id

    ORDER BY p.id DESC
");

$payments = $stmt->fetchAll();

function paymentStatusClass(string $status): string
{
    return match (strtolower($status)) {
        'paid', 'success', 'successful' => 'success',
        'pending' => 'warning',
        'failed', 'cancelled' => 'danger',
        'refunded' => 'info',
        default => 'secondary',
    };
}

$pageTitle = 'Payments Management';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($pageTitle) ?> - TableBuzz Admin
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        .subtitle {
            color: #6b7280;
            margin-top: 6px;
        }

        .back {
            text-decoration: none;
            background: #111827;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,.06);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
            text-transform: uppercase;
            color: #6b7280;
        }

        td {
            font-size: 14px;
        }

        .cafe {
            font-weight: 600;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
        }

        .amount {
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .warning {
            background: #fef3c7;
            color: #92400e;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .info {
            background: #dbeafe;
            color: #1e40af;
        }

        .secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #6b7280;
        }

        .payment-id {
            font-family: monospace;
            font-size: 12px;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <div>

            <h1>💳 Payments Management</h1>

            <div class="subtitle">
                View and monitor café subscription payments
            </div>

        </div>

        <a
            href="<?= e(APP_URL) ?>/admin/dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <div class="card">

        <?php if (!$payments): ?>

            <div class="empty">
                No payments found.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Café</th>

                        <th>Plan</th>

                        <th>Provider</th>

                        <th>Payment ID</th>

                        <th>Order ID</th>

                        <th>Amount</th>

                        <th>Status</th>

                        <th>Paid At</th>

                        <th>Created</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($payments as $payment): ?>

                    <tr>

                        <td>
                            #<?= (int) $payment['id'] ?>
                        </td>

                        <td>

                            <div class="cafe">
                                <?= e($payment['cafe_name']) ?>
                            </div>

                            <div class="muted">
                                <?= e($payment['cafe_slug']) ?>
                            </div>

                        </td>

                        <td>

                            <?= e($payment['plan_name'] ?? '—') ?>

                            <?php if (!empty($payment['plan_code'])): ?>

                                <div class="muted">
                                    <?= e($payment['plan_code']) ?>
                                </div>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= e($payment['provider'] ?? '—') ?>
                        </td>

                        <td class="payment-id">
                            <?= e($payment['payment_id'] ?? '—') ?>
                        </td>

                        <td class="payment-id">
                            <?= e($payment['order_id'] ?? '—') ?>
                        </td>

                        <td class="amount">

                            <?= e($payment['currency'] ?? 'INR') ?>

                            <?= number_format(
                                (float) $payment['amount'],
                                2
                            ) ?>

                        </td>

                        <td>

                            <span
                                class="badge <?= e(
                                    paymentStatusClass(
                                        (string) $payment['status']
                                    )
                                ) ?>"
                            >
                                <?= e(
                                    ucfirst(
                                        (string) $payment['status']
                                    )
                                ) ?>
                            </span>

                        </td>

                        <td>

                            <?= $payment['paid_at']
                                ? e(date(
                                    'd M Y, h:i A',
                                    strtotime($payment['paid_at'])
                                ))
                                : '—'
                            ?>

                        </td>

                        <td>

                            <?= e(date(
                                'd M Y, h:i A',
                                strtotime($payment['created_at'])
                            )) ?>

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