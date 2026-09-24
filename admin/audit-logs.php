<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$pageTitle = 'Audit Logs';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$action = trim($_GET['action'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');

/*
|--------------------------------------------------------------------------
| Fetch Audit Logs
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        al.*,
        a.name AS admin_name,
        cu.name AS owner_name,
        c.name AS cafe_name
    FROM audit_logs al
    LEFT JOIN admins a
        ON a.id = al.admin_id
    LEFT JOIN cafe_users cu
        ON cu.id = al.cafe_user_id
    LEFT JOIN cafes c
        ON c.id = al.cafe_id
    WHERE 1 = 1
";

$params = [];

/*
|--------------------------------------------------------------------------
| Action Filter
|--------------------------------------------------------------------------
*/

if ($action !== '') {

    $sql .= " AND al.action LIKE :action";

    $params[':action'] = '%' . $action . '%';
}

/*
|--------------------------------------------------------------------------
| Entity Type Filter
|--------------------------------------------------------------------------
*/

if ($entityType !== '') {

    $sql .= " AND al.entity_type = :entity_type";

    $params[':entity_type'] = $entityType;
}

/*
|--------------------------------------------------------------------------
| Order + Limit
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY al.id DESC
    LIMIT 200
";

/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$logs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Entity Types
|--------------------------------------------------------------------------
*/

$entityStmt = $pdo->query("
    SELECT DISTINCT entity_type
    FROM audit_logs
    WHERE entity_type IS NOT NULL
      AND entity_type != ''
    ORDER BY entity_type ASC
");

$entityTypes = $entityStmt->fetchAll(PDO::FETCH_COLUMN);

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
        <?= e($pageTitle) ?> - TableBuzz
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        /* Header */

        .header {
            background: #111827;
            color: white;
            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        .header h2 {
            margin: 0;
            font-size: 21px;
        }

        .header-links {
            display: flex;
            gap: 18px;
        }

        .header a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .header a:hover {
            text-decoration: underline;
        }

        /* Container */

        .container {
            padding: 30px;
            max-width: 1600px;
            margin: auto;
        }

        /* Cards */

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 18px;
        }

        /* Filters */

        .filters {
            display: grid;

            grid-template-columns:
                minmax(200px, 1fr)
                250px
                100px;

            gap: 12px;
        }

        input,
        select,
        button {

            width: 100%;

            padding: 11px 13px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;

            outline: none;
        }

        input:focus,
        select:focus {
            border-color: #111827;
        }

        button {

            background: #111827;

            color: white;

            border: none;

            cursor: pointer;

            font-weight: 600;
        }

        button:hover {
            background: #1f2937;
        }

        /* Table */

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;
        }

        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            font-size: 13px;

            vertical-align: top;
        }

        th {

            background: #f8fafc;

            color: #374151;

            font-weight: 600;

            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        /* Badge */

        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            background: #eef2ff;

            color: #3730a3;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;
        }

        /* Metadata */

        .json {

            max-width: 350px;

            max-height: 150px;

            overflow: auto;

            white-space: pre-wrap;

            word-break: break-word;

            background: #f8fafc;

            border-radius: 6px;

            padding: 8px;

            font-size: 11px;

            color: #374151;
        }

        /* Muted */

        .muted {
            color: #6b7280;
        }

        /* Empty */

        .empty {

            text-align: center;

            padding: 35px;

            color: #6b7280;
        }

        /* Responsive */

        @media (max-width: 700px) {

            .header {

                padding: 15px;

                flex-direction: column;

                align-items: flex-start;
            }

            .container {
                padding: 15px;
            }

            .filters {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <h2>
        🧾 TableBuzz Audit Logs
    </h2>

    <div class="header-links">

        <a
            href="<?= e(APP_URL) ?>/admin/dashboard.php"
        >
            Dashboard
        </a>

        <a
            href="<?= e(APP_URL) ?>/admin/cafes.php"
        >
            Cafés
        </a>

        <a
            href="<?= e(APP_URL) ?>/admin/logout.php"
        >
            Logout
        </a>

    </div>

</div>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <!-- =====================================================
         FILTER CARD
    ====================================================== -->

    <div class="card">

        <h3>
            🔍 Filter Audit Logs
        </h3>

        <form
            method="GET"
            class="filters"
        >

            <!-- Action -->

            <input
                type="text"
                name="action"
                placeholder="Search action..."
                value="<?= e($action) ?>"
            >


            <!-- Entity -->

            <select name="entity_type">

                <option value="">
                    All Entity Types
                </option>

                <?php foreach ($entityTypes as $type): ?>

                    <option
                        value="<?= e($type) ?>"
                        <?= $entityType === $type
                            ? 'selected'
                            : '' ?>
                    >

                        <?= e(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $type
                                )
                            )
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <!-- Button -->

            <button type="submit">

                Filter

            </button>

        </form>

    </div>


    <!-- =====================================================
         LOG TABLE
    ====================================================== -->

    <div class="card">

        <h3>
            📋 Recent Activity
        </h3>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Actor
                        </th>

                        <th>
                            Café
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Entity
                        </th>

                        <th>
                            Entity ID
                        </th>

                        <th>
                            IP Address
                        </th>

                        <th>
                            Metadata
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!$logs): ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty"
                        >

                            No audit logs found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($logs as $log): ?>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Actor
                        |--------------------------------------------------------------------------
                        */

                        if (!empty($log['admin_name'])) {

                            $actor =
                                'Admin: ' .
                                $log['admin_name'];

                        } elseif (
                            !empty($log['owner_name'])
                        ) {

                            $actor =
                                'Owner: ' .
                                $log['owner_name'];

                        } else {

                            $actor = 'System';

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Metadata
                        |--------------------------------------------------------------------------
                        */

                        $metadata =
                            $log['metadata'] ?? '';

                        if ($metadata !== '') {

                            $decoded =
                                json_decode(
                                    $metadata,
                                    true
                                );

                            if (
                                json_last_error()
                                === JSON_ERROR_NONE
                            ) {

                                $metadata =
                                    json_encode(
                                        $decoded,
                                        JSON_PRETTY_PRINT |
                                        JSON_UNESCAPED_SLASHES
                                    );
                            }
                        }

                        ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                #<?= (int) $log['id'] ?>

                            </td>


                            <!-- Time -->

                            <td>

                                <?= e(
                                    $log['created_at']
                                ) ?>

                            </td>


                            <!-- Actor -->

                            <td>

                                <?= e($actor) ?>

                            </td>


                            <!-- Cafe -->

                            <td>

                                <?= e(
                                    $log['cafe_name']
                                    ?? '-'
                                ) ?>

                            </td>


                            <!-- Action -->

                            <td>

                                <span class="badge">

                                    <?= e(
                                        $log['action']
                                    ) ?>

                                </span>

                            </td>


                            <!-- Entity -->

                            <td>

                                <?= e(
                                    $log['entity_type']
                                    ?? '-'
                                ) ?>

                            </td>


                            <!-- Entity ID -->

                            <td>

                                <?php if (
                                    $log['entity_id']
                                    !== null
                                ): ?>

                                    <?= (int)
                                        $log['entity_id'] ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- IP -->

                            <td>

                                <?= e(
                                    $log['ip_address']
                                    ?? '-'
                                ) ?>

                            </td>


                            <!-- Metadata -->

                            <td>

                                <div class="json">

                                    <?= e(
                                        $metadata ?: '-'
                                    ) ?>

                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>