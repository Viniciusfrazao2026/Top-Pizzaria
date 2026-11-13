<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$nomeLoja = get_config($conn, 'nome_loja', 'Pizzaria Palazzio');
$teste = isset($_GET['teste']);

if ($teste) {
    $pedido = [
        'id' => 0,
        'criado_em' => date('Y-m-d H:i:s'),
        'tipo_entrega' => 'entrega',
        'nome_cliente' => 'Pedido de teste',
        'telefone' => '(87) 90000-0000',
        'rua' => 'Rua Exemplo', 'numero' => '123', 'bairro' => 'Centro',
        'forma_pagamento' => 'pix', 'precisa_troco' => 0, 'troco_para' => null,
        'observacoes' => 'Este é apenas um teste de impressão.',
        'subtotal' => 109.00, 'taxa_entrega' => 5.00, 'total' => 114.00,
    ];
    $itens = [
        ['nome_produto' => 'Calabresa', 'tamanho' => 'G', 'quantidade' => 1, 'preco_unitario' => 40.00, 'subtotal' => 40.00, 'produto_id_2' => null, 'categoria' => 'Pizzas Salgadas', 'categoria_2' => null],
        ['nome_produto' => '1/2 Seis Queijos + 1/2 Chocolate', 'tamanho' => 'G', 'quantidade' => 1, 'preco_unitario' => 45.00, 'subtotal' => 45.00, 'produto_id_2' => 2, 'categoria' => 'Pizzas Salgadas', 'categoria_2' => 'Pizzas Doces'],
        ['nome_produto' => 'Coca-Cola 2L', 'tamanho' => 'Único', 'quantidade' => 2, 'preco_unitario' => 12.00, 'subtotal' => 24.00, 'produto_id_2' => null, 'categoria' => 'Bebidas', 'categoria_2' => null],
    ];
} else {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { die('Pedido não informado.'); }
    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    if (!$pedido) { die('Pedido não encontrado.'); }

    $itens = [];
    $stmtItens = $conn->prepare("SELECT pi.nome_produto, pi.tamanho, pi.quantidade, pi.preco_unitario, pi.subtotal, pi.produto_id_2,
            c1.nome AS categoria, c2.nome AS categoria_2
        FROM pedido_itens pi
        LEFT JOIN produtos p1 ON p1.id = pi.produto_id
        LEFT JOIN categorias c1 ON c1.id = p1.categoria_id
        LEFT JOIN produtos p2 ON p2.id = pi.produto_id_2
        LEFT JOIN categorias c2 ON c2.id = p2.categoria_id
        WHERE pi.pedido_id = ?
        ORDER BY (c1.ordem IS NULL), c1.ordem ASC, pi.id ASC");
    $stmtItens->bind_param('i', $id);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();
    while ($it = $resItens->fetch_assoc()) { $itens[] = $it; }
}

function tam_label($t) {
    if ($t === 'P') return 'Pequena';
    if ($t === 'M') return 'Média';
    if ($t === 'G') return 'Grande';
    return ($t && $t !== 'Único') ? $t : '';
}
function money($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

// Agrupa os itens por categoria (na ordem das categorias do cardápio).
// Meio a meio com categorias diferentes (ex.: salgada + doce) vai no grupo "Meio a meio".
$grupos = [];
foreach ($itens as $it) {
    $cat  = trim((string)($it['categoria'] ?? ''));
    $cat2 = trim((string)($it['categoria_2'] ?? ''));
    if (!empty($it['produto_id_2']) && $cat !== '' && $cat2 !== '' && $cat !== $cat2) {
        $titulo = 'Meio a meio';
    } else {
        $titulo = $cat !== '' ? $cat : 'Outros';
    }
    $grupos[$titulo][] = $it;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedido #<?= (int)$pedido['id'] ?></title>
<style>
  @page { size: 58mm auto; margin: 0; }
  * { box-sizing: border-box; }
  html, body {
    margin: 0; padding: 0; width: 58mm;
    font-family: 'Courier New', monospace;
    font-size: 13px; font-weight: 700; line-height: 1.45; color: #000; background: #fff;
    -webkit-font-smoothing: antialiased;
  }
  .recibo { padding: 2mm 3mm; }
  .center { text-align: center; }
  .bold { font-weight: 700; }
  .linha { border-top: 2px dashed #000; margin: 7px 0; }
  .titulo-loja { font-size: 17px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
  .cat-titulo { text-align: center; text-transform: uppercase; font-weight: 800; font-size: 13px; letter-spacing: 0.5px; border-top: 2px solid #000; border-bottom: 2px solid #000; margin: 8px 0 4px; padding: 2px 0; }
  .item-row { margin: 5px 0; }
  .item-nome { font-weight: 800; font-size: 13.5px; }
  .item-detalhe { display: flex; justify-content: space-between; font-weight: 700; }
  .meio-a-meio { font-size: 11.5px; font-weight: 700; }
  table.resumo { width: 100%; margin-top: 5px; font-weight: 700; }
  table.resumo td { padding: 2px 0; }
  .resumo-row { display: flex; justify-content: space-between; gap: 8px; font-weight: 700; margin: 3px 0; }
  .resumo-row.total-row { font-weight: 800; font-size: 15px; border-top: 2px dashed #000; padding-top: 5px; margin-top: 5px; }
  .total-row td { font-weight: 800; font-size: 15px; border-top: 2px dashed #000; padding-top: 5px; }
  .obs { font-size: 12px; font-weight: 700; font-style: italic; }
  @media screen {
    body { width: 58mm; margin: 20px auto; border: 1px solid #ccc; }
  }
  @media print {
    html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="recibo">
  <div class="center titulo-loja"><?= htmlspecialchars($nomeLoja) ?></div>
  <div class="center bold"><?= $teste ? 'RECIBO DE TESTE' : ('Pedido #' . (int)$pedido['id']) ?></div>
  <div class="center bold"><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></div>
  <div class="linha"></div>

  <div class="bold"><?= $pedido['tipo_entrega'] === 'entrega' ? '🛵 ENTREGA' : '🏪 RETIRADA' ?></div>
  <div>Cliente: <?= htmlspecialchars($pedido['nome_cliente']) ?></div>
  <div>Telefone: <?= htmlspecialchars($pedido['telefone']) ?></div>
  <?php if ($pedido['tipo_entrega'] === 'entrega'): ?>
    <div>Endereço: <?= htmlspecialchars($pedido['rua'] . ', ' . $pedido['numero']) ?></div>
    <div>Bairro: <?= htmlspecialchars($pedido['bairro']) ?></div>
  <?php endif; ?>
  <div class="linha"></div>

  <div class="bold">ITENS:</div>
  <?php foreach ($grupos as $titulo => $lista): ?>
    <div class="cat-titulo"><?= htmlspecialchars($titulo) ?></div>
    <?php foreach ($lista as $it): ?>
      <div class="item-row">
        <div class="item-nome">
          <?= (int)$it['quantidade'] ?>x <?= htmlspecialchars($it['nome_produto']) ?>
          <?= $it['produto_id_2'] ? ' (meio a meio)' : '' ?>
        </div>
        <?php if (!empty($it['produto_id_2']) && !empty($it['categoria']) && !empty($it['categoria_2']) && $it['categoria'] !== $it['categoria_2']): ?>
          <div class="meio-a-meio"><?= htmlspecialchars($it['categoria']) ?> + <?= htmlspecialchars($it['categoria_2']) ?></div>
        <?php endif; ?>
        <?php if (tam_label($it['tamanho'])): ?><div class="meio-a-meio">Tamanho: <?= tam_label($it['tamanho']) ?></div><?php endif; ?>
        <div class="item-detalhe"><span><?= money($it['preco_unitario']) ?> cada</span><span><?= money($it['subtotal']) ?></span></div>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="linha"></div>

  <div class="resumo-row"><span>Subtotal</span><span><?= money($pedido['subtotal']) ?></span></div>
  <?php if ($pedido['taxa_entrega'] > 0): ?>
    <div class="resumo-row"><span>Taxa entrega</span><span><?= money($pedido['taxa_entrega']) ?></span></div>
  <?php endif; ?>
  <div class="resumo-row total-row"><span>TOTAL</span><span><?= money($pedido['total']) ?></span></div>
  <div class="linha"></div>

  <div class="bold">
    Pagamento: <?= ['dinheiro' => 'Dinheiro', 'cartao' => 'Cartão', 'pix' => 'Pix'][$pedido['forma_pagamento']] ?? $pedido['forma_pagamento'] ?>
  </div>
  <?php if ($pedido['forma_pagamento'] === 'dinheiro'): ?>
    <div><?= $pedido['precisa_troco'] ? 'Troco para ' . money($pedido['troco_para']) : 'Não precisa de troco' ?></div>
  <?php endif; ?>

  <?php if (!empty($pedido['observacoes'])): ?>
    <div class="linha"></div>
    <div class="obs">Obs: <?= htmlspecialchars($pedido['observacoes']) ?></div>
  <?php endif; ?>

  <div class="linha"></div>
  <div class="center">Obrigado pela preferência!</div>
</div>

<script>
  window.onload = function () {
    setTimeout(function () { window.print(); }, 150);
  };
</script>
</body>
</html>
