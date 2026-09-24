<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

verifyCsrf();

$ticketId = (int) ($_POST['id'] ?? 0);

$status = $_POST['status'] ?? '';

$allowedStatuses = [
    'open',
    'in_progress',
    'resolved',
    'closed'
];

if (
    $ticketId <= 0 ||
    !in_array($status, $allowedStatuses, true)
) {
    exit('Invalid request.');
}


$stmt = $pdo->prepare("
    SELECT cafe_id
    FROM support_tickets
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $ticketId
]);

$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Ticket not found.');
}


$update = $pdo->prepare("
    UPDATE support_tickets
    SET
        status = :status,
        updated_at = NOW()
    WHERE id = :id
");

$update->execute([
    ':status' => $status,
    ':id' => $ticketId
]);


/* Audit */

$audit = $pdo->prepare("
    INSERT INTO audit_logs (
        cafe_id,
        admin_id,
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
        :admin_id,
        'status_change',
        'support_ticket',
        :entity_id,
        :ip_address,
        :user_agent,
        :metadata,
        NOW()
    )
");

$audit->execute([
    ':cafe_id' => $ticket['cafe_id'],
    ':admin_id' => $_SESSION['admin']['id'] ?? null,
    ':entity_id' => $ticketId,
    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ':metadata' => json_encode([
        'status' => $status
    ])
]);


header(
    'Location: ' .
    APP_URL .
    '/admin/support-tickets.php?updated=1'
);

exit;