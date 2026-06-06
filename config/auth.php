<?php
// ── RBAC: izin akses per role ──────────────────────────────────
define('RBAC', [
    'admin'  => ['admin', 'kasir', 'member', 'customer', 'guest'],
    'kasir'  => ['kasir', 'customer', 'guest'],
    'member' => ['member', 'customer', 'guest'],
    'guest'  => ['guest'],
]);

/**
 * Paksa pengguna sudah login dengan role tertentu.
 * Jika tidak memenuhi, kembalikan 403 JSON atau redirect.
 *
 * @param array  $allowedRoles  Daftar role yang diizinkan
 * @param bool   $jsonMode      true = kembalikan JSON 403, false = redirect
 */
function requireAuth(array $allowedRoles, bool $jsonMode = false): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $role = $_SESSION['user_role'] ?? 'guest';

    if (!in_array($role, $allowedRoles, true)) {
        if ($jsonMode) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
            exit;
        }
        $redirectMap = [
            'admin'  => '/padud-coffee/modules/admin/dashboard.php',
            'kasir'  => '/padud-coffee/modules/kasir/dashboard.php',
            'member' => '/padud-coffee/modules/member/dashboard.php',
        ];
        header('Location: ' . ($redirectMap[$role] ?? '/padud-coffee/index.php'));
        exit;
    }
}

function isLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function currentUser(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'id'   => (int)  ($_SESSION['user_id']   ?? 0),
        'name' => (string)($_SESSION['user_name'] ?? 'Guest'),
        'role' => (string)($_SESSION['user_role'] ?? 'guest'),
    ];
}

function currentRole(): string {
    return currentUser()['role'];
}

function currentUserId(): int {
    return currentUser()['id'];
}
