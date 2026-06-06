<?php
/**
 * api/ws_broadcast.php
 *
 * Endpoint INTERNAL â€” hanya dipanggil dari kode server (bukan browser langsung).
 * Menerima event dari controller dan meneruskannya ke WebSocket server via TCP.
 *
 * POST {event, target_role?, target_user?, data{}}
 */
require_once dirname(__DIR__) . '/config/helper.php';
require_once dirname(__DIR__) . '/config/websocket.php';

// Hanya izinkan request dari localhost / server internal
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$allowedIps = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

if (!in_array($remoteIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['event'])) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Field event wajib diisi.']);
    exit;
}

$event      = $input['event'];
$targetRole = $input['target_role'] ?? null;
$targetUser = isset($input['target_user']) ? (int) $input['target_user'] : null;
$data       = $input['data'] ?? [];

$ok = wsBroadcast($event, $data, $targetRole, $targetUser);

echo json_encode([
    'status'  => $ok ? 'success' : 'error',
    'message' => $ok ? 'Event terkirim ke WebSocket server.' : 'WebSocket server tidak merespons (mungkin belum berjalan).',
    'event'   => $event,
]);
