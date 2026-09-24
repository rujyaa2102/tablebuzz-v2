<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| Active Cafés
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        city,
        status
    FROM cafes
    WHERE status = 'active'
    ORDER BY name ASC
");

$cafes = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Handle Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $cafeId = filter_input(
        INPUT_POST,
        'cafe_id',
        FILTER_VALIDATE_INT
    );

    $name = cleanInput(
        $_POST['name'] ?? ''
    );

    $email = strtolower(
        cleanInput(
            $_POST['email'] ?? ''
        )
    );

    $password = $_POST['password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (!$cafeId || $cafeId <= 0) {
        $errors[] = 'Please select a café.';
    }

    if ($name === '') {
        $errors[] = 'Owner name is required.';
    }

    if (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] =
            'Password must be at least 8 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Café
    |--------------------------------------------------------------------------
    */

    $cafe = null;

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                status
            FROM cafes
            WHERE id = :cafe_id
            LIMIT 1
        ");

        $stmt->execute([
            ':cafe_id' => $cafeId
        ]);

        $cafe = $stmt->fetch();

        if (!$cafe) {
            $errors[] = 'Selected café does not exist.';
        } elseif ($cafe['status'] !== 'active') {
            $errors[] =
                'Owner can only be created for an active café.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Existing Email
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM cafe_users
            WHERE cafe_id = :cafe_id
              AND email = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':cafe_id' => $cafeId,
            ':email' => $email
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'This email is already registered for this café.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Owner
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $passwordHash = hashPassword(
                $password
            );

            $stmt = $pdo->prepare("
                INSERT INTO cafe_users
                (
                    cafe_id,
                    name,
                    email,
                    password,
                    role,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :cafe_id,
                    :name,
                    :email,
                    :password,
                    'owner',
                    'active',
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':name' => $name,
                ':email' => $email,
                ':password' => $passwordHash
            ]);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $admin = currentAdmin();

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (
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
                VALUES
                (
                    :cafe_id,
                    :admin_id,
                    'owner_created',
                    'cafe_user',
                    :entity_id,
                    :ip_address,
                    :user_agent,
                    :metadata,
                    NOW()
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':admin_id' => $admin['id'] ?? null,
                ':entity_id' => $pdo->lastInsertId(),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' =>
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'owner_name' => $name,
                    'owner_email' => $email
                ], JSON_UNESCAPED_UNICODE)
            ]);

            header(
                'Location: ' .
                APP_URL .
                '/admin/owners.php?created=1'
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                'Add Owner Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to create owner. Please try again.';
        }
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

    <title>
        Add Owner - TableBuzz
    </title>

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
            max-width: 700px;
            margin: auto;
            padding: 35px 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        h1 {
            margin-top: 0;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            padding: 11px 18px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #111827;
            color: white;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>
            Add Café Owner
        </h1>

        <p class="subtitle">
            Create a new owner account for a café.
        </p>


        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            autocomplete="off"
        >

            <?= csrfField() ?>


            <div class="form-group">

                <label>
                    Café
                </label>

                <select
                    name="cafe_id"
                    required
                >

                    <option value="">
                        Select Café
                    </option>

                    <?php foreach ($cafes as $cafe): ?>

                        <option
                            value="<?= (int) $cafe['id'] ?>"
                            <?= (
                                isset($_POST['cafe_id']) &&
                                (int) $_POST['cafe_id']
                                === (int) $cafe['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($cafe['name']) ?>

                            <?php if (!empty($cafe['city'])): ?>

                                -
                                <?= e($cafe['city']) ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Owner Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e($_POST['name'] ?? '') ?>"
                    placeholder="Enter owner name"
                    maxlength="150"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    placeholder="owner@example.com"
                    maxlength="190"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 8 characters"
                    minlength="8"
                    required
                >

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Create Owner
                </button>

                <a
                    href="<?= e(APP_URL) ?>/admin/owners.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>