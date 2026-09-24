<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    jsonResponse(
        false,
        'Invalid request method.'
    );
}


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$slug = cleanInput(
    $_POST['slug'] ?? ''
);

$gameSlug = cleanInput(
    $_POST['game_slug'] ?? ''
);


if ($slug === '' || $gameSlug === '') {

    jsonResponse(
        false,
        'Café and game are required.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Active Café
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

    jsonResponse(
        false,
        'Café not found.'
    );
}


$cafeId = (int) $cafe['id'];


/*
|--------------------------------------------------------------------------
| Get Active Game
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug,
        description,
        game_type
    FROM games
    WHERE slug = :game_slug
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    ':game_slug' => $gameSlug
]);

$game = $stmt->fetch();


if (!$game) {

    jsonResponse(
        false,
        'Game not found.'
    );
}


$gameId = (int) $game['id'];


/*
|--------------------------------------------------------------------------
| Check Active Reward
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        gr.id AS reward_id,
        gr.score_required,

        o.id AS offer_id,
        o.title AS offer_title,
        o.coupon_code,
        o.discount_type,
        o.discount_value,
        o.min_order_amount,
        o.expiry_date

    FROM game_rewards gr

    INNER JOIN offers o
        ON o.id = gr.offer_id
        AND o.cafe_id = gr.cafe_id

    WHERE gr.cafe_id = :reward_cafe_id
      AND gr.game_id = :reward_game_id
      AND gr.status = 'active'

      AND o.is_active = 1
      AND o.start_date <= CURDATE()
      AND o.expiry_date >= CURDATE()

    ORDER BY
        gr.score_required ASC,
        gr.id ASC

    LIMIT 1
");

$stmt->execute([
    ':reward_cafe_id' => $cafeId,
    ':reward_game_id' => $gameId
]);

$reward = $stmt->fetch();


if (!$reward) {

    jsonResponse(
        false,
        'No active reward available for this game.'
    );
}


/*
|--------------------------------------------------------------------------
| Generate Secure Session Token
|--------------------------------------------------------------------------
*/

$sessionToken = bin2hex(
    random_bytes(32)
);


/*
|--------------------------------------------------------------------------
| Create Game Session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO game_sessions
    (
        cafe_id,
        game_id,
        session_token,
        score,
        moves,
        status,
        started_at
    )
    VALUES
    (
        :cafe_id,
        :game_id,
        :session_token,
        0,
        0,
        'started',
        NOW()
    )
");

$stmt->execute([
    ':cafe_id' => $cafeId,
    ':game_id' => $gameId,
    ':session_token' => $sessionToken
]);


/*
|--------------------------------------------------------------------------
| Game Start Analytics
|--------------------------------------------------------------------------
*/

trackAnalyticsEvent(
    $cafeId,
    'game_start',
    'game',
    $gameId,
    null,
    [
        'game_slug' => $game['slug'],
        'game_name' => $game['name']
    ]
);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Game started successfully.',
    [
        'session_token' => $sessionToken,

        'game' => [
            'id' => $gameId,
            'name' => $game['name'],
            'slug' => $game['slug'],
            'game_type' => $game['game_type']
        ],

        'reward' => [
            'score_required' =>
                (int) $reward['score_required'],

            'title' =>
                $reward['offer_title'],

            'coupon_code' =>
                $reward['coupon_code'],

            'discount_type' =>
                $reward['discount_type'],

            'discount_value' =>
                (float) $reward['discount_value'],

            'min_order_amount' =>
                (float) $reward['min_order_amount'],

            'expiry_date' =>
                $reward['expiry_date']
        ]
    ]
);