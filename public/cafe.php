<?php

require_once __DIR__ . '/../app/config/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Get Request Parameters
|--------------------------------------------------------------------------
*/

$slug = cleanInput(
    $_GET['slug'] ?? ''
);

$tableNumber = cleanInput(
    $_GET['table'] ?? ''
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
        c.*,
        cs.currency,
        cs.primary_color,
        cs.secondary_color,
        cs.whatsapp_enabled,
        cs.games_enabled,
        cs.feedback_enabled
    FROM cafes c

    LEFT JOIN cafe_settings cs
        ON cs.cafe_id = c.id

    WHERE c.slug = :slug
      AND c.status = 'active'

    LIMIT 1
");

$stmt->execute([
    'slug' => $slug
]);

$cafe = $stmt->fetch();


if (!$cafe) {

    http_response_code(404);

    exit('Café not found.');
}


$cafeId = (int) $cafe['id'];

$feedbackSuccess = (
    ($_GET['feedback'] ?? '') === 'success'
);
if (empty($_SESSION['cafe_view_' . $cafeId])) {

    trackAnalyticsEvent(
        $cafeId,
        'cafe_view',
        'cafe',
        $cafeId,
        null,
        [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]
    );

    $_SESSION['cafe_view_' . $cafeId] = true;
}


/*
|--------------------------------------------------------------------------
| QR Table Tracking
|--------------------------------------------------------------------------
|
| Example:
|
| cafe.php?slug=cafe-goodluck&table=1
|
*/

$qrCodeId = null;

if ($tableNumber !== '') {

    /*
    |--------------------------------------------------------------------------
    | Find QR Code
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            table_number,
            code
        FROM qr_codes
        WHERE cafe_id = :cafe_id
          AND table_number = :table_number
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        'cafe_id' =>
            $cafeId,

        'table_number' =>
            $tableNumber
    ]);

    $qrCode = $stmt->fetch();


    if ($qrCode) {

        $qrCodeId =
            (int) $qrCode['id'];


        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Scan On Refresh
        |--------------------------------------------------------------------------
        */

        $analyticsSessionKey =
            'qr_scan_' . $qrCodeId;


        if (
            empty(
                $_SESSION[$analyticsSessionKey]
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | Analytics Session
            |--------------------------------------------------------------------------
            */

            $analyticsSessionId =
                session_id();


            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            $metadata = json_encode(
                [
                    'table_number' =>
                        $tableNumber,

                    'qr_code' =>
                        $qrCode['code'],

                    'user_agent' =>
                        $_SERVER['HTTP_USER_AGENT']
                        ?? null
                ],
                JSON_UNESCAPED_UNICODE
            );


            /*
            |--------------------------------------------------------------------------
            | Save QR Scan
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO analytics_events (
                    cafe_id,
                    qr_code_id,
                    event_type,
                    entity_type,
                    entity_id,
                    session_id,
                    metadata,
                    created_at
                )
                VALUES (
                    :cafe_id,
                    :qr_code_id,
                    :event_type,
                    :entity_type,
                    :entity_id,
                    :session_id,
                    :metadata,
                    NOW()
                )
            ");

            $stmt->execute([
                'cafe_id' =>
                    $cafeId,

                'qr_code_id' =>
                    $qrCodeId,

                'event_type' =>
                    'qr_scan',

                'entity_type' =>
                    'cafe',

                'entity_id' =>
                    $cafeId,

                'session_id' =>
                    $analyticsSessionId,

                'metadata' =>
                    $metadata
            ]);


            /*
            |--------------------------------------------------------------------------
            | Mark This QR As Tracked In Current Session
            |--------------------------------------------------------------------------
            */

            $_SESSION[$analyticsSessionKey] = true;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM menu_categories
    WHERE cafe_id = :cafe_id
      AND status = 'active'
    ORDER BY
        sort_order ASC,
        name ASC
");

$stmt->execute([
    'cafe_id' => $cafeId
]);

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Menu Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        m.*,
        c.name AS category_name
    FROM menu_items m

    LEFT JOIN menu_categories c
        ON c.id = m.category_id
        AND c.cafe_id = m.cafe_id

    WHERE m.cafe_id = :cafe_id
      AND m.is_available = 1

    ORDER BY
        c.sort_order ASC,
        m.sort_order ASC,
        m.id DESC
");

$stmt->execute([
    'cafe_id' => $cafeId
]);

$menuItems = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Group Menu Items By Category
|--------------------------------------------------------------------------
*/

$menuByCategory = [];


foreach ($menuItems as $item) {

    $categoryId =
        (int) $item['category_id'];


    if (
        !isset(
            $menuByCategory[$categoryId]
        )
    ) {

        $menuByCategory[$categoryId] = [];
    }


    $menuByCategory[$categoryId][] =
        $item;
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
        <?= e($cafe['name']) ?> - TableBuzz
    </title>


    <meta
        name="description"
        content="<?= e(
            $cafe['description']
            ?? $cafe['name']
        ) ?>"
    >


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

            background: #f7f7f7;

            color: #222;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .cafe-header {

            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #1f2937
                );

            color: white;

            padding: 45px 20px;

            text-align: center;
        }


        .cafe-header h1 {

            margin: 0 0 10px;

            font-size: 34px;
        }


        .cafe-header p {

            margin: 6px 0;

            opacity: .9;
        }


        /*
        |--------------------------------------------------------------------------
        | Table Badge
        |--------------------------------------------------------------------------
        */

        .table-badge {

            display: inline-block;

            margin-top: 15px;

            padding: 8px 16px;

            border-radius: 30px;

            background: rgba(
                255,
                255,
                255,
                .12
            );

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .25
                );

            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | Container
        |--------------------------------------------------------------------------
        */

        .container {

            max-width: 1100px;

            margin: auto;

            padding: 25px 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | Quick Actions
        |--------------------------------------------------------------------------
        */

        .quick-actions {

            display: flex;

            justify-content: center;

            gap: 12px;

            flex-wrap: wrap;

            margin-bottom: 25px;
        }


        .action-btn {

            display: inline-block;

            padding: 11px 18px;

            border-radius: 10px;

            text-decoration: none;

            color: white;

            background: #111827;

            font-weight: 600;

            transition: .2s;
        }


        .action-btn:hover {

            transform:
                translateY(-1px);

            opacity: .9;
        }


        .offer-btn {

            background: #b45309;
        }


        .game-btn {

            background: #166534;
        }


        .feedback-btn {

            background: #7c3aed;
        }


        .feedback-success {

            max-width: 800px;

            margin: 0 auto 25px;

            padding: 15px 18px;

            border-radius: 10px;

            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;

            text-align: center;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Category Navigation
        |--------------------------------------------------------------------------
        */

        .category-nav {

            display: flex;

            gap: 10px;

            overflow-x: auto;

            padding-bottom: 15px;

            margin-bottom: 25px;
        }


        .category-nav a {

            white-space: nowrap;

            padding: 10px 16px;

            background: white;

            border-radius: 20px;

            text-decoration: none;

            color: #222;

            border:
                1px solid #ddd;
        }


        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        .category-section {

            margin-bottom: 40px;
        }


        .category-title {

            font-size: 26px;

            margin-bottom: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | Menu
        |--------------------------------------------------------------------------
        */

        .menu-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(250px, 1fr)
                );

            gap: 20px;
        }


        .food-card {

            background: white;

            border-radius: 16px;

            overflow: hidden;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .08
                );
        }


        .food-image {

            width: 100%;

            height: 190px;

            object-fit: cover;

            display: block;
        }


        .food-placeholder {

            height: 190px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eee;

            font-size: 50px;
        }


        .food-content {

            padding: 18px;
        }


        .food-name {

            margin:
                0 0 8px;

            font-size: 20px;
        }


        .food-description {

            color: #666;

            min-height: 45px;

            line-height: 1.5;
        }


        .food-bottom {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 10px;

            margin-top: 15px;
        }


        .food-price {

            font-size: 20px;

            font-weight: bold;
        }


        .popular {

            font-size: 12px;

            background: #ffe7a3;

            padding: 5px 8px;

            border-radius: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {

            text-align: center;

            padding: 50px 20px;

            color: #666;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 600px
        ) {

            .cafe-header h1 {

                font-size: 28px;
            }


            .menu-grid {

                grid-template-columns: 1fr;
            }


            .quick-actions {

                flex-direction:
                    column;
            }


            .action-btn {

                text-align: center;

                width: 100%;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     CAFÉ HEADER
========================================================== -->

<header class="cafe-header">


    <h1>
        <?= e($cafe['name']) ?>
    </h1>


    <?php if (
        !empty(
            $cafe['description']
        )
    ): ?>

        <p>
            <?= e(
                $cafe['description']
            ) ?>
        </p>

    <?php endif; ?>


    <?php if (
        !empty(
            $cafe['address']
        )
    ): ?>

        <p>
            <?= e(
                $cafe['address']
            ) ?>
        </p>

    <?php endif; ?>


    <?php if (
        !empty(
            $cafe['phone']
        )
    ): ?>

        <p>
            <?= e(
                $cafe['phone']
            ) ?>
        </p>

    <?php endif; ?>


    <?php if (
        $tableNumber !== '' &&
        $qrCodeId !== null
    ): ?>

        <div class="table-badge">

            🪑 Table
            <?= e($tableNumber) ?>

        </div>

    <?php endif; ?>


</header>


<main class="container">


    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="quick-actions">


        <a
            class="action-btn offer-btn"
            href="offers.php?slug=<?= urlencode(
                $cafe['slug']
            ) ?>"
        >
            🎁 View Offers
        </a>


        <?php if (
            (int) $cafe['games_enabled'] === 1
        ): ?>

            <a
                class="action-btn game-btn"
                href="games.php?slug=<?= urlencode(
                    $cafe['slug']
                ) ?>"
            >
                🎮 Play & Win
            </a>

        <?php endif; ?>


        <?php if (
            (int) ($cafe['feedback_enabled'] ?? 0) === 1
        ): ?>

            <a
                class="action-btn feedback-btn"
                href="feedback.php?slug=<?= urlencode(
                    $cafe['slug']
                ) ?>"
            >
                💬 Give Feedback
            </a>

        <?php endif; ?>


    </div>


    <?php if ($feedbackSuccess): ?>

        <div class="feedback-success">
            <strong>✅ Thank you!</strong>
            Your feedback has been submitted successfully.
        </div>

    <?php endif; ?>


    <!-- =====================================================
         CATEGORY NAVIGATION
    ====================================================== -->

    <?php if ($categories): ?>


        <nav class="category-nav">


            <?php foreach (
                $categories as $category
            ): ?>


                <?php

                $categoryId =
                    (int) $category['id'];


                if (
                    empty(
                        $menuByCategory[
                            $categoryId
                        ]
                    )
                ) {

                    continue;
                }

                ?>


                <a
                    href="#category-<?= $categoryId ?>"
                >
                    <?= e(
                        $category['name']
                    ) ?>
                </a>


            <?php endforeach; ?>


        </nav>


        <!-- =================================================
             CATEGORY SECTIONS
        ================================================== -->

        <?php foreach (
            $categories as $category
        ): ?>


            <?php

            $categoryId =
                (int) $category['id'];


            $items =
                $menuByCategory[
                    $categoryId
                ] ?? [];


            if (!$items) {

                continue;
            }

            ?>


            <section
                class="category-section"
                id="category-<?= $categoryId ?>"
            >


                <h2
                    class="category-title"
                >

                    <?= e(
                        $category['name']
                    ) ?>

                </h2>


                <div class="menu-grid">


                    <?php foreach (
                        $items as $item
                    ): ?>


                        <article
                            class="food-card"
                        >


                            <?php if (
                                !empty(
                                    $item['image']
                                )
                            ): ?>


                                <img
                                    class="food-image"
                                    src="<?= APP_URL ?>/public/uploads/foods/<?= e(
                                        $item['image']
                                    ) ?>"
                                    alt="<?= e(
                                        $item['name']
                                    ) ?>"
                                    loading="lazy"
                                >


                            <?php else: ?>


                                <div
                                    class="food-placeholder"
                                >
                                    🍽️
                                </div>


                            <?php endif; ?>


                            <div
                                class="food-content"
                            >


                                <h3
                                    class="food-name"
                                >


                                    <a
                                        href="food.php?slug=<?= urlencode(
                                            $cafe['slug']
                                        ) ?>&id=<?= (int) $item['id'] ?>"
                                        style="
                                            text-decoration:none;
                                            color:inherit;
                                        "
                                    >

                                        <?= e(
                                            $item['name']
                                        ) ?>

                                    </a>


                                </h3>


                                <?php if (
                                    !empty(
                                        $item['description']
                                    )
                                ): ?>


                                    <div
                                        class="food-description"
                                    >

                                        <?= e(
                                            $item['description']
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                                <div
                                    class="food-bottom"
                                >


                                    <span
                                        class="food-price"
                                    >

                                        <?= e(
                                            $cafe['currency']
                                            ?? 'INR'
                                        ) ?>

                                        <?= number_format(
                                            (float)
                                            $item['price'],
                                            2
                                        ) ?>

                                    </span>


                                    <?php if (
                                        $item['is_popular']
                                    ): ?>


                                        <span
                                            class="popular"
                                        >
                                            ⭐ Popular
                                        </span>


                                    <?php endif; ?>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            </section>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">


            <h2>
                Menu Coming Soon
            </h2>


            <p>
                This café has not added menu items yet.
            </p>


        </div>


    <?php endif; ?>


</main>


</body>

</html>