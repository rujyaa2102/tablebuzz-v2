<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $name = cleanInput($_POST['name'] ?? '');

        if ($name === '') {

            $error = 'Category name is required.';

        } else {

            $stmt = $pdo->prepare("
                SELECT id
                FROM menu_categories
                WHERE cafe_id = :cafe_id
                  AND LOWER(name) = LOWER(:name)
                LIMIT 1
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':name' => $name
            ]);

            if ($stmt->fetch()) {

                $error = 'Category already exists.';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO menu_categories
                    (
                        cafe_id,
                        name,
                        sort_order,
                        status
                    )
                    VALUES
                    (
                        :cafe_id,
                        :name,
                        0,
                        'active'
                    )
                ");

                $stmt->execute([
                    ':cafe_id' => $cafeId,
                    ':name' => $name
                ]);

                $categoryId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs
                    (
                        cafe_id,
                        cafe_user_id,
                        action,
                        entity_type,
                        entity_id,
                        ip_address,
                        user_agent
                    )
                    VALUES
                    (
                        :cafe_id,
                        :cafe_user_id,
                        'create',
                        'menu_category',
                        :entity_id,
                        :ip_address,
                        :user_agent
                    )
                ");

                $stmt->execute([
                    ':cafe_id' => $cafeId,
                    ':cafe_user_id' => currentUserId(),
                    ':entity_id' => $categoryId,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                $success = 'Category added successfully.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE CATEGORY
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'update') {

        $categoryId = (int) ($_POST['category_id'] ?? 0);

        $name = cleanInput($_POST['name'] ?? '');

        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } elseif ($name === '') {

            $error = 'Category name is required.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Verify Ownership
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM menu_categories
                WHERE id = :id
                  AND cafe_id = :cafe_id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $categoryId,
                ':cafe_id' => $cafeId
            ]);

            if (!$stmt->fetch()) {

                $error = 'Category not found.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Duplicate Name Check
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM menu_categories
                    WHERE cafe_id = :cafe_id
                      AND LOWER(name) = LOWER(:name)
                      AND id != :id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':cafe_id' => $cafeId,
                    ':name' => $name,
                    ':id' => $categoryId
                ]);

                if ($stmt->fetch()) {

                    $error = 'Another category with this name already exists.';

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE menu_categories
                        SET
                            name = :name,
                            sort_order = :sort_order
                        WHERE id = :id
                          AND cafe_id = :cafe_id
                    ");

                    $stmt->execute([
                        ':name' => $name,
                        ':sort_order' => $sortOrder,
                        ':id' => $categoryId,
                        ':cafe_id' => $cafeId
                    ]);

                    $stmt = $pdo->prepare("
                        INSERT INTO audit_logs
                        (
                            cafe_id,
                            cafe_user_id,
                            action,
                            entity_type,
                            entity_id,
                            ip_address,
                            user_agent
                        )
                        VALUES
                        (
                            :cafe_id,
                            :cafe_user_id,
                            'update',
                            'menu_category',
                            :entity_id,
                            :ip_address,
                            :user_agent
                        )
                    ");

                    $stmt->execute([
                        ':cafe_id' => $cafeId,
                        ':cafe_user_id' => currentUserId(),
                        ':entity_id' => $categoryId,
                        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    $success = 'Category updated successfully.';
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'toggle') {

        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } else {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    status
                FROM menu_categories
                WHERE id = :id
                  AND cafe_id = :cafe_id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $categoryId,
                ':cafe_id' => $cafeId
            ]);

            $category = $stmt->fetch();

            if (!$category) {

                $error = 'Category not found.';

            } else {

                $newStatus =
                    $category['status'] === 'active'
                        ? 'inactive'
                        : 'active';

                $stmt = $pdo->prepare("
                    UPDATE menu_categories
                    SET status = :status
                    WHERE id = :id
                      AND cafe_id = :cafe_id
                ");

                $stmt->execute([
                    ':status' => $newStatus,
                    ':id' => $categoryId,
                    ':cafe_id' => $cafeId
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs
                    (
                        cafe_id,
                        cafe_user_id,
                        action,
                        entity_type,
                        entity_id,
                        ip_address,
                        user_agent
                    )
                    VALUES
                    (
                        :cafe_id,
                        :cafe_user_id,
                        'toggle_status',
                        'menu_category',
                        :entity_id,
                        :ip_address,
                        :user_agent
                    )
                ");

                $stmt->execute([
                    ':cafe_id' => $cafeId,
                    ':cafe_user_id' => currentUserId(),
                    ':entity_id' => $categoryId,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                $success =
                    $newStatus === 'active'
                        ? 'Category activated successfully.'
                        : 'Category deactivated successfully.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        sort_order,
        status,
        created_at
    FROM menu_categories
    WHERE cafe_id = :cafe_id
    ORDER BY sort_order ASC, id DESC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$categories = $stmt->fetchAll();

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
        Menu Categories - TableBuzz
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        h1 {
            margin-top: 0;
        }

        h2 {
            margin-top: 25px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
        }

        .message {
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .add-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        input {
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }

        input[name="name"] {
            min-width: 220px;
        }

        button {
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #111827;
            color: #ffffff;
        }

        .btn-success {
            background: #16a34a;
            color: #ffffff;
        }

        .btn-danger {
            background: #dc2626;
            color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f3f4f6;
        }

        .edit-form {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .sort-input {
            width: 80px;
        }

        .active {
            color: #15803d;
            font-weight: bold;
        }

        .inactive {
            color: #b91c1c;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 6px;
        }

        @media (max-width: 700px) {

            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead {
                display: none;
            }

            tr {
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 15px;
                padding: 10px;
            }

            td {
                border: 0;
                padding: 8px;
            }

            .edit-form {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h1>
        Menu Categories
    </h1>

    <a
        class="back-link"
        href="<?= e(APP_URL) ?>/owner/dashboard.php"
    >
        ← Dashboard
    </a>


    <?php if ($error): ?>

        <div class="message error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="message success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>


    <h2>
        Add Category
    </h2>

    <form
        method="POST"
        class="add-form"
    >

        <?= csrfField() ?>

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <input
            type="text"
            name="name"
            placeholder="e.g. South Indian"
            maxlength="100"
            required
        >

        <button
            type="submit"
            class="btn-primary"
        >
            Add Category
        </button>

    </form>


    <hr>


    <h2>
        Existing Categories
    </h2>


    <?php if (!$categories): ?>

        <p>
            No categories found.
        </p>

    <?php else: ?>

        <table>

            <thead>

            <tr>

                <th>
                    ID
                </th>

                <th>
                    Category
                </th>

                <th>
                    Sort Order
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

            <?php foreach ($categories as $category): ?>

                <tr>

                    <td>
                        <?= (int) $category['id'] ?>
                    </td>


                    <td>

                        <form
                            method="POST"
                            class="edit-form"
                        >

                            <?= csrfField() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="update"
                            >

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?= (int) $category['id'] ?>"
                            >

                            <input
                                type="text"
                                name="name"
                                value="<?= e($category['name']) ?>"
                                maxlength="100"
                                required
                            >

                            <input
                                class="sort-input"
                                type="number"
                                name="sort_order"
                                value="<?= (int) $category['sort_order'] ?>"
                                min="0"
                                max="9999"
                            >

                            <button
                                type="submit"
                                class="btn-primary"
                            >
                                Save
                            </button>

                        </form>

                    </td>


                    <td>
                        <?= (int) $category['sort_order'] ?>
                    </td>


                    <td>

                        <?php if ($category['status'] === 'active'): ?>

                            <span class="active">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="inactive">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <div class="actions">

                            <form method="POST">

                                <?= csrfField() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="toggle"
                                >

                                <input
                                    type="hidden"
                                    name="category_id"
                                    value="<?= (int) $category['id'] ?>"
                                >

                                <?php if ($category['status'] === 'active'): ?>

                                    <button
                                        type="submit"
                                        class="btn-danger"
                                    >
                                        Deactivate
                                    </button>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="btn-success"
                                    >
                                        Activate
                                    </button>

                                <?php endif; ?>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

</body>

</html>