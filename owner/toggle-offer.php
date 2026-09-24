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
            is_active
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

    $oldStatus = (int) $offer['is_active'];
    $newStatus = $oldStatus === 1 ? 0 : 1;

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE offers
        SET
            is_active = :status,
            updated_at = NOW()
        WHERE id = :id
          AND cafe_id = :cafe_id
    ");

    $update->execute([
        ':status' => $newStatus,
        ':id' => $id,
        ':cafe_id' => $cafeId
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException(
            'Offer status update failed.'
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
            'toggle_status',
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
            'old_is_active' => $oldStatus,
            'new_is_active' => $newStatus
        ], JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    header(
        'Location: ' .
        APP_URL .
        '/owner/offers.php?toggled=1'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'TableBuzz toggle offer error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to update offer status.');
}