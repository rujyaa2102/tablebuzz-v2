<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$cafeId || !$itemId || $itemId <= 0) {
    http_response_code(400);
    exit('Invalid food item.');
}

$stmt = $pdo->prepare("\n    SELECT *\n    FROM menu_items\n    WHERE id = :id\n      AND cafe_id = :cafe_id\n    LIMIT 1\n");
$stmt->execute([
    ':id' => $itemId,
    ':cafe_id' => $cafeId
]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit('Food item not found.');
}

$stmt = $pdo->prepare("\n    SELECT id, name\n    FROM menu_categories\n    WHERE cafe_id = :cafe_id\n      AND status = 'active'\n    ORDER BY sort_order, name\n");
$stmt->execute([':cafe_id' => $cafeId]);
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = cleanInput($_POST['name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $videoUrl = cleanInput($_POST['video_url'] ?? '');
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $isPopular = isset($_POST['is_popular']) ? 1 : 0;
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 190) {
        $error = 'Please enter a valid food name.';
    } elseif ($price === false || $price < 0) {
        $error = 'Please enter a valid price.';
    } elseif (!$categoryId || $sortOrder === false || $sortOrder < 0) {
        $error = 'Please select a valid category and sort order.';
    } elseif ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid video URL.';
    } else {
        $catStmt = $pdo->prepare("\n            SELECT id\n            FROM menu_categories\n            WHERE id = :category_id\n              AND cafe_id = :cafe_id\n              AND status = 'active'\n            LIMIT 1\n        ");
        $catStmt->execute([
            ':category_id' => $categoryId,
            ':cafe_id' => $cafeId
        ]);

        if (!$catStmt->fetch()) {
            $error = 'Invalid category.';
        }
    }

    $newImage = null;
    $oldImage = $item['image'] ?? null;

    if ($error === '' && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $tmp = $_FILES['image']['tmp_name'];
            $mime = mime_content_type($tmp);

            if (!isset($allowedTypes[$mime])) {
                $error = 'Only JPG, PNG and WEBP images are allowed.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be less than 5MB.';
            } elseif (@getimagesize($tmp) === false) {
                $error = 'Invalid image file.';
            } else {
                $uploadDir = __DIR__ . '/../public/uploads/foods/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $error = 'Unable to create upload directory.';
                } else {
                    $newImage = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
                    if (!move_uploaded_file($tmp, $uploadDir . $newImage)) {
                        $newImage = null;
                        $error = 'Unable to save image.';
                    }
                }
            }
        }
    }

    if ($error === '') {
        $finalImage = $newImage ?? $oldImage;

        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare("\n                UPDATE menu_items\n                SET\n                    category_id = :category_id,\n                    name = :name,\n                    description = :description,\n                    price = :price,\n                    image = :image,\n                    video_url = :video_url,\n                    is_popular = :is_popular,\n                    is_available = :is_available,\n                    sort_order = :sort_order,\n                    updated_at = NOW()\n                WHERE id = :id\n                  AND cafe_id = :cafe_id\n            ");

            $update->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':image' => $finalImage,
                ':video_url' => $videoUrl !== '' ? $videoUrl : null,
                ':is_popular' => $isPopular,
                ':is_available' => $isAvailable,
                ':sort_order' => $sortOrder,
                ':id' => $itemId,
                ':cafe_id' => $cafeId
            ]);

            $audit = $pdo->prepare("\n                INSERT INTO audit_logs (\n                    cafe_id, cafe_user_id, action, entity_type, entity_id,\n                    ip_address, user_agent, metadata, created_at\n                ) VALUES (\n                    :cafe_id, :cafe_user_id, 'menu_updated', 'menu_item', :entity_id,\n                    :ip_address, :user_agent, :metadata, NOW()\n                )\n            ");
            $audit->execute([
                ':cafe_id' => $cafeId,
                ':cafe_user_id' => currentUser()['id'] ?? null,
                ':entity_id' => $itemId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':metadata' => json_encode([
                    'name' => $name,
                    'price' => $price,
                    'is_available' => $isAvailable
                ], JSON_UNESCAPED_UNICODE)
            ]);

            $pdo->commit();

            if ($newImage && $oldImage && $newImage !== $oldImage) {
                $oldPath = __DIR__ . '/../public/uploads/foods/' . basename($oldImage);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            header('Location: ' . APP_URL . '/owner/menu.php?updated=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newImage) {
                $newPath = __DIR__ . '/../public/uploads/foods/' . basename($newImage);
                if (is_file($newPath)) {
                    @unlink($newPath);
                }
            }

            error_log('Edit Menu Error: ' . $e->getMessage());
            $error = 'Unable to update food item.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Food - TableBuzz</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#1f2937}.container{max-width:850px;margin:auto;padding:30px 20px}.card{background:#fff;border-radius:14px;padding:25px;box-shadow:0 4px 15px rgba(0,0,0,.05)}h1{margin-top:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.full{grid-column:1/-1}label{display:block;font-weight:600;margin-bottom:7px}input,textarea,select{width:100%;padding:11px;border:1px solid #d1d5db;border-radius:8px;font:inherit}textarea{min-height:120px;resize:vertical}.check{display:flex;gap:8px;align-items:center}.check input{width:auto}.current-image{margin-top:10px}.current-image img{width:150px;height:110px;object-fit:cover;border-radius:10px}.actions{display:flex;gap:10px;margin-top:22px}.btn{display:inline-block;padding:11px 16px;border:0;border-radius:8px;text-decoration:none;cursor:pointer}.primary{background:#2563eb;color:#fff}.dark{background:#111827;color:#fff}.error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:18px}@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}
    </style>
</head>
<body>
<div class="container">
<div class="card">
    <h1>✏️ Edit Food Item</h1>
    <a href="<?= e(APP_URL) ?>/owner/menu.php">← Back to Menu</a>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-top:20px">
        <?= csrfField() ?>
        <div class="grid">
            <div>
                <label>Food Name</label>
                <input type="text" name="name" maxlength="190" required value="<?= e($_POST['name'] ?? $item['name']) ?>">
            </div>

            <div>
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int)($_POST['category_id'] ?? $item['category_id']) === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Price</label>
                <input type="number" name="price" step="0.01" min="0" required value="<?= e($_POST['price'] ?? $item['price']) ?>">
            </div>

            <div>
                <label>Sort Order</label>
                <input type="number" name="sort_order" min="0" value="<?= e($_POST['sort_order'] ?? $item['sort_order']) ?>">
            </div>

            <div class="full">
                <label>Description</label>
                <textarea name="description"><?= e($_POST['description'] ?? $item['description']) ?></textarea>
            </div>

            <div class="full">
                <label>Video URL (optional)</label>
                <input type="url" name="video_url" placeholder="https://..." value="<?= e($_POST['video_url'] ?? ($item['video_url'] ?? '')) ?>">
            </div>

            <div class="full">
                <label>Food Image (optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($item['image'])): ?>
                    <div class="current-image">
                        <div>Current image:</div>
                        <img src="<?= e(APP_URL) ?>/public/uploads/foods/<?= e(basename($item['image'])) ?>" alt="Current food image">
                    </div>
                <?php endif; ?>
            </div>

            <div class="check">
                <input type="checkbox" id="is_popular" name="is_popular" <?= (isset($_POST['is_popular']) ? 'checked' : (!empty($item['is_popular']) ? 'checked' : '')) ?> >
                <label for="is_popular">Popular item</label>
            </div>

            <div class="check">
                <input type="checkbox" id="is_available" name="is_available" <?= (isset($_POST['is_available']) ? 'checked' : (!empty($item['is_available']) ? 'checked' : '')) ?> >
                <label for="is_available">Available</label>
            </div>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">💾 Save Changes</button>
            <a class="btn dark" href="<?= e(APP_URL) ?>/owner/menu.php">Cancel</a>
        </div>
    </form>
</div>
</div>
</body>
</html>
