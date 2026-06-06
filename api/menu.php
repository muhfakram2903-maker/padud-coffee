<?php
/**
 * api/menu.php
 * GET  /api/menu.php             → semua menu available
 * GET  /api/menu.php?cat=1       → filter by category_id
 * GET  /api/menu.php?id=5        → detail 1 menu
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') jsonError('Method not allowed.', 405);

$db = getDB();

// ── Detail satu menu ──────────────────────────────────────────────
if (!empty($_GET['id'])) {
    $id   = (int) $_GET['id'];
    $stmt = $db->prepare(
        "SELECT m.*, c.name AS category,
                COALESCE(ROUND(AVG(r.rating),1), 0) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM menus m
         JOIN categories c ON c.id = m.category_id
         LEFT JOIN menu_reviews r ON r.menu_id = m.id
         WHERE m.id = ? AND m.is_available = 1
         GROUP BY m.id"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $menu = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$menu) jsonError('Menu tidak ditemukan.', 404);
    jsonOk($menu, 'Detail menu');
}

// ── List menu (all / by category) ─────────────────────────────────
$sql = "SELECT m.id, m.name, m.description, m.price, m.image, m.is_available,
               c.id AS category_id, c.name AS category,
               COALESCE(ROUND(AVG(r.rating),1), 0) AS avg_rating,
               COUNT(r.id) AS review_count
        FROM menus m
        JOIN categories c ON c.id = m.category_id
        LEFT JOIN menu_reviews r ON r.menu_id = m.id
        WHERE m.is_available = 1";

$params = [];
$types  = '';

if (!empty($_GET['cat'])) {
    $catId   = (int) $_GET['cat'];
    $sql    .= ' AND m.category_id = ?';
    $params[] = $catId;
    $types   .= 'i';
}

if (!empty($_GET['q'])) {
    $q       = '%' . $db->real_escape_string($_GET['q']) . '%';
    $sql    .= ' AND m.name LIKE ?';
    $params[] = $q;
    $types   .= 's';
}

$sql .= ' GROUP BY m.id ORDER BY c.id, m.id'; // Group by and order

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$menus = [];
while ($row = $result->fetch_assoc()) $menus[] = $row;
$stmt->close();

// Categories list
$cats   = [];
$catRes = $db->query("SELECT id, name FROM categories ORDER BY id");
while ($c = $catRes->fetch_assoc()) $cats[] = $c;

jsonOk(['menus' => $menus, 'categories' => $cats], 'Daftar menu');
