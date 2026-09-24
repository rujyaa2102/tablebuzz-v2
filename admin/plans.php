<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        id,
        name,
        code,
        monthly_price,
        yearly_price,
        features,
        limits,
        status,
        created_at
    FROM plans
    ORDER BY id ASC
");

$plans = $stmt->fetchAll();

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
        Plans - TableBuzz Admin
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
            max-width: 1200px;
            margin: auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 6px;
        }

        .subtitle {
            margin: 0;
            color: #6b7280;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-toggle {
            background: #6b7280;
            color: white;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            box-shadow:
                0 4px 15px rgba(0,0,0,.05);
        }

        .plan-name {
            font-size: 22px;
            font-weight: bold;
        }

        .plan-code {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .price {
            margin-top: 20px;
            font-size: 28px;
            font-weight: bold;
        }

        .price small {
            font-size: 13px;
            color: #6b7280;
            font-weight: normal;
        }

        .yearly {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            margin-top: 15px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .section {
            margin-top: 20px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 8px;
        }

        ul {
            margin: 0;
            padding-left: 20px;
        }

        li {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .plan-actions {
            display: flex;
            gap: 8px;
            margin-top: 22px;
            flex-wrap: wrap;
        }

        .empty {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            color: #6b7280;
        }

        @media (max-width: 700px) {

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- Header -->

    <div class="header">

        <div>

            <h1>
                Subscription Plans
            </h1>

            <p class="subtitle">
                Manage TableBuzz pricing plans
            </p>

        </div>

        <div class="actions">

            <a
                href="<?= e(APP_URL) ?>/admin/dashboard.php"
                class="btn btn-dark"
            >
                ← Dashboard
            </a>

            <a
                href="<?= e(APP_URL) ?>/admin/add-plan.php"
                class="btn btn-primary"
            >
                + Add Plan
            </a>

        </div>

    </div>


    <!-- Plans -->

    <?php if (empty($plans)): ?>

        <div class="empty">
            No plans found.
        </div>

    <?php else: ?>

        <div class="grid">

            <?php foreach ($plans as $plan): ?>

                <?php

                $features = [];
                $limits = [];

                /*
                |--------------------------------------------------------------------------
                | Decode Features
                |--------------------------------------------------------------------------
                */

                if (!empty($plan['features'])) {

                    $decodedFeatures = json_decode(
                        $plan['features'],
                        true
                    );

                    if (is_array($decodedFeatures)) {
                        $features = $decodedFeatures;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Decode Limits
                |--------------------------------------------------------------------------
                */

                if (!empty($plan['limits'])) {

                    $decodedLimits = json_decode(
                        $plan['limits'],
                        true
                    );

                    if (is_array($decodedLimits)) {
                        $limits = $decodedLimits;
                    }
                }

                ?>

                <div class="card">

                    <!-- Plan Name -->

                    <div class="plan-name">

                        <?= e($plan['name']) ?>

                    </div>


                    <!-- Plan Code -->

                    <div class="plan-code">

                        Code:
                        <?= e($plan['code']) ?>

                    </div>


                    <!-- Monthly Price -->

                    <div class="price">

                        ₹<?= number_format(
                            (float) $plan['monthly_price'],
                            2
                        ) ?>

                        <small>
                            / month
                        </small>

                    </div>


                    <!-- Yearly Price -->

                    <div class="yearly">

                        ₹<?= number_format(
                            (float) $plan['yearly_price'],
                            2
                        ) ?>

                        / year

                    </div>


                    <!-- Status -->

                    <?php if (
                        $plan['status'] === 'active'
                    ): ?>

                        <span class="status active">
                            ACTIVE
                        </span>

                    <?php else: ?>

                        <span class="status inactive">
                            INACTIVE
                        </span>

                    <?php endif; ?>


                    <!-- Features -->

                    <?php if (!empty($features)): ?>

                        <div class="section">

                            <div class="section-title">
                                Features
                            </div>

                            <ul>

                                <?php foreach (
                                    $features as $key => $value
                                ): ?>

                                    <?php

                                    $label = ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $key
                                        )
                                    );

                                    if (is_scalar($value)) {
                                        $displayValue =
                                            (string) $value;
                                    } else {
                                        $displayValue =
                                            json_encode(
                                                $value,
                                                JSON_UNESCAPED_UNICODE
                                            );
                                    }

                                    ?>

                                    <li>

                                        <strong>
                                            <?= e($label) ?>:
                                        </strong>

                                        <?= e($displayValue) ?>

                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>


                    <!-- Limits -->

                    <?php if (!empty($limits)): ?>

                        <div class="section">

                            <div class="section-title">
                                Limits
                            </div>

                            <ul>

                                <?php foreach (
                                    $limits as $key => $value
                                ): ?>

                                    <?php

                                    $label = ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $key
                                        )
                                    );

                                    if (is_scalar($value)) {
                                        $displayValue =
                                            (string) $value;
                                    } else {
                                        $displayValue =
                                            json_encode(
                                                $value,
                                                JSON_UNESCAPED_UNICODE
                                            );
                                    }

                                    ?>

                                    <li>

                                        <strong>
                                            <?= e($label) ?>:
                                        </strong>

                                        <?= e($displayValue) ?>

                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>


                    <!-- Actions -->

                    <div class="plan-actions">

                        <a
                            href="<?= e(APP_URL) ?>/admin/edit-plan.php?id=<?= (int) $plan['id'] ?>"
                            class="btn btn-edit"
                        >
                            Edit
                        </a>

                        <a
                            href="<?= e(APP_URL) ?>/admin/toggle-plan.php?id=<?= (int) $plan['id'] ?>"
                            class="btn btn-toggle"
                        >
                            Toggle Status
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>