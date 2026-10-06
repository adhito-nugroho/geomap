<?php
// Logout admin (POST + CSRF agar tidak bisa di-CSRF via <img>).
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? null)) {
    auth_logout();
}
redirect('login.php');
