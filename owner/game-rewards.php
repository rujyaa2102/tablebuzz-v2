<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$user = currentUser();

if (
    !$user ||
    ($user['role'] ?? '') !== 'owner'
) {
    http_response_code(403);
    exit('Access denied.');
}

$cafeId = currentCafeId();

if (!$cafeId) {
    http_response_code(403);
    exit('Cafe not assigned.');
}


/*
|--------------------------------------------------------------------------
| Get Cafe
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug
    FROM cafes
    WHERE id = :cafe_id
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    http_response_code(404);
    exit('Cafe not found.');
}


/*
|--------------------------------------------------------------------------
| Get Games
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug,
        game_type,
        description
    FROM games
    WHERE status = 'active'
    ORDER BY id ASC
");

$stmt->execute();

$games = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Game Rewards
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        gr.id AS reward_id,
        gr.score_required,
        gr.status AS reward_status,

        g.id AS game_id,
        g.name AS game_name,
        g.slug AS game_slug,

        o.id AS offer_id,
        o.title AS offer_title,
        o.description AS offer_description,
        o.coupon_code,
        o.discount_type,
        o.discount_value,
        o.min_order_amount,
        o.start_date,
        o.expiry_date,
        o.is_active,
        o.usage_limit

    FROM game_rewards gr

    INNER JOIN games g
        ON g.id = gr.game_id

    INNER JOIN offers o
        ON o.id = gr.offer_id
        AND o.cafe_id = gr.cafe_id

    WHERE gr.cafe_id = :reward_cafe_id

    ORDER BY
        g.id ASC,
        gr.score_required ASC,
        gr.id DESC
");

$stmt->execute([
    ':reward_cafe_id' => $cafeId
]);

$rewards = $stmt->fetchAll();

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
    Game Rewards - <?= e($cafe['name']) ?>
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
    background: #f5f7fb;
    color: #111827;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 30px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    margin: 7px 0 0;
    color: #6b7280;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    border: 0;
    cursor: pointer;
}

.btn-dark {
    background: #111827;
    color: white;
}

.btn-light {
    background: white;
    color: #111827;
    border: 1px solid #d1d5db;
}

.info {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 25px;
    border-left: 4px solid #111827;
}

.info h3 {
    margin-top: 0;
}

.info p {
    color: #6b7280;
    line-height: 1.6;
}

.reward-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.reward-card {
    background: white;
    border-radius: 16px;
    padding: 22px;
    box-shadow:
        0 4px 18px
        rgba(0,0,0,0.05);
}

.reward-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.reward-header h2 {
    margin: 0;
    font-size: 20px;
}

.reward-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status.active {
    background: #dcfce7;
    color: #166534;
}

.status.inactive {
    background: #fee2e2;
    color: #991b1b;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns:
        repeat(2, 1fr);
    gap: 12px;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 10px 0 18px;
}

.checkbox input {
    width: auto;
}

.empty {
    background: white;
    padding: 50px;
    text-align: center;
    border-radius: 16px;
}

.note {
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

@media (max-width: 800px) {

    .reward-grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 500px) {

    .form-row {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="container">


    <!-- Header -->

    <div class="header">

        <div>

            <h1>
                🎮 Game Rewards
            </h1>

            <p>
                Configure rewards for
                <?= e($cafe['name']) ?>
            </p>

        </div>

        <div class="actions">

            <a
                href="<?= e(APP_URL) ?>/owner/dashboard.php"
                class="btn btn-light"
            >
                ← Dashboard
            </a>

            <a
                href="<?= e(APP_URL) ?>/public/games.php?slug=<?= urlencode($cafe['slug']) ?>"
                target="_blank"
                class="btn btn-dark"
            >
                👁️ View Games
            </a>

        </div>

    </div>


    <!-- Information -->

    <div class="info">

        <h3>
            🎁 How Game Rewards Work
        </h3>

        <p>
            Set the score required to unlock a reward,
            discount percentage or fixed amount,
            minimum order amount and coupon validity.
            Customers will receive the configured coupon
            after successfully completing the game.
        </p>

        <p class="note">

            Example:
            Memory Match → Score 6 → 10% OFF →
            Minimum Order ₹200 → Coupon GAME10

        </p>

    </div>


    <?php if (!$rewards): ?>

        <div class="empty">

            <h2>
                No Game Rewards Configured
            </h2>

            <p>
                Game rewards can be created from the
                reward management system.
            </p>

        </div>

    <?php else: ?>

        <div class="reward-grid">

            <?php foreach ($rewards as $reward): ?>

                <div class="reward-card">


                    <div class="reward-header">

                        <div>

                            <h2>
                                🎮
                                <?= e(
                                    $reward['game_name']
                                ) ?>
                            </h2>

                            <p>
                                <?= e(
                                    $reward['game_type'] ??
                                    $reward['game_slug']
                                ) ?>
                            </p>

                        </div>


                        <?php if (
                            $reward['reward_status']
                            === 'active'
                            &&
                            (int)
                            $reward['is_active']
                            === 1
                        ): ?>

                            <span class="status active">
                                ACTIVE
                            </span>

                        <?php else: ?>

                            <span class="status inactive">
                                INACTIVE
                            </span>

                        <?php endif; ?>

                    </div>


                    <form
                        method="POST"
                        action="<?= e(APP_URL) ?>/owner/save-game-reward.php"
                    >

                        <?= csrfField() ?>


                        <input
                            type="hidden"
                            name="reward_id"
                            value="<?= (int) $reward['reward_id'] ?>"
                        >


                        <!-- Required Score -->

                        <div class="form-group">

                            <label>
                                Required Score
                            </label>

                            <input
                                type="number"
                                name="score_required"
                                min="1"
                                max="100000"
                                value="<?= (int) $reward['score_required'] ?>"
                                required
                            >

                        </div>


                        <!-- Coupon -->

                        <div class="form-group">

                            <label>
                                Coupon Code
                            </label>

                            <input
                                type="text"
                                name="coupon_code"
                                maxlength="50"
                                value="<?= e($reward['coupon_code']) ?>"
                                required
                            >

                        </div>


                        <!-- Discount -->

                        <div class="form-row">


                            <div class="form-group">

                                <label>
                                    Discount Type
                                </label>

                                <select
                                    name="discount_type"
                                    required
                                >

                                    <option
                                        value="percentage"
                                        <?= $reward['discount_type']
                                            === 'percentage'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Percentage (%)
                                    </option>

                                    <option
                                        value="fixed"
                                        <?= $reward['discount_type']
                                            === 'fixed'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Fixed Amount (₹)
                                    </option>

                                </select>

                            </div>


                            <div class="form-group">

                                <label>
                                    Discount Value
                                </label>

                                <input
                                    type="number"
                                    name="discount_value"
                                    min="0.01"
                                    step="0.01"
                                    value="<?= e(
                                        (string)
                                        $reward['discount_value']
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <!-- Minimum Order -->

                        <div class="form-group">

                            <label>
                                Minimum Order Amount (₹)
                            </label>

                            <input
                                type="number"
                                name="min_order_amount"
                                min="0"
                                step="0.01"
                                value="<?= e(
                                    (string)
                                    $reward['min_order_amount']
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- Dates -->

                        <div class="form-row">


                            <div class="form-group">

                                <label>
                                    Start Date
                                </label>

                                <input
                                    type="date"
                                    name="start_date"
                                    value="<?= e(
                                        $reward['start_date']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Expiry Date
                                </label>

                                <input
                                    type="date"
                                    name="expiry_date"
                                    value="<?= e(
                                        $reward['expiry_date']
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <!-- Usage Limit -->

                        <div class="form-group">

                            <label>
                                Usage Limit
                            </label>

                            <input
                                type="number"
                                name="usage_limit"
                                min="0"
                                value="<?= (int) $reward['usage_limit'] ?>"
                                required
                            >

                            <div class="note">
                                Use 0 for unlimited usage.
                            </div>

                        </div>


                        <!-- Active -->

                        <div class="checkbox">

                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                id="active_<?= (int) $reward['reward_id'] ?>"
                                <?= (int)
                                    $reward['is_active'] === 1
                                    ? 'checked'
                                    : ''
                                ?>
                            >

                            <label
                                for="active_<?= (int) $reward['reward_id'] ?>"
                            >
                                Reward is Active
                            </label>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-dark"
                        >
                            💾 Save Reward
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>