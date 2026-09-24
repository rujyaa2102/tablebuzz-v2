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
    exit('Invalid offer ID.');
}

try {

    /*
    |--------------------------------------------------------------------------
    | Find Offer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            coupon_code
        FROM offers
        WHERE id = :id
          AND cafe_id = :cafe_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id,
        ':cafe_id' => $cafeId
    ]);

    $offer = $stmt->fetch();

    if (!$offer) {
        http_response_code(404);
        exit('Offer not found.');
    }

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    $delete = $pdo->prepare("
        DELETE FROM offers
        WHERE id = :id
          AND cafe_id = :cafe_id
    ");

    $delete->execute([
        ':id' => $id,
        ':cafe_id' => $cafeId
    ]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException(
            'Offer deletion failed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
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
            'delete',
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
        ':entity_id' => $id,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':metadata' => json_encode([
            'title' => $offer['title'],
            'coupon_code' => $offer['coupon_code']
        ], JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    header(
        'Location: ' .
        APP_URL .
        '/owner/offers.php?deleted=1'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'TableBuzz delete offer error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to delete offer.');
}