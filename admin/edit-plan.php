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
        monthly_price,
        yearly_price,
        features,
        limits,
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

$errors = [];

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $name = cleanInput(
        $_POST['name'] ?? ''
    );

    $code = strtoupper(
        cleanInput(
            $_POST['code'] ?? ''
        )
    );

    $monthlyPrice = (float) (
        $_POST['monthly_price'] ?? 0
    );

    $yearlyPrice = (float) (
        $_POST['yearly_price'] ?? 0
    );

    $featuresText =
        trim(
            $_POST['features'] ?? ''
        );

    $limitsText =
        trim(
            $_POST['limits'] ?? ''
        );


    if ($name === '') {
        $errors[] =
            'Plan name is required.';
    }

    if (
        !preg_match(
            '/^[A-Z0-9_-]{2,50}$/',
            $code
        )
    ) {
        $errors[] =
            'Invalid plan code.';
    }

    if ($monthlyPrice < 0) {
        $errors[] =
            'Monthly price cannot be negative.';
    }

    if ($yearlyPrice < 0) {
        $errors[] =
            'Yearly price cannot be negative.';
    }


    $features = [];

    if ($featuresText !== '') {

        $features = json_decode(
            $featuresText,
            true
        );

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($features)
        ) {
            $errors[] =
                'Features must be valid JSON.';
        }
    }


    $limits = [];

    if ($limitsText !== '') {

        $limits = json_decode(
            $limitsText,
            true
        );

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($limits)
        ) {
            $errors[] =
                'Limits must be valid JSON.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Code
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM plans
            WHERE code = :code
              AND id != :plan_id
            LIMIT 1
        ");

        $stmt->execute([
            ':code' => $code,
            ':plan_id' => $planId
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'This plan code already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                UPDATE plans
                SET
                    name = :name,
                    code = :code,
                    monthly_price = :monthly_price,
                    yearly_price = :yearly_price,
                    features = :features,
                    limits = :limits,
                    updated_at = NOW()
                WHERE id = :plan_id
            ");

            $stmt->execute([
                ':name' => $name,
                ':code' => $code,
                ':monthly_price' => $monthlyPrice,
                ':yearly_price' => $yearlyPrice,
                ':features' => json_encode(
                    $features,
                    JSON_UNESCAPED_UNICODE
                ),
                ':limits' => json_encode(
                    $limits,
                    JSON_UNESCAPED_UNICODE
                ),
                ':plan_id' => $planId
            ]);


            header(
                'Location: ' .
                APP_URL .
                '/admin/plans.php?updated=1'
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                'Edit Plan Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to update plan.';
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
        Edit Plan - TableBuzz
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 750px;
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
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
            font-family: monospace;
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
        }

        .primary {
            background: #2563eb;
            color: white;
        }

        .secondary {
            background: #111827;
            color: white;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>
            Edit Subscription Plan
        </h1>

        <div class="info">

            Plan ID:
            <strong>
                #<?= (int) $plan['id'] ?>
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


        <form method="POST">

            <?= csrfField() ?>


            <div class="form-group">

                <label>
                    Plan Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e(
                        $_POST['name']
                        ?? $plan['name']
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Plan Code
                </label>

                <input
                    type="text"
                    name="code"
                    value="<?= e(
                        $_POST['code']
                        ?? $plan['code']
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Monthly Price (₹)
                </label>

                <input
                    type="number"
                    name="monthly_price"
                    min="0"
                    step="0.01"
                    value="<?= e(
                        $_POST['monthly_price']
                        ?? $plan['monthly_price']
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Yearly Price (₹)
                </label>

                <input
                    type="number"
                    name="yearly_price"
                    min="0"
                    step="0.01"
                    value="<?= e(
                        $_POST['yearly_price']
                        ?? $plan['yearly_price']
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Features JSON
                </label>

                <textarea
                    name="features"
                ><?= e(
                    $_POST['features']
                    ?? $plan['features']
                    ?? ''
                ) ?></textarea>

            </div>


            <div class="form-group">

                <label>
                    Limits JSON
                </label>

                <textarea
                    name="limits"
                ><?= e(
                    $_POST['limits']
                    ?? $plan['limits']
                    ?? ''
                ) ?></textarea>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn primary"
                >
                    Save Changes
                </button>

                <a
                    href="<?= e(APP_URL) ?>/admin/plans.php"
                    class="btn secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>