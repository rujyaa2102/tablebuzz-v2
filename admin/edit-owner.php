<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$ownerId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$ownerId || $ownerId <= 0) {
    http_response_code(400);
    exit('Invalid owner ID.');
}

/*
|--------------------------------------------------------------------------
| Get Owner
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cu.id,
        cu.cafe_id,
        cu.name,
        cu.email,
        cu.status,
        c.name AS cafe_name
    FROM cafe_users cu
    INNER JOIN cafes c
        ON c.id = cu.cafe_id
    WHERE cu.id = :owner_id
      AND cu.role = 'owner'
    LIMIT 1
");

$stmt->execute([
    ':owner_id' => $ownerId
]);

$owner = $stmt->fetch();

if (!$owner) {
    http_response_code(404);
    exit('Owner not found.');
}

$errors = [];

/*
|--------------------------------------------------------------------------
| Update Owner
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

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

    if ($name === '') {
        $errors[] = 'Owner name is required.';
    }

    if (!isValidEmail($email)) {
        $errors[] =
            'Please enter a valid email address.';
    }

    if (
        $password !== '' &&
        strlen($password) < 8
    ) {
        $errors[] =
            'New password must be at least 8 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Email Duplicate Check
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM cafe_users
            WHERE cafe_id = :cafe_id
              AND email = :email
              AND id != :owner_id
            LIMIT 1
        ");

        $stmt->execute([
            ':cafe_id' => $owner['cafe_id'],
            ':email' => $email,
            ':owner_id' => $ownerId
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'Another owner already uses this email for this café.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            if ($password !== '') {

                $passwordHash = hashPassword(
                    $password
                );

                $stmt = $pdo->prepare("
                    UPDATE cafe_users
                    SET
                        name = :name,
                        email = :email,
                        password = :password,
                        updated_at = NOW()
                    WHERE id = :owner_id
                      AND role = 'owner'
                ");

                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $passwordHash,
                    ':owner_id' => $ownerId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE cafe_users
                    SET
                        name = :name,
                        email = :email,
                        updated_at = NOW()
                    WHERE id = :owner_id
                      AND role = 'owner'
                ");

                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':owner_id' => $ownerId
                ]);
            }


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
                    'owner_updated',
                    'cafe_user',
                    :entity_id,
                    :ip_address,
                    :user_agent,
                    :metadata,
                    NOW()
                )
            ");

            $stmt->execute([
                ':cafe_id' => $owner['cafe_id'],
                ':admin_id' => $admin['id'] ?? null,
                ':entity_id' => $ownerId,
                ':ip_address' =>
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' =>
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'owner_name' => $name,
                    'owner_email' => $email,
                    'password_changed' =>
                        $password !== ''
                ], JSON_UNESCAPED_UNICODE)
            ]);


            header(
                'Location: ' .
                APP_URL .
                '/admin/owners.php?updated=1'
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                'Edit Owner Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to update owner.';
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
        Edit Owner - TableBuzz
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

        .info {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 22px;
            color: #4b5563;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .help {
            color: #6b7280;
            font-size: 12px;
            margin-top: 6px;
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
            border-radius: 8px;
            border: none;
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
            Edit Café Owner
        </h1>

        <div class="info">

            Café:
            <strong>
                <?= e($owner['cafe_name']) ?>
            </strong>

        </div>


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
                    Owner Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e(
                        $_POST['name']
                        ?? $owner['name']
                    ) ?>"
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
                    value="<?= e(
                        $_POST['email']
                        ?? $owner['email']
                    ) ?>"
                    maxlength="190"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    minlength="8"
                    placeholder="Leave blank to keep current password"
                >

                <div class="help">
                    Leave this field empty if you don't want
                    to change the password.
                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Changes
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