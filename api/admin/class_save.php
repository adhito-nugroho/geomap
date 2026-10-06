<?php
// POST /api/admin/class_save.php -> tambah/ubah/hapus satu baris layer_classes.
// {csrf_token, id?, layer_id, nilai, label, warna, outline_warna, urutan} atau {csrf_token, id, delete:1}
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();

    // Hapus satu kelas
    if (!empty($in['delete']) && !empty($in['id'])) {
        $id = v_int($in, 'id', 1, PHP_INT_MAX);
        $stmt = $pdo->prepare(
            'DELETE c FROM layer_classes c JOIN layers l ON l.id = c.layer_id
             JOIN layer_groups g ON g.id = l.group_id
             WHERE c.id = ? AND g.map_id = 1'
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            json_fail('Kelas tidak ditemukan.', 404);
        }
        json_out(['ok' => true, 'deleted' => true]);
    }

    $layer = v_layer($pdo, $in['layer_id'] ?? null);
    $nilai = v_str($in, 'nilai', 100);
    $label = v_str($in, 'label', 150);
    $warna = v_color($in, 'warna');
    $outline = v_color($in, 'outline_warna');
    $urutan = v_int($in, 'urutan', 0, 100000);

    if (!empty($in['id'])) {
        $id = v_int($in, 'id', 1, PHP_INT_MAX);
        $stmt = $pdo->prepare(
            'UPDATE layer_classes SET nilai = ?, label = ?, warna = ?, outline_warna = ?, urutan = ?
             WHERE id = ? AND layer_id = ?'
        );
        $stmt->execute([$nilai, $label, $warna, $outline, $urutan, $id, (int) $layer['id']]);
        if ($stmt->rowCount() === 0) {
            // Baris tidak berubah atau tidak ada; pastikan ada
            $chk = $pdo->prepare('SELECT id FROM layer_classes WHERE id = ? AND layer_id = ? LIMIT 1');
            $chk->execute([$id, (int) $layer['id']]);
            if (!$chk->fetchColumn()) {
                json_fail('Kelas tidak ditemukan.', 404);
            }
        }
        json_out(['ok' => true, 'id' => $id]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO layer_classes (layer_id, nilai, label, warna, outline_warna, urutan)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), warna = VALUES(warna),
             outline_warna = VALUES(outline_warna), urutan = VALUES(urutan)'
    );
    $stmt->execute([(int) $layer['id'], $nilai, $label, $warna, $outline, $urutan]);
    $newId = (int) $pdo->lastInsertId();
    if ($newId === 0) {
        $chk = $pdo->prepare('SELECT id FROM layer_classes WHERE layer_id = ? AND nilai = ? LIMIT 1');
        $chk->execute([(int) $layer['id'], $nilai]);
        $newId = (int) $chk->fetchColumn();
    }
    json_out(['ok' => true, 'id' => $newId]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        json_fail('Nilai kelas bentrok dengan baris lain.', 409);
    }
    json_fail('Gagal menyimpan kelas.', 500);
} catch (Throwable $e) {
    json_fail('Gagal menyimpan kelas.', 500);
}
