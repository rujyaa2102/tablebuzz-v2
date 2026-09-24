<?php

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

requireOwner();

header(
    'Content-Type: application/json; charset=utf-8'
);


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
| CSRF
|--------------------------------------------------------------------------
*/

verifyCsrf();


/*
|--------------------------------------------------------------------------
| Current Café
|--------------------------------------------------------------------------
*/

$cafeId = currentCafeId();

if (!$cafeId) {

    jsonResponse(
        false,
        'Café session not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Table Number
|--------------------------------------------------------------------------
*/

$tableNumber = cleanInput(
    $_POST['table_number'] ?? ''
);

if ($tableNumber === '') {

    jsonResponse(
        false,
        'Table number is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Table Number
|--------------------------------------------------------------------------
*/

if (!preg_match(
    '/^[A-Za-z0-9_-]{1,30}$/',
    $tableNumber
)) {

    jsonResponse(
        false,
        'Invalid table number.'
    );
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
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    'cafe_id' => $cafeId
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
| Check Existing QR
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        code,
        destination_url,
        status
    FROM qr_codes
    WHERE cafe_id = :cafe_id
      AND table_number = :table_number
    LIMIT 1
");

$stmt->execute([
    'cafe_id' => $cafeId,
    'table_number' => $tableNumber
]);

$existingQr = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Existing QR
|--------------------------------------------------------------------------
*/

if ($existingQr) {

    jsonResponse(
        true,
        'QR code already exists.',
        [
            'id' =>
                (int) $existingQr['id'],

            'code' =>
                $existingQr['code'],

            'table_number' =>
                $tableNumber,

            'destination_url' =>
                $existingQr['destination_url'],

            'status' =>
                $existingQr['status']
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Generate Secure QR Code Identifier
|--------------------------------------------------------------------------
*/

$code =
    'TB-' .
    strtoupper(
        bin2hex(
            random_bytes(6)
        )
    );


/*
|--------------------------------------------------------------------------
| Destination URL
|--------------------------------------------------------------------------
*/

$destinationUrl =
    APP_URL .
    '/public/cafe.php?slug=' .
    rawurlencode($cafe['slug']) .
    '&table=' .
    rawurlencode($tableNumber);


/*
|--------------------------------------------------------------------------
| Insert QR
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO qr_codes (
        cafe_id,
        table_number,
        code,
        destination_url,
        status,
        created_at,
        updated_at
    )
    VALUES (
        :cafe_id,
        :table_number,
        :code,
        :destination_url,
        'active',
        NOW(),
        NOW()
    )
");

$stmt->execute([
    'cafe_id' =>
        $cafeId,

    'table_number' =>
        $tableNumber,

    'code' =>
        $code,

    'destination_url' =>
        $destinationUrl
]);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'QR code created successfully.',
    [
        'id' =>
            (int) $pdo->lastInsertId(),

        'code' =>
            $code,

        'table_number' =>
            $tableNumber,

        'destination_url' =>
            $destinationUrl,

        'status' =>
            'active'
    ]
);