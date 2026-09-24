<?php

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$slug = cleanInput(
    $_GET['slug'] ?? ''
);

if ($slug === '') {
    http_response_code(400);
    exit('Café is required.');
}


/*
|--------------------------------------------------------------------------
| Café
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug,
        logo,
        cover_image
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
    http_response_code(404);
    exit('Café not found.');
}


/*
|--------------------------------------------------------------------------
| Games
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        g.id,
        g.name,
        g.slug,
        g.description,
        g.game_type
    FROM games g

    INNER JOIN game_rewards gr
        ON gr.game_id = g.id

    INNER JOIN offers o
        ON o.id = gr.offer_id

    WHERE g.status = 'active'

      AND gr.cafe_id = :reward_cafe_id

      AND gr.status = 'active'

      AND o.cafe_id = :offer_cafe_id

      AND o.is_active = 1

      AND o.is_game_reward = 1

      AND CURDATE() BETWEEN o.start_date
                         AND o.expiry_date

    GROUP BY
        g.id,
        g.name,
        g.slug,
        g.description,
        g.game_type

    ORDER BY g.id ASC
");

$stmt->execute([
    'reward_cafe_id' => $cafe['id'],
    'offer_cafe_id' => $cafe['id']
]);

$games = $stmt->fetchAll();

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
        Games - <?= e($cafe['name']) ?>
    </title>

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
            background: #f5f5f5;
            color: #222;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            padding: 30px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 35px;
        }

        .header h1 {
            margin-bottom: 8px;
        }

        .header p {
            color: #666;
        }

        .games {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .game-card {
            background: white;
            padding: 25px;
            border-radius: 18px;
            box-shadow:
                0 5px 20px rgba(0,0,0,0.08);
        }

        .game-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .game-card h2 {
            margin: 0 0 10px;
        }

        .game-card p {
            color: #666;
            line-height: 1.5;
            min-height: 50px;
        }

        .play-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 20px;
            border-radius: 10px;
            background: #111;
            color: white;
            text-decoration: none;
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: #555;
            text-decoration: none;
        }

    </style>

</head>

<body>

<div class="container">

    <a
        class="back"
        href="cafe.php?slug=<?= e($cafe['slug']) ?>"
    >
        ← Back to Café
    </a>

    <div class="header">

        <h1>
            🎮 Play & Win
        </h1>

        <p>
            Play a game and unlock special café rewards.
        </p>

    </div>


    <?php if (!$games): ?>

        <div class="game-card">

            <h2>
                No games available
            </h2>

            <p>
                Games are currently unavailable.
            </p>

        </div>

    <?php else: ?>

        <div class="games">

            <?php foreach ($games as $game): ?>

                <?php

                $icon = match (
                    $game['game_type']
                ) {
                    'memory_match' => '🧠',
                    'quick_tap' => '⚡',
                    default => '🎮'
                };

                ?>

                <div class="game-card">

                    <div class="game-icon">
                        <?= $icon ?>
                    </div>

                    <h2>
                        <?= e($game['name']) ?>
                    </h2>

                    <p>
                        <?= e(
                            $game['description']
                        ) ?>
                    </p>

                    <a
                        class="play-btn"
                        href="play-game.php?slug=<?= e($cafe['slug']) ?>&game=<?= e($game['slug']) ?>"
                    >
                        Play Now
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>