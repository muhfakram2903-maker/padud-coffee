<?php
/**
 * api/midtrans_callback.php
 *
 * Endpoint webhook POST dari server Midtrans.
 * URL ini didaftarkan di Midtrans Dashboard â†’ Settings â†’ Configuration.
 *
 * Midtrans akan POST JSON ke URL ini setiap kali ada perubahan status transaksi.
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';
require_once dirname(__DIR__) . '/config/midtrans.php';
require_once dirname(__DIR__) . '/config/websocket.php';

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Baca payload
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

$orderId       = $payload['order_id']          ?? '';
$statusCode    = $payload['status_code']       ?? '';
$grossAmount   = $payload['gross_amount']      ?? '';
$transStatus   = $payload['transaction_status'] ?? '';
$fraudStatus   = $payload['fraud_status']      ?? '';
$signatureKey  = $payload['signature_key']     ?? '';

$db = getDB();

// â”€â”€ Log semua callback masuk â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$signatureValid = midtransVerifySignature($orderId, $statusCode, $grossAmount, $signatureKey) ? 1 : 0;

$db->query(sprintf(
    "INSERT INTO payment_logs (midtrans_order_id, event_type, payload, signature_valid)
     VALUES ('%s', '%s', '%s', %d)",
    $db->real_escape_string($orderId),
    $db->real_escape_string($transStatus),
    $db->real_escape_string($raw),
    $signatureValid
));
$logId = $db->insert_id;

// â”€â”€ Verifikasi signature â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!$signatureValid) {
    error_log("[MIDTRANS CB] Invalid signature for order: $orderId");
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

// â”€â”€ Cari transaksi di database â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stmt = $db->prepare(
    "SELECT t.id, t.order_id, t.status, o.user_id, o.total_amount
     FROM transactions t JOIN orders o ON o.id = t.order_id
     WHERE t.midtrans_order_id = ? OR o.order_number = ?
     ORDER BY t.id DESC LIMIT 1"
);
$stmt->bind_param('ss', $orderId, $orderId);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) {
    error_log("[MIDTRANS CB] Transaksi tidak ditemukan: $orderId");
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
    exit;
}

// Jangan proses jika sudah final
if (in_array($trx['status'], ['paid', 'refunded'], true)) {
    echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
    exit;
}

// â”€â”€ Map status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$newStatus = midtransMapStatus($transStatus, $fraudStatus);

// â”€â”€ Update transaksi â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stmt = $db->prepare(
    "UPDATE transactions
     SET status = ?, midtrans_response = ?, updated_at = NOW()
     WHERE id = ?"
);
$stmt->bind_param('ssi', $newStatus, $raw, $trx['id']);
$stmt->execute();
$stmt->close();

// Update log dengan transaction_id
$db->query("UPDATE payment_logs SET transaction_id = {$trx['id']} WHERE id = $logId");

// â”€â”€ Aksi berdasarkan status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($newStatus === 'paid') {
    // Update order status
    $db->query("UPDATE orders SET status = 'processing' WHERE id = {$trx['order_id']}");

    // Ambil order_number
    $orow = $db->query("SELECT order_number FROM orders WHERE id = {$trx['order_id']}")->fetch_assoc();

    // Tambah poin member
    if ($trx['user_id']) {
        $points = calculatePoints((float) $trx['total_amount']);
        if ($points > 0) {
            $db->query("UPDATE users SET points = points + $points WHERE id = {$trx['user_id']}");
            $db->query(sprintf(
                "INSERT INTO reward_history (user_id, order_id, points, type, description)
                 VALUES (%d, %d, %d, 'earn', 'Poin dari pesanan %s')",
                $trx['user_id'], $trx['order_id'], $points,
                $db->real_escape_string($orow['order_number'] ?? '')
            ));
        }

        // Notifikasi ke member
        $db->query(sprintf(
            "INSERT INTO notifications (user_id, order_id, type, title, message)
             VALUES (%d, %d, 'payment', 'Pembayaran Berhasil', 'Pembayaran pesanan %s telah diterima via Midtrans.')",
            $trx['user_id'], $trx['order_id'],
            $db->real_escape_string($orow['order_number'] ?? '')
        ));
    }

    // WebSocket broadcast
    wsNotifyPaymentConfirmed($trx['order_id'], $orow['order_number'] ?? '', $trx['user_id'] ?: null);
}

if ($newStatus === 'failed' || $newStatus === 'expired') {
    $db->query("UPDATE orders SET status = 'cancelled' WHERE id = {$trx['order_id']}");
}

error_log("[MIDTRANS CB] Order $orderId â†’ $newStatus");
echo json_encode(['status' => 'ok', 'transaction_status' => $newStatus]);
