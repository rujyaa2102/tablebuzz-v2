<?php

require_once __DIR__ . '/../app/config/bootstrap.php';

requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Invalid café ID.');
}

$stmt = $pdo->prepare("\n    SELECT id, name, status\n    FROM cafes\n    WHERE id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $id]);
$cafe = $stmt->fetch();

if (!$cafe) {
    http_response_code(404);
    exit('Café not found.');
}

$newStatus = $cafe['status'] === 'active' ? 'inactive' : 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare("\n            UPDATE cafes\n            SET status = :status, updated_at = NOW()\n            WHERE id = :id\n        ");
        $update->execute([
            ':status' => $newStatus,
            ':id' => $id
        ]);

        $audit = $pdo->prepare("\n            INSERT INTO audit_logs (\n                cafe_id, admin_id, action, entity_type, entity_id,\n                ip_address, user_agent, metadata, created_at\n            ) VALUES (\n                :cafe_id, :admin_id, 'cafe_status_changed', 'cafe', :entity_id,\n                :ip_address, :user_agent, :metadata, NOW()\n            )\n        ");
        $audit->execute([
            ':cafe_id' => $id,
            ':admin_id' => currentAdmin()['id'] ?? null,
            ':entity_id' => $id,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':metadata' => json_encode([
                'old_status' => $cafe['status'],
                'new_status' => $newStatus,
                'cafe_name' => $cafe['name']
            ], JSON_UNESCAPED_UNICODE)
        ]);

        $pdo->commit();

        header('Location: ' . APP_URL . '/admin/cafes.php?updated=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Toggle Cafe Error: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to update café status.');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Café Status - TableBuzz</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#1f2937}.container{max-width:520px;margin:80px auto;padding:20px}.card{background:#fff;padding:30px;border-radius:14px;box-shadow:0 4px 15px rgba(0,0,0,.05)}.warning{background:#fff7ed;color:#9a3412;padding:14px;border-radius:8px;margin:20px 0}.buttons{display:flex;gap:10px}.btn{padding:10px 16px;border-radius:8px;border:0;text-decoration:none;cursor:pointer}.primary{background:#2563eb;color:#fff}.dark{background:#111827;color:#fff}
</style>
</head>
<body>
<div class="container"><div class="card">
<h2>Change Café Status</h2>
<p>Café: <strong><?= e($cafe['name']) ?></strong></p>
<p>Current status: <strong><?= e(ucfirst($cafe['status'])) ?></strong></p>
<div class="warning">The new status will be <strong><?= e(ucfirst($newStatus)) ?></strong>.</div>
<form method="POST">
<?= csrfField() ?>
<div class="buttons">
<button class="btn primary" type="submit">Confirm Change</button>
<a class="btn dark" href="<?= e(APP_URL) ?>/admin/cafes.php">Cancel</a>
</div>
</form>
</div></div>
</body>
</html>
