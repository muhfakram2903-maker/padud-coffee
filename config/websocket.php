<?php
// ── WebSocket Server Config ────────────────────────────────────
define('WS_HOST', '127.0.0.1');
define('WS_PORT', 8080);
define('WS_TIMEOUT', 3); // detik

/**
 * Kirim event broadcast ke WebSocket server melalui TCP socket.
 * WebSocket server (websocket/server.php) harus berjalan.
 *
 * @param string $event   Nama event: new_order, order_status, payment_confirmed, alert_15min
 * @param array  $payload Data yang dikirim
 * @param string|null $targetRole  null = semua, 'kasir' | 'member' | 'admin'
 * @param int|null    $targetUser  user_id spesifik, null = broadcast per role
 */
function wsBroadcast(string $event, array $payload, ?string $targetRole = null, ?int $targetUser = null): bool {
    $message = json_encode([
        'event'       => $event,
        'target_role' => $targetRole,
        'target_user' => $targetUser,
        'data'        => $payload,
        'time'        => date('Y-m-d H:i:s'),
    ]);

    $socket = @fsockopen(WS_HOST, WS_PORT, $errno, $errstr, WS_TIMEOUT);
    if (!$socket) {
        error_log("[WS] Tidak bisa konek ke server: $errstr ($errno)");
        return false;
    }

    // Kirim internal command (server mendengarkan di port WS_PORT)
    fwrite($socket, $message . "\n");
    fclose($socket);
    return true;
}

/**
 * Broadcast notifikasi pesanan baru ke semua Kasir
 */
function wsNotifyKasir(array $order): void {
    wsBroadcast('new_order', [
        'order_id'     => $order['id'],
        'order_number' => $order['order_number'],
        'table_number' => $order['table_number'] ?? '-',
        'total'        => $order['total_amount'],
    ], 'kasir');
}

/**
 * Broadcast update status pesanan ke Member / Customer session
 */
function wsNotifyOrderStatus(int $orderId, string $orderNumber, string $status, ?int $userId = null): void {
    wsBroadcast('order_status', [
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'status'       => $status,
    ], $userId ? null : 'member', $userId);
}

/**
 * Broadcast konfirmasi pembayaran ke Member
 */
function wsNotifyPaymentConfirmed(int $orderId, string $orderNumber, ?int $userId = null): void {
    wsBroadcast('payment_confirmed', [
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
    ], $userId ? null : 'member', $userId);
}

/**
 * Broadcast peringatan pesanan terbengkalai > 15 menit ke Kasir
 */
function wsAlertOrderStale(int $orderId, string $orderNumber, int $minutesPending): void {
    wsBroadcast('alert_15min', [
        'order_id'       => $orderId,
        'order_number'   => $orderNumber,
        'minutes_pending' => $minutesPending,
    ], 'kasir');
}
