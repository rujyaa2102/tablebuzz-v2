<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

verifyCsrf();

$cafeId = currentCafeId();
$userId = currentUserId();

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$cafeId || !$id || $id <= 0) {
    http_response_code(400);
    exit('Invalid food ID.');
}

try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Menu Item
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            is_available
        FROM menu_items
        WHERE id = :id
          AND cafe_id = :cafe_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id'      => $id,
        ':cafe_id' => $cafeId
    ]);

    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        exit('Food item not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate New Availability
    |--------------------------------------------------------------------------
    */

    $oldStatus = (int) $item['is_available'];
    $newStatus = $oldStatus === 1 ? 0 : 1;

    /*
    |--------------------------------------------------------------------------
    | Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Update Menu Item
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE menu_items
        SET
            is_available = :status,
            updated_at = NOW()
        WHERE id = :id
          AND cafe_id = :cafe_id
    ");

    $update->execute([
        ':status'  => $newStatus,
        ':id'      => $id,
        ':cafe_id' => $cafeId
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Menu availability update failed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

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
            'toggle_status',
            'menu_item',
            :entity_id,
            :ip_address,
            :user_agent,
            :metadata,
            NOW()
        )
    ");

    $audit->execute([
        ':cafe_id'      => $cafeId,
        ':cafe_user_id' => $userId,
        ':entity_id'    => $id,
        ':ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':metadata'     => json_encode([
            'name'              => $item['name'],
            'old_is_available'  => $oldStatus,
            'new_is_available'  => $newStatus
        ], JSON_UNESCAPED_UNICODE)
    ]);

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' .
        APP_URL .
        '/owner/menu.php?updated=1'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'TableBuzz menu toggle error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to update food availability.');
}