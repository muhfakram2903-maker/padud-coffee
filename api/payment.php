<?php
/**
 * api/payment.php
 *
 * POST {action:snap_token, order_id}    â†’ Minta Snap Token Midtrans
 * POST {action:confirm, transaction_id, status, method} â†’ Konfirmasi manual (Kasir)
 * GET  ?order_number=ORD-xxx            â†’ Cek status pembayaran
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/midtrans.php';
require_once dirname(__DIR__) . '/config/websocket.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db = getDB();

// â•â• GET â€” Cek status pembayaran â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_GET['order_number'])) jsonError('order_number wajib diisi.', 422);

    $on   = clean($_GET['order_number']);
    $stmt = $db->prepare(
        "SELECT t.id, t.status, t.payment_method, t.payment_type, t.amount, t.created_at, t.processed_at
         FROM transactions t JOIN orders o ON o.id = t.order_id
         WHERE o.order_number = ? ORDER BY t.id DESC LIMIT 1"
    );
    $stmt->bind_param('s', $on);
    $stmt->execute();
    $trx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trx) jsonError('Transaksi tidak ditemukan.', 404);
    jsonOk($trx, 'Status pembayaran');
}

// â•â• POST â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = getInput();
    $action = $input['action'] ?? '';

    // â”€ Buat Snap Token Midtrans â”€
    if ($action === 'snap_token') {
        requireFields($input, ['order_id']);
        $orderId = (int) $input['order_id'];

        $stmt = $db->prepare(
            "SELECT o.*, t.table_number FROM orders o
             LEFT JOIN tables t ON t.id = o.table_id
             WHERE o.id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) jsonError('Pesanan tidak ditemukan.', 404);

        // Data items
        $istmt = $db->prepare(
            "SELECT oi.menu_id, m.name, oi.price, oi.quantity
             FROM order_items oi JOIN menus m ON m.id = oi.menu_id
             WHERE oi.order_id = ?"
        );
        $istmt->bind_param('i', $orderId);
        $istmt->execute();
        $items = $istmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $istmt->close();

        // Data customer
        $customer = ['name' => $order['customer_name'] ?? 'Guest', 'email' => '', 'phone' => ''];
        if ($order['user_id']) {
            $ustmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $ustmt->bind_param('i', $order['user_id']);
            $ustmt->execute();
            $udata = $ustmt->get_result()->fetch_assoc();
            $ustmt->close();
            if ($udata) $customer = $udata;
        }

        try {
            $snap = midtransCreateSnapToken($order, $items, $customer);

            // Simpan token ke tabel transactions
            $db->query(sprintf(
                "UPDATE transactions SET payment_type = 'midtrans', midtrans_token = '%s',
                 midtrans_order_id = '%s', payment_method = 'midtrans'
                 WHERE order_id = %d AND status = 'pending' ORDER BY id DESC LIMIT 1",
                $db->real_escape_string($snap['token']),
                $db->real_escape_string($order['order_number']),
                $orderId
            ));

            jsonOk([
                'snap_token'   => $snap['token'],
                'redirect_url' => $snap['redirect_url'],
                'client_key'   => MIDTRANS_CLIENT_KEY,
            ], 'Snap token berhasil dibuat');

        } catch (RuntimeException $e) {
            error_log('[MIDTRANS] ' . $e->getMessage());
            jsonError('Gagal menghubungi payment gateway. Gunakan pembayaran manual.', 502);
        }
    }

    // â”€ Konfirmasi Pembayaran Manual (oleh Kasir) â”€
    if ($action === 'confirm') {
        requireAuth(['kasir', 'admin'], true);
        requireFields($input, ['transaction_id', 'status']);

        $trxId    = (int)   $input['transaction_id'];
        $status   = clean($input['status']);   // paid | failed
        $method   = clean($input['payment_method'] ?? 'cash');
        $kasirId  = currentUserId();

        if (!in_array($status, ['paid', 'failed'], true)) jsonError('Status tidak valid.', 422);

        $stmt = $db->prepare(
            "UPDATE transactions SET status = ?, payment_method = ?, processed_by = ?, processed_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssii', $status, $method, $kasirId, $trxId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok || $db->affected_rows === 0) jsonError('Transaksi tidak ditemukan.', 404);

        // Ambil order untuk update status & broadcast
        $row = $db->query(
            "SELECT o.id, o.order_number, o.user_id, o.total_amount
             FROM transactions t JOIN orders o ON o.id = t.order_id WHERE t.id = $trxId LIMIT 1"
        )->fetch_assoc();

        if ($row) {
            if ($status === 'paid') {
                $db->query("UPDATE orders SET status = 'processing' WHERE id = {$row['id']}");

                // Tambah poin member
                if ($row['user_id']) {
                    $points = calculatePoints((float) $row['total_amount']);
                    if ($points > 0) {
                        $db->query("UPDATE users SET points = points + $points WHERE id = {$row['user_id']}");
                        $db->query(sprintf(
                            "INSERT INTO reward_history (user_id, order_id, points, type, description)
                             VALUES (%d, %d, %d, 'earn', 'Poin dari pesanan %s')",
                            $row['user_id'], $row['id'], $points,
                            $db->real_escape_string($row['order_number'])
                        ));
                    }
                }

                wsNotifyPaymentConfirmed($row['id'], $row['order_number'], $row['user_id'] ?: null);
            }

            // Notifikasi DB ke member
            if ($row['user_id']) {
                $msg = $status === 'paid'
                    ? "Pembayaran pesanan {$row['order_number']} telah dikonfirmasi."
                    : "Pembayaran pesanan {$row['order_number']} ditolak. Hubungi kasir.";
                $db->query(sprintf(
                    "INSERT INTO notifications (user_id, order_id, type, title, message)
                     VALUES (%d, %d, 'payment', 'Status Pembayaran', '%s')",
                    $row['user_id'], $row['id'], $db->real_escape_string($msg)
                ));
            }
        }

        jsonOk(['transaction_id' => $trxId, 'status' => $status], 'Pembayaran dikonfirmasi');
    }

    // â”€ Upload bukti bayar â”€
    if ($action === 'upload_proof') {
        requireFields($input, ['transaction_id']);
        $trxId = (int) $input['transaction_id'];

        if (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            jsonError('File bukti pembayaran tidak valid.', 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['proof']['tmp_name']);
        if (!in_array($mime, $allowed, true)) jsonError('Format file harus JPG, PNG, atau WebP.', 422);

        $ext      = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $trxId . '_' . time() . '.' . $ext;
        $dest     = dirname(__DIR__) . '/assets/uploads/' . $filename;

        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
            jsonError('Gagal menyimpan file.', 500);
        }

        $db->query("UPDATE transactions SET payment_proof = '$filename' WHERE id = $trxId");
        jsonOk(['file' => $filename], 'Bukti pembayaran berhasil diupload');
    }

    jsonError('Action tidak dikenal.', 400);
}

jsonError('Method tidak diizinkan.', 405);
