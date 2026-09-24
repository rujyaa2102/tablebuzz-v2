<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();

if (!$cafeId) {
    http_response_code(403);
    exit('Cafe not assigned.');
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
    WHERE id = :cafe_id
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
| Messages
|--------------------------------------------------------------------------
*/

$successMessage = null;

$messages = [
    'created' => 'Offer created successfully.',
    'updated' => 'Offer updated successfully.',
    'toggled' => 'Offer status updated successfully.',
    'deleted' => 'Offer deleted successfully.'
];

foreach ($messages as $key => $message) {
    if (isset($_GET[$key])) {
        $successMessage = $message;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        description,
        coupon_code,
        discount_type,
        discount_value,
        min_order_amount,
        start_date,
        expiry_date,
        is_active,
        is_game_reward,
        usage_limit,
        created_at
    FROM offers
    WHERE cafe_id = :cafe_id
    ORDER BY
        is_active DESC,
        expiry_date ASC,
        id DESC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$offers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Status Helper
|--------------------------------------------------------------------------
*/

function getOfferStatus(array $offer): array
{
    $today = date('Y-m-d');

    if ((int) $offer['is_active'] !== 1) {
        return [
            'label' => 'Inactive',
            'class' => 'inactive'
        ];
    }

    if (
        !empty($offer['start_date']) &&
        $offer['start_date'] > $today
    ) {
        return [
            'label' => 'Scheduled',
            'class' => 'scheduled'
        ];
    }

    if (
        !empty($offer['expiry_date']) &&
        $offer['expiry_date'] < $today
    ) {
        return [
            'label' => 'Expired',
            'class' => 'expired'
        ];
    }

    return [
        'label' => 'Active',
        'class' => 'active'
    ];
}

/*
|--------------------------------------------------------------------------
| Discount Helper
|--------------------------------------------------------------------------
*/

function getOfferDiscount(array $offer): string
{
    if ($offer['discount_type'] === 'percentage') {

        return rtrim(
            rtrim(
                number_format(
                    (float) $offer['discount_value'],
                    2,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . '% OFF';
    }

    return '₹' . number_format(
        (float) $offer['discount_value'],
        2
    ) . ' OFF';
}

$totalOffers = count($offers);

$activeOffers = 0;
$scheduledOffers = 0;
$expiredOffers = 0;
$inactiveOffers = 0;

foreach ($offers as $offer) {

    $status = getOfferStatus($offer);

    switch ($status['class']) {

        case 'active':
            $activeOffers++;
            break;

        case 'scheduled':
            $scheduledOffers++;
            break;

        case 'expired':
            $expiredOffers++;
            break;

        case 'inactive':
            $inactiveOffers++;
            break;
    }
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
    Offers - <?= e($cafe['name']) ?> | TableBuzz
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;
    background: #f5f7fb;
    color: #111827;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    background: #111827;
    color: white;
    padding: 25px 15px;

    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;

    overflow-y: auto;
}

.brand {
    font-size: 23px;
    font-weight: 700;
    padding: 0 12px 25px;
}

.brand span {
    display: block;
    margin-top: 4px;
    color: #9ca3af;
    font-size: 12px;
    font-weight: 400;
}

.nav-title {
    color: #9ca3af;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px 12px 8px;
}

.nav a {
    display: block;
    color: #d1d5db;
    text-decoration: none;

    padding: 11px 12px;
    margin-bottom: 3px;

    border-radius: 8px;
    font-size: 14px;
}

.nav a:hover,
.nav a.active {
    background: #374151;
    color: white;
}

.main {
    margin-left: 250px;
    width: calc(100% - 250px);
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.heading h1 {
    margin: 0;
    font-size: 28px;
}

.heading p {
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

.alert {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat {
    background: white;
    border-radius: 14px;
    padding: 20px;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.04);
}

.stat-title {
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 27px;
    font-weight: 700;
}

.card {
    background: white;
    border-radius: 15px;
    overflow: hidden;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.04);
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th,
td {
    padding: 15px 16px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

th {
    background: #f9fafb;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

td {
    font-size: 14px;
}

tr:last-child td {
    border-bottom: 0;
}

.offer-title {
    font-weight: 700;
}

.offer-description {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
    max-width: 250px;
}

.coupon {
    display: inline-block;
    background: #f3f4f6;
    border: 1px dashed #9ca3af;
    padding: 6px 9px;
    border-radius: 6px;
    font-family: monospace;
    font-weight: 700;
    font-size: 12px;
}

.discount {
    font-weight: 700;
    color: #166534;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.badge.active {
    background: #dcfce7;
    color: #166534;
}

.badge.inactive {
    background: #f3f4f6;
    color: #4b5563;
}

.badge.expired {
    background: #fee2e2;
    color: #991b1b;
}

.badge.scheduled {
    background: #fef3c7;
    color: #92400e;
}

.badge.game {
    background: #ede9fe;
    color: #6d28d9;
    margin-left: 5px;
}

.date {
    color: #374151;
    font-size: 13px;
}

.muted {
    color: #9ca3af;
}

.action-form {
    display: inline;
}

.action-link {
    display: inline-block;
    padding: 6px 9px;
    border: 0;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    margin-right: 4px;
    margin-bottom: 4px;
}

.edit {
    background: #eff6ff;
    color: #1d4ed8;
}

.toggle {
    background: #f3f4f6;
    color: #374151;
}

.delete {
    background: #fee2e2;
    color: #b91c1c;
}

.empty {
    padding: 60px 25px;
    text-align: center;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.empty h3 {
    margin: 0;
    font-size: 20px;
}

.empty p {
    color: #6b7280;
    margin: 8px 0 20px;
}

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 800px) {

    .sidebar {
        position: static;
        width: 100%;
    }

    .layout {
        display: block;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 550px) {

    .stats {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="layout">

<aside class="sidebar">

    <div class="brand">
        🍽️ TableBuzz

        <span>
            Café Owner Panel
        </span>
    </div>

    <div class="nav">

        <div class="nav-title">
            Overview
        </div>

        <a href="<?= e(APP_URL) ?>/owner/dashboard.php">
            🏠 Dashboard
        </a>

        <a href="<?= e(APP_URL) ?>/owner/analytics.php">
            📊 Analytics
        </a>

        <div class="nav-title">
            Café Management
        </div>

        <a href="<?= e(APP_URL) ?>/owner/categories.php">
            📂 Categories
        </a>

        <a href="<?= e(APP_URL) ?>/owner/menu.php">
            🍔 Menu
        </a>

        <a href="<?= e(APP_URL) ?>/owner/add-menu.php">
            ➕ Add Food
        </a>

        <div class="nav-title">
            Customer Engagement
        </div>

        <a
            href="<?= e(APP_URL) ?>/owner/offers.php"
            class="active"
        >
            🎁 Offers
        </a>

        <a href="<?= e(APP_URL) ?>/owner/game-rewards.php">
            🎮 Game Rewards
        </a>

        <a href="<?= e(APP_URL) ?>/owner/qr-codes.php">
            📱 QR Codes
        </a>

        <a href="#">
            💬 Feedback
        </a>

        <div class="nav-title">
            Account
        </div>

        <a
            href="<?= e(APP_URL) ?>/public/cafe.php?slug=<?= urlencode($cafe['slug']) ?>"
            target="_blank"
        >
            👁️ View Café
        </a>

        <a href="<?= e(APP_URL) ?>/owner/support.php">
            🎫 Support
        </a>

        <a href="<?= e(APP_URL) ?>/owner/logout.php">
            🚪 Logout
        </a>

    </div>

</aside>


<main class="main">

    <div class="topbar">

        <div class="heading">

            <h1>
                🎁 Offers
            </h1>

            <p>
                Manage offers and coupon codes for
                <?= e($cafe['name']) ?>.
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
                href="<?= e(APP_URL) ?>/owner/add-offer.php"
                class="btn btn-dark"
            >
                + Add Offer
            </a>

        </div>

    </div>


    <?php if ($successMessage): ?>

        <div class="alert alert-success">
            ✅ <?= e($successMessage) ?>
        </div>

    <?php endif; ?>


    <div class="stats">

        <div class="stat">
            <div class="stat-title">Total Offers</div>
            <div class="stat-value">
                <?= $totalOffers ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-title">Active Offers</div>
            <div class="stat-value">
                <?= $activeOffers ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-title">Scheduled</div>
            <div class="stat-value">
                <?= $scheduledOffers ?>
            </div>
        </div>

        <div class="stat">
            <div class="stat-title">Expired</div>
            <div class="stat-value">
                <?= $expiredOffers ?>
            </div>
        </div>

    </div>


    <div class="card">

    <?php if (empty($offers)): ?>

        <div class="empty">

            <div class="empty-icon">
                🎁
            </div>

            <h3>
                No Offers Yet
            </h3>

            <p>
                Create your first customer offer.
            </p>

            <a
                href="<?= e(APP_URL) ?>/owner/add-offer.php"
                class="btn btn-dark"
            >
                + Create Offer
            </a>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                <tr>
                    <th>Offer</th>
                    <th>Coupon</th>
                    <th>Discount</th>
                    <th>Minimum Order</th>
                    <th>Validity</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach ($offers as $offer): ?>

                    <?php
                    $status = getOfferStatus($offer);
                    ?>

                    <tr>

                        <td>

                            <div class="offer-title">

                                <?= e($offer['title']) ?>

                                <?php if (
                                    (int) $offer['is_game_reward'] === 1
                                ): ?>

                                    <span class="badge game">
                                        🎮 Game
                                    </span>

                                <?php endif; ?>

                            </div>

                            <?php if (
                                !empty($offer['description'])
                            ): ?>

                                <div class="offer-description">
                                    <?= e($offer['description']) ?>
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty($offer['coupon_code'])
                            ): ?>

                                <span class="coupon">
                                    <?= e($offer['coupon_code']) ?>
                                </span>

                            <?php else: ?>

                                <span class="muted">
                                    No Code
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span class="discount">
                                <?= e(
                                    getOfferDiscount($offer)
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <?php if (
                                (float) $offer['min_order_amount'] > 0
                            ): ?>

                                ₹<?= e(
                                    number_format(
                                        (float) $offer['min_order_amount'],
                                        2
                                    )
                                ) ?>

                            <?php else: ?>

                                <span class="muted">
                                    No minimum
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="date">

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime($offer['start_date'])
                                    )
                                ) ?>

                                <br>

                                <span class="muted">
                                    to
                                </span>

                                <br>

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime($offer['expiry_date'])
                                    )
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?php if (
                                $offer['usage_limit'] !== null
                            ): ?>

                                <?= e(
                                    (int) $offer['usage_limit']
                                ) ?>

                            <?php else: ?>

                                <span class="muted">
                                    Unlimited
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span
                                class="badge <?= e(
                                    $status['class']
                                ) ?>"
                            >
                                <?= e(
                                    $status['label']
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <a
                                href="<?= e(APP_URL) ?>/owner/edit-offer.php?id=<?= (int) $offer['id'] ?>"
                                class="action-link edit"
                            >
                                ✏️ Edit
                            </a>


                            <form
                                method="POST"
                                action="<?= e(APP_URL) ?>/owner/toggle-offer.php"
                                class="action-form"
                                onsubmit="return confirm('Change offer status?');"
                            >

                                <?= csrfField() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $offer['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-link toggle"
                                >
                                    🔄 Toggle
                                </button>

                            </form>


                            <form
                                method="POST"
                                action="<?= e(APP_URL) ?>/owner/delete-offer.php"
                                class="action-form"
                                onsubmit="return confirm('Delete this offer permanently?');"
                            >

                                <?= csrfField() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $offer['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-link delete"
                                >
                                    🗑️ Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

    </div>

</main>

</div>

</body>

</html>