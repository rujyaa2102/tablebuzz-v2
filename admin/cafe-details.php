<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$cafeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$cafeId || $cafeId <= 0) {
    http_response_code(400);
    exit('Invalid café ID.');
}

/*
|--------------------------------------------------------------------------
| Café Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.*,
        cs.currency,
        cs.timezone,
        cs.primary_color,
        cs.secondary_color,
        cs.whatsapp_enabled,
        cs.games_enabled,
        cs.feedback_enabled
    FROM cafes c
    LEFT JOIN cafe_settings cs
        ON cs.cafe_id = c.id
    WHERE c.id = :cafe_id
    LIMIT 1
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    http_response_code(404);
    exit('Café not found.');
}

/*
|--------------------------------------------------------------------------
| Owner
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        role,
        status,
        created_at
    FROM cafe_users
    WHERE cafe_id = :cafe_id
    ORDER BY id ASC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$owners = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM menu_categories
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$categoryCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM menu_items
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$menuCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM offers
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$offerCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM qr_codes
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$qrCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feedback
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$feedbackCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$analyticsCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM loyalty_customers
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$loyaltyCustomerCount = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM support_tickets
    WHERE cafe_id = :cafe_id
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$supportTicketCount = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Subscription
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.status,
        s.billing_cycle,
        s.trial_start,
        s.trial_end,
        s.start_date,
        s.end_date,
        p.name AS plan_name,
        p.code AS plan_code
    FROM subscriptions s
    LEFT JOIN plans p
        ON p.id = s.plan_id
    WHERE s.cafe_id = :cafe_id
    ORDER BY s.id DESC
    LIMIT 1
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$subscription = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Recent Audit Logs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        al.*,
        a.name AS admin_name,
        cu.name AS owner_name
    FROM audit_logs al
    LEFT JOIN admins a
        ON a.id = al.admin_id
    LEFT JOIN cafe_users cu
        ON cu.id = al.cafe_user_id
    WHERE al.cafe_id = :cafe_id
    ORDER BY al.id DESC
    LIMIT 10
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$auditLogs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function statusClass(string $status): string
{
    return $status === 'active'
        ? 'status-active'
        : 'status-inactive';
}

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
        <?= e($cafe['name']) ?> - TableBuzz Admin
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 30px 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 16px;
            background: #111827;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
        }

        .cafe-header {
            background: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 25px;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        .cafe-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .cafe-title h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .cafe-title p {
            margin: 4px 0;
            color: #6b7280;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 30px;
        }

        .stat-card p {
            margin: 8px 0 0;
            color: #6b7280;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        .card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .label {
            color: #6b7280;
        }

        .value {
            font-weight: 600;
            text-align: right;
        }

        .owner {
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .owner:last-child {
            margin-bottom: 0;
        }

        .owner-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .owner-email {
            color: #6b7280;
            font-size: 14px;
        }

        .small-status {
            display: inline-block;
            margin-top: 8px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background: #f3f4f6;
        }

        .audit {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }

        .audit:last-child {
            border-bottom: none;
        }

        .audit-action {
            font-weight: bold;
        }

        .audit-meta {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .empty {
            color: #6b7280;
            padding: 10px 0;
        }

        @media (max-width: 600px) {

            .cafe-title {
                flex-direction: column;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <a
            href="<?= e(APP_URL) ?>/admin/cafes.php"
            class="back-btn"
        >
            ← Back to Cafés
        </a>

    </div>


    <!-- Café Header -->

    <div class="cafe-header">

        <div class="cafe-title">

            <div>

                <h1>
                    <?= e($cafe['name']) ?>
                </h1>

                <p>
                    <?= e($cafe['address'] ?? '') ?>
                    <?php if (!empty($cafe['city'])): ?>
                        ,
                        <?= e($cafe['city']) ?>
                    <?php endif; ?>
                </p>

                <p>
                    Slug:
                    <strong>
                        <?= e($cafe['slug']) ?>
                    </strong>
                </p>

            </div>

            <div>

                <span
                    class="status
                    <?= e(statusClass($cafe['status'])) ?>"
                >
                    <?= e(strtoupper($cafe['status'])) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">
            <h3><?= $menuCount ?></h3>
            <p>Menu Items</p>
        </div>

        <div class="stat-card">
            <h3><?= $categoryCount ?></h3>
            <p>Categories</p>
        </div>

        <div class="stat-card">
            <h3><?= $offerCount ?></h3>
            <p>Offers</p>
        </div>

        <div class="stat-card">
            <h3><?= $qrCount ?></h3>
            <p>QR Codes</p>
        </div>

        <div class="stat-card">
            <h3><?= $feedbackCount ?></h3>
            <p>Feedback</p>
        </div>

        <div class="stat-card">
            <h3><?= $analyticsCount ?></h3>
            <p>Analytics Events</p>
        </div>

        <div class="stat-card">
            <h3><?= $loyaltyCustomerCount ?></h3>
            <p>Loyalty Customers</p>
        </div>

        <div class="stat-card">
            <h3><?= $supportTicketCount ?></h3>
            <p>Support Tickets</p>
        </div>

    </div>


    <div class="grid">

        <!-- Café Information -->

        <div class="card">

            <h2>☕ Café Information</h2>

            <div class="info-row">
                <span class="label">Phone</span>
                <span class="value">
                    <?= e($cafe['phone'] ?: '-') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Email</span>
                <span class="value">
                    <?= e($cafe['email'] ?: '-') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">City</span>
                <span class="value">
                    <?= e($cafe['city'] ?: '-') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">State</span>
                <span class="value">
                    <?= e($cafe['state'] ?: '-') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Pincode</span>
                <span class="value">
                    <?= e($cafe['pincode'] ?: '-') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Currency</span>
                <span class="value">
                    <?= e($cafe['currency'] ?: 'INR') ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Timezone</span>
                <span class="value">
                    <?= e($cafe['timezone'] ?: 'Asia/Kolkata') ?>
                </span>
            </div>

        </div>


        <!-- Features -->

        <div class="card">

            <h2>⚙️ Café Features</h2>

            <div class="info-row">
                <span class="label">WhatsApp</span>
                <span class="value">
                    <?= $cafe['whatsapp_enabled']
                        ? 'Enabled'
                        : 'Disabled' ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Games</span>
                <span class="value">
                    <?= $cafe['games_enabled']
                        ? 'Enabled'
                        : 'Disabled' ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Feedback</span>
                <span class="value">
                    <?= $cafe['feedback_enabled']
                        ? 'Enabled'
                        : 'Disabled' ?>
                </span>
            </div>

            <div class="info-row">
                <span class="label">Created</span>
                <span class="value">
                    <?= e($cafe['created_at']) ?>
                </span>
            </div>

        </div>


        <!-- Owners -->

        <div class="card">

            <h2>👤 Café Owners</h2>

            <?php if (empty($owners)): ?>

                <div class="empty">
                    No owner account found.
                </div>

            <?php else: ?>

                <?php foreach ($owners as $owner): ?>

                    <div class="owner">

                        <div class="owner-name">
                            <?= e($owner['name']) ?>
                        </div>

                        <div class="owner-email">
                            <?= e($owner['email']) ?>
                        </div>

                        <span class="small-status">
                            <?= e(strtoupper($owner['status'])) ?>
                            ·
                            <?= e($owner['role']) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <!-- Subscription -->

        <div class="card">

            <h2>💳 Subscription</h2>

            <?php if (!$subscription): ?>

                <div class="empty">
                    No subscription found.
                </div>

            <?php else: ?>

                <div class="info-row">
                    <span class="label">Plan</span>
                    <span class="value">
                        <?= e(
                            $subscription['plan_name']
                            ?: 'Unknown'
                        ) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value">
                        <?= e(
                            strtoupper(
                                $subscription['status']
                            )
                        ) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="label">Billing</span>
                    <span class="value">
                        <?= e(
                            strtoupper(
                                $subscription['billing_cycle']
                            )
                        ) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="label">Start Date</span>
                    <span class="value">
                        <?= e(
                            $subscription['start_date']
                            ?: '-'
                        ) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="label">End Date</span>
                    <span class="value">
                        <?= e(
                            $subscription['end_date']
                            ?: '-'
                        ) ?>
                    </span>
                </div>

            <?php endif; ?>

        </div>


        <!-- Audit Logs -->

        <div class="card" style="grid-column: 1 / -1;">

            <h2>📝 Recent Activity</h2>

            <?php if (empty($auditLogs)): ?>

                <div class="empty">
                    No audit activity recorded yet.
                </div>

            <?php else: ?>

                <?php foreach ($auditLogs as $log): ?>

                    <div class="audit">

                        <div class="audit-action">
                            <?= e($log['action']) ?>
                        </div>

                        <div class="audit-meta">

                            Entity:
                            <?= e(
                                $log['entity_type']
                                ?: '-'
                            ) ?>

                            <?php if ($log['entity_id']): ?>

                                #
                                <?= e(
                                    (string)
                                    $log['entity_id']
                                ) ?>

                            <?php endif; ?>

                            ·

                            <?php
                            $actor =
                                $log['admin_name']
                                ?: $log['owner_name']
                                ?: 'System';
                            ?>

                            By:
                            <?= e($actor) ?>

                            ·

                            <?= e($log['created_at']) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>