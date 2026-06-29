<?php
// fungsi dashboard/student/daftar.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../modules/register_module.php';

date_default_timezone_set('UTC');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = get_user_from_session($pdo);
if (!$user) {
    http_response_code(403);
    exit('Access denied');
}

// sebelum pengembalian header redirect, tentukan target redirect balik:
$redirectBack = '/dashboard/student/';
if (!empty($_POST['from']) && $_POST['from'] === 'beranda_notice_bottom') {
    $redirectBack = '/dashboard/';
}

// Ambil kebijakan sistem
$policyStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'default_student_module_status' LIMIT 1");
$policyStmt->execute();
$policy = $policyStmt->fetchColumn();
$defaultStatus = ($policy !== false) ? (int)$policy : 0;
$defaultStatus = in_array($defaultStatus, [0, 1], true) ? $defaultStatus : 0;
$isPending = ($defaultStatus === 1);

// Ambil max parent limit (default 2)
$limitStmt = $pdo->prepare("SELECT value FROM registration_policies WHERE key_name = 'max_parent_modules_per_student' LIMIT 1");
$limitStmt->execute();
$limitVal = $limitStmt->fetchColumn();
$maxParentLimit = ($limitVal !== false) ? max(1, (int)$limitVal) : 2;

// ----------------------- INPUT VALIDATION & RACE-PROOFING -----------------------

// Ambil input mentah dari form (integer-sanitized)
$selected_raw = $_POST['modules'] ?? [];
$selected_raw = array_values(array_unique(array_filter(array_map('intval', (array)$selected_raw))));

$present_raw = $_POST['modules_present'] ?? [];
$present_raw = array_values(array_unique(array_filter(array_map('intval', (array)$present_raw))));

// Validasi: hanya terima category id yang benar-benar ada di DB
$selected = [];
$present = [];

$allCandidate = array_values(array_unique(array_merge($selected_raw, $present_raw)));
if (!empty($allCandidate)) {
    $placeholders = implode(',', array_fill(0, count($allCandidate), '?'));
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id IN ($placeholders)");
    $stmt->execute($allCandidate);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $validMap = array_flip($validIds);

    // filter submitted lists
    foreach ($selected_raw as $v) if (isset($validMap[$v])) $selected[] = $v;
    foreach ($present_raw as $v) if (isset($validMap[$v])) $present[] = $v;

    // optional logging if someone tried to submit invalid ids
    if (count($selected_raw) !== count($selected) || count($present_raw) !== count($present)) {
        error_log("modules[] validation: user={$user['id']} submitted invalid category ids. submitted=" . json_encode($selected_raw) . " present=" . json_encode($present_raw) . " valid=" . json_encode($validIds));
    }
}

// Jika tidak ada perubahan, kembali (cegah melanjutkan)
if (empty($selected) && empty($present)) {
    header('Location: ' . $redirectBack);
    exit;
}

// Hitung perubahan berdasarkan input yang sudah tervalidasi
$toAdd = array_values(array_diff($selected, $present));
$toRemove = array_values(array_diff($present, $selected));

// Acquire advisory lock (MySQL GET_LOCK) untuk mencegah race condition
$lockName = "user_modules_lock_" . (int)$user['id'];
$gotLock = false;
$lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 6)"); // 6s timeout
$lockStmt->execute([$lockName]);
$gotLock = (bool)$lockStmt->fetchColumn();

if (!$gotLock) {
    // jika gagal ambil lock, tolak sementara dan arahkan balik
    $_SESSION['flash_message'] = 'Sistem sedang sibuk. Silakan coba beberapa saat lagi.';
    header('Location: ' . $redirectBack);
    exit;
}

// NOTE: pastikan lock akan dilepas di finally/catch (dibereskan di bawah setelah try/catch)

/**
 * Helper: ambil daftar "top-level" parent id dari daftar kategori
 * (memakai categories_closure + join categories c where c.parent_id IS NULL)
 */
function get_top_parents(PDO $pdo, array $categoryIds): array {
    if (empty($categoryIds)) return [];
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $sql = "
        SELECT DISTINCT cc.ancestor_id
        FROM categories_closure cc
        JOIN categories c ON cc.ancestor_id = c.id
        WHERE cc.descendant_id IN ($placeholders)
          AND c.parent_id IS NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($categoryIds);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

try {
    // Ambil current active category ids untuk user (status != 2)
    $curStmt = $pdo->prepare("SELECT category_id FROM modules WHERE user_id = ? AND status != 2");
    $curStmt->execute([$user['id']]);
    $currentCategoryIds = array_map('intval', $curStmt->fetchAll(PDO::FETCH_COLUMN));

    $currentParents = get_top_parents($pdo, $currentCategoryIds);
    $parentsOfRemove = get_top_parents($pdo, $toRemove);
    $parentsOfAdd = get_top_parents($pdo, $toAdd);

    // Compute resulting parent set after applying add/remove
    $remainingParents = array_values(array_diff($currentParents, $parentsOfRemove));
    $resultingParents = array_unique(array_merge($remainingParents, $parentsOfAdd));

    if (count($resultingParents) > $maxParentLimit) {
        $_SESSION['flash_message'] = 'Registration failed: maximum parent modules is ' . $maxParentLimit . '. Please adjust your selection.';
        header('Location: ' . $redirectBack);
        exit;
    }

    $pdo->beginTransaction();

    // Tambahkan modul baru atau aktifkan kembali
    if (!empty($toAdd)) {
        registerModules($pdo, $user['id'], $toAdd, $defaultStatus);
    }

    // Ambil status modul yang akan dibatalkan
    $lockedIds = [];
    if (!empty($toRemove)) {
        $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
        $selectStmt = $pdo->prepare("
            SELECT id, status FROM modules 
            WHERE user_id = ? AND category_id IN ($placeholders)
        ");
        $selectParams = array_merge([$user['id']], $toRemove);
        $selectStmt->execute($selectParams);
        $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $mid = (int)$row['id'];
            $status = (int)$row['status'];

            // Jika sistem pending dan status modul adalah pending, jangan izinkan pembatalan
            if ($isPending && $status === 1) {
                $lockedIds[] = $mid;
                continue;
            }

            $cancelStmt = $pdo->prepare("
                UPDATE modules 
                SET status = 2, is_reviewed = 0, updated_at = NOW() 
                WHERE id = ?
            ");
            $cancelStmt->execute([$mid]);
        }
    }

    $pdo->commit();

    if (!empty($lockedIds)) {
        $_SESSION['flash_message'] = 'Some modules cannot be cancelled because they are pending approval.';
    } else {
        $_SESSION['flash_message'] = ($defaultStatus === 0)
            ? 'Changes saved successfully'
            : 'Changes pending approval';
    }

    header('Location: ' . $redirectBack);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Modul gagal diproses: ' . $e->getMessage());
    http_response_code(500);
    echo 'Failed to process module registration.';
    exit;
} finally {
    // release advisory lock jika diperoleh
    if (!empty($gotLock) && !empty($lockName)) {
        try {
            $releaseStmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
            $releaseStmt->execute([$lockName]);
        } catch (Exception $inner) {
            // jangan ganggu respons utama jika gagal melepaskan lock,
            // tapi catat agar bisa investigasi
            error_log('Failed to release lock ' . $lockName . ' : ' . $inner->getMessage());
        }
    }
}
