<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$subscriptionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    !$subscriptionId ||
    $subscriptionId <= 0
) {
    http_response_code(400);
    exit('Invalid subscription ID.');
}


/*
|--------------------------------------------------------------------------
| Get Subscription
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.cafe_id,
        s.status,
        c.name AS cafe_name
    FROM subscriptions s
    INNER JOIN cafes c
        ON c.id = s.cafe_id
    WHERE s.id = :subscription_id
    LIMIT 1
");

$stmt->execute([
    ':subscription_id' => $subscriptionId
]);

$subscription = $stmt->fetch();

if (!$subscription) {
    http_response_code(404);
    exit('Subscription not found.');
}


/*
|--------------------------------------------------------------------------
| Determine New Status
|--------------------------------------------------------------------------
*/

$newStatus =
    $subscription['status'] === 'active'
        ? 'inactive'
        : 'active';


/*
|--------------------------------------------------------------------------
| Confirmation Page
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST'):

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
        Change Subscription Status
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
            margin-top: 25px;
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
            Change Subscription Status
        </h2>

        <p>

            Café:
            <strong>
                <?= e(
                    $subscription['cafe_name']
                ) ?>
            </strong>

        </p>

        <p>

            Current Status:
            <strong>
                <?= e(
                    strtoupper(
                        $subscription['status']
                    )
                ) ?>
            </strong>

        </p>

        <p>

            New Status:
            <strong>
                <?= e(
                    strtoupper($newStatus)
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
                    href="<?= e(APP_URL) ?>/admin/subscriptions.php"
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

endif;


/*
|--------------------------------------------------------------------------
| Verify CSRF
|--------------------------------------------------------------------------
*/

verifyCsrf();


/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET
            status = :status,
            updated_at = NOW()
        WHERE id = :subscription_id
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':subscription_id' => $subscriptionId
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
            'subscription_status_changed',
            'subscription',
            :entity_id,
            :ip_address,
            :user_agent,
            :metadata,
            NOW()
        )
    ");

    $stmt->execute([
        ':cafe_id' =>
            $subscription['cafe_id'],
        ':admin_id' =>
            $admin['id'] ?? null,
        ':entity_id' =>
            $subscriptionId,
        ':ip_address' =>
            $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' =>
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':metadata' => json_encode([
            'cafe_name' =>
                $subscription['cafe_name'],
            'old_status' =>
                $subscription['status'],
            'new_status' =>
                $newStatus
        ], JSON_UNESCAPED_UNICODE)
    ]);


    header(
        'Location: ' .
        APP_URL .
        '/admin/subscriptions.php?status_changed=1'
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'Toggle Subscription Error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to change subscription status.'
    );
}