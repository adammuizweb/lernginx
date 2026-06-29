<?php
// Partial expects: $cid, $name, $checked (bool), $statusLabel, $indent (int), $isReviewed (bool), $locked (bool), $parent_id
$parent_id = isset($parent_id) ? (int)$parent_id : 0;
$isTop = ($parent_id === 0);
?>
<div class="checkbox-item"
     style="margin-left:<?= (int)$indent ?>px"
     data-category-id="<?= (int)$cid ?>"
     data-parent-id="<?= $parent_id ?>"
     data-is-top="<?= $isTop ? '1' : '0' ?>"
     data-reviewed="<?= $isReviewed ? '1' : '0' ?>"
     data-locked="<?= $locked ? '1' : '0' ?>">
  <label>
    <input type="checkbox"
           name="modules[]"
           value="<?= htmlspecialchars($cid, ENT_QUOTES) ?>"
           <?= $checked ? 'checked' : '' ?>
           <?= $locked ? 'disabled' : '' ?>>
    <?= htmlspecialchars($name) ?> <?= $statusLabel ?>
  </label>

  <?php if ($checked): ?>
    <input type="hidden" name="modules_present[]" value="<?= htmlspecialchars($cid, ENT_QUOTES) ?>">
  <?php endif; ?>
</div>
