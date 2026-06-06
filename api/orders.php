<?php
/**
 * api/orders.php
 *
 * POST  {action:create}             â†’ Buat order dari cart (Customer/Member)
 * GET   ?order_number=ORD-xxx       â†’ Status pesanan (Customer/Member polling)
 * PUT   {order_id, status}          â†’ Update status pesanan (Kasir/Admin)
 * GET   ?role=kasir&status=pending  â†’ Daftar pesanan untuk Kasir
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/websocket.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// â•â• GET â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
if ($method === 'GET') {

    // â”€ Status pesanan berdasarkan order_number (public tracking) â”€
    if (!empty($_GET['order_number'])) {
        $on   = clean($_GET['order_number']);
        $stmt = $db->prepare(
            "SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
                    t.table_number, tr.status AS payment_status, tr.payment_method
             FROM orders o
             LEFT JOIN tables t ON t.id = o.table_id
             LEFT JOIN transactions tr ON tr.order_id = o.id
             WHERE o.order_number = ? LIMIT 1"
        );
        $stmt->bind_param('s', $on);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) jsonError('Pesanan tidak ditemukan.', 404);

        // Items
        $istmt = $db->prepare(
            "SELECT oi.quantity, oi.price, oi.notes, m.name
             FROM order_items oi JOIN menus m ON m.id = oi.menu_id
             WHERE oi.order_id = ?"
        );
        $istmt->bind_param('i', $order['id']);
        $istmt->execute();
        $items = $istmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $istmt->close();

        jsonOk(['order' => $order, 'items' => $items], 'Status pesanan');
    }

    // â”€ List pesanan untuk Kasir / Admin â”€
    requireAuth(['kasir', 'admin'], true);

    $where  = '1=1';
    $params = [];
    $types  = '';

    if (!empty($_GET['status'])) {
        $st      = clean($_GET['status']);
        $where  .= ' AND o.status = ?';
        $params[] = $st;
        $types   .= 's';
    }
    if (!empty($_GET['date'])) {
        $dt      = clean($_GET['date']);
        $where  .= ' AND DATE(o.created_at) = ?';
        $params[] = $dt;
        $types   .= 's';
    }

    $sql = "SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at,
                   t.table_number, o.customer_name,
                   tr.status AS payment_status, tr.payment_method
            FROM orders o
            LEFT JOIN tables t ON t.id = o.table_id
            LEFT JOIN transactions tr ON tr.order_id = o.id
            WHERE $where ORDER BY o.created_at DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonOk(['orders' => $orders], 'Daftar pesanan');
}

// â•â• POST â€” Buat Pesanan â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
if ($method === 'POST') {
    $input = getInput();

    // Cart dari session
    if (empty($_SESSION['cart'])) jsonError('Keranjang kosong.', 400);

    $tableId      = !empty($input['table_id']) ? (int) $input['table_id'] : null;
    $customerName = clean($input['customer_name'] ?? 'Guest');
    $userVoucherId = !empty($input['user_voucher_id']) ? (int) $input['user_voucher_id'] : null;
    $notes         = clean($input['notes'] ?? '');

    $userId = isLoggedIn() ? currentUserId() : null;

    // Hitung subtotal
    $cartItems = $_SESSION['cart'];
    $subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cartItems));

    // Hitung diskon voucher
    $discountAmount = 0.0;
    if ($userVoucherId && $userId) {
        $vstmt = $db->prepare(
            "SELECT uv.id, v.discount_amount, v.discount_type, v.min_order
             FROM user_vouchers uv JOIN vouchers v ON v.id = uv.voucher_id
             WHERE uv.id = ? AND uv.user_id = ? AND uv.is_used = 0 AND v.is_active = 1 LIMIT 1"
        );
        $vstmt->bind_param('ii', $userVoucherId, $userId);
        $vstmt->execute();
        $voucher = $vstmt->get_result()->fetch_assoc();
        $vstmt->close();

        if ($voucher && $subtotal >= $voucher['min_order']) {
            $discountAmount = $voucher['discount_type'] === 'percent'
                ? $subtotal * ($voucher['discount_amount'] / 100)
                : (float) $voucher['discount_amount'];
        } else {
            $userVoucherId = null; // invalid, abaikan
        }
    }

    $total       = max(0, $subtotal - $discountAmount);
    $orderNumber = generateOrderNumber();

    $db->begin_transaction();
    try {
        // Insert order
        $ostmt = $db->prepare(
            "INSERT INTO orders (order_number, table_id, user_id, customer_name, user_voucher_id, subtotal, discount_amount, total_amount, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ostmt->bind_param('siiisdds', $orderNumber, $tableId, $userId, $customerName, $userVoucherId, $subtotal, $discountAmount, $total, $notes);

        // Perbaikan: bind_param butuh variabel, bukan ekspresi langsung
        $d1 = $discountAmount; $t1 = $total;
        $ostmt = $db->prepare(
            "INSERT INTO orders (order_number, table_id, user_id, customer_name, user_voucher_id, subtotal, discount_amount, total_amount, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $ostmt->bind_param('siisisddss', $orderNumber, $tableId, $userId, $customerName, $userVoucherId, $subtotal, $d1, $t1, $notes, $dummy = 'pending');
        // Rewrite sederhana
        $ostmt->close();

        $db->query(sprintf(
            "INSERT INTO orders (order_number, table_id, user_id, customer_name, user_voucher_id, subtotal, discount_amount, total_amount, notes, status)
             VALUES ('%s', %s, %s, '%s', %s, %.2f, %.2f, %.2f, '%s', 'pending')",
            $db->real_escape_string($orderNumber),
            $tableId   ? $tableId   : 'NULL',
            $userId    ? $userId    : 'NULL',
            $db->real_escape_string($customerName),
            $userVoucherId ? $userVoucherId : 'NULL',
            $subtotal, $discountAmount, $total,
            $db->real_escape_string($notes)
        ));

        $orderId = $db->insert_id;

        // Insert order items
        foreach ($cartItems as $item) {
            $db->query(sprintf(
                "INSERT INTO order_items (order_id, menu_id, quantity, price, notes) VALUES (%d, %d, %d, %.2f, '%s')",
                $orderId, (int)$item['menu_id'], (int)$item['quantity'], (float)$item['price'],
                $db->real_escape_string($item['notes'] ?? '')
            ));
        }

        // Mark voucher as used
        if ($userVoucherId && $userId) {
            $db->query("UPDATE user_vouchers SET is_used = 1, used_at = NOW() WHERE id = $userVoucherId");
        }

        // Insert transaksi awal (pending)
        $trxCode = generateTransactionCode();
        $db->query(sprintf(
            "INSERT INTO transactions (order_id, transaction_code, payment_method, payment_type, amount, status)
             VALUES (%d, '%s', 'pending', 'manual', %.2f, 'pending')",
            $orderId, $db->real_escape_string($trxCode), $total
        ));
        $transactionId = $db->insert_id;

        $db->commit();

        // Kosongkan cart
        $_SESSION['cart'] = [];

        // Broadcast ke Kasir via WebSocket
        wsNotifyKasir([
            'id'           => $orderId,
            'order_number' => $orderNumber,
            'table_number' => $tableId ? "M$tableId" : '-',
            'total_amount' => $total,
        ]);

        jsonOk([
            'order_id'         => $orderId,
            'order_number'     => $orderNumber,
            'transaction_id'   => $transactionId,
            'transaction_code' => $trxCode,
            'total'            => $total,
        ], 'Pesanan berhasil dibuat', 201);

    } catch (Throwable $e) {
        $db->rollback();
        error_log('[ORDER] ' . $e->getMessage());
        jsonError('Gagal membuat pesanan. Coba lagi.', 500);
    }
}

// â•â• PUT â€” Update Status Pesanan â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
if ($method === 'PUT') {
    requireAuth(['kasir', 'admin'], true);
    $input = getInput();
    requireFields($input, ['order_id', 'status']);

    $orderId = (int)   $input['order_id'];
    $status  = clean($input['status']);
    $allowed = ['pending', 'processing', 'ready', 'completed', 'cancelled'];

    if (!in_array($status, $allowed, true)) jsonError('Status tidak valid.', 422);

    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $orderId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok || $db->affected_rows === 0) jsonError('Pesanan tidak ditemukan atau gagal diperbarui.', 404);

    // Ambil order_number untuk broadcast
    $row = $db->query("SELECT order_number, user_id FROM orders WHERE id = $orderId")->fetch_assoc();

    // Broadcast status ke member/customer
    wsNotifyOrderStatus($orderId, $row['order_number'], $status, $row['user_id'] ?: null);

    // Notifikasi DB
    if ($row['user_id']) {
        $msg = "Pesanan {$row['order_number']} status: $status";
        $db->query("INSERT INTO notifications (user_id, order_id, type, title, message) VALUES ({$row['user_id']}, $orderId, 'order_status', 'Update Pesanan', '$msg')");
    }

    jsonOk(['order_id' => $orderId, 'new_status' => $status], 'Status pesanan diperbarui');
}

jsonError('Method tidak diizinkan.', 405);
