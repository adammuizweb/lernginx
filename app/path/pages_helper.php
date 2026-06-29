<?php
// includes/helpers_pages.php
// Assumes $pdo is a PDO instance connected to the DB (InnoDB, utf8mb4).
// All functions accept $pdo as first param to avoid globals.

date_default_timezone_set('UTC');

/**
 * Slugify sederhana.
 */
// --- pages_slugify: wrapper yang aman (pakai global slugify() jika tersedia)
if (! function_exists('pages_slugify')) {
    function pages_slugify(string $text): string {
        // gunakan fungsi slugify global jika ada (agar konsisten dengan posts)
        if (function_exists('slugify')) {
            return slugify($text);
        }

        // fallback (mirip implementasi sebelumnya)
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($trans !== false) $text = $trans;
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if ($text === '') return 'item';
        return $text;
    }
}

/**
 * Ensure slug unique for pages. Appends -1, -2, ... if needed.
 * $excludeId used when editing so we don't clash with the current row.
 */
function unique_page_slug(PDO $pdo, string $slugBase, ?int $excludeId = null): string {
    $slug = slugify($slugBase);
    $i = 0;
    while (true) {
        $candidate = $i === 0 ? $slug : ($slug . '-' . $i);
        if ($excludeId !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :slug AND id != :id LIMIT 1");
            $stmt->execute([':slug' => $candidate, ':id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $candidate]);
        }
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt === 0) return $candidate;
        $i++;
    }
}

/**
 * Create a new page. Returns array ['success'=>bool, 'id'=>int|null, 'error'=>string|null]
 * $data keys: title, slug (optional), content, excerpt (optional), thumbnail (optional), status, created_by
 */
function page_create(PDO $pdo, array $data): array {
    try {
        $pdo->beginTransaction();

        if (empty($data['title']) || empty($data['content']) || empty($data['created_by'])) {
            throw new Exception('Title, content, and created_by are required.');
        }

        $slug = $data['slug'] ?? slugify($data['title']);
        $slug = unique_page_slug($pdo, $slug);

        $sql = "INSERT INTO pages (title, slug, content, excerpt, thumbnail, status, created_by, created_at)
                VALUES (:title, :slug, :content, :excerpt, :thumbnail, :status, :created_by, :created_at)";
        $stmt = $pdo->prepare($sql);
        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $slug,
            ':content' => $data['content'],
            ':excerpt' => $data['excerpt'] ?? null,
            ':thumbnail' => $data['thumbnail'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':created_by' => (int)$data['created_by'],
            ':created_at' => $now,
        ]);

        $id = (int)$pdo->lastInsertId();

        // optionally sync tags if provided (accepts array of tag names or slugs)
        if (!empty($data['tags']) && is_array($data['tags'])) {
            tags_sync_for_page($pdo, $id, $data['tags']);
        }

        $pdo->commit();
        return ['success' => true, 'id' => $id, 'error' => null];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'id' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Update page by id. $data same shape as create. If you pass slug it will be made unique.
 */
function page_update(PDO $pdo, int $id, array $data): array {
    try {
        $pdo->beginTransaction();

        // fetch existing
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new Exception('Page not found.');

        $title = $data['title'] ?? $existing['title'];
        $content = $data['content'] ?? $existing['content'];
        $excerpt = array_key_exists('excerpt', $data) ? $data['excerpt'] : $existing['excerpt'];
        $thumbnail = array_key_exists('thumbnail', $data) ? $data['thumbnail'] : $existing['thumbnail'];
        $status = $data['status'] ?? $existing['status'];

        if (isset($data['slug']) && $data['slug'] !== '') {
            $slug = unique_page_slug($pdo, $data['slug'], $id);
        } else {
            // if title changed and no explicit slug passed, keep original slug (common behavior)
            $slug = $existing['slug'];
        }

        $sql = "UPDATE pages SET title = :title, slug = :slug, content = :content, excerpt = :excerpt, thumbnail = :thumbnail, status = :status, updated_at = :updated_at WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':content' => $content,
            ':excerpt' => $excerpt,
            ':thumbnail' => $thumbnail,
            ':status' => $status,
            ':updated_at' => $now,
            ':id' => $id,
        ]);

        // sync tags if provided (if not provided, do nothing)
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            tags_sync_for_page($pdo, $id, $data['tags']);
        }

        $pdo->commit();
        return ['success' => true, 'id' => $id, 'error' => null];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'id' => $id, 'error' => $e->getMessage()];
    }
}

/**
 * Soft delete page: sets is_deleted = 1 and deleted_at.
 */
