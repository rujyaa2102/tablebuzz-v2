<?php

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$slug = cleanInput($_GET['slug'] ?? '');
$gameSlug = cleanInput($_GET['game'] ?? '');

if ($slug === '' || $gameSlug === '') {
    http_response_code(400);
    exit('Invalid game request.');
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
        slug
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
| Game
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
    'game_slug' => $gameSlug
]);

$game = $stmt->fetch();

if (!$game) {
    http_response_code(404);
    exit('Game not found.');
}


/*
|--------------------------------------------------------------------------
| Check Game Reward
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        gr.score_required
    FROM game_rewards gr
    INNER JOIN offers o
        ON o.id = gr.offer_id
    WHERE gr.cafe_id = :cafe_id
      AND gr.game_id = :game_id
      AND gr.status = 'active'
      AND o.is_active = 1
      AND o.is_game_reward = 1
      AND CURDATE() BETWEEN o.start_date
                         AND o.expiry_date
    ORDER BY gr.score_required ASC
    LIMIT 1
");

$stmt->execute([
    'cafe_id' => $cafe['id'],
    'game_id' => $game['id']
]);

$reward = $stmt->fetch();

if (!$reward) {
    http_response_code(404);
    exit('No reward available for this game.');
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
    <?= e($game['name']) ?> -
    <?= e($cafe['name']) ?>
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

    background:
        linear-gradient(
            135deg,
            #111827,
            #1f2937
        );

    min-height: 100vh;
    color: white;
}

.container {
    width: 100%;
    max-width: 850px;
    margin: auto;
    padding: 25px 15px 50px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.back {
    color: white;
    text-decoration: none;
    opacity: .8;
}

.game-box {
    background: white;
    color: #222;
    border-radius: 24px;
    padding: 25px;
    box-shadow:
        0 20px 50px rgba(0,0,0,.3);
}

.game-header {
    text-align: center;
}

.game-header h1 {
    margin: 0 0 8px;
}

.game-header p {
    color: #666;
}

.info {
    display: flex;
    justify-content: center;
    gap: 25px;
    margin: 20px 0;
    font-weight: bold;
}

.start-area {
    text-align: center;
    padding: 30px 10px;
}

.start-btn {
    border: none;
    background: #111827;
    color: white;
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 16px;
    cursor: pointer;
}

.start-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}


/* Memory Match */

.memory-board {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);

    gap: 10px;

    max-width: 500px;
    margin: 25px auto;
}

.memory-card {
    aspect-ratio: 1;
    border: none;
    border-radius: 12px;
    background: #111827;
    color: white;
    font-size: 28px;
    cursor: pointer;
}

.memory-card.open,
.memory-card.matched {
    background: #e5e7eb;
    color: #111;
}

.memory-card.matched {
    opacity: .65;
    cursor: default;
}


/* Quick Tap */

.tap-area {
    position: relative;
    height: 400px;
    background: #f3f4f6;
    border-radius: 18px;
    margin: 25px auto;
    overflow: hidden;
}

.tap-target {
    position: absolute;

    width: 70px;
    height: 70px;

    border-radius: 50%;

    border: none;

    background: #111827;
    color: white;

    font-weight: bold;

    cursor: pointer;

    display: none;
}


/* Result */

.result {
    display: none;

    text-align: center;

    padding: 25px;

    margin-top: 20px;

    border-radius: 16px;

    background: #f3f4f6;
}

.result h2 {
    margin-top: 0;
}

.coupon {
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 15px;
    border: 2px dashed #111827;
}

.coupon-code {
    font-size: 28px;
    font-weight: bold;
    letter-spacing: 2px;
    margin: 15px 0;
}

.error {
    color: #b91c1c;
    margin-top: 15px;
}

.success {
    color: #166534;
}

.hidden {
    display: none !important;
}

