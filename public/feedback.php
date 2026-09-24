<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    exit('Cafe not specified.');
}

$stmt = $pdo->prepare("
    SELECT id, name, slug, status
    FROM cafes
    WHERE slug = :slug
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    'slug' => $slug
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    exit('Cafe not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Feedback - <?= e($cafe['name']) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f7fb;
    font-family: Arial, sans-serif;
}

.container {
    max-width: 600px;
    margin: 60px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
}

label {
    display: block;
    margin-top: 18px;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

.rating {
    display: flex;
    gap: 8px;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating input {
    display: none;
}

.rating label {
    font-size: 32px;
    cursor: pointer;
    color: #d1d5db;
    margin: 0;
}

.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #f59e0b;
}

button {
    width: 100%;
    margin-top: 25px;
    padding: 13px;
    border: none;
    border-radius: 8px;
    background: #111827;
    color: white;
    font-size: 16px;
    cursor: pointer;
}

.back {
    display: inline-block;
    margin-top: 18px;
    text-decoration: none;
    color: #374151;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>
            💬 Share Your Feedback
        </h1>

        <p>
            <?= e($cafe['name']) ?>
        </p>

        <form method="POST"
              action="<?= e(APP_URL) ?>/public/submit-feedback.php">

            <?= csrfField() ?>

            <input type="hidden"
                   name="slug"
                   value="<?= e($cafe['slug']) ?>">

            <label>
                Your Name
            </label>

            <input
                type="text"
                name="customer_name"
                maxlength="100"
                placeholder="Your name">

            <label>
                Rating
            </label>

            <div class="rating">

                <input
                    type="radio"
                    id="star5"
                    name="rating"
                    value="5"
                    required>

                <label for="star5">★</label>

                <input
                    type="radio"
                    id="star4"
                    name="rating"
                    value="4">

                <label for="star4">★</label>

                <input
                    type="radio"
                    id="star3"
                    name="rating"
                    value="3">

                <label for="star3">★</label>

                <input
                    type="radio"
                    id="star2"
                    name="rating"
                    value="2">

                <label for="star2">★</label>

                <input
                    type="radio"
                    id="star1"
                    name="rating"
                    value="1">

                <label for="star1">★</label>

            </div>

            <label>
                Your Feedback
            </label>

            <textarea
                name="message"
                maxlength="2000"
                required
                placeholder="Tell us about your experience..."></textarea>

            <button type="submit">
                Submit Feedback
            </button>

        </form>

        <a class="back"
           href="<?= e(APP_URL) ?>/public/cafe.php?slug=<?= e(urlencode($cafe['slug'])) ?>">
            ← Back to Cafe
        </a>

    </div>

</div>

</body>
</html>