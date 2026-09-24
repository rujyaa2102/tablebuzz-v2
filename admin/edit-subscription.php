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
        s.*,
        c.name AS cafe_name,
        p.name AS plan_name
    FROM subscriptions s
    INNER JOIN cafes c
        ON c.id = s.cafe_id
    LEFT JOIN plans p
        ON p.id = s.plan_id
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
| Get Active Plans
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        code,
        monthly_price,
        yearly_price
    FROM plans
    WHERE status = 'active'
    ORDER BY id ASC
");

$plans = $stmt->fetchAll();

$errors = [];


/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $planId = filter_input(
        INPUT_POST,
        'plan_id',
        FILTER_VALIDATE_INT
    );

    $status = cleanInput(
        $_POST['status'] ?? ''
    );

    $billingCycle = cleanInput(
        $_POST['billing_cycle'] ?? ''
    );

    $trialStart = cleanInput(
        $_POST['trial_start'] ?? ''
    );

    $trialEnd = cleanInput(
        $_POST['trial_end'] ?? ''
    );

    $startDate = cleanInput(
        $_POST['start_date'] ?? ''
    );

    $endDate = cleanInput(
        $_POST['end_date'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'trial',
        'active',
        'expired',
        'cancelled',
        'inactive'
    ];

    if (
        !$planId ||
        $planId <= 0
    ) {
        $errors[] =
            'Please select a valid plan.';
    }

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        $errors[] =
            'Invalid subscription status.';
    }

    if (
        !in_array(
            $billingCycle,
            ['monthly', 'yearly'],
            true
        )
    ) {
        $errors[] =
            'Invalid billing cycle.';
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Plan
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM plans
            WHERE id = :plan_id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':plan_id' => $planId
        ]);

        if (!$stmt->fetch()) {

            $errors[] =
                'Selected plan is not available.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Date Validation
    |--------------------------------------------------------------------------
    */

    $dateFields = [
        'Trial Start' => $trialStart,
        'Trial End' => $trialEnd,
        'Start Date' => $startDate,
        'End Date' => $endDate
    ];

    foreach (
        $dateFields as $label => $date
    ) {

        if ($date === '') {
            continue;
        }

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $date
        ) {

            $errors[] =
                $label .
                ' must be a valid date.';
        }
    }


    if (
        $trialStart !== '' &&
        $trialEnd !== '' &&
        $trialStart > $trialEnd
    ) {

        $errors[] =
            'Trial start cannot be after trial end.';
    }


    if (
        $startDate !== '' &&
        $endDate !== '' &&
        $startDate > $endDate
    ) {

        $errors[] =
            'Start date cannot be after end date.';
    }


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                UPDATE subscriptions
                SET
                    plan_id = :plan_id,
                    status = :status,
                    billing_cycle = :billing_cycle,
                    trial_start = :trial_start,
                    trial_end = :trial_end,
                    start_date = :start_date,
                    end_date = :end_date,
                    updated_at = NOW()
                WHERE id = :subscription_id
            ");

            $stmt->execute([
                ':plan_id' => $planId,
                ':status' => $status,
                ':billing_cycle' => $billingCycle,
                ':trial_start' =>
                    $trialStart !== ''
                        ? $trialStart
                        : null,
                ':trial_end' =>
                    $trialEnd !== ''
                        ? $trialEnd
                        : null,
                ':start_date' =>
                    $startDate !== ''
                        ? $startDate
                        : null,
                ':end_date' =>
                    $endDate !== ''
                        ? $endDate
                        : null,
                ':subscription_id' =>
                    $subscriptionId
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
                    'subscription_updated',
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
                    'old_plan_id' =>
                        $subscription['plan_id'],
                    'new_plan_id' =>
                        $planId,
                    'old_status' =>
                        $subscription['status'],
                    'new_status' =>
                        $status,
                    'billing_cycle' =>
                        $billingCycle
                ], JSON_UNESCAPED_UNICODE)
            ]);


            header(
                'Location: ' .
                APP_URL .
                '/admin/subscriptions.php?updated=1'
            );

            exit;

        } catch (Throwable $e) {

            error_log(
                'Edit Subscription Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to update subscription.';
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
        Edit Subscription - TableBuzz
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
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 22px;
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
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
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
            Edit Subscription
        </h1>

        <div class="info">

            Café:
            <strong>
                <?= e(
                    $subscription['cafe_name']
                ) ?>
            </strong>

            <br>

            Current Plan:
            <strong>
                <?= e(
                    $subscription['plan_name']
                    ?: 'No Plan'
                ) ?>
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
                    Plan
                </label>

                <select
                    name="plan_id"
                    required
                >

                    <option value="">
                        Select Plan
                    </option>

                    <?php foreach ($plans as $plan): ?>

                        <?php

                        $selectedPlan =
                            isset($_POST['plan_id'])
                                ? (int) $_POST['plan_id']
                                : (int) $subscription['plan_id'];

                        ?>

                        <option
                            value="<?= (int) $plan['id'] ?>"
                            <?= (
                                $selectedPlan
                                === (int) $plan['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $plan['name']
                            ) ?>

                            -
                            ₹<?= number_format(
                                (float)
                                $plan['monthly_price'],
                                2
                            ) ?>/month

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Status
                </label>

                <?php

                $currentStatus =
                    $_POST['status']
                    ?? $subscription['status'];

                ?>

                <select
                    name="status"
                    required
                >

                    <?php
                    $statuses = [
                        'trial',
                        'active',
                        'expired',
                        'cancelled',
                        'inactive'
                    ];
                    ?>

                    <?php foreach (
                        $statuses as $status
                    ): ?>

                        <option
                            value="<?= e($status) ?>"
                            <?= (
                                $currentStatus ===
                                $status
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                ucfirst($status)
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Billing Cycle
                </label>

                <?php

                $currentCycle =
                    $_POST['billing_cycle']
                    ?? $subscription['billing_cycle'];

                ?>

                <select
                    name="billing_cycle"
                    required
                >

                    <option
                        value="monthly"
                        <?= (
                            $currentCycle === 'monthly'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Monthly
                    </option>

                    <option
                        value="yearly"
                        <?= (
                            $currentCycle === 'yearly'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Yearly
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Trial Start
                </label>

                <input
                    type="date"
                    name="trial_start"
                    value="<?= e(
                        $_POST['trial_start']
                        ?? $subscription['trial_start']
                        ?? ''
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Trial End
                </label>

                <input
                    type="date"
                    name="trial_end"
                    value="<?= e(
                        $_POST['trial_end']
                        ?? $subscription['trial_end']
                        ?? ''
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Start Date
                </label>

                <input
                    type="date"
                    name="start_date"
                    value="<?= e(
                        $_POST['start_date']
                        ?? $subscription['start_date']
                        ?? ''
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    End Date
                </label>

                <input
                    type="date"
                    name="end_date"
                    value="<?= e(
                        $_POST['end_date']
                        ?? $subscription['end_date']
                        ?? ''
                    ) ?>"
                >

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    class="btn primary"
                >
                    Save Subscription
                </button>

                <a
                    href="<?= e(APP_URL) ?>/admin/subscriptions.php"
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