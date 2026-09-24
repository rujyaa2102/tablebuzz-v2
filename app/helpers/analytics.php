<?php

function trackAnalyticsEvent(
    int $cafeId,
    string $eventType,
    string $entityType = 'cafe',
    ?int $entityId = null,
    ?int $qrCodeId = null,
    array $metadata = []
): bool {

    global $pdo;

    if ($cafeId <= 0 || $eventType === '') {
        return false;
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO analytics_events
            (
                cafe_id,
                qr_code_id,
                event_type,
                entity_type,
                entity_id,
                session_id,
                metadata,
                created_at
            )
            VALUES
            (
                :cafe_id,
                :qr_code_id,
                :event_type,
                :entity_type,
                :entity_id,
                :session_id,
                :metadata,
                NOW()
            )
        ");

        $stmt->execute([
            ':cafe_id' => $cafeId,
            ':qr_code_id' => $qrCodeId,
            ':event_type' => $eventType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':session_id' => session_id(),
            ':metadata' => !empty($metadata)
                ? json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE
                )
                : null
        ]);

        return true;

    } catch (Throwable $e) {

        error_log(
            'Analytics Error: ' .
            $e->getMessage()
        );

        return false;
    }
}