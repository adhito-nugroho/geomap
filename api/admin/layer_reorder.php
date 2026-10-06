<?php
// POST /api/admin/layer_reorder.php {csrf_token, groups:[{id,urutan}], layers:[{id,group_id,urutan}]}
// Simpan urutan hasil drag & drop (SortableJS). Layer boleh pindah group.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

try {
    $in = admin_input();
    $pdo = db();
    $groups = $in['groups'] ?? [];
    $layers = $in['layers'] ?? [];
    if (!is_array($groups) || !is_array($layers)) {
        json_fail('Format urutan tidak valid.');
    }

    $pdo->beginTransaction();
    $stmtG = $pdo->prepare('UPDATE layer_groups SET urutan = ? WHERE id = ? AND map_id = 1');
    foreach ($groups as $g) {
        if (!is_array($g) || !isset($g['id'], $g['urutan'])) {
            throw new RuntimeException('Format groups tidak valid.');
        }
        $stmtG->execute([(int) $g['urutan'], (int) $g['id']]);
    }
    $stmtChkG = $pdo->prepare('SELECT id FROM layer_groups WHERE id = ? AND map_id = 1 LIMIT 1');
    $stmtL = $pdo->prepare('UPDATE layers SET group_id = ?, urutan = ? WHERE id = ?');
    $stmtChkL = $pdo->prepare(
        'SELECT l.id FROM layers l JOIN layer_groups g ON g.id = l.group_id
         WHERE l.id = ? AND g.map_id = 1 LIMIT 1'
    );
    foreach ($layers as $l) {
        if (!is_array($l) || !isset($l['id'], $l['group_id'], $l['urutan'])) {
            throw new RuntimeException('Format layers tidak valid.');
        }
        $stmtChkG->execute([(int) $l['group_id']]);
        if (!$stmtChkG->fetchColumn()) {
            throw new RuntimeException('Group tujuan tidak valid.');
        }
        $stmtChkL->execute([(int) $l['id']]);
        if (!$stmtChkL->fetchColumn()) {
            throw new RuntimeException('Layer tidak valid.');
        }
        $stmtL->execute([(int) $l['group_id'], (int) $l['urutan'], (int) $l['id']]);
    }
    $pdo->commit();
    json_out(['ok' => true]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail($e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Gagal menyimpan urutan.', 500);
}
