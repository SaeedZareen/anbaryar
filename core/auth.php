<?php
require_once __DIR__ . '/db.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (!is_logged_in()) {
        return null;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.*, w.name AS warehouse_name, w.type AS warehouse_type FROM users u LEFT JOIN warehouses w ON w.id = u.warehouse_id WHERE u.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function login(string $username, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }
    if (!password_verify($password, $user['password'])) {
        return false;
    }
    $_SESSION['user_id'] = $user['id'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function user_can_access_warehouse(?array $user, string $warehouseType): bool
{
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'admin') {
        return true;
    }
    return $user['warehouse_type'] === $warehouseType;
}
