<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!admin_logado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autenticado']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("UPDATE pedidos SET impresso = 1 WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

echo json_encode(['ok' => true]);
