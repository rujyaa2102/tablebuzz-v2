<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$errors = [];

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

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {
        $errors[] = 'Plan name is required.';
    }

    if (
        !preg_match(
            '/^[A-Z0-9_-]{2,50}$/',
            $code
        )
    ) {
        $errors[] =
            'Plan code must contain only A-Z, 0-9, underscore or hyphen.';
    }

    if ($monthlyPrice < 0) {
        $errors[] =
            'Monthly price cannot be negative.';
    }

    if ($yearlyPrice < 0) {
        $errors[] =
            'Yearly price cannot be negative.';
    }

    /*
    |--------------------------------------------------------------------------
    | JSON Validation
    |--------------------------------------------------------------------------
    */

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
            LIMIT 1
        ");

        $stmt->execute([
            ':code' => $code
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'This plan code already exists.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO plans
                (
                    name,
                    code,
                    monthly_price,
                    yearly_price,
                    features,
                    limits,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :name,
                    :code,
                    :monthly_price,
                    :yearly_price,
                    :features,
                    :limits,
                    'active',
                    NOW(),
                    NOW()
                )
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
                )
            ]);

            header(
                'Location: ' .
                APP_URL .
                '/admin/plans.php?created=1'
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                'Add Plan Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to create plan.';
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
        Add Plan - TableBuzz
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

        .help {
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
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
            text-decoration: none;
            border: none;
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
            Add Subscription Plan
        </h1>


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
                        $_POST['name'] ?? ''
                    ) ?>"
                    placeholder="Example: Growth"
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
                        $_POST['code'] ?? ''
                    ) ?>"
                    placeholder="Example: GROWTH"
                    required
                >

                <div class="help">
                    Example:
                    STARTER, GROWTH, PREMIUM
                </div>

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
                        ?? '0'
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
                        ?? '0'
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
                    placeholder='{"qr_codes":"Unlimited","games":"Yes","analytics":"Advanced"}'
                ><?= e(
                    $_POST['features'] ?? ''
                ) ?></textarea>

                <div class="help">
                    Must be valid JSON.
                </div>

            </div>


            <div class="form-group">

                <label>
                    Limits JSON
                </label>

                <textarea
                    name="limits"
                    placeholder='{"cafes":1,"menu_items":100,"owners":2}'
                ><?= e(
                    $_POST['limits'] ?? ''
                ) ?></textarea>

                <div class="help">
                    Must be valid JSON.
                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn primary"
                >
                    Create Plan
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