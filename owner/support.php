<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();

if (!$cafeId) {
    http_response_code(403);
    exit('Cafe not assigned.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $subject = trim($_POST['subject'] ?? '');
        $ticketMessage = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';

        $allowedPriorities = [
            'low',
            'medium',
            'high',
            'urgent'
        ];

        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'medium';
        }

        if ($subject === '' || $ticketMessage === '') {

            $error = 'Subject and message are required.';

        } elseif (mb_strlen($subject) > 255) {

            $error = 'Subject is too long.';

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (
                    cafe_id,
                    cafe_user_id,
                    subject,
                    message,
                    priority,
                    status,
                    created_at,
                    updated_at
                )
                VALUES (
                    :cafe_id,
                    :cafe_user_id,
                    :subject,
                    :message,
                    :priority,
                    'open',
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':cafe_user_id' => $_SESSION['user']['id'] ?? null,
                ':subject' => $subject,
                ':message' => $ticketMessage,
                ':priority' => $priority
            ]);

            $ticketId = (int) $pdo->lastInsertId();

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
                    'ticket_created',
                    'support_ticket',
                    :entity_id,
                    :ip_address,
                    :user_agent,
                    :metadata,
                    NOW()
                )
            " );

            $audit->execute([
                ':cafe_id' => $cafeId,
                ':cafe_user_id' => $_SESSION['user']['id'] ?? null,
                ':entity_id' => $ticketId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'priority' => $priority,
                    'subject' => $subject
                ], JSON_UNESCAPED_UNICODE)
            ]);

            $message = 'Support ticket created successfully.';
        }
}

$stmt = $pdo->prepare("
    SELECT
        id,
        subject,
        message,
        priority,
        status,
        created_at,
        updated_at
    FROM support_tickets
    WHERE cafe_id = :cafe_id
    ORDER BY id DESC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$tickets = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Support - TableBuzz</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #1f2937;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 30px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

h1 {
    margin: 0 0 5px;
}

.subtitle {
    margin: 0;
    color: #6b7280;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn-dark {
    background: #111827;
    color: #fff;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.card {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 220px;
    gap: 18px;
}

.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
}

input,
textarea,
select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-family: inherit;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.ticket {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 15px;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    align-items: flex-start;
}

.ticket-title {
    font-size: 18px;
    font-weight: 700;
}

.ticket-id {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}

.ticket-message {
    margin-top: 14px;
    white-space: pre-wrap;
    line-height: 1.6;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 5px;
}

.priority-low {
    background: #e5e7eb;
    color: #374151;
}

.priority-medium {
    background: #dbeafe;
    color: #1e40af;
}

.priority-high {
    background: #fef3c7;
    color: #92400e;
}

.priority-urgent {
    background: #fee2e2;
    color: #991b1b;
}

.status-open {
    background: #dbeafe;
    color: #1e40af;
}

.status-in_progress {
    background: #fef3c7;
    color: #92400e;
}

.status-resolved {
    background: #dcfce7;
    color: #166534;
}

.status-closed {
    background: #e5e7eb;
    color: #374151;
}

.meta {
    color: #6b7280;
    font-size: 12px;
    margin-top: 12px;
}

.empty {
    text-align: center;
    color: #6b7280;
    padding: 30px;
}

@media (max-width: 700px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .ticket-header {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>🎫 Support</h1>

            <p class="subtitle">
                Create and track your TableBuzz support tickets
            </p>

        </div>

        <a
            href="<?= e(APP_URL) ?>/owner/dashboard.php"
            class="btn btn-dark"
        >
            ← Dashboard
        </a>

    </div>


    <?php if ($message): ?>

        <div class="alert success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- CREATE TICKET -->

    <div class="card">

        <h2>Create Support Ticket</h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <div class="form-grid">

                <div>

                    <label>
                        Subject
                    </label>

                    <input
                        type="text"
                        name="subject"
                        maxlength="255"
                        placeholder="Example: QR code is not working"
                        required
                    >

                </div>


                <div>

                    <label>
                        Priority
                    </label>

                    <select name="priority">

                        <option value="low">
                            Low
                        </option>

                        <option value="medium" selected>
                            Medium
                        </option>

                        <option value="high">
                            High
                        </option>

                        <option value="urgent">
                            Urgent
                        </option>

                    </select>

                </div>


                <div class="full">

                    <label>
                        Message
                    </label>

                    <textarea
                        name="message"
                        placeholder="Describe your issue..."
                        required
                    ></textarea>

                </div>


                <div class="full">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        🎫 Create Ticket
                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- TICKETS -->

    <div class="card">

        <h2>My Tickets</h2>

        <?php if (!$tickets): ?>

            <div class="empty">
                No support tickets found.
            </div>

        <?php else: ?>

            <?php foreach ($tickets as $ticket): ?>

                <div class="ticket">

                    <div class="ticket-header">

                        <div>

                            <div class="ticket-title">

                                <?= e($ticket['subject']) ?>

                            </div>

                            <div class="ticket-id">

                                Ticket #<?= (int) $ticket['id'] ?>

                            </div>

                        </div>


                        <div>

                            <span
                                class="badge priority-<?= e($ticket['priority']) ?>"
                            >
                                <?= e(ucfirst($ticket['priority'])) ?>
                            </span>

                            <span
                                class="badge status-<?= e($ticket['status']) ?>"
                            >
                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $ticket['status']
                                        )
                                    )
                                ) ?>
                            </span>

                        </div>

                    </div>


                    <div class="ticket-message">

                        <?= e($ticket['message']) ?>

                    </div>


                    <div class="meta">

                        Created:
                        <?= e(
                            date(
                                'd M Y, h:i A',
                                strtotime($ticket['created_at'])
                            )
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>

</html>