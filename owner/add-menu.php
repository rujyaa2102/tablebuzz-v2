<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();

$error = '';

/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$name = '';
$description = '';
$price = '';
$categoryId = '';
$isPopular = 0;


/*
|--------------------------------------------------------------------------
| Fetch Active Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name
    FROM menu_categories
    WHERE cafe_id = :cafe_id
      AND status = 'active'
    ORDER BY sort_order ASC, name ASC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $name = cleanInput(
        $_POST['name'] ?? ''
    );

    $description = cleanInput(
        $_POST['description'] ?? ''
    );

    $price = trim(
        $_POST['price'] ?? ''
    );

    $categoryId = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT
    );

    $isPopular = isset($_POST['is_popular']) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error = 'Food name is required.';

    } elseif (mb_strlen($name) > 150) {

        $error = 'Food name must be less than 150 characters.';

    } elseif ($description !== '' && mb_strlen($description) > 2000) {

        $error = 'Description must be less than 2000 characters.';

    } elseif (
        $price === '' ||
        !is_numeric($price) ||
        (float) $price < 0
    ) {

        $error = 'Please enter a valid price.';

    } elseif ((float) $price > 99999999.99) {

        $error = 'Price is too large.';

    } elseif (!$categoryId || $categoryId <= 0) {

        $error = 'Please select a category.';

    }


    /*
    |--------------------------------------------------------------------------
    | Verify Category Ownership
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT
                id
            FROM menu_categories
            WHERE id = :category_id
              AND cafe_id = :cafe_id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':category_id' => $categoryId,
            ':cafe_id' => $cafeId
        ]);

        if (!$stmt->fetch()) {

            $error = 'Invalid category.';

        }
    }


    /*
    |--------------------------------------------------------------------------
    | Image Upload
    |--------------------------------------------------------------------------
    */

    $imageName = null;

    $uploadedImagePath = null;

    if (
        $error === '' &&
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['image']['error'] !== UPLOAD_ERR_OK
        ) {

            $error = 'Image upload failed.';

        } elseif (
            $_FILES['image']['size'] > 5 * 1024 * 1024
        ) {

            $error = 'Image must be less than 5MB.';

        } else {

            $tmpFile = $_FILES['image']['tmp_name'];

            /*
            |--------------------------------------------------------------------------
            | Verify Real Image
            |--------------------------------------------------------------------------
            */

            $imageInfo = @getimagesize($tmpFile);

            if ($imageInfo === false) {

                $error = 'Uploaded file is not a valid image.';

            } else {

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $mime = '';

                if (function_exists('finfo_open')) {

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    if ($finfo) {

                        $mime = finfo_file(
                            $finfo,
                            $tmpFile
                        );

                        finfo_close($finfo);
                    }
                }

                if ($mime === '') {

                    $mime = $imageInfo['mime'] ?? '';
                }


                if (!isset($allowedTypes[$mime])) {

                    $error =
                        'Only JPG, PNG and WEBP images are allowed.';

                } else {

                    $extension =
                        $allowedTypes[$mime];

                    $imageName =
                        bin2hex(random_bytes(16))
                        . '.'
                        . $extension;


                    $uploadDir =
                        __DIR__
                        . '/../public/uploads/foods/';


                    if (!is_dir($uploadDir)) {

                        if (!mkdir(
                            $uploadDir,
                            0755,
                            true
                        )) {

                            $error =
                                'Unable to create upload directory.';
                        }
                    }


                    if ($error === '') {

                        $destination =
                            $uploadDir
                            . $imageName;


                        if (
                            !move_uploaded_file(
                                $tmpFile,
                                $destination
                            )
                        ) {

                            $error =
                                'Unable to save image.';

                        } else {

                            $uploadedImagePath =
                                $destination;
                        }
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Menu Item
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Menu Item
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO menu_items
                (
                    cafe_id,
                    category_id,
                    name,
                    description,
                    price,
                    image,
                    is_popular,
                    is_available,
                    sort_order
                )
                VALUES
                (
                    :cafe_id,
                    :category_id,
                    :name,
                    :description,
                    :price,
                    :image,
                    :is_popular,
                    1,
                    0
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => number_format(
                    (float) $price,
                    2,
                    '.',
                    ''
                ),
                ':image' => $imageName,
                ':is_popular' => $isPopular
            ]);


            $menuItemId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (
                    cafe_id,
                    cafe_user_id,
                    action,
                    entity_type,
                    entity_id,
                    ip_address,
                    user_agent,
                    metadata
                )
                VALUES
                (
                    :cafe_id,
                    :cafe_user_id,
                    'create',
                    'menu_item',
                    :entity_id,
                    :ip_address,
                    :user_agent,
                    :metadata
                )
            ");

            $stmt->execute([
                ':cafe_id' => $cafeId,
                ':cafe_user_id' => currentUserId(),
                ':entity_id' => $menuItemId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'name' => $name,
                    'price' => (float) $price,
                    'category_id' => $categoryId
                ])
            ]);


            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                'Location: '
                . APP_URL
                . '/owner/menu.php?created=1'
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove Uploaded Image If DB Insert Failed
            |--------------------------------------------------------------------------
            */

            if (
                $uploadedImagePath !== null &&
                is_file($uploadedImagePath)
            ) {

                @unlink($uploadedImagePath);
            }


            error_log(
                'TableBuzz add-menu error: '
                . $e->getMessage()
            );

            $error =
                'Unable to add menu item. Please try again.';
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
        Add Food - TableBuzz
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .container {
            max-width: 850px;
            margin: auto;
        }

        .header {
            background: #111827;
            color: #ffffff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            opacity: .85;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.05);
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
        select,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .help {
            margin-top: 5px;
            font-size: 12px;
            color: #6b7280;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-light {
            background: #f3f4f6;
            color: #111827;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox input {
            width: auto;
        }

        .no-category {
            text-align: center;
            padding: 25px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 8px;
        }

    </style>

</head>

<body>

<div class="container">


    <!-- Header -->

    <div class="header">

        <h1>
            🍽️ Add Food Item
        </h1>

        <p>
            Add a new food item to your café menu.
        </p>

    </div>


    <!-- Main Card -->

    <div class="card">


        <a
            class="btn btn-light"
            href="<?= e(APP_URL) ?>/owner/menu.php"
        >
            ← Back to Menu
        </a>


        <br>
        <br>


        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <?php if (!$categories): ?>

            <div class="no-category">

                <strong>
                    No active categories found.
                </strong>

                <p>
                    Please create a category before adding food.
                </p>

                <a
                    class="btn btn-primary"
                    href="<?= e(APP_URL) ?>/owner/categories.php"
                >
                    Create Category
                </a>

            </div>

        <?php else: ?>


            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <?= csrfField() ?>


                <!-- Food Name -->

                <div class="form-group">

                    <label for="name">
                        Food Name
                    </label>

                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="<?= e($name) ?>"
                        maxlength="150"
                        placeholder="e.g. Masala Dosa"
                        required
                    >

                </div>


                <!-- Category -->

                <div class="form-group">

                    <label for="category_id">
                        Category
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>


                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (string) $categoryId === (string) $category['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($category['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Description -->

                <div class="form-group">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        maxlength="2000"
                        placeholder="Describe the food item..."
                    ><?= e($description) ?></textarea>

                    <div class="help">
                        Maximum 2000 characters.
                    </div>

                </div>


                <!-- Price -->

                <div class="form-group">

                    <label for="price">
                        Price (₹)
                    </label>

                    <input
                        id="price"
                        type="number"
                        name="price"
                        value="<?= e($price) ?>"
                        step="0.01"
                        min="0"
                        max="99999999.99"
                        placeholder="e.g. 180.00"
                        required
                    >

                </div>


                <!-- Image -->

                <div class="form-group">

                    <label for="image">
                        Food Image
                    </label>

                    <input
                        id="image"
                        type="file"
                        name="image"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="help">
                        JPG, PNG or WEBP. Maximum 5MB.
                    </div>

                </div>


                <!-- Popular -->

                <div class="form-group">

                    <label class="checkbox">

                        <input
                            type="checkbox"
                            name="is_popular"
                            value="1"
                            <?= $isPopular ? 'checked' : '' ?>
                        >

                        Mark as Popular Item

                    </label>

                </div>


                <!-- Actions -->

                <div class="actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        ➕ Add Food
                    </button>

                    <a
                        class="btn btn-light"
                        href="<?= e(APP_URL) ?>/owner/menu.php"
                    >
                        Cancel
                    </a>

                </div>

            </form>


        <?php endif; ?>

    </div>

</div>

</body>

</html>