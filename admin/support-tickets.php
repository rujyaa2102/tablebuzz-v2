<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        st.id,
        st.subject,
        st.message,
        st.priority,
        st.status,
        st.created_at,
        st.updated_at,

        c.id AS cafe_id,
        c.name AS cafe_name,
        c.slug AS cafe_slug,

        cu.name AS user_name,
        cu.email AS user_email

    FROM support_tickets st

    INNER JOIN cafes c
        ON c.id = st.cafe_id

    LEFT JOIN cafe_users cu
        ON cu.id = st.cafe_user_id

    ORDER BY
        CASE st.status
            WHEN 'open' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'closed' THEN 4
            ELSE 5
        END,
        st.id DESC
");

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

<title>Support Tickets - TableBuzz Admin</title>

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
    max-width: 1400px;
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
    padding: 9px 13px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    border: none;
    cursor: pointer;
}

.btn-dark {
    background: #111827;
    color: white;
}

.btn-edit {
    background: #2563eb;
    color: white;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th,
td {
    padding: 13px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f9fafb;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
}

td {
    font-size: 14px;
}

.subject {
    font-weight: 700;
}

.message {
    max-width: 300px;
    color: #4b5563;
    white-space: pre-wrap;
}

.cafe {
    font-weight: 600;
}

.small {
    color: #6b7280;
    font-size: 12px;
    margin-top: 3px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
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

.empty {
    text-align: center;
    padding: 50px;
    color: #6b7280;
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <h1>🎫 Support Tickets</h1>

            <p class="subtitle">
                Manage café support requests
            </p>

        </div>

        <a
            href="<?= e(APP_URL) ?>/admin/dashboard.php"
            class="btn btn-dark"
        >
            ← Dashboard
        </a>

    </div>


    <div class="card">

        <?php if (!$tickets): ?>

            <div class="empty">
                No support tickets found.
            </div>

        <?php else: ?>

            <table>

                <thead>

                <tr>

                    <th>ID</th>

                    <th>Café</th>

                    <th>Subject</th>

                    <th>Message</th>

                    <th>Priority</th>

                    <th>Status</th>

                    <th>Created</th>

                    <th>Action</th>

                </tr>

                </thead>

                <tbody>

                <?php foreach ($tickets as $ticket): ?>

                    <tr>

                        <td>
                            #<?= (int) $ticket['id'] ?>
                        </td>


                        <td>

                            <div class="cafe">
                                <?= e($ticket['cafe_name']) ?>
                            </div>

                            <div class="small">
                                <?= e($ticket['cafe_slug']) ?>
                            </div>

                        </td>


                        <td>

                            <div class="subject">
                                <?= e($ticket['subject']) ?>
                            </div>

                            <?php if ($ticket['user_name']): ?>

                                <div class="small">
                                    By:
                                    <?= e($ticket['user_name']) ?>
                                </div>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="message">
                                <?= e($ticket['message']) ?>
                            </div>

                        </td>


                        <td>

                            <span
                                class="badge priority-<?= e($ticket['priority']) ?>"
                            >
                                <?= e(ucfirst($ticket['priority'])) ?>
                            </span>

                        </td>


                        <td>

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

                        </td>


                        <td>

                            <?= e(
                                date(
                                    'd M Y, h:i A',
                                    strtotime($ticket['created_at'])
                                )
                            ) ?>

                        </td>


                        <td>

                            <a
                                href="<?= e(APP_URL) ?>/admin/edit-ticket.php?id=<?= (int) $ticket['id'] ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>

</html>