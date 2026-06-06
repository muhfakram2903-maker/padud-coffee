<?php
require_once dirname(__DIR__) . '/config/database.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Token QR tidak valid atau tidak ditemukan.");
}

$db = getDB();
$stmt = $db->prepare("SELECT table_id FROM qr_codes WHERE token = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $tableId = $row['table_id'];
        
        // Dapatkan nomor meja asli dari tabel 'tables'
        $stmt2 = $db->prepare("SELECT table_number FROM tables WHERE id = ? LIMIT 1");
        $stmt2->bind_param("i", $tableId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($row2 = $res2->fetch_assoc()) {
            $tableNum = $row2['table_number'];
            $stmt2->close();
            $stmt->close();
            
            // Redirect ke halaman menu dengan membawa token dan nomor meja
            header("Location: ../modules/customer/menu.php?token=" . urlencode($token) . "&table=" . urlencode($tableNum));
            exit;
        }
        $stmt2->close();
    }
    $stmt->close();
}

die("QR Code sudah kedaluwarsa atau tidak valid.");
