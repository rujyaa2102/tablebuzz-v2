<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| Get All Café Owners
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        cu.id,
        cu.cafe_id,
        cu.name,
        cu.email,
        cu.role,
        cu.status,
        cu.created_at,
        c.name AS cafe_name,
        c.slug AS cafe_slug,
        c.status AS cafe_status
    FROM cafe_users cu
    INNER JOIN cafes c
        ON c.id = cu.cafe_id
    WHERE cu.role = 'owner'
    ORDER BY cu.id DESC
");

$owners = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Café Owners - TableBuzz Admin
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f4f6f9;
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
            gap: 20px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 6px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            border: none;
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

        .btn-view {
            background: #0f766e;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-toggle {
            background: #6b7280;
            color: white;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow:
                0 4px 15px rgba(0, 0, 0, .05);
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .owner-name {
            font-weight: 600;
        }

        .owner-email {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .cafe-status {
            font-size: 12px;
            color: #6b7280;
        }

        .action-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty {
            padding: 40px;
            text-align: center;
            color: #6b7280;
        }

        @media (max-width: 700px) {

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- Header -->

    <div class="header">

        <div>

            <h1>
                Café Owners
            </h1>

            <p>
                Manage all café owner accounts
            </p>

        </div>

        <div class="actions">

            <a
                href="<?= e(APP_URL) ?>/admin/dashboard.php"
                class="btn btn-dark"
            >
                ← Dashboard
            </a>

            <a
                href="<?= e(APP_URL) ?>/admin/add-owner.php"
                class="btn btn-primary"
            >
                + Add Owner
            </a>

        </div>

    </div>


    <!-- Owners -->

    <div class="table-card">

        <?php if (empty($owners)): ?>

            <div class="empty">

                No café owners found.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Owner
                        </th>

                        <th>
                            Café
                        </th>

                        <th>
                            Owner Status
                        </th>

                        <th>
                            Café Status
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($owners as $owner): ?>

                    <tr>

                        <!-- ID -->

                        <td>

                            <?= (int) $owner['id'] ?>

                        </td>


                        <!-- Owner -->

                        <td>

                            <div class="owner-name">

                                <?= e(
                                    $owner['name']
                                ) ?>

                            </div>

                            <div class="owner-email">

                                <?= e(
                                    $owner['email']
                                ) ?>

                            </div>

                        </td>


                        <!-- Café -->

                        <td>

                            <strong>

                                <?= e(
                                    $owner['cafe_name']
                                ) ?>

                            </strong>

                            <br>

                            <small>

                                /<?= e(
                                    $owner['cafe_slug']
                                ) ?>

                            </small>

                        </td>


                        <!-- Owner Status -->

                        <td>

                            <?php if (
                                $owner['status'] === 'active'
                            ): ?>

                                <span
                                    class="status active"
                                >
                                    ACTIVE
                                </span>

                            <?php else: ?>

                                <span
                                    class="status inactive"
                                >
                                    INACTIVE
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Café Status -->

                        <td>

                            <?php if (
                                $owner['cafe_status']
                                === 'active'
                            ): ?>

                                <span
                                    class="status active"
                                >
                                    ACTIVE
                                </span>

                            <?php else: ?>

                                <span
                                    class="status inactive"
                                >
                                    INACTIVE
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Created -->

                        <td>

                            <?= e(
                                $owner['created_at']
                            ) ?>

                        </td>


                        <!-- Actions -->

                        <td>

                            <div class="action-links">

    <a
        href="<?= e(APP_URL) ?>/admin/edit-owner.php?id=<?= (int) $owner['id'] ?>"
        class="btn btn-edit"
    >
        Edit
    </a>

    <a
        href="<?= e(APP_URL) ?>/admin/toggle-owner.php?id=<?= (int) $owner['id'] ?>"
        class="btn btn-toggle"
    >
        Toggle
    </a>

    <a
        href="<?= e(APP_URL) ?>/admin/delete-owner.php?id=<?= (int) $owner['id'] ?>"
        class="btn"
        style="background:#dc2626;color:white;"
    >
        Delete
    </a>

</div>

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