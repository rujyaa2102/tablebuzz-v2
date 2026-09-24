<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$ticketId = (int) ($_GET['id'] ?? 0);

if ($ticketId <= 0) {
    exit('Invalid ticket ID.');
}

$stmt = $pdo->prepare("
    SELECT
        st.*,
        c.name AS cafe_name,
        c.slug AS cafe_slug
    FROM support_tickets st
    INNER JOIN cafes c
        ON c.id = st.cafe_id
    WHERE st.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $ticketId
]);

$ticket = $stmt->fetch();

if (!$ticket) {
    exit('Support ticket not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $priority = $_POST['priority'] ?? '';
        $status = $_POST['status'] ?? '';

        $allowedPriorities = [
            'low',
            'medium',
            'high',
            'urgent'
        ];

        $allowedStatuses = [
            'open',
            'in_progress',
            'resolved',
            'closed'
        ];

        if (!in_array($priority, $allowedPriorities, true)) {

            $error = 'Invalid priority.';

        } elseif (!in_array($status, $allowedStatuses, true)) {

            $error = 'Invalid status.';

        } else {

            $update = $pdo->prepare("
                UPDATE support_tickets
                SET
                    priority = :priority,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                ':priority' => $priority,
                ':status' => $status,
                ':id' => $ticketId
            ]);


            /*
             * Audit Log
             */

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
                    'update',
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
                    'priority' => $priority,
                    'status' => $status
                ])
            ]);


            header(
                'Location: ' .
                APP_URL .
                '/admin/support-tickets.php?updated=1'
            );

            exit;
        }
    }

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Ticket - TableBuzz</title>

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
    max-width: 900px;
    margin: auto;
    padding: 30px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    align-items: center;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

h1 {
    margin: 0;
}

.cafe {
    color: #6b7280;
    margin-top: 5px;
}

.section {
    margin-top: 22px;
}

.label {
    font-weight: 700;
    margin-bottom: 7px;
}

.value {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    white-space: pre-wrap;
    line-height: 1.6;
}

select {
    width: 100%;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 8px;
    border: none;
    text-decoration: none;
    cursor: pointer;
}

.btn-dark {
    background: #111827;
    color: white;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
}

@media(max-width:700px) {

    .grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>🎫 Edit Support Ticket</h1>

            <div class="cafe">
                <?= e($ticket['cafe_name']) ?>
                —
                Ticket #<?= (int) $ticket['id'] ?>
            </div>

        </div>

        <a
            href="<?= e(APP_URL) ?>/admin/support-tickets.php"
            class="btn btn-dark"
        >
            ← Back
        </a>

    </div>


    <div class="card">

        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <div class="section">

            <div class="label">
                Subject
            </div>

            <div class="value">
                <?= e($ticket['subject']) ?>
            </div>

        </div>


        <div class="section">

            <div class="label">
                Message
            </div>

            <div class="value">
                <?= e($ticket['message']) ?>
            </div>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >


            <div class="grid section">

                <div>

                    <div class="label">
                        Priority
                    </div>

                    <select name="priority">

                        <?php

                        $priorities = [
                            'low',
                            'medium',
                            'high',
                            'urgent'
                        ];

                        ?>

                        <?php foreach ($priorities as $priority): ?>

                            <option
                                value="<?= e($priority) ?>"
                                <?= $ticket['priority'] === $priority
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e(ucfirst($priority)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <div class="label">
                        Status
                    </div>

                    <select name="status">

                        <?php

                        $statuses = [
                            'open',
                            'in_progress',
                            'resolved',
                            'closed'
                        ];

                        ?>

                        <?php foreach ($statuses as $status): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $ticket['status'] === $status
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $status
                                        )
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <div class="actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    💾 Save Changes
                </button>

                <a
                    href="<?= e(APP_URL) ?>/admin/support-tickets.php"
                    class="btn btn-dark"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>