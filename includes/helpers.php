<?php
// Helper umum: escape output, CSRF, redirect.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Cookie aman untuk pemakaian internal via Cloudflare Tunnel (https)
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/** Escape HTML. Pakai untuk SEMUA output dari DB / GeoJSON. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ambil / buat CSRF token sesi. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validasi CSRF token dari POST. */
function csrf_check(?string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Redirect aman (hanya path lokal). */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Field atribut GeoJSON yang disembunyikan di popup identify (dikonfigurasi di sini, Fase 3). */
function hidden_fields(): array
{
    return ['OBJECTID', 'ObjectID', 'Shape_Area', 'Shape_Leng', 'Shape_Length', 'SHAPE_Area', 'SHAPE_Length', 'Id', 'ID'];
}
