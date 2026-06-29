<?php
/**
 * registerModules:
 * dashboard/modules/register_module.php
 * - $selectedCategoryIds: array of category ids user explicitly chose (children or parents).
 * - $defaultStatus: 0 = aktif, 1 = pending
 *
 * Fungsi akan:
 * - mengumpulkan semua ancestor dari kategori terpilih (menggunakan categories_closure)
 * - insert modul yang belum ada dan update yang pernah ada (mis. reaktivasi dari status 2)
 */
function registerModules(PDO $pdo, int $userId, array $selectedCategoryIds, int $defaultStatus = 0): void {
    if (empty($selectedCategoryIds)) {
        return;
    }

    // Ambil semua ancestor dari kategori yang dipilih (termasuk kategori itu sendiri)
    $placeholders = implode(',', array_fill(0, count($selectedCategoryIds), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT ancestor_id
        FROM categories_closure
        WHERE descendant_id IN ($placeholders)
    ");
    $stmt->execute($selectedCategoryIds);
    $ancestorIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Gabungkan kategori yang dipilih + ancestor-nya
    $allCategoryIds = array_unique(array_merge($selectedCategoryIds, $ancestorIds));
    if (empty($allCategoryIds)) return;

    // Ambil semua modul yang sudah ada untuk user ini (untuk kategori2 relevan)
    $placeholders = implode(',', array_fill(0, count($allCategoryIds), '?'));
    $checkStmt = $pdo->prepare("
        SELECT category_id, status
        FROM modules
        WHERE user_id = ? AND category_id IN ($placeholders)
    ");
    $checkStmt->execute(array_merge([$userId], $allCategoryIds));
    $existing = [];
    foreach ($checkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int)$row['category_id']] = (int)$row['status'];
    }

    // Siapkan statement insert dan update
    $insertStmt = $pdo->prepare("
        INSERT INTO modules (user_id, category_id, status, is_reviewed, created_at, updated_at)
        VALUES (?, ?, ?, 0, NOW(), NOW())
    ");
    $updateStmt = $pdo->prepare("
        UPDATE modules
        SET status = ?, is_reviewed = 0, updated_at = NOW()
        WHERE user_id = ? AND category_id = ?
    ");

    foreach ($allCategoryIds as $cid) {
        $cid = (int)$cid;

        if (!isset($existing[$cid])) {
            // Modul belum ada → insert baru
            $insertStmt->execute([$userId, $cid, $defaultStatus]);
        } elseif ($existing[$cid] !== $defaultStatus) {
            // Jika status sebelumnya adalah dibatalkan (2), izinkan reaktivasi meskipun defaultStatus === 1
            if ($existing[$cid] === 2) {
                $updateStmt->execute([$defaultStatus, $userId, $cid]);
            }
            // Jika status sebelumnya bukan 2 dan sistem pending, jangan ubah
            elseif ($defaultStatus === 1) {
                continue;
            }
            // Jika sistem bebas, izinkan update
            else {
                $updateStmt->execute([$defaultStatus, $userId, $cid]);
            }
        }
    }
}
