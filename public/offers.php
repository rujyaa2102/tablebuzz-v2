<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

$slug = cleanInput(
    $_GET['slug'] ?? ''
);

if ($slug === '') {
    http_response_code(404);
    exit('Café not found.');
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
        slug
    FROM cafes
    WHERE slug = :slug
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    ':slug' => $slug
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    http_response_code(404);
    exit('Café not found.');
}


/*
|--------------------------------------------------------------------------
| Analytics - Offers View
|--------------------------------------------------------------------------
*/

trackAnalyticsEvent(
    (int) $cafe['id'],
    'offers_view',
    'cafe',
    (int) $cafe['id']
);


/*
|--------------------------------------------------------------------------
| Active Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM offers
    WHERE cafe_id = :cafe_id
      AND is_active = 1
      AND start_date <= CURDATE()
      AND expiry_date >= CURDATE()
    ORDER BY
        expiry_date ASC,
        id DESC
");

$stmt->execute([
    ':cafe_id' => $cafe['id']
]);

$offers = $stmt->fetchAll();

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
        Offers - <?= e($cafe['name']) ?>
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
            max-width: 1000px;
            margin: auto;
            padding: 25px 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin-bottom: 10px;
        }

        .header a {
            color: #333;
            text-decoration: none;
        }

        .header a:hover {
            text-decoration: underline;
        }

        .offers {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(260px, 1fr)
                );
            gap: 20px;
        }

        .offer {
            background: white;
            padding: 25px;
            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(0, 0, 0, 0.07);
        }

        .offer h2 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .code {
            display: inline-block;
            background: #111;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            letter-spacing: 1px;
            font-weight: bold;
        }

        .discount {
            font-size: 25px;
            font-weight: bold;
            margin: 15px 0;
        }

        .empty {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 18px;
        }

    </style>

</head>

<body>

<div class="container">


    <!-- Header -->

    <div class="header">

        <h1>
            Offers at <?= e($cafe['name']) ?>
        </h1>

        <a
            href="cafe.php?slug=<?= urlencode($cafe['slug']) ?>"
        >
            ← Back to Menu
        </a>

    </div>


    <!-- Offers -->

    <?php if (!$offers): ?>

        <div class="empty">

            <h2>
                No Active Offers
            </h2>

            <p>
                Check again later for new offers.
            </p>

        </div>

    <?php else: ?>

        <div class="offers">

            <?php foreach ($offers as $offer): ?>

                <article class="offer">


                    <!-- Offer Title -->

                    <h2>
                        <?= e($offer['title']) ?>
                    </h2>


                    <!-- Discount -->

                    <div class="discount">

                        <?php if (
                            $offer['discount_type']
                            === 'percentage'
                        ): ?>

                            <?= e(
                                (string)
                                $offer['discount_value']
                            ) ?>% OFF

                        <?php else: ?>

                            ₹<?= number_format(
                                (float)
                                $offer['discount_value'],
                                2
                            ) ?> OFF

                        <?php endif; ?>

                    </div>


                    <!-- Description -->

                    <?php if (
                        !empty(
                            $offer['description']
                        )
                    ): ?>

                        <p>

                            <?= nl2br(
                                e(
                                    $offer['description']
                                )
                            ) ?>

                        </p>

                    <?php endif; ?>


                    <!-- Coupon Code -->

                    <p>
                        Coupon Code:
                    </p>

                    <span class="code">

                        <?= e(
                            $offer['coupon_code']
                        ) ?>

                    </span>


                    <!-- Minimum Order -->

                    <?php if (
                        $offer['min_order_amount'] > 0
                    ): ?>

                        <p>

                            Minimum order:

                            ₹<?= number_format(
                                (float)
                                $offer[
                                    'min_order_amount'
                                ],
                                2
                            ) ?>

                        </p>

                    <?php endif; ?>


                    <!-- Expiry -->

                    <p>

                        Valid till:

                        <?= e(
                            $offer['expiry_date']
                        ) ?>

                    </p>


                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


</div>

</body>

</html>