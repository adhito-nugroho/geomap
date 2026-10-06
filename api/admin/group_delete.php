<?php
// POST /api/admin/group_delete.php {csrf_token, id, confirm?}
// Hapus group; bila berisi layer, tolak kecuali confirm=1 (kaskade ikut menghapus layer+kelas).
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $id = v_int($in, 'id', 1, PHP_INT_MAX);

    $stmt = $pdo->prepare('SELECT id FROM layer_groups WHERE id = ? AND map_id = 1 LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        json_fail('Group tidak ditemukan.', 404);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM layers WHERE group_id = ?');
    $stmt->execute([$id]);
    $n = (int) $stmt->fetchColumn();
    if ($n > 0 && empty($in['confirm'])) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => "Group berisi {$n} layer. Hapus beserta seluruh isinya?",
            'layer_count' => $n,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM layer_groups WHERE id = ? AND map_id = 1');
    $stmt->execute([$id]);
    json_out(['ok' => true, 'deleted_layers' => $n]);
} catch (Throwable $e) {
    json_fail('Gagal menghapus group.', 500);
}
