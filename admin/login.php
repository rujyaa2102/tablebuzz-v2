<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

if (isAdminLoggedIn()) {

    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $email = cleanInput(
        $_POST['email'] ?? ''
    );

    $password = $_POST['password'] ?? '';

    if (
        !isValidEmail($email) ||
        $password === ''
    ) {

        $error = 'Invalid email or password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM admins
            WHERE email = :email
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $admin = $stmt->fetch();

        if (
            $admin &&
            verifyPassword(
                $password,
                $admin['password']
            )
        ) {

            loginAdmin($admin);

            header('Location: dashboard.php');
            exit;

        } else {

            $error = 'Invalid email or password.';
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
        Admin Login - TableBuzz
    </title>

</head>

<body>

<h1>TableBuzz Super Admin</h1>

<?php if ($error): ?>

    <p>
        <?= e($error) ?>
    </p>

<?php endif; ?>

<form method="POST">

    <?= csrfField() ?>

    <div>

        <label>
            Email
        </label>

        <br>

        <input
            type="email"
            name="email"
            required
            autocomplete="email"
        >

    </div>

    <br>

    <div>

        <label>
            Password
        </label>

        <br>

        <input
            type="password"
            name="password"
            required
            autocomplete="current-password"
        >

    </div>

    <br>

    <button type="submit">
        Login
    </button>

</form>

</body>

</html>