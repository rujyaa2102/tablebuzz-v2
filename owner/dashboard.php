<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$user = currentUser();

if (
    !$user ||
    ($user['role'] ?? '') !== 'owner'
) {
    http_response_code(403);
    exit('Access denied.');
}

$cafeId = currentCafeId();

if (!$cafeId) {
    http_response_code(403);
    exit('Cafe not assigned.');
}


/*
|--------------------------------------------------------------------------
| Get Café
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug,
        logo,
        cover_image,
        address,
        city,
        state,
        status
    FROM cafes
    WHERE id = :cafe_id
    LIMIT 1
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    http_response_code(404);
    exit('Cafe not found.');
}


/*
|--------------------------------------------------------------------------
| Menu Items Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM menu_items
    WHERE cafe_id = :menu_cafe_id
");

$stmt->execute([
    ':menu_cafe_id' => $cafeId
]);

$menuCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Categories Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM menu_categories
    WHERE cafe_id = :category_cafe_id
");

$stmt->execute([
    ':category_cafe_id' => $cafeId
]);

$categoryCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Active Offers Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM offers
    WHERE cafe_id = :offer_cafe_id
      AND is_active = 1
      AND start_date <= CURDATE()
      AND expiry_date >= CURDATE()
");

$stmt->execute([
    ':offer_cafe_id' => $cafeId
]);

$offerCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Feedback Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feedback
    WHERE cafe_id = :feedback_cafe_id
");

$stmt->execute([
    ':feedback_cafe_id' => $cafeId
]);

$feedbackCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| QR Codes Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM qr_codes
    WHERE cafe_id = :qr_cafe_id
      AND status = 'active'
");

$stmt->execute([
    ':qr_cafe_id' => $cafeId
]);

$qrCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Analytics - Last 7 Days
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        event_type,
        COUNT(*) AS total
    FROM analytics_events
    WHERE cafe_id = :analytics_cafe_id
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL 7 DAY
      )
    GROUP BY event_type
");

$stmt->execute([
    ':analytics_cafe_id' => $cafeId
]);

$analytics = [];

foreach ($stmt->fetchAll() as $row) {

    $analytics[$row['event_type']] =
        (int) $row['total'];
}


$qrScans =
    $analytics['qr_scan'] ?? 0;

$cafeViews =
    $analytics['cafe_view'] ?? 0;

$foodViews =
    $analytics['food_view'] ?? 0;

$gameStarts =
    $analytics['game_start'] ?? 0;

$gameRewards =
    $analytics['game_reward'] ?? 0;

$offersViews =
    $analytics['offers_view'] ?? 0;


/*
|--------------------------------------------------------------------------
| Recent Analytics Activity
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        event_type,
        entity_type,
        entity_id,
        metadata,
        created_at
    FROM analytics_events
    WHERE cafe_id = :recent_cafe_id
    ORDER BY id DESC
    LIMIT 5
");

$stmt->execute([
    ':recent_cafe_id' => $cafeId
]);

$recentActivity = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Event Label
|--------------------------------------------------------------------------
*/

