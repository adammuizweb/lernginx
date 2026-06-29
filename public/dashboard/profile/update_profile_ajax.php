<?php
// dashboard/profile/update_profile_ajax.php
define('DASHBOARD_CONTEXT', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
date_default_timezone_set('UTC');
header('Content-Type: application/json; charset=utf-8');

// DEV: set true sementara di dev untuk melihat error DB di response (jangan aktifkan di production)
define('DEV_DEBUG', false);

try {
    if (!function_exists('get_user_from_session')) throw new Exception('Auth missing');
    $user = get_user_from_session($pdo);
    if (!$user) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Access denied']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

    // Fields we accept (keep in sync with fragment.php)
    $allowed = [
      'display_name','foto_profil','nomor_telpon','alamat_rumah',
      'tanggal_lahir','asal_sekolah','tahun_masuk','jurusan','nisn'
    ];

    $data = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $_POST)) {
            $data[$f] = trim((string)$_POST[$f]);
        }
    }

    // If no allowed fields provided, return early
    if (count($data) === 0) {
        echo json_encode(['success'=>false,'message'=>'No data to save.']); exit;
    }

    // Server-side rule:
    // If the current user is in first-login state (missing display_name or nomor_telpon),
    // any attempt to save must include BOTH display_name and nomor_telpon (non-empty).
    $currentlyMissing = [];
    if (empty(trim($user['display_name'] ?? ''))) $currentlyMissing[] = 'display_name';
    if (empty(trim($user['nomor_telpon'] ?? ''))) $currentlyMissing[] = 'nomor_telpon';

    if (count($currentlyMissing) > 0) {
        // Require that POST includes both required fields and they're non-empty.
        $missingInPost = [];
        foreach (['display_name','nomor_telpon'] as $req) {
            $val = array_key_exists($req, $data) ? trim((string)$data[$req]) : '';
            if ($val === '') $missingInPost[] = $req;
        }
        if (count($missingInPost) > 0) {
            // Build friendly message
            $readable = [];
            foreach ($missingInPost as $m) {
                if ($m === 'display_name') $readable[] = 'Full Name';
                if ($m === 'nomor_telpon') $readable[] = 'Phone Number';
            }
            $msg = 'Please fill in: ' . implode(' and ', $readable) . '.';
            echo json_encode(['success'=>false,'message'=>$msg]); exit;
        }
    }

    // Normalize: tanggal_lahir (DATE) and tahun_masuk (INT)
    if (array_key_exists('tanggal_lahir', $data)) {
        $val = $data['tanggal_lahir'];
        if ($val === '' || $val === '0000-00-00') {
            $data['tanggal_lahir'] = null;
        } else {
            $d = date_create_from_format('Y-m-d', $val);
            if (!$d) {
                $data['tanggal_lahir'] = null;
            } else {
                $data['tanggal_lahir'] = $d->format('Y-m-d');
            }
        }
    }
    if (array_key_exists('tahun_masuk', $data)) {
        $val = $data['tahun_masuk'];
        if ($val === '') {
            $data['tahun_masuk'] = null;
        } else {
            $ival = (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            $data['tahun_masuk'] = $ival > 0 ? $ival : null;
        }
    }

    // display_name length guard
    if (isset($data['display_name']) && $data['display_name'] !== '') {
        if (mb_strlen($data['display_name']) > 150) {
            $data['display_name'] = mb_substr($data['display_name'], 0, 150);
        }
    }

    // Build SQL
    $setParts = [];
    $params = [];
    foreach ($data as $k => $v) {
        $setParts[] = "`$k` = :$k";
        $params[$k] = $v;
    }

    if (count($setParts) === 0) {
        echo json_encode(['success'=>false,'message'=>'No changes to save.']); exit;
    }

    $sql = "UPDATE users SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :__uid";

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if ($v === null) {
                $stmt->bindValue(':' . $k, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':__uid', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        $err = $e->getMessage();
        error_log('FIRSTLOG UPDATE ERR: '.$err);
        if (defined('DEV_DEBUG') && DEV_DEBUG) {
            echo json_encode(['success'=>false,'message'=>'Failed to save to database: '.$err]); 
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to save to database.']);
        }
        exit;
    }

    // refresh user after update (use available helper)
    $fresh = null;
    if (function_exists('get_user_full_by_id')) {
        $fresh = get_user_full_by_id($pdo, $user['id']);
    } elseif (function_exists('get_user_by_id')) {
        $fresh = get_user_by_id($pdo, $user['id']);
    } else {
        // minimal fallback: re-query users table (non-sensitive)
        try {
            $q = $pdo->prepare("SELECT id,email,display_name,foto_profil,nomor_telpon,alamat_rumah,tanggal_lahir,asal_sekolah,tahun_masuk,jurusan,nisn,updated_at FROM users WHERE id = :id LIMIT 1");
            $q->execute([':id'=>$user['id']]);
            $fresh = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $fresh = $user; // best-effort
        }
    }

    echo json_encode(['success'=>true,'message'=>'Profile saved.','user'=>$fresh]);
    exit;

} catch (Throwable $t) {
    error_log('FIRSTLOG AJAX EX: '.$t->getMessage().' in '.$t->getFile().':'.$t->getLine());
    if (defined('DEV_DEBUG') && DEV_DEBUG) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Server error: '.$t->getMessage()]);
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Server error.']);
    }
    exit;
}
