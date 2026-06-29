<?php
function render_category_checkbox(array $nodes, array $tree, array $userModules, int $defaultStatus = 0, int $indent = 0): void {
    $partialPath = __DIR__ . '/../category_checkbox_item.php'; // relatif terhadap file ini

    foreach ($nodes as $n) {
        $cid = (int)$n['id'];
        $name = $n['name'];
        $parent_id = isset($n['parent_id']) ? (int)$n['parent_id'] : 0;

        $hasRegistration = array_key_exists($cid, $userModules);
        $status = $hasRegistration ? (int)$userModules[$cid]['status'] : null;
        $isReviewed = $hasRegistration ? (bool)$userModules[$cid]['is_reviewed'] : false;

        // checked: tampil tercentang bila user sudah pernah mendaftar (status != 2)
        $checked = $hasRegistration && $status !== 2;

        // locked: hanya jika sistem pending dan status modul adalah 1 (pending)
        $locked = ($defaultStatus === 1) && ($status === 1);

        $statusLabel = '';
        if ($hasRegistration) {
            if ($status === 0) {
                $statusLabel = '<span class="status-label status-aktif">✅ Active</span>';
            } elseif ($status === 1) {
                $statusLabel = '<span class="status-label status-pending">⏳ Pending</span>';
            } elseif ($status === 2) {
                $statusLabel = '<span class="status-label">🔁 Dibatalkan</span>';
            }
        }

        // tambahkan indikasi reviewed ke statusLabel jika ada
        if ($isReviewed) {
            $statusLabel .= ' <span class="badge badge-secondary">Direview</span>';
        } else if ($hasRegistration) {
            $statusLabel .= ' <span class="badge badge-light">Belum direview</span>';
        }

        if (is_file($partialPath)) {
            // partial mengharapkan variabel lokal: $cid, $name, $checked, $statusLabel, $indent, $isReviewed, $locked, $parent_id
            include $partialPath;
        } else {
            echo '<div class="checkbox-item" style="margin-left:' . (int)$indent . 'px" data-category-id="' . $cid . '" data-parent-id="' . $parent_id . '" data-reviewed="' . ($isReviewed ? '1' : '0') . '">';
            echo '<label>';
            echo '<input type="checkbox" name="modules[]" value="' . htmlspecialchars($cid, ENT_QUOTES) . '" '
                . ($checked ? 'checked ' : '')
                . ($locked ? 'disabled ' : '')
                . '>';
            echo htmlspecialchars($name) . ' ' . $statusLabel;
            echo '</label>';
            if ($checked) {
                echo '<input type="hidden" name="modules_present[]" value="' . htmlspecialchars($cid, ENT_QUOTES) . '">';
            }
            echo '</div>';
        }

        if (isset($tree[$cid])) {
            render_category_checkbox($tree[$cid], $tree, $userModules, $defaultStatus, $indent + 20);
        }
    }
}
