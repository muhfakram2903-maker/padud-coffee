<?php
// ── Midtrans Credentials ───────────────────────────────────────
define('MIDTRANS_SERVER_KEY', 'YOUR_SERVER_KEY');
define('MIDTRANS_CLIENT_KEY', 'YOUR_CLIENT_KEY');
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_BASE_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions'
);
define('MIDTRANS_STATUS_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://api.midtrans.com/v2/'
    : 'https://api.sandbox.midtrans.com/v2/'
);

/**
 * Buat Snap Token Midtrans
 *
 * @param array $order  Data pesanan dari tabel orders
 * @param array $items  Item pesanan [{name, price, quantity}]
 * @param array $customer  ['name', 'email', 'phone']
 * @return array ['token' => '...', 'redirect_url' => '...']
 */
function midtransCreateSnapToken(array $order, array $items, array $customer): array {
    $itemDetails = [];
    foreach ($items as $item) {
        $itemDetails[] = [
            'id'       => (string) $item['menu_id'],
            'price'    => (int) $item['price'],
            'quantity' => (int) $item['quantity'],
            'name'     => substr($item['name'], 0, 50),
        ];
    }

    $payload = [
        'transaction_details' => [
            'order_id'     => $order['order_number'],
            'gross_amount' => (int) $order['total_amount'],
        ],
        'item_details'    => $itemDetails,
        'customer_details' => [
            'first_name' => $customer['name'] ?? 'Guest',
            'email'      => $customer['email'] ?? 'guest@padudcoffee.com',
            'phone'      => $customer['phone'] ?? '08000000000',
        ],
        'callbacks' => [
            'finish'   => rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/padud-coffee/modules/customer/payment_return.php',
            'error'    => rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/padud-coffee/modules/customer/payment_return.php?status=error',
            'pending'  => rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/padud-coffee/modules/customer/payment_return.php?status=pending',
        ],
    ];

    $ch = curl_init(MIDTRANS_BASE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($httpCode !== 201 || empty($result['token'])) {
        throw new RuntimeException('Midtrans error: ' . ($result['error_messages'][0] ?? 'Unknown error'));
    }

    return [
        'token'        => $result['token'],
        'redirect_url' => $result['redirect_url'],
    ];
}

/**
 * Ambil status transaksi dari Midtrans (untuk polling)
 */
function midtransGetStatus(string $orderId): array {
    $url = MIDTRANS_STATUS_URL . $orderId . '/status';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

/**
 * Verifikasi signature dari webhook Midtrans
 * signature = SHA512(order_id + status_code + gross_amount + server_key)
 */
function midtransVerifySignature(string $orderId, string $statusCode, string $grossAmount, string $receivedSignature): bool {
    $expected = hash('sha512', $orderId . $statusCode . $grossAmount . MIDTRANS_SERVER_KEY);
    return hash_equals($expected, $receivedSignature);
}

/**
 * Map status Midtrans ke status transaksi internal
 */
function midtransMapStatus(string $transactionStatus, string $fraudStatus = ''): string {
    return match(true) {
        in_array($transactionStatus, ['capture', 'settlement'], true) && ($fraudStatus === '' || $fraudStatus === 'accept') => 'paid',
        $transactionStatus === 'pending'  => 'pending',
        $transactionStatus === 'expire'   => 'expired',
        $transactionStatus === 'refund'   => 'refunded',
        default                           => 'failed',
    };
}
