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
        id,
        cafe_id,
        name,
        email,
        status
    FROM cafe_users
    WHERE id = :owner_id
      AND role = 'owner'
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

$newStatus =
    $owner['status'] === 'active'
        ? 'inactive'
        : 'active';

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
            Change Owner Status
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

            .buttons {
                display: flex;
                gap: 10px;
                margin-top: 20px;
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
                background: #2563eb;
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
                Change Owner Status
            </h2>

            <p>

                Owner:
                <strong>
                    <?= e($owner['name']) ?>
                </strong>

            </p>

            <p>

                Current status:
                <strong>
                    <?= e(
                        strtoupper(
                            $owner['status']
                        )
                    ) ?>
                </strong>

            </p>

            <p>

                New status:
                <strong>
                    <?= e(
                        strtoupper(
                            $newStatus
                        )
                    ) ?>
                </strong>

            </p>


            <form method="POST">

                <?= csrfField() ?>

                <div class="buttons">

                    <button type="submit">
                        Confirm
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
| Update Status
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        UPDATE cafe_users
        SET
            status = :status,
            updated_at = NOW()
        WHERE id = :owner_id
          AND role = 'owner'
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':owner_id' => $ownerId
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
            'owner_status_changed',
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
            'old_status' => $owner['status'],
            'new_status' => $newStatus,
            'owner_email' => $owner['email']
        ], JSON_UNESCAPED_UNICODE)
    ]);


    header(
        'Location: ' .
        APP_URL .
        '/admin/owners.php?status_changed=1'
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'Toggle Owner Error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to change owner status.'
    );
}