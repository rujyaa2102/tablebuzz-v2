<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();

/*
|--------------------------------------------------------------------------
| Fetch Menu Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        m.*,
        c.name AS category_name,
        c.status AS category_status
    FROM menu_items m
    LEFT JOIN menu_categories c
        ON c.id = m.category_id
        AND c.cafe_id = m.cafe_id
    WHERE m.cafe_id = :cafe_id
    ORDER BY
        COALESCE(c.sort_order, 9999) ASC,
        m.sort_order ASC,
        m.id DESC
");

$stmt->execute([
    ':cafe_id' => $cafeId
]);

$items = $stmt->fetchAll();

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
    Menu - TableBuzz
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
    max-width: 1300px;
    margin: auto;
    padding: 30px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.header h1 {
    margin: 0 0 6px;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 8px;
    text-decoration: none;
    border: 0;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.dark {
    background: #111827;
    color: #fff;
}

.primary {
    background: #2563eb;
    color: #fff;
}

.light {
    background: #f3f4f6;
    color: #111827;
}

.danger {
    background: #dc2626;
    color: #fff;
}

.success {
    background: #16a34a;
    color: #fff;
}

.card {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    margin-top: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, .05);
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: middle;
    font-size: 13px;
}

th {
    background: #f8fafc;
    font-weight: 700;
}

.food-img {
    width: 80px;
    height: 65px;
    object-fit: cover;
    border-radius: 8px;
    display: block;
}

.food-name {
    font-weight: 700;
    font-size: 15px;
}

.description {
    display: block;
    max-width: 280px;
    margin-top: 5px;
    color: #6b7280;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.muted {
    color: #6b7280;
}

.status {
    font-weight: 700;
}

.yes {
    color: #15803d;
}

.no {
    color: #b91c1c;
}

.category-inactive {
    color: #b45309;
    font-size: 12px;
    font-weight: 600;
}

.inline {
    display: inline;
}

.actions-cell {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.small {
    padding: 7px 10px;
    font-size: 12px;
}

.message {
    padding: 13px 16px;
    border-radius: 8px;
    margin-top: 20px;
    background: #dcfce7;
    color: #166534;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #6b7280;
}

.price {
    font-weight: 700;
    white-space: nowrap;
}

</style>

</head>

<body>

<div class="container">


    <!-- Header -->

    <div class="header">

        <div>

            <h1>
                🍽️ Menu Management
            </h1>

            <div class="muted">
                Manage food items for your café.
            </div>

        </div>


        <div class="actions">

            <a
                class="btn dark"
                href="<?= e(APP_URL) ?>/owner/dashboard.php"
            >
                ← Dashboard
            </a>

            <a
                class="btn light"
                href="<?= e(APP_URL) ?>/owner/categories.php"
            >
                Categories
            </a>

            <a
                class="btn primary"
                href="<?= e(APP_URL) ?>/owner/add-menu.php"
            >
                + Add Food
            </a>

        </div>

    </div>


    <!-- Success Messages -->

    <?php if (isset($_GET['updated'])): ?>

        <div class="message">
            ✅ Menu item updated successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted'])): ?>

        <div class="message">
            🗑️ Menu item deleted successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['enabled'])): ?>

        <div class="message">
            ✅ Menu item enabled successfully.
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['disabled'])): ?>

        <div class="message">
            ⛔ Menu item disabled successfully.
        </div>

    <?php endif; ?>


    <!-- Menu Table -->

    <div class="card">

        <div class="table-wrap">

            <table>

                <thead>

                <tr>

                    <th>
                        Image
                    </th>

                    <th>
                        Food
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Popular
                    </th>

                    <th>
                        Availability
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

                </thead>


                <tbody>

                <?php if (!$items): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No food items found.
                            Add your first menu item.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($items as $item): ?>

                        <tr>


                            <!-- Image -->

                            <td>

                                <?php if (!empty($item['image'])): ?>

                                    <img
                                        class="food-img"
                                        src="<?= e(APP_URL) ?>/public/uploads/foods/<?= e(basename($item['image'])) ?>"
                                        alt="<?= e($item['name']) ?>"
                                    >

                                <?php else: ?>

                                    <span class="muted">
                                        No image
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Food -->

                            <td>

                                <div class="food-name">
                                    <?= e($item['name']) ?>
                                </div>

                                <?php if (!empty($item['description'])): ?>

                                    <span
                                        class="description"
                                        title="<?= e($item['description']) ?>"
                                    >
                                        <?= e($item['description']) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="muted">
                                        No description
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Category -->

                            <td>

                                <?php if (!empty($item['category_name'])): ?>

                                    <?= e($item['category_name']) ?>

                                    <?php if (
                                        isset($item['category_status']) &&
                                        $item['category_status'] !== 'active'
                                    ): ?>

                                        <br>

                                        <span class="category-inactive">
                                            ⚠ Category inactive
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="muted">
                                        Uncategorized
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Price -->

                            <td>

                                <span class="price">
                                    ₹<?= number_format(
                                        (float) $item['price'],
                                        2
                                    ) ?>
                                </span>

                            </td>


                            <!-- Popular -->

                            <td>

                                <?php if ((int) $item['is_popular'] === 1): ?>

                                    <span class="status yes">
                                        ⭐ Yes
                                    </span>

                                <?php else: ?>

                                    <span class="status no">
                                        No
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Availability -->

                            <td>

                                <?php if ((int) $item['is_available'] === 1): ?>

                                    <span class="status yes">
                                        Available
                                    </span>

                                <?php else: ?>

                                    <span class="status no">
                                        Unavailable
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="actions-cell">


                                    <!-- Edit -->

                                    <a
                                        class="btn small light"
                                        href="<?= e(APP_URL) ?>/owner/edit-menu.php?id=<?= (int) $item['id'] ?>"
                                    >
                                        Edit
                                    </a>


                                    <!-- Toggle -->

                                    <form
                                        class="inline"
                                        method="POST"
                                        action="<?= e(APP_URL) ?>/owner/toggle-menu.php"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $item['id'] ?>"
                                        >

                                        <?php if ((int) $item['is_available'] === 1): ?>

                                            <button
                                                class="btn small dark"
                                                type="submit"
                                            >
                                                Disable
                                            </button>

                                        <?php else: ?>

                                            <button
                                                class="btn small success"
                                                type="submit"
                                            >
                                                Enable
                                            </button>

                                        <?php endif; ?>

                                    </form>


                                    <!-- Delete -->

                                    <form
                                        class="inline"
                                        method="POST"
                                        action="<?= e(APP_URL) ?>/owner/delete-menu.php"
                                        onsubmit="return confirm('Delete this food item permanently?');"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $item['id'] ?>"
                                        >

                                        <button
                                            class="btn small danger"
                                            type="submit"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>