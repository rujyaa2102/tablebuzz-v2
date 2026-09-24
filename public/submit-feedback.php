<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/');
}

verifyCsrf();

$slug = trim($_POST['slug'] ?? '');
$customerName = trim($_POST['customer_name'] ?? '');
$message = trim($_POST['message'] ?? '');
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);

if ($slug === '') {
    exit('Invalid cafe.');
}

if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
    exit('Please select a valid rating.');
}

if (mb_strlen($customerName) > 100) {
    exit('Name is too long.');
}

if ($message === '' || mb_strlen($message) < 3) {
    exit('Please enter your feedback.');
}

if (mb_strlen($message) > 2000) {
    exit('Feedback is too long.');
}

$stmt = $pdo->prepare("
    SELECT id, name, slug, status
    FROM cafes
    WHERE slug = :slug
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    'slug' => $slug
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    exit('Cafe not found.');
}

$cafeId = (int)$cafe['id'];

try {

    $pdo->beginTransaction();

    $insert = $pdo->prepare("
        INSERT INTO feedback
        (
            cafe_id,
            customer_name,
            message,
            rating,
            created_at
        )
        VALUES
        (
            :cafe_id,
            :customer_name,
            :message,
            :rating,
            NOW()
        )
    ");

    $insert->execute([
        'cafe_id' => $cafeId,
        'customer_name' => $customerName !== ''
            ? $customerName
            : 'Anonymous',
        'message' => $message,
        'rating' => $rating
    ]);

    $feedbackId = (int)$pdo->lastInsertId();

    trackAnalyticsEvent(
        $cafeId,
        'feedback_submitted',
        'feedback',
        $feedbackId,
        null,
        [
            'rating' => $rating
        ]
    );

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Feedback submit error: ' . $e->getMessage());

    exit('Unable to submit feedback.');
}

redirect(
    APP_URL . '/public/cafe.php?slug=' .
    urlencode($slug) .
    '&feedback=success'
);