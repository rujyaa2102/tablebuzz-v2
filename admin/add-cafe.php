<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $cafeName = cleanInput($_POST['cafe_name'] ?? '');
    $slug = cleanInput($_POST['slug'] ?? '');

    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? '');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    $address = cleanInput($_POST['address'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');

    $ownerName = cleanInput($_POST['owner_name'] ?? '');
    $ownerEmail = cleanInput($_POST['owner_email'] ?? '');
    $ownerPassword = $_POST['owner_password'] ?? '';

    if (
        $cafeName === '' ||
        $slug === '' ||
        $ownerName === '' ||
        !isValidEmail($ownerEmail) ||
        strlen($ownerPassword) < 8
    ) {

        $error = 'Please enter valid café and owner details.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
             * Check slug
             */

            $stmt = $pdo->prepare("
                SELECT id
                FROM cafes
                WHERE slug = :slug
                LIMIT 1
            ");

            $stmt->execute([
                ':slug' => $slug
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'This café slug already exists.'
                );
            }

            /*
             * Create café
             */

            $stmt = $pdo->prepare("
                INSERT INTO cafes
                (
                    name,
                    slug,
                    address,
                    city,
                    state,
                    pincode,
                    phone,
                    email,
                    status
                )
                VALUES
                (
                    :name,
                    :slug,
                    :address,
                    :city,
                    :state,
                    :pincode,
                    :phone,
                    :email,
                    'active'
                )
            ");

            $stmt->execute([
                ':name' => $cafeName,
                ':slug' => $slug,
                ':address' => $address,
                ':city' => $city,
                ':state' => $state,
                ':pincode' => $pincode,
                ':phone' => $phone,
                ':email' => $email
            ]);

            $cafeId = (int) $pdo->lastInsertId();

            /*
             * Create café settings
             */

            $stmt = $pdo->prepare("
                INSERT INTO cafe_settings
                (
                    cafe_id,
                    currency,
                    timezone
                )
                VALUES
                (
                    :cafe_id,
                    'INR',
                    'Asia/Kolkata'
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId
            ]);

            /*
             * Create owner
             */

            $stmt = $pdo->prepare("
                INSERT INTO cafe_users
                (
                    cafe_id,
                    name,
                    email,
                    password,
                    role,
                    status
                )
                VALUES
                (
                    :cafe_id,
                    :name,
                    :email,
                    :password,
                    'owner',
                    'active'
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':name' => $ownerName,
                ':email' => $ownerEmail,
                ':password' => hashPassword($ownerPassword)
            ]);

            $pdo->commit();

            header('Location: cafes.php');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
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
        Add Café - TableBuzz
    </title>

</head>

<body>

<h1>
    Add New Café
</h1>

<a href="cafes.php">
    ← Back to Cafés
</a>

<hr>

<?php if ($error): ?>

    <p>
        <?= e($error) ?>
    </p>

<?php endif; ?>

<form method="POST">

    <?= csrfField() ?>

    <h2>
        Café Details
    </h2>

    <p>
        <label>Café Name</label><br>

        <input
            type="text"
            name="cafe_name"
            required
        >
    </p>

    <p>
        <label>Slug</label><br>

        <input
            type="text"
            name="slug"
            placeholder="my-cafe"
            required
        >
    </p>

    <p>
        <label>Address</label><br>

        <textarea
            name="address"
        ></textarea>
    </p>

    <p>
        <label>City</label><br>

        <input
            type="text"
            name="city"
        >
    </p>

    <p>
        <label>State</label><br>

        <input
            type="text"
            name="state"
        >
    </p>

    <p>
        <label>Pincode</label><br>

        <input
            type="text"
            name="pincode"
        >
    </p>

    <p>
        <label>Phone</label><br>

        <input
            type="text"
            name="phone"
        >
    </p>

    <p>
        <label>Café Email</label><br>

        <input
            type="email"
            name="email"
        >
    </p>

    <hr>

    <h2>
        Owner Account
    </h2>

    <p>
        <label>Owner Name</label><br>

        <input
            type="text"
            name="owner_name"
            required
        >
    </p>

    <p>
        <label>Owner Email</label><br>

        <input
            type="email"
            name="owner_email"
            required
        >
    </p>

    <p>
        <label>Owner Password</label><br>

        <input
            type="password"
            name="owner_password"
            minlength="8"
            required
        >
    </p>

    <button type="submit">
        Create Café
    </button>

</form>

</body>

</html>