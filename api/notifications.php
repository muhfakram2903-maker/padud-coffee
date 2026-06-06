<?php
/**
 * api/notifications.php
 *
 * GET  ?limit=20                â†’ Ambil notifikasi user/role saat ini
 * POST {action:read, id}        â†’ Tandai 1 notifikasi sebagai dibaca
 * POST {action:read_all}        â†’ Tandai semua sebagai dibaca
 * GET  ?unread_count=1          â†’ Hanya hitung notifikasi belum dibaca
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';
require_once dirname(__DIR__) . '/config/auth.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

requireAuth(['admin', 'kasir', 'member'], true);

$db   = getDB();
$user = currentUser();

// â”€â”€ GET â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Hanya hitungan
    if (!empty($_GET['unread_count'])) {
        $count = countUnread($db, $user);
        jsonOk(['unread_count' => $count], 'Jumlah notifikasi belum dibaca');
    }

    $limit = min((int)($_GET['limit'] ?? 20), 50);

    if ($user['role'] === 'member') {
        $stmt = $db->prepare(
            "SELECT id, order_id, type, title, message, is_read, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param('ii', $user['id'], $limit);
    } else {
        // kasir / admin â€” ambil notifikasi role + broadcast
        $stmt = $db->prepare(
            "SELECT id, order_id, type, title, message, is_read, created_at
             FROM notifications WHERE target_role = ? OR user_id = ?
             ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param('sii', $user['role'], $user['id'], $limit);
    }

    $stmt->execute();
    $notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $unread = countUnread($db, $user);

    jsonOk(['notifications' => $notifs, 'unread_count' => $unread], 'Daftar notifikasi');
}

// â”€â”€ POST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = getInput();
    $action = $input['action'] ?? '';

    if ($action === 'read') {
        requireFields($input, ['id']);
        $id = (int) $input['id'];

        // Pastikan notifikasi milik user ini
        $stmt = $db->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE id = ? AND (user_id = ? OR target_role = ?)"
        );
        $stmt->bind_param('iis', $id, $user['id'], $user['role']);
        $stmt->execute();
        $stmt->close();

        jsonOk(['id' => $id], 'Notifikasi ditandai dibaca');
    }

    if ($action === 'read_all') {
        if ($user['role'] === 'member') {
            $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = {$user['id']}");
        } else {
            $role = $db->real_escape_string($user['role']);
            $db->query("UPDATE notifications SET is_read = 1 WHERE target_role = '$role' OR user_id = {$user['id']}");
        }
        jsonOk([], 'Semua notifikasi ditandai dibaca');
    }

    jsonError('Action tidak dikenal.', 400);
}

jsonError('Method tidak diizinkan.', 405);

// â”€â”€ Helper: hitung unread â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function countUnread(mysqli $db, array $user): int {
    if ($user['role'] === 'member') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $user['id']);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (target_role = ? OR user_id = ?) AND is_read = 0");
        $stmt->bind_param('si', $user['role'], $user['id']);
    }
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count;
}
