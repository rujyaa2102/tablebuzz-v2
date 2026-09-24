<?php
require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$user = $_SESSION['user'];
$cafeId = (int)$user['cafe_id'];

$stmt = $pdo->prepare("
    SELECT id, customer_name, message, rating, created_at
    FROM feedback
    WHERE cafe_id = :cafe_id
    ORDER BY created_at DESC
");
$stmt->execute(['cafe_id' => $cafeId]);
$feedbacks = $stmt->fetchAll();

$total = count($feedbacks);

$avgRating = 0;
if ($total > 0) {
    $sum = 0;
    foreach ($feedbacks as $feedback) {
        $sum += (int)$feedback['rating'];
    }
    $avgRating = round($sum / $total, 1);
}

function ratingStars($rating): string
{
    $rating = max(0, min(5, (int)$rating));

    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }

    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - TableBuzz</title>

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

        .header {
            background: #111827;
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .back {
            color: white;
            text-decoration: none;
            background: #374151;
            padding: 9px 14px;
            border-radius: 7px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .stat small {
            color: #6b7280;
        }

        .stat h2 {
            margin: 8px 0 0;
            font-size: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .feedback {
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 0;
        }

        .feedback:last-child {
            border-bottom: none;
        }

        .top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .name {
            font-size: 17px;
            font-weight: bold;
        }

        .stars {
            color: #f59e0b;
            font-size: 20px;
            letter-spacing: 2px;
        }

        .message {
            margin: 12px 0;
            line-height: 1.6;
            color: #4b5563;
        }

        .date {
            color: #9ca3af;
            font-size: 13px;
        }

        .delete-btn {
            border: none;
            background: #fee2e2;
            color: #b91c1c;
            padding: 7px 11px;
            border-radius: 6px;
            cursor: pointer;
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #6b7280;
        }

        @media(max-width:700px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .top {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="header">
    <h2>💬 Customer Feedback</h2>

    <a class="back"
       href="<?= e(APP_URL) ?>/owner/dashboard.php">
        ← Dashboard
    </a>
</div>

<div class="container">

    <div class="stats">

        <div class="stat">
            <small>Total Feedback</small>
            <h2><?= e($total) ?></h2>
        </div>

        <div class="stat">
            <small>Average Rating</small>
            <h2>
                <?= e($avgRating) ?> / 5 ⭐
            </h2>
        </div>

    </div>

    <div class="card">

        <?php if (!$feedbacks): ?>

            <div class="empty">
                <h3>No feedback yet</h3>
                <p>Customer feedback will appear here.</p>
            </div>

        <?php else: ?>

            <?php foreach ($feedbacks as $feedback): ?>

                <div class="feedback">

                    <div class="top">

                        <div>
                            <div class="name">
                                <?= e($feedback['customer_name'] ?: 'Anonymous') ?>
                            </div>

                            <div class="date">
                                <?= e(date('d M Y, h:i A', strtotime($feedback['created_at']))) ?>
                            </div>
                        </div>

                        <div>
                            <div class="stars">
                                <?= e(ratingStars($feedback['rating'])) ?>
                            </div>

                            <small>
                                <?= e($feedback['rating']) ?>/5
                            </small>
                        </div>

                    </div>

                    <div class="message">
                        <?= nl2br(e($feedback['message'])) ?>
                    </div>

                    <form method="POST"
                          action="<?= e(APP_URL) ?>/owner/delete-feedback.php"
                          onsubmit="return confirm('Delete this feedback?');">

                        <?= csrfField() ?>

                        <input type="hidden"
                               name="id"
                               value="<?= e($feedback['id']) ?>">

                        <button class="delete-btn"
                                type="submit">
                            🗑 Delete
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>