</style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <a
            class="back"
            href="games.php?slug=<?= e($cafe['slug']) ?>"
        >
            ← Games
        </a>

        <strong>
            <?= e($cafe['name']) ?>
        </strong>

    </div>


    <div class="game-box">

        <div class="game-header">

            <h1>
                <?= e($game['name']) ?>
            </h1>

            <p>
                <?= e($game['description']) ?>
            </p>

        </div>


        <div class="info">

            <span>
                🎯 Target:
                <?= (int) $reward['score_required'] ?>
            </span>

            <span>
                ⭐ Score:
                <span id="score">0</span>
            </span>

            <span>
                ⏱️
                <span id="timer">--</span>
            </span>

        </div>


        <div class="start-area" id="startArea">

            <p>
                Press Start to begin your game.
            </p>

            <button
                class="start-btn"
                id="startBtn"
            >
                Start Game
            </button>

            <div
                id="startError"
                class="error"
            ></div>

        </div>


        <!-- MEMORY MATCH -->

        <div
            id="memoryGame"
            class="hidden"
        >

            <div
                id="memoryBoard"
                class="memory-board"
            ></div>

        </div>


        <!-- QUICK TAP -->

        <div
            id="quickTapGame"
            class="hidden"
        >

            <div
                id="tapArea"
                class="tap-area"
            >

                <button
                    id="tapTarget"
                    class="tap-target"
                >
                    TAP
                </button>

            </div>

        </div>


        <!-- RESULT -->

        <div
            id="result"
            class="result"
        >

            <h2 id="resultTitle">
                Game Complete
            </h2>

            <p id="resultMessage"></p>

            <div
                id="couponBox"
                class="coupon hidden"
            >

                <h3>
                    🎉 Reward Unlocked
                </h3>

                <p id="couponTitle"></p>

                <div
                    id="couponCode"
                    class="coupon-code"
                ></div>

                <p id="couponDetails"></p>

            </div>

        </div>

    </div>

</div>


<script>

const APP_URL = <?= json_encode(APP_URL) ?>;

const CAFE_SLUG = <?= json_encode($cafe['slug']) ?>;

const GAME_SLUG = <?= json_encode($game['slug']) ?>;

const GAME_TYPE = <?= json_encode($game['game_type']) ?>;

const TARGET_SCORE =
    <?= (int) $reward['score_required'] ?>;


let sessionToken = null;

let currentScore = 0;

let currentMoves = 0;

let gameFinished = false;

let startTime = null;

let timerInterval = null;


/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const startBtn =
    document.getElementById('startBtn');

const startArea =
    document.getElementById('startArea');

const startError =
    document.getElementById('startError');

const memoryGame =
    document.getElementById('memoryGame');

const quickTapGame =
    document.getElementById('quickTapGame');

const scoreElement =
    document.getElementById('score');

const timerElement =
    document.getElementById('timer');

const result =
    document.getElementById('result');

const resultTitle =
    document.getElementById('resultTitle');

const resultMessage =
    document.getElementById('resultMessage');

const couponBox =
    document.getElementById('couponBox');

const couponCode =
    document.getElementById('couponCode');

const couponTitle =
    document.getElementById('couponTitle');

const couponDetails =
    document.getElementById('couponDetails');


/*
|--------------------------------------------------------------------------
| Start Game
|--------------------------------------------------------------------------
*/

startBtn.addEventListener(
    'click',
    startGame
);


