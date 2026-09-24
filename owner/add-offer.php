<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();
$userId = currentUserId();

if (!$cafeId) {
    http_response_code(403);
    exit('Cafe not assigned.');
}

$errors = [];

$title = '';
$description = '';
$couponCode = '';
$discountType = 'percentage';
$discountValue = '';
$minOrderAmount = '0';
$startDate = date('Y-m-d');
$expiryDate = date('Y-m-d', strtotime('+30 days'));
$usageLimit = '';
$isActive = 1;
$isGameReward = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountValue = trim($_POST['discount_value'] ?? '');
    $minOrderAmount = trim($_POST['min_order_amount'] ?? '0');
    $startDate = trim($_POST['start_date'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $usageLimit = trim($_POST['usage_limit'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isGameReward = isset($_POST['is_game_reward']) ? 1 : 0;

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($title === '') {
        $errors[] = 'Offer title is required.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'Offer title must not exceed 150 characters.';
    }

    if (!in_array($discountType, ['percentage', 'fixed'], true)) {
        $errors[] = 'Invalid discount type.';
    }

    if (
        $discountValue === '' ||
        !is_numeric($discountValue) ||
        (float) $discountValue <= 0
    ) {
        $errors[] = 'Discount value must be greater than 0.';
    }

    if (
        $discountType === 'percentage' &&
        (float) $discountValue > 100
    ) {
        $errors[] = 'Percentage discount cannot exceed 100%.';
    }

    if (
        $minOrderAmount === '' ||
        !is_numeric($minOrderAmount) ||
        (float) $minOrderAmount < 0
    ) {
        $errors[] = 'Minimum order amount is invalid.';
    }

    if ($startDate === '') {
        $errors[] = 'Start date is required.';
    }

    if ($expiryDate === '') {
        $errors[] = 'Expiry date is required.';
    }

    if (
        $startDate !== '' &&
        $expiryDate !== '' &&
        $expiryDate < $startDate
    ) {
        $errors[] = 'Expiry date cannot be before start date.';
    }

    if ($usageLimit !== '') {

        if (
            !ctype_digit($usageLimit) ||
            (int) $usageLimit <= 0
        ) {
            $errors[] = 'Usage limit must be a positive whole number.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Coupon Validation
    |--------------------------------------------------------------------------
    */

    if ($couponCode !== '') {

        if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $couponCode)) {
            $errors[] =
                'Coupon code can contain only letters, numbers, hyphen and underscore.';
        }

        $check = $pdo->prepare("
            SELECT id
            FROM offers
            WHERE cafe_id = :cafe_id
              AND coupon_code = :coupon_code
            LIMIT 1
        ");

        $check->execute([
            ':cafe_id' => $cafeId,
            ':coupon_code' => $couponCode
        ]);

        if ($check->fetch()) {
            $errors[] = 'This coupon code already exists.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO offers (
                    cafe_id,
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
                    created_at,
                    updated_at
                )
                VALUES (
                    :cafe_id,
                    :title,
                    :description,
                    :coupon_code,
                    :discount_type,
                    :discount_value,
                    :min_order_amount,
                    :start_date,
                    :expiry_date,
                    :is_active,
                    :is_game_reward,
                    :usage_limit,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':title' => $title,
                ':description' => $description !== ''
                    ? $description
                    : null,
                ':coupon_code' => $couponCode !== ''
                    ? $couponCode
                    : null,
                ':discount_type' => $discountType,
                ':discount_value' => (float) $discountValue,
                ':min_order_amount' => (float) $minOrderAmount,
                ':start_date' => $startDate,
                ':expiry_date' => $expiryDate,
                ':is_active' => $isActive,
                ':is_game_reward' => $isGameReward,
                ':usage_limit' => $usageLimit !== ''
                    ? (int) $usageLimit
                    : null
            ]);

            $offerId = (int) $pdo->lastInsertId();

            $audit = $pdo->prepare("
                INSERT INTO audit_logs (
                    cafe_id,
                    cafe_user_id,
                    action,
                    entity_type,
                    entity_id,
                    ip_address,
                    user_agent,
                    metadata,
                    created_at
                )
                VALUES (
                    :cafe_id,
                    :cafe_user_id,
                    'create',
                    'offer',
                    :entity_id,
                    :ip_address,
                    :user_agent,
                    :metadata,
                    NOW()
                )
            ");

            $audit->execute([
                ':cafe_id' => $cafeId,
                ':cafe_user_id' => $userId,
                ':entity_id' => $offerId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'title' => $title,
                    'coupon_code' => $couponCode,
                    'discount_type' => $discountType,
                    'discount_value' => (float) $discountValue,
                    'is_game_reward' => $isGameReward
                ], JSON_UNESCAPED_UNICODE)
            ]);

            $pdo->commit();

            header(
                'Location: ' .
                APP_URL .
                '/owner/offers.php?created=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'TableBuzz add offer error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to create offer. Please try again.';
        }
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
    Add Offer | TableBuzz
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #111827;
}

.container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
}

.card {
    background: white;
    padding: 28px;
    border-radius: 15px;
    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-size: 14px;
    font-weight: 600;
}

input,
textarea,
select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 9px;
}

.checkbox input {
    width: auto;
}

.actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    padding: 11px 17px;
    border-radius: 8px;
    border: 0;
    text-decoration: none;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: #111827;
    color: white;
}

