<?php
// POST /api/admin/group_save.php {csrf_token, id?, nama} -> tambah/ubah group (map 1).
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $nama = v_str($in, 'nama', 100);

    if (!empty($in['id'])) {
        $id = v_int($in, 'id', 1, PHP_INT_MAX);
        $stmt = $pdo->prepare('UPDATE layer_groups SET nama = ? WHERE id = ? AND map_id = 1');
        $stmt->execute([$nama, $id]);
        if ($stmt->rowCount() === 0) {
            json_fail('Group tidak ditemukan.', 404);
        }
        json_out(['ok' => true, 'id' => $id]);
    }

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM layer_groups WHERE map_id = 1');
    $stmt->execute();
    $urut = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO layer_groups (map_id, nama, urutan) VALUES (1, ?, ?)');
    $stmt->execute([$nama, $urut]);
    json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_fail('Nama group sudah ada di peta ini.', 409);
    }
    json_fail('Gagal menyimpan group.', 500);
} catch (Throwable $e) {
    json_fail('Gagal menyimpan group.', 500);
}
