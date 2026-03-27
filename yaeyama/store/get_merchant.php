<?php
require_once '../config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['name' => null]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT name FROM merchants WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['name' => $row ? $row['name'] : null]);
} catch (Exception $e) {
    echo json_encode(['name' => null]);
}
