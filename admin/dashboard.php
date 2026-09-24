<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$admin = currentAdmin();

/*
|--------------------------------------------------------------------------
| Platform Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM cafes
");
$totalCafes = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM cafe_users
");
$totalCafeUsers = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM analytics_events
");
$totalEvents = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM menu_items
");
$totalMenuItems = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM offers
");
$totalOffers = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM qr_codes
");
$totalQrCodes = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM feedback
");
$totalFeedback = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM support_tickets
    WHERE status IN ('open', 'in_progress')
");
$openTickets = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM subscriptions
    WHERE status = 'active'
");
$activeSubscriptions = (int) $stmt->fetch()['total'];


$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE status = 'paid'
");
$totalRevenue = (float) $stmt->fetch()['total'];


/*
|--------------------------------------------------------------------------
| Recent Audit Logs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        action,
        entity_type,
        entity_id,
        created_at
    FROM audit_logs
    ORDER BY id DESC
    LIMIT 8
");

$recentLogs = $stmt->fetchAll();

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
        Admin Dashboard - TableBuzz
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            background: #111827;
            color: #ffffff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            opacity: 0.85;
        }

        .section {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .section h2 {
            margin-top: 0;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 28px;
            font-weight: bold;
        }

        .links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .links a {
            display: block;
            padding: 14px 16px;
            background: #f3f4f6;
            color: #111827;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .links a:hover {
            background: #e5e7eb;
        }

        .feedback-link {
            background: #fff7ed !important;
            color: #9a3412 !important;
        }

        .feedback-link:hover {
            background: #ffedd5 !important;
        }

        .danger {
            background: #fee2e2 !important;
            color: #991b1b !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
        }

        .logout {
            display: inline-block;
            margin-top: 10px;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .empty {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            color: #6b7280;
        }

        @media (max-width: 600px) {

            .container {
                padding: 15px;
            }

            th,
            td {
                font-size: 13px;
                padding: 8px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- Header -->

    <div class="header">

        <h1>
            TableBuzz Super Admin
        </h1>

        <p>
            Welcome,
            <strong>
                <?= e($admin['name']) ?>
            </strong>
        </p>

        <a
            class="logout"
            href="<?= e(APP_URL) ?>/admin/logout.php"
        >
            Logout
        </a>

    </div>


    <!-- Platform Overview -->

    <div class="section">

        <h2>
            📊 Platform Overview
        </h2>

        <div class="stats">

            <div class="card">

                <div class="card-title">
                    Total Cafés
                </div>

                <div class="card-value">
                    <?= $totalCafes ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Café Users
                </div>

                <div class="card-value">
                    <?= $totalCafeUsers ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Menu Items
                </div>

                <div class="card-value">
                    <?= $totalMenuItems ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Offers
                </div>

                <div class="card-value">
                    <?= $totalOffers ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    QR Codes
                </div>

                <div class="card-value">
                    <?= $totalQrCodes ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Analytics Events
                </div>

                <div class="card-value">
                    <?= $totalEvents ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Feedback
                </div>

                <div class="card-value">
                    <?= $totalFeedback ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Active Subscriptions
                </div>

                <div class="card-value">
                    <?= $activeSubscriptions ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Open Support Tickets
                </div>

                <div class="card-value">
                    <?= $openTickets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Paid Revenue
                </div>

                <div class="card-value">
                    ₹<?= number_format($totalRevenue, 2) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- Management -->

    <div class="section">

        <h2>
            ⚙️ Management
        </h2>

        <div class="links">

            <a href="<?= e(APP_URL) ?>/admin/cafes.php">
                🏪 Manage Cafés
            </a>


            <a href="<?= e(APP_URL) ?>/admin/owners.php">
                👤 Café Owners
            </a>


            <a href="<?= e(APP_URL) ?>/admin/plans.php">
                💳 Subscription Plans
            </a>


            <a href="<?= e(APP_URL) ?>/admin/subscriptions.php">
                📋 Subscriptions
            </a>


            <a href="<?= e(APP_URL) ?>/admin/payments.php">
                💰 Payments
            </a>


            <!-- NEW: Feedback Management -->

            <a
                class="feedback-link"
                href="<?= e(APP_URL) ?>/admin/feedback.php"
            >
                💬 Customer Feedback
            </a>


            <a href="<?= e(APP_URL) ?>/admin/support-tickets.php">
                🎫 Support Tickets
            </a>


            <a href="<?= e(APP_URL) ?>/admin/audit-logs.php">
                🧾 Audit Logs
            </a>

        </div>

    </div>


    <!-- Recent Audit Activity -->

    <div class="section">

        <h2>
            🧾 Recent Admin Activity
        </h2>

        <?php if (!$recentLogs): ?>

            <div class="empty">
                No audit activity found.
            </div>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Action
                            </th>

                            <th>
                                Entity
                            </th>

                            <th>
                                ID
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($recentLogs as $log): ?>

                        <tr>

                            <td>
                                <?= e($log['action']) ?>
                            </td>

                            <td>
                                <?= e($log['entity_type']) ?>
                            </td>

                            <td>
                                <?= e($log['entity_id']) ?>
                            </td>

                            <td>
                                <?= e($log['created_at']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- Quick Links -->

    <div class="section">

        <h2>
            🔗 Quick Access
        </h2>

        <div class="links">

            <a href="<?= e(APP_URL) ?>/admin/cafes.php">
                🏪 View All Cafés
            </a>


            <a href="<?= e(APP_URL) ?>/admin/owners.php">
                👤 Manage Owners
            </a>


            <a
                class="feedback-link"
                href="<?= e(APP_URL) ?>/admin/feedback.php"
            >
                💬 Customer Feedback
            </a>


            <a href="<?= e(APP_URL) ?>/admin/audit-logs.php">
                🧾 View Full Audit Logs
            </a>


            <a
                class="danger"
                href="<?= e(APP_URL) ?>/admin/logout.php"
            >
                🚪 Logout
            </a>

        </div>

    </div>

</div>

</body>

</html>