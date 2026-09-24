<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$foodId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$slug = cleanInput(
    $_GET['slug'] ?? ''
);

if (!$foodId || $slug === '') {
    http_response_code(404);
    exit('Food not found.');
}


/*
|--------------------------------------------------------------------------
| Get Food + Café
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        m.*,

        c.id AS cafe_id,
        c.name AS cafe_name,
        c.slug AS cafe_slug,
        c.description AS cafe_description,
        c.address AS cafe_address,

        mc.name AS category_name

    FROM menu_items m

    INNER JOIN cafes c
        ON c.id = m.cafe_id

    LEFT JOIN menu_categories mc
        ON mc.id = m.category_id
        AND mc.cafe_id = m.cafe_id

    WHERE m.id = :food_id
      AND c.slug = :slug
      AND c.status = 'active'
      AND m.is_available = 1

    LIMIT 1
");

$stmt->execute([
    ':food_id' => $foodId,
    ':slug' => $slug
]);

$food = $stmt->fetch();

if (!$food) {
    http_response_code(404);
    exit('Food item not found.');
}
/*
|--------------------------------------------------------------------------
| Analytics - Food View
|--------------------------------------------------------------------------
*/

trackAnalyticsEvent(
    (int) $food['cafe_id'],
    'food_view',
    'menu_item',
    (int) $food['id'],
    null,
    [
        'food_name' => $food['name']
    ]
);

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
        <?= e($food['name']) ?> -
        <?= e($food['cafe_name']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            color: #222;
        }

        .container {
            max-width: 900px;
            margin: auto;
            padding: 25px 15px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
        }

        .food-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 8px 30px rgba(0,0,0,0.08);
        }

        .food-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            display: block;
        }

        .placeholder {
            height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eee;
            font-size: 80px;
        }

        .content {
            padding: 30px;
        }

        .category {
            color: #777;
            font-size: 14px;
        }

        h1 {
            margin: 10px 0;
            font-size: 34px;
        }

        .description {
            color: #666;
            line-height: 1.7;
            font-size: 17px;
        }

        .price {
            font-size: 28px;
            font-weight: bold;
            margin: 20px 0;
        }

        .popular {
            display: inline-block;
            background: #ffe5a3;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .cafe-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

    </style>

</head>

<body>

<div class="container">

    <a
        class="back"
        href="cafe.php?slug=<?= urlencode($food['cafe_slug']) ?>"
    >
        ← Back to Menu
    </a>

    <article class="food-card">

        <?php if (!empty($food['image'])): ?>

            <img
                class="food-image"
                src="<?= APP_URL ?>/public/uploads/foods/<?= e($food['image']) ?>"
                alt="<?= e($food['name']) ?>"
            >

        <?php else: ?>

            <div class="placeholder">
                🍽️
            </div>

        <?php endif; ?>


        <div class="content">

            <?php if (!empty($food['category_name'])): ?>

                <div class="category">
                    <?= e($food['category_name']) ?>
                </div>

            <?php endif; ?>


            <h1>
                <?= e($food['name']) ?>
            </h1>


            <?php if ($food['is_popular']): ?>

                <span class="popular">
                    ⭐ Popular
                </span>

            <?php endif; ?>


            <div class="price">
                ₹<?= number_format(
                    (float) $food['price'],
                    2
                ) ?>
            </div>


            <?php if (!empty($food['description'])): ?>

                <div class="description">

                    <?= nl2br(
                        e($food['description'])
                    ) ?>

                </div>

            <?php endif; ?>


            <div class="cafe-info">

                <strong>
                    <?= e($food['cafe_name']) ?>
                </strong>

                <?php if (!empty($food['cafe_address'])): ?>

                    <p>
                        <?= e($food['cafe_address']) ?>
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </article>

</div>

</body>

</html>