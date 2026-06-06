<?php
// ── JSON response helper ───────────────────────────────────────
function jsonOk(mixed $data = [], string $message = 'OK', int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message = 'Error', int $code = 400, mixed $data = null): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['status' => 'error', 'message' => $message];
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Sanitize ───────────────────────────────────────────────────
function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Redirect ───────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── Format Rupiah ──────────────────────────────────────────────
function rupiah(float $n, bool $prefix = true): string {
    return ($prefix ? 'Rp ' : '') . number_format($n, 0, ',', '.');
}

// ── Order Number Generator ─────────────────────────────────────
function generateOrderNumber(): string {
    return 'ORD-' . strtoupper(date('ymd')) . '-' . strtoupper(substr(uniqid(), -5));
}

// ── Transaction Code Generator ─────────────────────────────────
function generateTransactionCode(): string {
    return 'TRX-' . strtoupper(date('ymd')) . '-' . strtoupper(substr(uniqid(), -6));
}

// ── QR Token Generator ─────────────────────────────────────────
function generateQrToken(int $tableId): string {
    return bin2hex(random_bytes(16)) . '-t' . $tableId;
}

// ── Time Ago ───────────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return $diff . ' detik lalu';
    if ($diff < 3600)  return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    return floor($diff/86400) . ' hari lalu';
}

// ── Input body JSON / POST ─────────────────────────────────────
function getInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
    return $_POST;
}

// ── Validate required fields ───────────────────────────────────
function requireFields(array $data, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            jsonError("Field '{$f}' wajib diisi.", 422);
        }
    }
}

// ── Points calculation ─────────────────────────────────────────
// 1 poin per Rp 1.000 yang dibelanjakan
function calculatePoints(float $totalAmount): int {
    return (int) floor($totalAmount / 1000);
}