async function startGame()
{
    startBtn.disabled = true;

    startError.textContent = '';

    const formData =
        new FormData();

    formData.append(
        'slug',
        CAFE_SLUG
    );

    formData.append(
        'game_slug',
        GAME_SLUG
    );


    try {

        const response =
            await fetch(
                APP_URL +
                '/api/games/start.php',
                {
                    method: 'POST',
                    body: formData
                }
            );


        const data =
            await response.json();


        if (!data.success) {

            startBtn.disabled = false;

            startError.textContent =
                data.message;

            return;
        }


        sessionToken =
            data.data.session_token;


        currentScore = 0;

        currentMoves = 0;

        gameFinished = false;

        scoreElement.textContent = '0';


        startArea.classList.add(
            'hidden'
        );


        startTime =
            Date.now();


        startTimer();


        if (
            GAME_TYPE ===
            'memory_match'
        ) {

            startMemoryMatch();

        } else if (
            GAME_TYPE ===
            'quick_tap'
        ) {

            startQuickTap();

        } else {

            showError(
                'Unsupported game type.'
            );

        }

    } catch (error) {

        startBtn.disabled = false;

        startError.textContent =
            'Unable to start game.';

        console.error(error);
    }
}


/*
|--------------------------------------------------------------------------
| Timer
|--------------------------------------------------------------------------
*/

function startTimer()
{
    clearInterval(
        timerInterval
    );

    timerInterval =
        setInterval(() => {

            const elapsed =
                Math.floor(
                    (
                        Date.now() -
                        startTime
                    ) / 1000
                );

            timerElement.textContent =
                elapsed + 's';


            if (
                GAME_TYPE ===
                'memory_match' &&
                elapsed >= 90
            ) {

                finishGame();

            }


            if (
                GAME_TYPE ===
                'quick_tap' &&
                elapsed >= 15
            ) {

                finishGame();

            }

        }, 250);
}


/*
|--------------------------------------------------------------------------
| MEMORY MATCH
|--------------------------------------------------------------------------
*/

let firstCard = null;

let secondCard = null;

let lockBoard = false;

let matchedPairs = 0;


function startMemoryMatch()
{
    memoryGame.classList.remove(
        'hidden'
    );


    const symbols = [
        '☕',
        '🍕',
        '🍔',
        '🍰',
        '🥤',
        '🍟',
        '🍩',
        '🌮'
    ];


    const cards = [
        ...symbols,
        ...symbols
    ];


    cards.sort(
        () => Math.random() - 0.5
    );


    const board =
        document.getElementById(
            'memoryBoard'
        );


    board.innerHTML = '';


    firstCard = null;

    secondCard = null;

    lockBoard = false;

    matchedPairs = 0;


    cards.forEach(
        (symbol, index) => {

            const button =
                document.createElement(
                    'button'
                );

            button.className =
                'memory-card';

            button.dataset.symbol =
                symbol;

            button.dataset.index =
                index;

            button.textContent =
                '?';


            button.addEventListener(
                'click',
                () => {

                    handleMemoryCard(
                        button
                    );

                }
            );


            board.appendChild(
                button
            );

        }
    );
}


function handleMemoryCard(card)
{
    if (
        lockBoard ||
        gameFinished ||
        card.classList.contains(
            'matched'
        ) ||
        card === firstCard
    ) {
        return;
    }


    card.classList.add('open');

    card.textContent =
        card.dataset.symbol;


    if (!firstCard) {

        firstCard = card;

        return;
    }


    secondCard = card;

    currentMoves++;

    if (
        firstCard.dataset.symbol ===
        secondCard.dataset.symbol
    ) {

        firstCard.classList.add(
            'matched'
        );

        secondCard.classList.add(
            'matched'
        );

        matchedPairs++;

        currentScore =
            matchedPairs;

        scoreElement.textContent =
            currentScore;


        firstCard = null;

        secondCard = null;


        if (
            matchedPairs === 8
        ) {

            finishGame();

        }

    } else {

        lockBoard = true;

        setTimeout(() => {

            firstCard.classList.remove(
                'open'
            );

            secondCard.classList.remove(
                'open'
            );

            firstCard.textContent = '?';

            secondCard.textContent = '?';

            firstCard = null;

            secondCard = null;

            lockBoard = false;

        }, 650);

    }
}


/*
|--------------------------------------------------------------------------
| QUICK TAP
|--------------------------------------------------------------------------
*/

let tapTimeout = null;


