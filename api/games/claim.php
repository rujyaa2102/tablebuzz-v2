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

$sessionToken = cleanInput(
    $_POST['session_token'] ?? ''
);

$submittedScore = filter_var(
    $_POST['score'] ?? null,
    FILTER_VALIDATE_INT
);

$submittedMoves = filter_var(
    $_POST['moves'] ?? null,
    FILTER_VALIDATE_INT
);


if (
    $slug === '' ||
    $gameSlug === '' ||
    $sessionToken === '' ||
    $submittedScore === false ||
    $submittedMoves === false
) {

    jsonResponse(
        false,
        'Invalid game data.'
    );
}


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if (
    $submittedScore < 0 ||
    $submittedMoves < 0
) {

    jsonResponse(
        false,
        'Invalid score or moves.'
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
| Get Game Session
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        cafe_id,
        game_id,
        session_token,
        score,
        moves,
        status,
        started_at,
        completed_at

    FROM game_sessions

    WHERE session_token = :session_token
      AND cafe_id = :session_cafe_id
      AND game_id = :session_game_id

    LIMIT 1
");

$stmt->execute([
    ':session_token' =>
        $sessionToken,

    ':session_cafe_id' =>
        $cafeId,

    ':session_game_id' =>
        $gameId
]);

$session = $stmt->fetch();


if (!$session) {

    jsonResponse(
        false,
        'Game session not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Replay
|--------------------------------------------------------------------------
*/

if ($session['status'] !== 'started') {

    jsonResponse(
        false,
        'This game session has already been completed.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Game Rules
|--------------------------------------------------------------------------
*/

$startedAt = strtotime(
    $session['started_at']
);

$elapsedSeconds =
    time() - $startedAt;


if ($game['game_type'] === 'memory_match') {

    if ($elapsedSeconds > 90) {

        jsonResponse(
            false,
            'Game time expired.'
        );
    }

    if (
        $submittedMoves < 1 ||
        $submittedMoves > 30
    ) {

        jsonResponse(
            false,
            'Invalid number of moves.'
        );
    }

} elseif (
    $game['game_type'] === 'quick_tap'
) {

    if ($elapsedSeconds > 15) {

        jsonResponse(
            false,
            'Game time expired.'
        );
    }

    $submittedMoves = 0;
}


/*
|--------------------------------------------------------------------------
| Get Active Reward
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        gr.id AS reward_id,
        gr.score_required,

        o.id AS offer_id,
        o.title AS offer_title,
        o.description AS offer_description,
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

      AND :submitted_score >= gr.score_required

    ORDER BY
        gr.score_required DESC,
        gr.id DESC

    LIMIT 1
");

$stmt->execute([
    ':reward_cafe_id' =>
        $cafeId,

    ':reward_game_id' =>
        $gameId,

    ':submitted_score' =>
        $submittedScore
]);

$reward = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Session
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            status

        FROM game_sessions

        WHERE id = :session_id

        FOR UPDATE
    ");

    $stmt->execute([
        ':session_id' =>
            $session['id']
    ]);

    $lockedSession =
        $stmt->fetch();


    if (
        !$lockedSession ||
        $lockedSession['status'] !== 'started'
    ) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Game session is no longer available.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Game Session
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE game_sessions

        SET
            score = :score,
            moves = :moves,
            status = 'completed',
            completed_at = NOW()

        WHERE id = :session_id
          AND status = 'started'
    ");

    $stmt->execute([
        ':score' =>
            $submittedScore,

        ':moves' =>
            $submittedMoves,

        ':session_id' =>
            $session['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | No Reward
    |--------------------------------------------------------------------------
    */

    if (!$reward) {

        $pdo->commit();

        jsonResponse(
            true,
            'Game completed. No reward unlocked.',
            [
                'score' =>
                    $submittedScore,

                'moves' =>
                    $submittedMoves,

                'reward_unlocked' =>
                    false
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Reward Claim
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO game_reward_claims
        (
            cafe_id,
            game_id,
            offer_id,
            game_session_id,
            score,
            moves,
            claimed_at
        )

        VALUES
        (
            :cafe_id,
            :game_id,
            :offer_id,
            :game_session_id,
            :score,
            :moves,
            NOW()
        )
    ");

    $stmt->execute([
        ':cafe_id' =>
            $cafeId,

        ':game_id' =>
            $gameId,

        ':offer_id' =>
            (int) $reward['offer_id'],

        ':game_session_id' =>
            (int) $session['id'],

        ':score' =>
            $submittedScore,

        ':moves' =>
            $submittedMoves
    ]);


    /*
    |--------------------------------------------------------------------------
    | Game Reward Analytics
    |--------------------------------------------------------------------------
    */

    trackAnalyticsEvent(
        $cafeId,
        'game_reward',
        'game',
        $gameId,
        null,
        [
            'game_slug' =>
                $game['slug'],

            'game_name' =>
                $game['name'],

            'offer_id' =>
                (int) $reward['offer_id'],

            'coupon_code' =>
                $reward['coupon_code'],

            'score' =>
                $submittedScore,

            'moves' =>
                $submittedMoves
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Congratulations! Reward unlocked.',
        [
            'score' =>
                $submittedScore,

            'moves' =>
                $submittedMoves,

            'reward_unlocked' =>
                true,

            'reward' => [

                'title' =>
                    $reward['offer_title'],

                'description' =>
                    $reward['offer_description'],

                'coupon_code' =>
                    $reward['coupon_code'],

                'discount_type' =>
                    $reward['discount_type'],

                'discount_value' =>
                    (float)
                    $reward['discount_value'],

                'min_order_amount' =>
                    (float)
                    $reward['min_order_amount'],

                'expiry_date' =>
                    $reward['expiry_date']
            ]
        ]
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Game Claim Error: ' .
        $e->getMessage()
    );

    jsonResponse(
        false,
        'Unable to claim reward right now.'
    );
}