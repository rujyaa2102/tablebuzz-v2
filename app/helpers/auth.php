<?php

declare(strict_types=1);

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']['id']);
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'cafe_id' => !empty($user['cafe_id']) ? (int) $user['cafe_id'] : null
    ];
}

function currentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function currentCafeId(): ?int
{
    $cafeId = $_SESSION['user']['cafe_id'] ?? null;
    return is_numeric($cafeId) && (int) $cafeId > 0 ? (int) $cafeId : null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/owner/login.php');
        exit;
    }
}

function requireOwner(): void
{
    global $pdo;

    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/owner/login.php');
        exit;
    }

    $userId = (int) ($_SESSION['user']['id'] ?? 0);
    $cafeId = (int) ($_SESSION['user']['cafe_id'] ?? 0);
    $role = (string) ($_SESSION['user']['role'] ?? '');

    if ($userId <= 0 || $cafeId <= 0 || $role !== 'owner') {
        logoutUser();
        header('Location: ' . APP_URL . '/owner/login.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT cu.id, cu.role, cu.status AS user_status, cu.cafe_id, c.status AS cafe_status FROM cafe_users cu INNER JOIN cafes c ON c.id = cu.cafe_id WHERE cu.id = :user_id AND cu.cafe_id = :cafe_id LIMIT 1');
        $stmt->execute([':user_id' => $userId, ':cafe_id' => $cafeId]);
        $owner = $stmt->fetch();

        if (!$owner || $owner['role'] !== 'owner' || $owner['user_status'] !== 'active' || $owner['cafe_status'] !== 'active') {
            logoutUser();
            header('Location: ' . APP_URL . '/owner/login.php?error=account');
            exit;
        }
    } catch (Throwable $e) {
        error_log('Owner authentication error: ' . $e->getMessage());
        http_response_code(500);
        exit('Authentication check failed.');
    }
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