.btn-light {
    background: white;
    color: #111827;
    border: 1px solid #d1d5db;
}

.errors {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}

.errors li {
    margin-bottom: 5px;
}

.hint {
    margin-top: 5px;
    color: #6b7280;
    font-size: 12px;
}

@media (max-width: 650px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }
}

</style>

</head>

<body>

<div class="container">

<div class="header">

    <div>
        <h1>🎁 Add Offer</h1>
        <p>Create a customer offer for your café.</p>
    </div>

    <a
        href="<?= e(APP_URL) ?>/owner/offers.php"
        class="btn btn-light"
    >
        ← Back
    </a>

</div>


<?php if (!empty($errors)): ?>

<div class="errors">

    <strong>Please fix the following:</strong>

    <ul>

        <?php foreach ($errors as $error): ?>

            <li><?= e($error) ?></li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<div class="card">

<form method="POST">

    <?= csrfField() ?>

    <div class="form-group">

        <label>
            Offer Title *
        </label>

        <input
            type="text"
            name="title"
            maxlength="150"
            value="<?= e($title) ?>"
            placeholder="Example: Weekend Special"
            required
        >

    </div>


    <div class="form-group">

        <label>
            Description
        </label>

        <textarea
            name="description"
            placeholder="Describe the offer..."
        ><?= e($description) ?></textarea>

    </div>


    <div class="grid">

        <div class="form-group">

            <label>
                Coupon Code
            </label>

            <input
                type="text"
                name="coupon_code"
                maxlength="50"
                value="<?= e($couponCode) ?>"
                placeholder="GOODLUCK20"
            >

            <div class="hint">
                Leave blank if this offer does not require a coupon.
            </div>

        </div>


        <div class="form-group">

            <label>
                Discount Type *
            </label>

            <select name="discount_type">

                <option
                    value="percentage"
                    <?= $discountType === 'percentage'
                        ? 'selected'
                        : '' ?>
                >
                    Percentage
                </option>

                <option
                    value="fixed"
                    <?= $discountType === 'fixed'
                        ? 'selected'
                        : '' ?>
                >
                    Fixed Amount
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>
                Discount Value *
            </label>

            <input
                type="number"
                name="discount_value"
                min="0.01"
                step="0.01"
                value="<?= e($discountValue) ?>"
                placeholder="20"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Minimum Order Amount
            </label>

            <input
                type="number"
                name="min_order_amount"
                min="0"
                step="0.01"
                value="<?= e($minOrderAmount) ?>"
                placeholder="200"
            >

        </div>


        <div class="form-group">

            <label>
                Start Date *
            </label>

            <input
                type="date"
                name="start_date"
                value="<?= e($startDate) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Expiry Date *
            </label>

            <input
                type="date"
                name="expiry_date"
                value="<?= e($expiryDate) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                Usage Limit
            </label>

            <input
                type="number"
                name="usage_limit"
                min="1"
                step="1"
                value="<?= e($usageLimit) ?>"
                placeholder="100"
            >

            <div class="hint">
                Leave blank for unlimited usage.
            </div>

        </div>

    </div>


    <div class="form-group">

        <label class="checkbox">

            <input
                type="checkbox"
                name="is_active"
                value="1"
                <?= $isActive ? 'checked' : '' ?>
            >

            Active immediately

        </label>

    </div>


    <div class="form-group">

        <label class="checkbox">

            <input
                type="checkbox"
                name="is_game_reward"
                value="1"
                <?= $isGameReward ? 'checked' : '' ?>
            >

            🎮 This offer is a game reward

        </label>

    </div>


    <div class="actions">

        <button
            type="submit"
            class="btn btn-primary"
        >
            Create Offer
        </button>

        <a
            href="<?= e(APP_URL) ?>/owner/offers.php"
            class="btn btn-light"
        >
            Cancel
        </a>

    </div>

</form>

</div>

</div>

</body>

</html>