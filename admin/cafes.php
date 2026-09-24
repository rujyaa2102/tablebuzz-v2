<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.slug,
        c.city,
        c.phone,
        c.status,
        c.created_at,
        COUNT(cu.id) AS owner_count
    FROM cafes c
    LEFT JOIN cafe_users cu
        ON cu.cafe_id = c.id
        AND cu.role = 'owner'
    GROUP BY c.id
    ORDER BY c.id DESC
");

$cafes = $stmt->fetchAll();

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
        Cafés - TableBuzz Admin
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
            gap: 15px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
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
                0 4px 15px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
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

        .action-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty {
            padding: 30px;
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
                Café Management
            </h1>

            <p>
                Manage all TableBuzz cafés
            </p>

        </div>

        <div class="actions">

            <a
                href="dashboard.php"
                class="btn btn-dark"
            >
                ← Dashboard
            </a>

            <a
                href="add-cafe.php"
                class="btn btn-primary"
            >
                + Add New Café
            </a>

        </div>

    </div>


    <!-- Café Table -->

    <div class="table-card">

        <?php if (empty($cafes)): ?>

            <div class="empty">

                No cafés found.

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Café
                        </th>

                        <th>
                            City
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Owners
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($cafes as $cafe): ?>

                    <tr>

                        <!-- ID -->

                        <td>

                            <?= (int) $cafe['id'] ?>

                        </td>


                        <!-- Café -->

                        <td>

                            <strong>
                                <?= e($cafe['name']) ?>
                            </strong>

                            <br>

                            <small>
                                /<?= e($cafe['slug']) ?>
                            </small>

                        </td>


                        <!-- City -->

                        <td>

                            <?= e(
                                $cafe['city'] ?: '-'
                            ) ?>

                        </td>


                        <!-- Phone -->

                        <td>

                            <?= e(
                                $cafe['phone'] ?: '-'
                            ) ?>

                        </td>


                        <!-- Owner Count -->

                        <td>

                            <?= (int) $cafe['owner_count'] ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <?php if (
                                $cafe['status'] === 'active'
                            ): ?>

                                <span class="status active">
                                    ACTIVE
                                </span>

                            <?php else: ?>

                                <span class="status inactive">
                                    INACTIVE
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Actions -->

                        <td>

                            <div class="action-links">

                                <!-- View Details -->

                                <a
                                    href="<?= e(APP_URL) ?>/admin/cafe-details.php?id=<?= (int) $cafe['id'] ?>"
                                    class="btn btn-view"
                                >
                                    View
                                </a>


                                <!-- Edit -->

                                <a
                                    href="<?= e(APP_URL) ?>/admin/edit-cafe.php?id=<?= (int) $cafe['id'] ?>"
                                    class="btn btn-edit"
                                >
                                    Edit
                                </a>


                                <!-- Toggle -->

                                <a
                                    href="<?= e(APP_URL) ?>/admin/toggle-cafe.php?id=<?= (int) $cafe['id'] ?>"
                                    class="btn btn-toggle"
                                    onclick="return confirm('Are you sure you want to change this café status?');"
                                >
                                    Toggle
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