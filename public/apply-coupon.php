<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

$slug = cleanInput(
    $_POST['slug'] ?? ''
);

$couponCode = strtoupper(
    cleanInput($_POST['coupon_code'] ?? '')
);

$orderAmount = (float) (
    $_POST['order_amount'] ?? 0
);


if (
    $slug === '' ||
    $couponCode === '' ||
    $orderAmount <= 0
) {

    jsonResponse(
        false,
        'Invalid coupon request.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Café
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
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


/*
|--------------------------------------------------------------------------
| Find Coupon
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM offers
    WHERE cafe_id = :cafe_id
      AND coupon_code = :coupon_code
      AND is_active = 1
      AND start_date <= CURDATE()
      AND expiry_date >= CURDATE()
    LIMIT 1
");

$stmt->execute([
    ':cafe_id' => $cafe['id'],
    ':coupon_code' => $couponCode
]);

$offer = $stmt->fetch();

if (!$offer) {

    jsonResponse(
        false,
        'Invalid or expired coupon.'
    );
}


/*
|--------------------------------------------------------------------------
| Minimum Order
|--------------------------------------------------------------------------
*/

$minimumOrder =
    (float) $offer['min_order_amount'];

if ($orderAmount < $minimumOrder) {

    jsonResponse(
        false,
        'Minimum order amount is ₹' .
        number_format($minimumOrder, 2)
    );
}


/*
|--------------------------------------------------------------------------
| Calculate Discount
|--------------------------------------------------------------------------
*/

$discount = 0;

if (
    $offer['discount_type']
    === 'percentage'
) {

    $discount =
        $orderAmount *
        ((float) $offer['discount_value'] / 100);

} else {

    $discount =
        (float) $offer['discount_value'];
}


/*
|--------------------------------------------------------------------------
| Never allow discount > order
|--------------------------------------------------------------------------
*/

$discount = min(
    $discount,
    $orderAmount
);

$finalAmount =
    $orderAmount - $discount;


jsonResponse(
    true,
    'Coupon applied successfully.',
    [
        'coupon_code' =>
            $offer['coupon_code'],

        'discount_type' =>
            $offer['discount_type'],

        'discount_value' =>
            (float) $offer['discount_value'],

        'order_amount' =>
            round($orderAmount, 2),

        'discount_amount' =>
            round($discount, 2),

        'final_amount' =>
            round($finalAmount, 2)
    ]
);