function dashboardEventLabel(
    string $event
): string {

    return match ($event) {

        'qr_scan' =>
            'QR Scan',

        'cafe_view' =>
            'Cafe Visit',

        'food_view' =>
            'Food View',

        'offers_view' =>
            'Offers View',

        'game_start' =>
            'Game Started',

        'game_reward' =>
            'Game Reward',

        default =>
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    $event
                )
            )
    };
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
        Owner Dashboard - <?= e($cafe['name']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f7fb;
            color: #111827;
        }


        /*
        |--------------------------------------------------------------------------
        | Layout
        |--------------------------------------------------------------------------
        */

        .layout {
            display: flex;
            min-height: 100vh;
        }


        /*
        |--------------------------------------------------------------------------
        | Sidebar
        |--------------------------------------------------------------------------
        */

        .sidebar {
            width: 250px;
            background: #111827;
            color: white;
            padding: 25px 15px;

            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            overflow-y: auto;
        }

        .brand {
            font-size: 23px;
            font-weight: 700;
            padding: 0 12px 25px;
        }

        .brand span {
            color: #9ca3af;
            font-size: 12px;
            display: block;
            margin-top: 4px;
            font-weight: 400;
        }

        .nav-title {
            color: #9ca3af;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 12px 8px;
        }

        .nav a {
            display: block;
            color: #d1d5db;
            text-decoration: none;

            padding: 11px 12px;
            margin-bottom: 3px;

            border-radius: 8px;
            font-size: 14px;
        }

        .nav a:hover {
            background: #1f2937;
            color: white;
        }

        .nav a.active {
            background: #374151;
            color: white;
        }


        /*
        |--------------------------------------------------------------------------
        | Main
        |--------------------------------------------------------------------------
        */

        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 30px;
        }


        /*
        |--------------------------------------------------------------------------
        | Top Header
        |--------------------------------------------------------------------------
        */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
            margin-bottom: 30px;
        }

        .welcome h1 {
            margin: 0;
            font-size: 28px;
        }

        .welcome p {
            margin: 7px 0 0;
            color: #6b7280;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;

            padding: 10px 15px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-light {
            background: white;
            color: #111827;

            border: 1px solid #d1d5db;
        }


        /*
        |--------------------------------------------------------------------------
        | Café Card
        |--------------------------------------------------------------------------
        */

        .cafe-card {
            background: white;
            border-radius: 16px;

            padding: 22px;

            margin-bottom: 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.04);
        }

        .cafe-name {
            font-size: 21px;
            font-weight: 700;
        }

        .cafe-address {
            color: #6b7280;
            margin-top: 5px;
            font-size: 14px;
        }

        .status {
            display: inline-block;

            margin-top: 10px;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;
            font-weight: 600;
        }

        .status.active {
            background: #dcfce7;
            color: #166534;
        }

        .status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }


        /*
        |--------------------------------------------------------------------------
        | Stats
        |--------------------------------------------------------------------------
        */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 25px;
        }

        .stat {
            background: white;

            border-radius: 14px;

            padding: 20px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.04);
        }

        .stat-title {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | Analytics
        |--------------------------------------------------------------------------
        */

        .section {
            margin-top: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 15px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .analytics-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }

        .analytics-card {
            background: white;
            padding: 18px;

            border-radius: 13px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.04);
        }

        .analytics-card .number {
            font-size: 25px;
            font-weight: 700;
        }

        .analytics-card .label {
            margin-top: 5px;
            color: #6b7280;
            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Quick Actions
        |--------------------------------------------------------------------------
        */

        .quick-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }

        .quick {
            background: white;

            padding: 20px;

            border-radius: 13px;

            text-decoration: none;

            color: #111827;

            border: 1px solid #eee;

            transition:
                transform 0.15s,
                box-shadow 0.15s;
        }

        .quick:hover {
            transform: translateY(-2px);

            box-shadow:
                0 7px 20px
                rgba(0,0,0,0.07);
        }

        .quick-icon {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .quick-title {
            font-weight: 700;
        }

        .quick-text {
            margin-top: 5px;
            color: #6b7280;
            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Activity
        |--------------------------------------------------------------------------
        */

        .activity {
            background: white;
            border-radius: 14px;
            overflow: hidden;
        }

        .activity-row {
            display: grid;

            grid-template-columns:
                180px 150px 1fr 180px;

            gap: 15px;

            padding: 15px 20px;

            border-bottom: 1px solid #eee;

            align-items: center;
        }

        .activity-row:last-child {
            border-bottom: 0;
        }

        .event {
            font-weight: 600;
        }

        .meta {
            color: #6b7280;
            font-size: 13px;

            word-break: break-word;
        }

        .time {
            color: #6b7280;
            font-size: 12px;
            text-align: right;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .quick-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .analytics-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 800px) {

            .sidebar {
                position: static;
                width: 100%;
            }

            .layout {
                display: block;
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .cafe-card {
                flex-direction: column;
                align-items: flex-start;
            }

        }


        @media (max-width: 550px) {

            .stats,
            .quick-grid,
            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .activity-row {
                grid-template-columns: 1fr;
            }

            .time {
                text-align: left;
            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- ============================================================
         SIDEBAR
         ============================================================ -->

    <aside class="sidebar">

        <div class="brand">

            🍽️ TableBuzz

            <span>
                Café Owner Panel
            </span>

        </div>


        <div class="nav">

            <div class="nav-title">
                Overview
            </div>

            <a
                href="<?= e(APP_URL) ?>/owner/dashboard.php"
                class="active"
            >
                🏠 Dashboard
            </a>

            <a
                href="<?= e(APP_URL) ?>/owner/analytics.php"
            >
                📊 Analytics
            </a>


            <div class="nav-title">
                Café Management
            </div>

            <a
                href="<?= e(APP_URL) ?>/owner/categories.php"
            >
                📂 Categories
            </a>

            <a
                href="<?= e(APP_URL) ?>/owner/menu.php"
            >
                🍔 Menu
            </a>

            <a
                href="<?= e(APP_URL) ?>/owner/add-menu.php"
            >
                ➕ Add Food
            </a>


            <div class="nav-title">
                Customer Engagement
            </div>

           <a   href="<?= e(APP_URL) ?>/owner/offers.php">
                🎁 Offers
            </a>

            <a href="<?= e(APP_URL) ?>/owner/feedback.php">
                💬 Feedback
            </a>

            <a
            href="<?= e(APP_URL) ?>/owner/game-rewards.php"
            >
            🎮 Game Rewards

            </a>

            <a
                href="<?= e(APP_URL) ?>/owner/qr-codes.php"
            >
                📱 QR Codes
            </a>

            <div class="nav-title">
                Account
            </div>

            <a
                href="<?= e(APP_URL) ?>/public/cafe.php?slug=<?= urlencode($cafe['slug']) ?>"
                target="_blank"
            >
                👁️ View Café
            </a>

            <a
                href="<?= e(APP_URL) ?>/owner/logout.php"
            >
                🚪 Logout
            </a>

            <a 
                href="<?= e(APP_URL) ?>/owner/support.php">
                
                🎫 Support
            </a>

        </div>

    </aside>


    <!-- ============================================================
         MAIN
         ============================================================ -->

    <main class="main">


        <!-- Top Header -->

        <div class="topbar">

            <div class="welcome">

                <h1>
                    Welcome, <?= e($user['name']) ?> 👋
                </h1>

                <p>
                    Manage your café and customer engagement.
                </p>

            </div>


            <div class="top-actions">

                <a
                    href="<?= e(APP_URL) ?>/public/cafe.php?slug=<?= urlencode($cafe['slug']) ?>"
                    target="_blank"
                    class="btn btn-light"
                >
                    👁️ View Café
                </a>

                <a
                    href="<?= e(APP_URL) ?>/owner/logout.php"
                    class="btn btn-dark"
                >
                    Logout
                </a>

            </div>

        </div>


        <!-- Café Information -->

        <div class="cafe-card">

            <div>

                <div class="cafe-name">

                    <?= e($cafe['name']) ?>

                </div>

                <div class="cafe-address">

                    <?= e(
                        trim(
                            implode(
                                ', ',
                                array_filter([
                                    $cafe['address'],
                                    $cafe['city'],
                                    $cafe['state']
                                ])
                            )
                        )
                    ) ?>

                </div>


                <span
                    class="status
                    <?= $cafe['status'] === 'active'
                        ? 'active'
                        : 'inactive'
                    ?>"
                >

                    <?= e(
                        ucfirst(
                            $cafe['status']
                        )
                    ) ?>

                </span>

            </div>


            <div>

                <a
                    href="<?= e(APP_URL) ?>/owner/analytics.php"
                    class="btn btn-dark"
                >
                    📊 Full Analytics
                </a>

            </div>

        </div>


        <!-- ========================================================
             MAIN STATS
             ======================================================== -->

        <div class="stats">


            <div class="stat">

                <div class="stat-title">
                    Menu Items
                </div>

                <div class="stat-value">
                    <?= $menuCount ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    Categories
                </div>

                <div class="stat-value">
                    <?= $categoryCount ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    Active Offers
                </div>

                <div class="stat-value">
                    <?= $offerCount ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-title">
                    QR Codes
                </div>

                <div class="stat-value">
                    <?= $qrCount ?>
                </div>

            </div>

        </div>


        <!-- ========================================================
             QUICK ACTIONS
             ======================================================== -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Quick Actions
                </h2>

            </div>


            <div class="quick-grid">


                <a
                    class="quick"
                    href="<?= e(APP_URL) ?>/owner/add-menu.php"
                >

                    <div class="quick-icon">
                        🍔
                    </div>

                    <div class="quick-title">
                        Add Food
                    </div>

                    <div class="quick-text">
                        Add a new menu item.
                    </div>

                </a>


                <a
                    class="quick"
                    href="<?= e(APP_URL) ?>/owner/categories.php"
                >

                    <div class="quick-icon">
                        📂
                    </div>

                    <div class="quick-title">
                        Categories
                    </div>

                    <div class="quick-text">
                        Manage menu categories.
                    </div>

                </a>


                <a
                    class="quick"
                    href="<?= e(APP_URL) ?>/owner/qr-codes.php"
                >

                    <div class="quick-icon">
                        📱
                    </div>

                    <div class="quick-title">
                        QR Codes
                    </div>

                    <div class="quick-text">
                        Create table QR codes.
                    </div>

                </a>


                <a
                    class="quick"
                    href="<?= e(APP_URL) ?>/owner/analytics.php"
                >

                    <div class="quick-icon">
                        📊
                    </div>

                    <div class="quick-title">
                        Analytics
                    </div>

                    <div class="quick-text">
                        View customer activity.
                    </div>

                </a>

            </div>

        </div>


        <!-- ========================================================
             CUSTOMER ANALYTICS
             ======================================================== -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Customer Engagement
                </h2>

                <a
                    href="<?= e(APP_URL) ?>/owner/analytics.php"
                    class="btn btn-light"
                >
                    View Details →
                </a>

            </div>


            <div class="analytics-grid">


                <div class="analytics-card">

                    <div class="number">
                        <?= $qrScans ?>
                    </div>

                    <div class="label">
                        QR Scans · 7 Days
                    </div>

                </div>


                <div class="analytics-card">

                    <div class="number">
                        <?= $cafeViews ?>
                    </div>

                    <div class="label">
                        Café Visits · 7 Days
                    </div>

                </div>


                <div class="analytics-card">

                    <div class="number">
                        <?= $foodViews ?>
                    </div>

                    <div class="label">
                        Food Views · 7 Days
                    </div>

                </div>


                <div class="analytics-card">

                    <div class="number">
                        <?= $offersViews ?>
                    </div>

                    <div class="label">
                        Offers Views · 7 Days
                    </div>

                </div>


                <div class="analytics-card">

                    <div class="number">
                        <?= $gameStarts ?>
                    </div>

                    <div class="label">
                        Games Started · 7 Days
                    </div>

                </div>


                <div class="analytics-card">

                    <div class="number">
                        <?= $gameRewards ?>
                    </div>

                    <div class="label">
                        Rewards Claimed · 7 Days
                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================
             RECENT ACTIVITY
             ======================================================== -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Recent Activity
                </h2>

                <a
                    href="<?= e(APP_URL) ?>/owner/analytics.php"
                    class="btn btn-light"
                >
                    All Activity →
                </a>

            </div>


            <div class="activity">


                <?php if (
                    empty($recentActivity)
                ): ?>

                    <div
                        style="
                            padding:25px;
                            color:#6b7280;
                        "
                    >
                        No recent activity yet.
                    </div>

                <?php else: ?>


                    <?php foreach (
                        $recentActivity
                        as $event
                    ): ?>


                        <?php

                        $metadata = [];

                        if (
                            !empty(
                                $event['metadata']
                            )
                        ) {

                            $decoded =
                                json_decode(
                                    $event['metadata'],
                                    true
                                );

                            if (
                                is_array(
                                    $decoded
                                )
                            ) {
                                $metadata =
                                    $decoded;
                            }
                        }

                        ?>


                        <div class="activity-row">


                            <div class="event">

                                <?= e(
                                    dashboardEventLabel(
                                        $event[
                                            'event_type'
                                        ]
                                    )
                                ) ?>

                            </div>


                            <div class="meta">

                                <?= e(
                                    $event[
                                        'entity_type'
                                    ]
                                ) ?>

                                <?php if (
                                    !empty(
                                        $event[
                                            'entity_id'
                                        ]
                                    )
                                ): ?>

                                    #
                                    <?= (int)
                                        $event[
                                            'entity_id'
                                        ] ?>

                                <?php endif; ?>

                            </div>


                            <div class="meta">

                                <?php if (
                                    !empty(
                                        $metadata
                                    )
                                ): ?>

                                    <?= e(
                                        json_encode(
                                            $metadata,
                                            JSON_UNESCAPED_UNICODE
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </div>


                            <div class="time">

                                <?= e(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            $event[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>

        </div>


    </main>

</div>

</body>

</html>