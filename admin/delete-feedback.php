<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/admin/feedback.php');
}

verifyCsrf();

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    redirect(APP_URL . '/admin/feedback.php');
}

$stmt = $pdo->prepare("
    SELECT
        id,
        cafe_id,
        customer_name,
        rating
    FROM feedback
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$feedback = $stmt->fetch();

if (!$feedback) {
    redirect(APP_URL . '/admin/feedback.php');
}

try {

    $pdo->beginTransaction();

    $delete = $pdo->prepare("
        DELETE FROM feedback
        WHERE id = :id
    ");

    $delete->execute([
        'id' => $id
    ]);

    auditLog(
        'admin_delete_feedback',
        'feedback',
        $id,
        (int)$feedback['cafe_id'],
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

    error_log(
        'Admin delete feedback error: ' .
        $e->getMessage()
    );
}

redirect(APP_URL . '/admin/feedback.php');