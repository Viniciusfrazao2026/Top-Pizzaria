<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!admin_logado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autenticado']);
    exit;
}

$pedidos = [];
$res = $conn->query("SELECT id, criado_em, tipo_entrega, nome_cliente, telefone, rua, numero, bairro,
                     forma_pagamento, precisa_troco, troco_para, observacoes, subtotal, taxa_entrega, total
                     FROM pedidos WHERE impresso = 0 ORDER BY id ASC LIMIT 20");
while ($p = $res->fetch_assoc()) {
    $itens = [];
    $stmtItens = $conn->prepare("SELECT nome_produto, tamanho, quantidade, preco_unitario, subtotal, produto_id_2 FROM pedido_itens WHERE pedido_id = ?");
    $stmtItens->bind_param('i', $p['id']);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();
    while ($it = $resItens->fetch_assoc()) {
        $itens[] = $it;
    }
    $p['itens'] = $itens;
    $pedidos[] = $p;
}

echo json_encode(['ok' => true, 'pedidos' => $pedidos], JSON_UNESCAPED_UNICODE);
