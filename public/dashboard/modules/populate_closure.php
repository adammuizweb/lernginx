<?php
// path/populate_closure.php
require_once __DIR__ . '/../../includes/bootstrap.php'; // provides $pdo

// insert self-links
$pdo->exec("INSERT IGNORE INTO categories_closure (ancestor_id, descendant_id, depth) SELECT id, id, 0 FROM categories");

// collect all parent->child edges
$stmtEdges = $pdo->query("SELECT id, parent_id FROM categories WHERE parent_id IS NOT NULL");
$edges = $stmtEdges->fetchAll(PDO::FETCH_ASSOC);

// build adjacency list
$children = [];
foreach ($edges as $e) $children[(int)$e['parent_id']][] = (int)$e['id'];

// function to walk ancestors for a node
function addClosureForNode(PDO $pdo, int $nodeId, array $children) {
    // stack of ancestors to process: pairs (ancestor, descendant, depth)
    $stack = [];
    // walk up via parent lookups using categories table
    $cur = $nodeId;
    while (true) {
        $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
        $stmt->execute([$cur]);
        $parent = $stmt->fetchColumn();
        if (!$parent) break;
        $stack[] = (int)$parent;
        $cur = (int)$parent;
    }
    // insert closure rows: for each ancestor A in stack, (A, nodeId, depth)
    $depth = 1;
    foreach ($stack as $ancestor) {
        $ins = $pdo->prepare("INSERT IGNORE INTO categories_closure (ancestor_id, descendant_id, depth) VALUES (?, ?, ?)");
        $ins->execute([$ancestor, $nodeId, $depth]);
        $depth++;
    }
}

// process every category id once
$stmtIds = $pdo->query("SELECT id FROM categories");
while ($row = $stmtIds->fetch(PDO::FETCH_ASSOC)) {
    $id = (int)$row['id'];
    addClosureForNode($pdo, $id, $children);
}

echo "categories_closure populated\n";
