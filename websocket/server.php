#!/usr/bin/env php
<?php
/**
 * websocket/server.php
 *
 * WebSocket Server untuk Padud Coffee.
 * Jalankan dari CLI: php websocket/server.php
 *
 * Fungsi:
 *  - Terima koneksi dari browser (kasir, member dashboard)
 *  - Terima broadcast event dari api/ws_broadcast.php via TCP internal port
 *  - Forward event ke client yang sesuai berdasarkan role / user_id
 *
 * Dependensi: PHP 8.1+, ext-sockets
 */
require_once __DIR__ . '/../config/websocket.php';

define('WS_EXTERNAL_PORT', 8080); // Koneksi browser (WebSocket)
define('WS_INTERNAL_PORT', 8081); // Internal broadcast dari PHP server

echo "[WS] Padud Coffee WebSocket Server\n";
echo "[WS] External (browser) : ws://0.0.0.0:" . WS_EXTERNAL_PORT . "\n";
echo "[WS] Internal (broadcast): tcp://127.0.0.1:" . WS_INTERNAL_PORT . "\n";
echo "[WS] Tekan Ctrl+C untuk berhenti\n\n";

// ─── Socket eksternal (WebSocket browser) ─────────────────────
$extSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($extSock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($extSock, '0.0.0.0', WS_EXTERNAL_PORT);
socket_listen($extSock, 10);
socket_set_nonblock($extSock);

// ─── Socket internal (broadcast dari PHP API) ──────────────────
$intSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($intSock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($intSock, '127.0.0.1', WS_INTERNAL_PORT);
socket_listen($intSock, 10);
socket_set_nonblock($intSock);

$clients   = []; // [ socket => ['handshaked'=>bool, 'role'=>string, 'user_id'=>int] ]
$allRead   = [$extSock, $intSock];

while (true) {
    $read   = array_merge([$extSock, $intSock], array_keys($clients));
    $write  = null;
    $except = null;

    if (socket_select($read, $write, $except, 0, 200000) < 1) continue;

    // ── Koneksi browser baru ───────────────────────────────────
    if (in_array($extSock, $read)) {
        $newClient = socket_accept($extSock);
        if ($newClient !== false) {
            socket_set_nonblock($newClient);
            $clients[(int) $newClient] = [
                'socket'     => $newClient,
                'handshaked' => false,
                'role'       => null,
                'user_id'    => null,
            ];
            echo "[WS] Koneksi baru: #" . (int)$newClient . "\n";
        }
        unset($read[array_search($extSock, $read)]);
    }

    // ── Pesan internal dari API ────────────────────────────────
    if (in_array($intSock, $read)) {
        $internalConn = socket_accept($intSock);
        if ($internalConn !== false) {
            $raw = '';
            while ($chunk = @socket_read($internalConn, 2048)) $raw .= $chunk;
            socket_close($internalConn);

            $msg = json_decode(trim($raw), true);
            if ($msg && isset($msg['event'])) {
                broadcastToClients($clients, $msg);
            }
        }
        unset($read[array_search($intSock, $read)]);
    }

    // ── Pesan dari client browser ──────────────────────────────
    foreach ($read as $sock) {
        $key = (int) $sock;
        if (!isset($clients[$key])) continue;

        $data = @socket_read($sock, 4096);

        if ($data === false || $data === '') {
            // Client disconnect
            echo "[WS] Disconnect: #$key\n";
            socket_close($sock);
            unset($clients[$key]);
            continue;
        }

        $client = &$clients[$key];

        // ── WebSocket handshake ────────────────────────────────
        if (!$client['handshaked']) {
            $headers = parseHeaders($data);
            if (isset($headers['Sec-WebSocket-Key'])) {
                $accept = base64_encode(sha1($headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                $response = "HTTP/1.1 101 Switching Protocols\r\n"
                          . "Upgrade: websocket\r\n"
                          . "Connection: Upgrade\r\n"
                          . "Sec-WebSocket-Accept: $accept\r\n\r\n";
                socket_write($sock, $response);
                $client['handshaked'] = true;
                echo "[WS] Handshake #$key OK\n";
            }
            continue;
        }

        // ── Decode WebSocket frame ─────────────────────────────
        $decoded = decodeFrame($data);
        if ($decoded === null) continue;

        $payload = json_decode($decoded, true);
        if (!$payload || !isset($payload['event'])) continue;

        // Client register event: { event: "register", role: "kasir", user_id: 3 }
        if ($payload['event'] === 'register') {
            $client['role']    = $payload['role']    ?? null;
            $client['user_id'] = (int)($payload['user_id'] ?? 0);
            echo "[WS] Client #$key registered: role={$client['role']} user_id={$client['user_id']}\n";

            // Konfirmasi ke client
            sendFrame($sock, json_encode(['event' => 'registered', 'message' => 'OK']));
        }

        // Ping/pong
        if ($payload['event'] === 'ping') {
            sendFrame($sock, json_encode(['event' => 'pong', 'time' => date('H:i:s')]));
        }
    }
}

// ─── Fungsi Helper ────────────────────────────────────────────

function broadcastToClients(array &$clients, array $msg): void {
    $event      = $msg['event']       ?? '';
    $targetRole = $msg['target_role'] ?? null;
    $targetUser = $msg['target_user'] ?? null;
    $json       = json_encode($msg);

    foreach ($clients as $key => $client) {
        if (!$client['handshaked']) continue;

        $roleMatch = $targetRole === null || $client['role'] === $targetRole;
        $userMatch = $targetUser === null || (int)$client['user_id'] === (int)$targetUser;

        if ($roleMatch && $userMatch) {
            sendFrame($client['socket'], $json);
            echo "[WS] Broadcast '$event' → #$key (role={$client['role']})\n";
        }
    }
}

function sendFrame($socket, string $text): void {
    $len = strlen($text);
    if ($len <= 125) {
        $frame = chr(0x81) . chr($len) . $text;
    } elseif ($len <= 65535) {
        $frame = chr(0x81) . chr(126) . pack('n', $len) . $text;
    } else {
        $frame = chr(0x81) . chr(127) . pack('J', $len) . $text;
    }
    @socket_write($socket, $frame, strlen($frame));
}

function decodeFrame(string $data): ?string {
    if (strlen($data) < 2) return null;
    $masked = (ord($data[1]) >> 7) & 1;
    $len    = ord($data[1]) & 127;

    if ($len === 126) {
        $masks  = substr($data, 4, 4);
        $offset = 8;
    } elseif ($len === 127) {
        $masks  = substr($data, 10, 4);
        $offset = 14;
    } else {
        $masks  = substr($data, 2, 4);
        $offset = 6;
    }

    if (!$masked) return substr($data, $offset - 4);

    $text = '';
    for ($i = $offset; $i < strlen($data); $i++) {
        $text .= $data[$i] ^ $masks[($i - $offset) % 4];
    }
    return $text;
}

function parseHeaders(string $request): array {
    $headers = [];
    foreach (explode("\r\n", $request) as $line) {
        if (str_contains($line, ': ')) {
            [$key, $val] = explode(': ', $line, 2);
            $headers[trim($key)] = trim($val);
        }
    }
    return $headers;
}
