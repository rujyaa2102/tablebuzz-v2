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
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        APP_URL .
        '/owner/game-rewards.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

verifyCsrf();


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$rewardId = filter_input(
    INPUT_POST,
    'reward_id',
    FILTER_VALIDATE_INT
);

$scoreRequired = filter_input(
    INPUT_POST,
    'score_required',
    FILTER_VALIDATE_INT
);

$discountType = cleanInput(
    $_POST['discount_type'] ?? ''
);

$discountValue = filter_var(
    $_POST['discount_value'] ?? null,
    FILTER_VALIDATE_FLOAT
);

$minOrderAmount = filter_var(
    $_POST['min_order_amount'] ?? null,
    FILTER_VALIDATE_FLOAT
);

$couponCode = strtoupper(
    cleanInput(
        $_POST['coupon_code'] ?? ''
    )
);

$startDate = cleanInput(
    $_POST['start_date'] ?? ''
);

$expiryDate = cleanInput(
    $_POST['expiry_date'] ?? ''
);

$usageLimit = filter_var(
    $_POST['usage_limit'] ?? null,
    FILTER_VALIDATE_INT
);

$isActive = isset(
    $_POST['is_active']
) ? 1 : 0;


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    !$rewardId ||
    !$scoreRequired ||
    $scoreRequired < 1
) {
    exit('Invalid score.');
}

if (
    !in_array(
        $discountType,
        ['percentage', 'fixed'],
        true
    )
) {
    exit('Invalid discount type.');
}

if (
    $discountValue === false ||
    $discountValue <= 0
) {
    exit('Invalid discount value.');
}

if (
    $minOrderAmount === false ||
    $minOrderAmount < 0
) {
    exit('Invalid minimum order amount.');
}

if ($usageLimit === false || $usageLimit < 0) {
    exit('Invalid usage limit.');
}

if ($couponCode === '') {
    exit('Coupon code is required.');
}

if (
    !preg_match(
        '/^[A-Z0-9_-]{3,50}$/',
        $couponCode
    )
) {
    exit(
        'Coupon code can contain only A-Z, 0-9, underscore and hyphen.'
    );
}

if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $startDate
    )
) {
    exit('Invalid start date.');
}

if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $expiryDate
    )
) {
    exit('Invalid expiry date.');
}

if ($expiryDate < $startDate) {
    exit(
        'Expiry date cannot be before start date.'
    );
}


/*
|--------------------------------------------------------------------------
| Percentage Validation
|--------------------------------------------------------------------------
*/

if (
    $discountType === 'percentage' &&
    $discountValue > 100
) {
    exit(
        'Percentage discount cannot exceed 100%.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Reward + Verify Ownership
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        gr.id AS reward_id,
        gr.cafe_id,
        gr.game_id,
        gr.offer_id

    FROM game_rewards gr

    WHERE gr.id = :reward_id
      AND gr.cafe_id = :cafe_id

    LIMIT 1
");

$stmt->execute([
    ':reward_id' => $rewardId,
    ':cafe_id' => $cafeId
]);

$reward = $stmt->fetch();

if (!$reward) {
    http_response_code(404);
    exit('Game reward not found.');
}


/*
|--------------------------------------------------------------------------
| Check Coupon Uniqueness
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM offers
    WHERE cafe_id = :coupon_cafe_id
      AND coupon_code = :coupon_code
      AND id != :current_offer_id
    LIMIT 1
");

$stmt->execute([
    ':coupon_cafe_id' =>
        $cafeId,

    ':coupon_code' =>
        $couponCode,

    ':current_offer_id' =>
        (int) $reward['offer_id']
]);

$existingCoupon = $stmt->fetch();

if ($existingCoupon) {
    exit(
        'This coupon code already exists for your café.'
    );
}


/*
|--------------------------------------------------------------------------
| Update Reward + Offer
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Update Offer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE offers

        SET
            coupon_code = :coupon_code,
            discount_type = :discount_type,
            discount_value = :discount_value,
            min_order_amount = :min_order_amount,
            start_date = :start_date,
            expiry_date = :expiry_date,
            usage_limit = :usage_limit,
            is_active = :is_active,
            updated_at = NOW()

        WHERE id = :offer_id
          AND cafe_id = :offer_cafe_id
    ");

    $stmt->execute([

        ':coupon_code' =>
            $couponCode,

        ':discount_type' =>
            $discountType,

        ':discount_value' =>
            $discountValue,

        ':min_order_amount' =>
            $minOrderAmount,

        ':start_date' =>
            $startDate,

        ':expiry_date' =>
            $expiryDate,

        ':usage_limit' =>
            $usageLimit,

        ':is_active' =>
            $isActive,

        ':offer_id' =>
            (int) $reward['offer_id'],

        ':offer_cafe_id' =>
            $cafeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Update Game Reward
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE game_rewards

        SET
            score_required = :score_required,
            status = :reward_status

        WHERE id = :reward_id
          AND cafe_id = :reward_cafe_id
    ");

    $stmt->execute([

        ':score_required' =>
            $scoreRequired,

        ':reward_status' =>
            $isActive
                ? 'active'
                : 'inactive',

        ':reward_id' =>
            $rewardId,

        ':reward_cafe_id' =>
            $cafeId
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' .
        APP_URL .
        '/owner/game-rewards.php?updated=1'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Game Reward Update Error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to update game reward.'
    );
}