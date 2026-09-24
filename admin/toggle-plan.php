<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$planId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$planId || $planId <= 0) {
    http_response_code(400);
    exit('Invalid plan ID.');
}

/*
|--------------------------------------------------------------------------
| Get Plan
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        code,
        status
    FROM plans
    WHERE id = :plan_id
    LIMIT 1
");

$stmt->execute([
    ':plan_id' => $planId
]);

$plan = $stmt->fetch();

if (!$plan) {
    http_response_code(404);
    exit('Plan not found.');
}

$newStatus =
    $plan['status'] === 'active'
        ? 'inactive'
        : 'active';

/*
|--------------------------------------------------------------------------
| POST Confirmation
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
            Change Plan Status
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
                Change Plan Status
            </h2>

            <p>

                Plan:
                <strong>
                    <?= e($plan['name']) ?>
                </strong>

            </p>

            <p>

                Current status:
                <strong>
                    <?= e(
                        strtoupper(
                            $plan['status']
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
                        href="<?= e(APP_URL) ?>/admin/plans.php"
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
| Update
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        UPDATE plans
        SET
            status = :status,
            updated_at = NOW()
        WHERE id = :plan_id
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':plan_id' => $planId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | Plans are global, not cafe-specific.
    | Therefore cafe_id remains NULL.
    |
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
            NULL,
            :admin_id,
            'plan_status_changed',
            'plan',
            :entity_id,
            :ip_address,
            :user_agent,
            :metadata,
            NOW()
        )
    ");

    $stmt->execute([
        ':admin_id' => $admin['id'] ?? null,
        ':entity_id' => $planId,
        ':ip_address' =>
            $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' =>
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':metadata' => json_encode([
            'plan_name' => $plan['name'],
            'plan_code' => $plan['code'],
            'old_status' => $plan['status'],
            'new_status' => $newStatus
        ], JSON_UNESCAPED_UNICODE)
    ]);


    header(
        'Location: ' .
        APP_URL .
        '/admin/plans.php?status_changed=1'
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'Toggle Plan Error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to change plan status.'
    );
}