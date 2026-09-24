<?php

declare(strict_types=1);

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin']['id']);
}

function currentAdmin(): ?array
{
    return isAdminLoggedIn() ? $_SESSION['admin'] : null;
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn() || (int) ($_SESSION['admin']['id'] ?? 0) <= 0) {
        logoutAdmin();
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}

function loginAdmin(array $admin): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['admin'] = [
        'id' => (int) ($admin['id'] ?? 0),
        'name' => (string) ($admin['name'] ?? ''),
        'email' => (string) ($admin['email'] ?? '')
    ];
}

function logoutAdmin(): void
{
    unset($_SESSION['admin']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
