<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/owner/feedback.php');
}

verifyCsrf();

$user = $_SESSION['user'];
$cafeId = (int)$user['cafe_id'];

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirect(APP_URL . '/owner/feedback.php');
}

$stmt = $pdo->prepare("
    SELECT id, customer_name, message, rating
    FROM feedback
    WHERE id = :id
      AND cafe_id = :cafe_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'cafe_id' => $cafeId
]);

$feedback = $stmt->fetch();

if (!$feedback) {
    redirect(APP_URL . '/owner/feedback.php');
}

try {

    $pdo->beginTransaction();

    $delete = $pdo->prepare("
        DELETE FROM feedback
        WHERE id = :id
          AND cafe_id = :cafe_id
    ");

    $delete->execute([
        'id' => $id,
        'cafe_id' => $cafeId
    ]);

    auditLog(
        'delete_feedback',
        'feedback',
        $id,
        $cafeId,
        null,
        [
            'customer_name' => $feedback['customer_name'],
            'rating' => $feedback['rating']
        ]
    );

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete feedback error: ' . $e->getMessage());
}

redirect(APP_URL . '/owner/feedback.php');