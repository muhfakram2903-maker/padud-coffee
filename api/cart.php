<?php
/**
 * api/cart.php  â€” Session-based cart (tidak butuh login)
 *
 * GET    /api/cart.php                   â†’ isi keranjang
 * POST   /api/cart.php  {action:add, menu_id, quantity, notes}  â†’ tambah item
 * POST   /api/cart.php  {action:update, key, quantity}          â†’ ubah jumlah
 * POST   /api/cart.php  {action:remove, key}                    â†’ hapus 1 item
 * POST   /api/cart.php  {action:clear}                          â†’ kosongkan cart
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Inisialisasi cart di session jika belum ada
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// â”€â”€ GET â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonOk(cartSummary(), 'Cart saat ini');
}

// â”€â”€ POST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = getInput();
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'add':
            requireFields($input, ['menu_id', 'quantity']);
            $menuId   = (int) $input['menu_id'];
            $qty      = max(1, (int) $input['quantity']);
            $notes    = clean($input['notes'] ?? '');

            // Ambil data menu dari DB
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name, price FROM menus WHERE id = ? AND is_available = 1 LIMIT 1");
            $stmt->bind_param('i', $menuId);
            $stmt->execute();
            $menu = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$menu) jsonError('Menu tidak ditemukan atau tidak tersedia.', 404);

            // Key unik per menu+notes
            $key = $menuId . '_' . md5($notes);

            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] += $qty;
            } else {
                $_SESSION['cart'][$key] = [
                    'key'      => $key,
                    'menu_id'  => $menuId,
                    'name'     => $menu['name'],
                    'price'    => (float) $menu['price'],
                    'quantity' => $qty,
                    'notes'    => $notes,
                ];
            }
            jsonOk(cartSummary(), 'Item ditambahkan ke keranjang');

        case 'update':
            requireFields($input, ['key', 'quantity']);
            $key = $input['key'];
            $qty = (int) $input['quantity'];

            if (!isset($_SESSION['cart'][$key])) jsonError('Item tidak ditemukan di keranjang.', 404);

            if ($qty <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['quantity'] = $qty;
            }
            jsonOk(cartSummary(), 'Keranjang diperbarui');

        case 'remove':
            requireFields($input, ['key']);
            $key = $input['key'];
            unset($_SESSION['cart'][$key]);
            jsonOk(cartSummary(), 'Item dihapus dari keranjang');

        case 'clear':
            $_SESSION['cart'] = [];
            jsonOk(cartSummary(), 'Keranjang dikosongkan');

        default:
            jsonError('Action tidak dikenal.', 400);
    }
}

jsonError('Method tidak diizinkan.', 405);

// â”€â”€ Helper: ringkasan cart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function cartSummary(): array {
    $items    = array_values($_SESSION['cart'] ?? []);
    $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
    return [
        'items'      => $items,
        'item_count' => count($items),
        'subtotal'   => $subtotal,
    ];
}
