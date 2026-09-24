<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    exit('Method Not Allowed');
}


/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

verifyCsrf();


/*
|--------------------------------------------------------------------------
| Current Café
|--------------------------------------------------------------------------
*/

$cafeId = currentCafeId();


/*
|--------------------------------------------------------------------------
| Food ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);


if (!$cafeId || !$id || $id <= 0) {

    http_response_code(400);

    exit('Invalid food ID.');
}


/*
|--------------------------------------------------------------------------
| Fetch Food Item
|--------------------------------------------------------------------------
|
| cafe_id is mandatory here.
| This prevents one café owner from deleting another café's item.
|
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        image
    FROM menu_items
    WHERE id = :id
      AND cafe_id = :cafe_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':cafe_id' => $cafeId
]);

$item = $stmt->fetch();


if (!$item) {

    http_response_code(404);

    exit('Food item not found.');
}


/*
|--------------------------------------------------------------------------
| Delete Menu Item
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Delete Database Record
    |--------------------------------------------------------------------------
    */

    $delete = $pdo->prepare("
        DELETE FROM menu_items
        WHERE id = :id
          AND cafe_id = :cafe_id
    ");

    $delete->execute([
        ':id' => $id,
        ':cafe_id' => $cafeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Verify Delete
    |--------------------------------------------------------------------------
    */

    if ($delete->rowCount() !== 1) {

        throw new RuntimeException(
            'Menu item could not be deleted.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $audit = $pdo->prepare("
        INSERT INTO audit_logs
        (
            cafe_id,
            cafe_user_id,
            action,
            entity_type,
            entity_id,
            ip_address,
            user_agent,
            metadata
        )
        VALUES
        (
            :cafe_id,
            :cafe_user_id,
            'delete',
            'menu_item',
            :entity_id,
            :ip_address,
            :user_agent,
            :metadata
        )
    ");

    $audit->execute([
        ':cafe_id' => $cafeId,
        ':cafe_user_id' => currentUserId(),
        ':entity_id' => $id,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':metadata' => json_encode([
            'name' => $item['name']
        ], JSON_UNESCAPED_UNICODE)
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    error_log(
        'TableBuzz Delete Menu Error: '
        . $e->getMessage()
    );


    http_response_code(500);

    exit(
        'Unable to delete food item. Please try again.'
    );
}


/*
|--------------------------------------------------------------------------
| Delete Image After Successful DB Transaction
|--------------------------------------------------------------------------
|
| Image is deleted only after the database delete succeeds.
|
*/

if (!empty($item['image'])) {

    $imageName = basename(
        $item['image']
    );

    $imagePath =
        __DIR__
        . '/../public/uploads/foods/'
        . $imageName;


    if (is_file($imagePath)) {

        if (!@unlink($imagePath)) {

            /*
            |--------------------------------------------------------------------------
            | Do not fail the request.
            |--------------------------------------------------------------------------
            |
            | DB record is already deleted successfully.
            | If image cleanup fails, log it for later cleanup.
            |
            */

            error_log(
                'TableBuzz: Unable to delete menu image: '
                . $imagePath
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: '
    . APP_URL
    . '/owner/menu.php?deleted=1'
);

exit;