function startQuickTap()
{
    quickTapGame.classList.remove(
        'hidden'
    );


    showNextTarget();
}


function showNextTarget()
{
    if (gameFinished) {
        return;
    }


    const area =
        document.getElementById(
            'tapArea'
        );

    const target =
        document.getElementById(
            'tapTarget'
        );


    const maxX =
        area.clientWidth -
        target.offsetWidth;


    const maxY =
        area.clientHeight -
        target.offsetHeight;


    const x =
        Math.floor(
            Math.random() *
            Math.max(maxX, 1)
        );


    const y =
        Math.floor(
            Math.random() *
            Math.max(maxY, 1)
        );


    target.style.left =
        x + 'px';

    target.style.top =
        y + 'px';

    target.style.display =
        'block';


    clearTimeout(
        tapTimeout
    );


    tapTimeout =
        setTimeout(() => {

            finishGame();

        }, 1500);
}


document
    .getElementById('tapTarget')
    .addEventListener(
        'click',
        () => {

            if (gameFinished) {
                return;
            }


            currentScore++;

            scoreElement.textContent =
                currentScore;


            if (
                currentScore >=
                TARGET_SCORE
            ) {

                finishGame();

                return;
            }


            showNextTarget();

        }
    );


/*
|--------------------------------------------------------------------------
| Finish Game
|--------------------------------------------------------------------------
*/

async function finishGame()
{
    if (gameFinished) {
        return;
    }


    gameFinished = true;


    clearInterval(
        timerInterval
    );

    clearTimeout(
        tapTimeout
    );


    if (
        GAME_TYPE ===
        'quick_tap'
    ) {

        document
            .getElementById(
                'tapTarget'
            )
            .style.display = 'none';

    }


    if (!sessionToken) {

        showError(
            'Game session missing.'
        );

        return;
    }


    const formData =
        new FormData();


    formData.append(
        'slug',
        CAFE_SLUG
    );

    formData.append(
        'game_slug',
        GAME_SLUG
    );

    formData.append(
        'session_token',
        sessionToken
    );

    formData.append(
        'score',
        currentScore
    );

    formData.append(
        'moves',
        currentMoves
    );


    try {

        const response =
            await fetch(
                APP_URL +
                '/api/games/claim.php',
                {
                    method: 'POST',
                    body: formData
                }
            );


        const data =
            await response.json();


        showResult(data);

    } catch (error) {

        showError(
            'Unable to process reward.'
        );

        console.error(error);
    }
}


/*
|--------------------------------------------------------------------------
| Result
|--------------------------------------------------------------------------
*/

function showResult(data)
{
    result.style.display =
        'block';


    if (!data.success) {

        resultTitle.textContent =
            'Game Failed';

        resultMessage.textContent =
            data.message;

        return;
    }


    const responseData =
        data.data;


    if (
        responseData.reward_unlocked
    ) {

        resultTitle.textContent =
            '🎉 Congratulations!';

        resultMessage.textContent =
            'You unlocked a special reward.';

        couponBox.classList.remove(
            'hidden'
        );


        couponTitle.textContent =
            responseData.coupon.title;


        couponCode.textContent =
            responseData.coupon.code;


        couponDetails.textContent =
            responseData.coupon.discount_value +
            '% OFF • Minimum order ₹' +
            responseData.coupon.min_order_amount +
            ' • Valid until ' +
            responseData.coupon.expiry_date;

    } else {

        resultTitle.textContent =
            'Game Complete';

        resultMessage.textContent =
            'Your score was ' +
            responseData.score +
            '. Try again to unlock a reward.';
    }
}


/*
|--------------------------------------------------------------------------
| Error
|--------------------------------------------------------------------------
*/

function showError(message)
{
    result.style.display =
        'block';

    resultTitle.textContent =
        'Something went wrong';

    resultMessage.textContent =
        message;
}

</script>

</body>

</html>