<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Redirect Already Logged-in Owner
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$email = '';


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $email = cleanInput(
        $_POST['email'] ?? ''
    );

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (!isValidEmail($email)) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find Active Owner
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                cu.*,
                c.name AS cafe_name,
                c.slug AS cafe_slug,
                c.status AS cafe_status
            FROM cafe_users cu

            INNER JOIN cafes c
                ON c.id = cu.cafe_id

            WHERE cu.email = :email
              AND cu.status = 'active'
              AND c.status = 'active'

            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verify Password
        |--------------------------------------------------------------------------
        */

        if (
            $user &&
            verifyPassword(
                $password,
                $user['password']
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | Login User
            |--------------------------------------------------------------------------
            */

            loginUser($user);

            header('Location: dashboard.php');
            exit;

        } else {

            $error =
                'Invalid email or password.';

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
        Café Owner Login - TableBuzz
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #1f2937
                );

            color: #111827;
        }


        .login-container {

            width: 100%;

            max-width: 430px;
        }


        .brand {

            text-align: center;

            color: white;

            margin-bottom: 20px;
        }


        .brand h1 {

            margin: 0 0 8px;

            font-size: 30px;
        }


        .brand p {

            margin: 0;

            color: #d1d5db;

            font-size: 14px;
        }


        .card {

            background: white;

            border-radius: 16px;

            padding: 30px;

            box-shadow:
                0 20px 50px
                rgba(
                    0,
                    0,
                    0,
                    .25
                );
        }


        .card h2 {

            margin:
                0 0 8px;

            font-size: 24px;
        }


        .subtitle {

            margin:
                0 0 25px;

            color: #6b7280;

            font-size: 14px;
        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;

            padding: 12px 14px;

            border-radius: 8px;

            margin-bottom: 18px;

            font-size: 14px;
        }


        .field {

            margin-bottom: 18px;
        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            font-size: 14px;
        }


        input {

            width: 100%;

            padding: 12px 13px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

            transition: .2s;
        }


        input:focus {

            border-color: #111827;

            box-shadow:
                0 0 0 3px
                rgba(
                    17,
                    24,
                    39,
                    .08
                );
        }


        button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 8px;

            background: #111827;

            color: white;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: .2s;
        }


        button:hover {

            background: #1f2937;

            transform:
                translateY(-1px);
        }


        .footer {

            text-align: center;

            margin-top: 20px;

            color: #6b7280;

            font-size: 13px;
        }


        @media (
            max-width: 500px
        ) {

            .card {

                padding: 24px;
            }

            .brand h1 {

                font-size: 26px;
            }

        }

    </style>

</head>


<body>


<div class="login-container">


    <!-- Brand -->

    <div class="brand">

        <h1>
            ☕ TableBuzz
        </h1>

        <p>
            Smart QR Customer Engagement Platform
        </p>

    </div>


    <!-- Login Card -->

    <div class="card">

        <h2>
            Café Owner Login
        </h2>

        <p class="subtitle">
            Sign in to manage your café.
        </p>


        <?php if ($error): ?>

            <div class="error">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <?= csrfField() ?>


            <!-- Email -->

            <div class="field">

                <label for="email">
                    Email Address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= e($email) ?>"
                    required
                    autocomplete="email"
                    placeholder="owner@example.com"
                >

            </div>


            <!-- Password -->

            <div class="field">

                <label for="password">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >

            </div>


            <!-- Submit -->

            <button type="submit">
                🔐 Login
            </button>

        </form>


        <div class="footer">

            TableBuzz Café Management

        </div>

    </div>


</div>


</body>

</html>