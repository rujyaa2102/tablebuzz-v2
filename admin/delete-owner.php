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

/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

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
            Delete Owner
        </title>

        <style>

            body {
                margin: 0;
                background: #f4f6f9;
                font-family: Arial, sans-serif;
            }

            .container {
                max-width: 500px;
                margin: 80px auto;
                padding: 20px;
            }

            .card {
                background: white;
                padding: 30px;
                border-radius: 14px;
                box-shadow:
                    0 4px 15px rgba(0,0,0,.05);
            }

            .warning {
                background: #fee2e2;
                color: #991b1b;
                padding: 14px;
                border-radius: 8px;
                margin: 20px 0;
            }

            .buttons {
                display: flex;
                gap: 10px;
            }

            button,
            a {
                padding: 10px 16px;
                border-radius: 8px;
                border: none;
                text-decoration: none;
                cursor: pointer;
            }

            button {
                background: #dc2626;
                color: white;
            }

            a {
                background: #111827;
                color: white;
            }

        </style>

    </head>

    <body>

    <div class="container">

        <div class="card">

            <h2>
                Delete Café Owner
            </h2>

            <div class="warning">

                <strong>
                    Warning:
                </strong>

                This action will permanently delete
                the owner account.

            </div>

            <p>

                Owner:
                <strong>
                    <?= e($owner['name']) ?>
                </strong>

            </p>

            <p>

                Email:
                <strong>
                    <?= e($owner['email']) ?>
                </strong>

            </p>

            <p>

                Café:
                <strong>
                    <?= e($owner['cafe_name']) ?>
                </strong>

            </p>


            <form method="POST">

                <?= csrfField() ?>

                <div class="buttons">

                    <button type="submit">
                        Delete Permanently
                    </button>

                    <a
                        href="<?= e(APP_URL) ?>/admin/owners.php"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

    </body>

    </html>

    <?php

    exit;
}

verifyCsrf();

/*
|--------------------------------------------------------------------------
| Delete Owner
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Audit Before Delete
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
            'owner_deleted',
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
            'owner_name' => $owner['name'],
            'owner_email' => $owner['email']
        ], JSON_UNESCAPED_UNICODE)
    ]);


    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM cafe_users
        WHERE id = :owner_id
          AND role = 'owner'
    ");

    $stmt->execute([
        ':owner_id' => $ownerId
    ]);


    header(
        'Location: ' .
        APP_URL .
        '/admin/owners.php?deleted=1'
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'Delete Owner Error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to delete owner.'
    );
}