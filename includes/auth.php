<?php
// Penjagaan halaman admin: login + role.
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** Coba login. Return true jika username+password cocok. */
function auth_attempt(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    // Cegah session fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['role'] = $row['role'];
    return true;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? 'viewer',
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

/** Paksa harus login, kalau belum lempar ke login. */
function require_login(): void
{
    if (!is_logged_in()) {
        // Path relatif agar works di geomap.test maupun localhost/geomap
        redirect('login.php');
    }
}

/** Paksa harus admin. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses ditolak: butuh peran admin.');
    }
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
