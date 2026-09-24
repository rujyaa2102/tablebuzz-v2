<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        f.id,
        f.customer_name,
        f.message,
        f.rating,
        f.created_at,
        c.id AS cafe_id,
        c.name AS cafe_name,
        c.slug AS cafe_slug
    FROM feedback f
    INNER JOIN cafes c
        ON c.id = f.cafe_id
    ORDER BY f.created_at DESC
");

$feedbacks = $stmt->fetchAll();

$total = count($feedbacks);

$average = 0;

if ($total > 0) {

    $sum = 0;

    foreach ($feedbacks as $feedback) {
        $sum += (int)$feedback['rating'];
    }

    $average = round($sum / $total, 1);
}

function adminStars($rating): string
{
    $rating = max(0, min(5, (int)$rating));

    $result = '';

    for ($i = 1; $i <= 5; $i++) {
        $result .= $i <= $rating ? '★' : '☆';
    }

    return $result;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Feedback Management - TableBuzz</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f4f6;
    font-family: Arial, sans-serif;
    color: #111827;
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

.header a {
    color: white;
    text-decoration: none;
    background: #374151;
    padding: 9px 14px;
    border-radius: 7px;
}

.container {
    max-width: 1300px;
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
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.stat small {
    color: #6b7280;
}

.stat h2 {
    margin: 8px 0 0;
}

.table-wrap {
    background: white;
    border-radius: 12px;
    padding: 20px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f9fafb;
}

.stars {
    color: #f59e0b;
    white-space: nowrap;
}

.message {
    min-width: 250px;
    max-width: 400px;
}

.delete {
    border: none;
    background: #fee2e2;
    color: #b91c1c;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
}

.empty {
    padding: 40px;
    text-align: center;
    color: #6b7280;
}

@media(max-width:700px) {

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="header">

    <h2>
        💬 Feedback Management
    </h2>

    <a href="<?= e(APP_URL) ?>/admin/dashboard.php">
        ← Dashboard
    </a>

</div>

<div class="container">

    <div class="stats">

        <div class="stat">

            <small>
                Total Feedback
            </small>

            <h2>
                <?= e($total) ?>
            </h2>

        </div>

        <div class="stat">

            <small>
                Average Rating
            </small>

            <h2>
                <?= e($average) ?> / 5 ⭐
            </h2>

        </div>

    </div>

    <div class="table-wrap">

        <?php if (!$feedbacks): ?>

            <div class="empty">
                No feedback found.
            </div>

        <?php else: ?>

        <table>

            <thead>

            <tr>
                <th>ID</th>
                <th>Café</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Message</th>
                <th>Date</th>
                <th>Action</th>
            </tr>

            </thead>

            <tbody>

            <?php foreach ($feedbacks as $feedback): ?>

            <tr>

                <td>
                    #<?= e($feedback['id']) ?>
                </td>

                <td>
                    <strong>
                        <?= e($feedback['cafe_name']) ?>
                    </strong>

                    <br>

                    <small>
                        <?= e($feedback['cafe_slug']) ?>
                    </small>
                </td>

                <td>
                    <?= e($feedback['customer_name'] ?: 'Anonymous') ?>
                </td>

                <td>

                    <div class="stars">
                        <?= e(adminStars($feedback['rating'])) ?>
                    </div>

                    <?= e($feedback['rating']) ?>/5

                </td>

                <td class="message">
                    <?= nl2br(e($feedback['message'])) ?>
                </td>

                <td>
                    <?= e(date(
                        'd M Y, h:i A',
                        strtotime($feedback['created_at'])
                    )) ?>
                </td>

                <td>

                    <form method="POST"
                          action="<?= e(APP_URL) ?>/admin/delete-feedback.php"
                          onsubmit="return confirm('Delete this feedback?');">

                        <?= csrfField() ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= e($feedback['id']) ?>">

                        <button
                            class="delete"
                            type="submit">

                            🗑 Delete

                        </button>

                    </form>

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