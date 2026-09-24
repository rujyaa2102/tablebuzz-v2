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
| Date Range
|--------------------------------------------------------------------------
*/

$days = 7;

if (
    isset($_GET['days']) &&
    in_array(
        (int) $_GET['days'],
        [7, 30, 90],
        true
    )
) {
    $days = (int) $_GET['days'];
}


/*
|--------------------------------------------------------------------------
| Main Analytics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        event_type,
        COUNT(*) AS total
    FROM analytics_events
    WHERE cafe_id = :cafe_id
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
    GROUP BY event_type
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$eventStats = [];

foreach ($stmt->fetchAll() as $row) {

    $eventStats[$row['event_type']] =
        (int) $row['total'];
}


/*
|--------------------------------------------------------------------------
| QR Scans
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :qr_cafe_id
      AND event_type = 'qr_scan'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':qr_cafe_id' => $cafeId
]);

$qrScans = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Cafe Views
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :view_cafe_id
      AND event_type = 'cafe_view'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':view_cafe_id' => $cafeId
]);

$cafeViews = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Food Views
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :food_cafe_id
      AND event_type = 'food_view'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':food_cafe_id' => $cafeId
]);

$foodViews = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Offers Views
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :offers_cafe_id
      AND event_type = 'offers_view'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':offers_cafe_id' => $cafeId
]);

$offersViews = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Game Starts
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :game_cafe_id
      AND event_type = 'game_start'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':game_cafe_id' => $cafeId
]);

$gameStarts = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Game Rewards
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM analytics_events
    WHERE cafe_id = :reward_cafe_id
      AND event_type = 'game_reward'
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':reward_cafe_id' => $cafeId
]);

$gameRewards = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Feedback
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feedback
    WHERE cafe_id = :feedback_cafe_id
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
");

$stmt->execute([
    ':feedback_cafe_id' => $cafeId
]);

$feedbackCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Daily Chart
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        DATE(created_at) AS event_date,
        COUNT(*) AS total
    FROM analytics_events
    WHERE cafe_id = :chart_cafe_id
      AND created_at >= DATE_SUB(
          NOW(),
          INTERVAL {$days} DAY
      )
    GROUP BY DATE(created_at)
    ORDER BY event_date ASC
");

$stmt->execute([
    ':chart_cafe_id' => $cafeId
]);

$dailyData = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Recent Activity
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
    LIMIT 15
");

$stmt->execute([
    ':recent_cafe_id' => $cafeId
]);

$recentEvents = $stmt->fetchAll();


function analyticsLabel(string $event): string
{
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

<title>Analytics - TableBuzz</title>

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
    background: #f5f7fb;
    color: #1f2937;
}

.container {
    max-width: 1200px;
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

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    margin: 6px 0 0;
    color: #6b7280;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 8px;
    background: #111827;
    color: #fff;
    font-size: 14px;
}

.btn.light {
    background: #fff;
    color: #111827;
    border: 1px solid #d1d5db;
}

.range {
    margin-bottom: 25px;
}

.range a {
    display: inline-block;
    text-decoration: none;
    padding: 8px 14px;
    margin-right: 6px;
    border-radius: 7px;
    background: #fff;
    border: 1px solid #ddd;
    color: #333;
}

.cards {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 16px;
}

.card {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.05);
}

.card-title {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 10px;
}

.card-value {
    font-size: 30px;
    font-weight: 700;
}

.section {
    margin-top: 30px;
}

.section h2 {
    font-size: 20px;
    margin-bottom: 15px;
}

.chart {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    overflow-x: auto;
}

.chart-bars {
    min-width: 650px;
    height: 260px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 20px 10px 35px;
    border-bottom: 1px solid #ddd;
}

.bar-wrapper {
    flex: 1;
    min-width: 40px;
    height: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    position: relative;
}

.bar {
    width: 70%;
    max-width: 55px;
    background: #111827;
    border-radius: 6px 6px 0 0;
    min-height: 4px;
}

.bar-value {
    position: absolute;
    bottom: 100%;
    margin-bottom: 5px;
    font-size: 11px;
    color: #555;
}

.bar-date {
    position: absolute;
    bottom: -25px;
    font-size: 11px;
    color: #777;
    white-space: nowrap;
}

.activity {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
}

.activity-row {
    display: grid;
    grid-template-columns:
        180px 180px 1fr 180px;
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
    font-size: 13px;
    text-align: right;
}

@media (max-width: 900px) {

    .cards {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .activity-row {
        grid-template-columns: 1fr 1fr;
    }

    .time {
        text-align: left;
    }
}

@media (max-width: 600px) {

    .container {
        padding: 20px 15px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .cards {
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

<div class="container">

    <div class="header">

        <div>
            <h1>📊 Analytics</h1>

            <p>
                Track your café's customer engagement
            </p>
        </div>

        <div class="actions">

            <a
                href="<?= e(APP_URL) ?>/owner/dashboard.php"
                class="btn light"
            >
                ← Dashboard
            </a>

        </div>

    </div>


    <div class="range">

        <a href="?days=7">
            Last 7 Days
        </a>

        <a href="?days=30">
            Last 30 Days
        </a>

        <a href="?days=90">
            Last 90 Days
        </a>

    </div>


    <div class="cards">

        <div class="card">

            <div class="card-title">
                QR Scans
            </div>

            <div class="card-value">
                <?= $qrScans ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Café Visits
            </div>

            <div class="card-value">
                <?= $cafeViews ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Food Views
            </div>

            <div class="card-value">
                <?= $foodViews ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Offers Views
            </div>

            <div class="card-value">
                <?= $offersViews ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Games Started
            </div>

            <div class="card-value">
                <?= $gameStarts ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Rewards Claimed
            </div>

            <div class="card-value">
                <?= $gameRewards ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Feedback
            </div>

            <div class="card-value">
                <?= $feedbackCount ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Engagement Events
            </div>

            <div class="card-value">
                <?= array_sum($eventStats) ?>
            </div>

        </div>

    </div>


    <div class="section">

        <h2>
            📈 Activity - Last <?= $days ?> Days
        </h2>

        <div class="chart">

            <?php

            $maxValue = 1;

            foreach ($dailyData as $day) {

                $maxValue = max(
                    $maxValue,
                    (int) $day['total']
                );
            }

            ?>

            <div class="chart-bars">

                <?php if (empty($dailyData)): ?>

                    <div>
                        No analytics data yet.
                    </div>

                <?php else: ?>

                    <?php foreach ($dailyData as $day): ?>

                        <?php

                        $total =
                            (int) $day['total'];

                        $height =
                            max(
                                5,
                                ($total / $maxValue) * 200
                            );

                        ?>

                        <div class="bar-wrapper">

                            <div
                                class="bar"
                                style="height: <?= $height ?>px;"
                            ></div>

                            <div class="bar-value">
                                <?= $total ?>
                            </div>

                            <div class="bar-date">
                                <?= e(
                                    date(
                                        'd M',
                                        strtotime(
                                            $day['event_date']
                                        )
                                    )
                                ) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div class="section">

        <h2>
            🕐 Recent Activity
        </h2>

        <div class="activity">

            <?php if (empty($recentEvents)): ?>

                <div style="padding:20px;">
                    No activity recorded yet.
                </div>

            <?php else: ?>

                <?php foreach ($recentEvents as $event): ?>

                    <?php

                    $metadata = [];

                    if (!empty($event['metadata'])) {

                        $decoded =
                            json_decode(
                                $event['metadata'],
                                true
                            );

                        if (is_array($decoded)) {
                            $metadata = $decoded;
                        }
                    }

                    ?>

                    <div class="activity-row">

                        <div class="event">

                            <?= e(
                                analyticsLabel(
                                    $event['event_type']
                                )
                            ) ?>

                        </div>

                        <div class="meta">

                            <?= e(
                                $event['entity_type']
                            ) ?>

                            <?php if (
                                !empty($event['entity_id'])
                            ): ?>

                                #<?= (int) $event['entity_id'] ?>

                            <?php endif; ?>

                        </div>

                        <div class="meta">

                            <?php if (
                                !empty($metadata)
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
                                        $event['created_at']
                                    )
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>