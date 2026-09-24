<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        s.id,
        s.cafe_id,
        s.plan_id,
        s.status,
        s.billing_cycle,
        s.trial_start,
        s.trial_end,
        s.start_date,
        s.end_date,
        s.created_at,

        c.name AS cafe_name,
        c.slug AS cafe_slug,
        c.status AS cafe_status,

        p.name AS plan_name,
        p.code AS plan_code,
        p.monthly_price,
        p.yearly_price

    FROM subscriptions s

    INNER JOIN cafes c
        ON c.id = s.cafe_id

    LEFT JOIN plans p
        ON p.id = s.plan_id

    ORDER BY s.id DESC
");

$subscriptions = $stmt->fetchAll();

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
        Subscriptions - TableBuzz Admin
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .container {
            max-width: 1250px;
            margin: auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 6px;
        }

        .subtitle {
            margin: 0;
            color: #6b7280;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-toggle {
            background: #6b7280;
            color: white;
        }

        .table-card {
            background: white;
            border-radius: 14px;
            overflow-x: auto;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .cafe-name {
            font-weight: 600;
        }

        .slug {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .plan-name {
            font-weight: 600;
        }

        .plan-code {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .trial {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .expired {
            background: #fef3c7;
            color: #92400e;
        }

        .action-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: #6b7280;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>
                Subscriptions
            </h1>

            <p class="subtitle">
                Manage café subscription accounts
            </p>

        </div>

        <div class="actions">

            <a
                href="<?= e(APP_URL) ?>/admin/dashboard.php"
                class="btn btn-dark"
            >
                ← Dashboard
            </a>

        </div>

    </div>


    <div class="table-card">

        <?php if (empty($subscriptions)): ?>

            <div class="empty">
                No subscriptions found.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Café
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Billing
                        </th>

                        <th>
                            Trial
                        </th>

                        <th>
                            Subscription Period
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $subscriptions as $subscription
                ): ?>

                    <tr>

                        <td>
                            #<?= (int) $subscription['id'] ?>
                        </td>


                        <td>

                            <div class="cafe-name">

                                <?= e(
                                    $subscription['cafe_name']
                                ) ?>

                            </div>

                            <div class="slug">

                                /<?= e(
                                    $subscription['cafe_slug']
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $subscription['plan_name']
                                )
                            ): ?>

                                <div class="plan-name">

                                    <?= e(
                                        $subscription['plan_name']
                                    ) ?>

                                </div>

                                <div class="plan-code">

                                    <?= e(
                                        $subscription['plan_code']
                                    ) ?>

                                </div>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                ucfirst(
                                    $subscription['billing_cycle']
                                    ?: '-'
                                )
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                $subscription['trial_start']
                                ||
                                $subscription['trial_end']
                            ): ?>

                                <?= e(
                                    $subscription['trial_start']
                                    ?: '-'
                                ) ?>

                                <br>

                                →

                                <br>

                                <?= e(
                                    $subscription['trial_end']
                                    ?: '-'
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                $subscription['start_date']
                                ?: '-'
                            ) ?>

                            <br>

                            →

                            <br>

                            <?= e(
                                $subscription['end_date']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?php

                            $status =
                                $subscription['status'];

                            $statusClass = 'inactive';

                            if ($status === 'active') {
                                $statusClass = 'active';
                            } elseif ($status === 'trial') {
                                $statusClass = 'trial';
                            } elseif ($status === 'expired') {
                                $statusClass = 'expired';
                            }

                            ?>

                            <span
                                class="status <?= e(
                                    $statusClass
                                ) ?>"
                            >

                                <?= e(
                                    strtoupper(
                                        $status
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <div class="action-links">

                                <a
                                    href="<?= e(APP_URL) ?>/admin/edit-subscription.php?id=<?= (int) $subscription['id'] ?>"
                                    class="btn btn-edit"
                                >
                                    Edit
                                </a>

                                <a
                                    href="<?= e(APP_URL) ?>/admin/toggle-subscription.php?id=<?= (int) $subscription['id'] ?>"
                                    class="btn btn-toggle"
                                >
                                    Toggle
                                </a>

                            </div>

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