function page_soft_delete(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("UPDATE pages SET is_deleted = 1, deleted_at = :deleted_at WHERE id = :id");
        $stmt->execute([':deleted_at' => date('Y-m-d H:i:s'), ':id' => $id]);
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore soft-deleted page.
 */
function page_restore(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("UPDATE pages SET is_deleted = 0, deleted_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Hard delete page (removes row). Use with care.
 */
function page_hard_delete(PDO $pdo, int $id): array {
    try {
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get single page with tags joined. $by may be 'id' or 'slug'.
 */
function page_get(PDO $pdo, $by, $value, bool $includeDeleted = false) {
    $whereDeleted = $includeDeleted ? '1=1' : '(p.is_deleted = 0 OR p.is_deleted IS NULL)';
    if ($by === 'id') {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username AS author_name
            FROM pages p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = :val AND $whereDeleted
            LIMIT 1
        ");
        $stmt->execute([':val' => (int)$value]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username AS author_name
            FROM pages p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.slug = :val AND $whereDeleted
            LIMIT 1
        ");
        $stmt->execute([':val' => (string)$value]);
    }
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) return null;

    // load tags
    $tstmt = $pdo->prepare("
        SELECT t.*
        FROM tags t
        JOIN page_tag pt ON pt.tag_id = t.id
        WHERE pt.page_id = :pid
        ORDER BY t.name ASC
    ");
    $tstmt->execute([':pid' => $page['id']]);
    $page['tags'] = $tstmt->fetchAll(PDO::FETCH_ASSOC);
    return $page;
}

/**
 * Build and return paginated list of pages based on $opts:
 * opts: per_page, page, status, q (search), created_from, created_to, updated_from, updated_to, author_id, includeDeleted (bool)
 * returns ['items'=>[], 'total'=>int, 'per_page'=>int, 'page'=>int, 'total_pages'=>int]
 */
function pages_list(PDO $pdo, array $opts = []): array {
    $per_page = isset($opts['per_page']) ? (int)$opts['per_page'] : 10;
    $page = isset($opts['page']) && (int)$opts['page'] > 0 ? (int)$opts['page'] : 1;
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if (!($opts['includeDeleted'] ?? false)) {
        $where[] = "(p.is_deleted = 0 OR p.is_deleted IS NULL)";
    }

    if (!empty($opts['status'])) {
        $where[] = "p.status = :status";
        $params[':status'] = $opts['status'];
    }
    if (!empty($opts['author_id'])) {
        $where[] = "p.created_by = :author_id";
        $params[':author_id'] = (int)$opts['author_id'];
    }
    if (!empty($opts['tag'])) {
    $where[] = "p.id IN (
        SELECT pt.page_id
        FROM page_tag pt
        JOIN tags t ON t.id = pt.tag_id
        WHERE t.name LIKE :tag
    )";
    $params[':tag'] = '%' . $opts['tag'] . '%';
}
    if (!empty($opts['created_from'])) {
        $where[] = "p.created_at >= :created_from";
        $params[':created_from'] = $opts['created_from'] . ' 00:00:00';
    }
    if (!empty($opts['created_to'])) {
        $where[] = "p.created_at <= :created_to";
        $params[':created_to'] = $opts['created_to'] . ' 23:59:59';
    }
    if (!empty($opts['updated_from'])) {
        $where[] = "p.updated_at >= :updated_from";
        $params[':updated_from'] = $opts['updated_from'] . ' 00:00:00';
    }
    if (!empty($opts['updated_to'])) {
        $where[] = "p.updated_at <= :updated_to";
        $params[':updated_to'] = $opts['updated_to'] . ' 23:59:59';
    }
    if (!empty($opts['q'])) {
        $where[] = "(p.title LIKE :q_title OR p.slug LIKE :q_slug OR p.content LIKE :q_content)";
        $params[':q_title'] = '%' . $opts['q'] . '%';
        $params[':q_slug'] = '%' . $opts['q'] . '%';
        $params[':q_content'] = '%' . $opts['q'] . '%';
    }

    $where_sql = count($where) ? implode(' AND ', $where) : '1=1';

    // count
    $countSql = "SELECT COUNT(*) FROM pages p WHERE $where_sql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $total_pages = max(1, (int)ceil($total / $per_page));

    $sql = "
        SELECT p.*, u.username AS author_name
        FROM pages p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE $where_sql
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);

    // bind dynamic params
    foreach ($params as $k => $v) {
        // detect int
        if (is_int($v)) $stmt->bindValue($k, $v, PDO::PARAM_INT);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // optionally attach tags for each page (cheap approach: batch fetch)
    $pageIds = array_column($items, 'id');
    if (!empty($pageIds)) {
        $in = implode(',', array_fill(0, count($pageIds), '?'));
        $tstmt = $pdo->prepare("
            SELECT pt.page_id, t.id AS tag_id, t.name, t.slug
            FROM page_tag pt
            JOIN tags t ON t.id = pt.tag_id
            WHERE pt.page_id IN ($in)
            ORDER BY t.name ASC
        ");
        $tstmt->execute($pageIds);
        $rows = $tstmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['page_id']][] = ['id' => $r['tag_id'], 'name' => $r['name'], 'slug' => $r['slug']];
        }
        foreach ($items as &$it) {
            $it['tags'] = $map[$it['id']] ?? [];
        }
        unset($it);
    }

    return [
        'items' => $items,
        'total' => $total,
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => $total_pages,
    ];
}

/* --------------------------
   TAG HELPERS
   -------------------------- */

/**
 * Get tags list (all or with limit)
 */
function tags_get_all(PDO $pdo, int $limit = 0): array {
    $sql = "SELECT * FROM tags ORDER BY name ASC";
    if ($limit > 0) $sql .= " LIMIT " . (int)$limit;
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Find or create tag by name (returns tag array)
 */
function tag_find_or_create_by_name(PDO $pdo, string $name) {
    $slug = slugify($name);
    // first try by slug
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($t) return $t;

    // try by exact name (case-insensitive)
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE LOWER(name) = LOWER(:name) LIMIT 1");
    $stmt->execute([':name' => $name]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($t) return $t;

    // create
    $stmt = $pdo->prepare("INSERT INTO tags (name, slug, description, created_at) VALUES (:name, :slug, :desc, :created_at)");
    $stmt->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':desc' => null,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);
    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * tags_sync_for_page(PDO, page_id, tags[])
 * - $tags can be array of names OR array of tag ids (mixed allowed)
 * - Ensures tags exist and the page_tag table contains exactly the provided tags
 */
function tags_sync_for_page(PDO $pdo, int $page_id, array $tags): array {
    $ownTx = false;
    try {
        if (! $pdo->inTransaction()) {
            $pdo->beginTransaction();
            $ownTx = true;
        }

        $finalTagIds = [];

        // normalize input: numeric => id, else create/find by name
        foreach ($tags as $t) {
            if (is_int($t) || ctype_digit((string)$t)) {
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => (int)$t]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $finalTagIds[] = (int)$row['id'];
            } else {
                $tag = tag_find_or_create_by_name($pdo, (string)$t);
                if ($tag && isset($tag['id'])) $finalTagIds[] = (int)$tag['id'];
            }
        }

        // existing tag ids
        $stmt = $pdo->prepare("SELECT tag_id FROM page_tag WHERE page_id = :pid");
        $stmt->execute([':pid' => $page_id]);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $existing = array_map('intval', $existing);

        $toAdd = array_values(array_diff($finalTagIds, $existing));
        $toRemove = array_values(array_diff($existing, $finalTagIds));

        if (!empty($toAdd)) {
            $insStmt = $pdo->prepare("INSERT INTO page_tag (page_id, tag_id) VALUES (:pid, :tid)");
            foreach ($toAdd as $tid) {
                $insStmt->execute([':pid' => $page_id, ':tid' => $tid]);
            }
        }

        if (!empty($toRemove)) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $params = array_merge([$page_id], $toRemove);
            $sql = "DELETE FROM page_tag WHERE page_id = ? AND tag_id IN ($placeholders)";
            $delStmt = $pdo->prepare($sql);
            $delStmt->execute($params);
        }

        if ($ownTx) $pdo->commit();

        return ['success' => true, 'added' => $toAdd, 'removed' => $toRemove];
    } catch (Exception $e) {
        if ($ownTx && $pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (Exception $_) { /* ignore */ }
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Attach a single tag id to a page (idempotent)
 */
function tag_attach_to_page(PDO $pdo, int $page_id, int $tag_id): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_tag WHERE page_id = :pid AND tag_id = :tid");
    $stmt->execute([':pid' => $page_id, ':tid' => $tag_id]);
    if ((int)$stmt->fetchColumn() > 0) return true;
    $ins = $pdo->prepare("INSERT INTO page_tag (page_id, tag_id) VALUES (:pid, :tid)");
    return $ins->execute([':pid' => $page_id, ':tid' => $tag_id]);
}

/**
 * Detach tag from page
 */
function tag_detach_from_page(PDO $pdo, int $page_id, int $tag_id): bool {
    $stmt = $pdo->prepare("DELETE FROM page_tag WHERE page_id = :pid AND tag_id = :tid");
    return $stmt->execute([':pid' => $page_id, ':tid' => $tag_id]);
}
