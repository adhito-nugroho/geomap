<?php
// Halaman login admin. Fase 1: autentikasi sesi + CSRF + password_verify.
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_check($token)) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman ini.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!auth_attempt($username, $password)) {
        // Pesan generik agar tidak membocorkan username mana yang ada
        $error = 'Username atau password salah.';
    } else {
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — WebGIS Perhutanan Sosial</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-emerald-950 flex items-center justify-center p-4">
<main class="w-full max-w-sm bg-white rounded-xl shadow-xl p-6">
  <div class="flex items-center gap-3 mb-1">
    <img src="../assets/logo.png" alt="Logo CDK Wilayah Bojonegoro" class="w-12 h-12 rounded-full object-cover" onerror="this.style.display='none'">
    <div>
      <h1 class="text-lg font-bold text-emerald-900 leading-tight">WebGIS Perhutanan Sosial</h1>
      <p class="text-xs text-emerald-700 font-medium">CDK Wilayah Bojonegoro</p>
    </div>
  </div>
  <p class="text-sm text-gray-500 mb-4">Login admin internal Cabang Dinas Kehutanan</p>

  <?php if ($error !== ''): ?>
    <div class="mb-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="login.php" class="space-y-3">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label class="block">
      <span class="text-sm font-medium">Username</span>
      <input name="username" required autocomplete="username"
             class="mt-1 w-full border rounded px-3 py-2" value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label class="block">
      <span class="text-sm font-medium">Password</span>
      <input type="password" name="password" required autocomplete="current-password"
             class="mt-1 w-full border rounded px-3 py-2">
    </label>
    <button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white rounded px-3 py-2 font-semibold">
      Masuk
    </button>
  </form>

  <p class="mt-4 text-xs text-gray-500">Kredensial diatur via <code>seed_*_password</code> di <code>config.php</code> lokal.</p>
  <p class="mt-2 text-xs"><a class="text-emerald-700 underline" href="../index.php">← Kembali ke viewer</a></p>
</main>
</body>
</html>
