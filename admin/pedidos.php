<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Pedidos';
$activeMenu = 'pedidos';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['novo','em_preparo','saiu_entrega','concluido','cancelado'], true)) {
        $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
    }
}

$filtro = $_GET['status'] ?? '';
$sql = "SELECT * FROM pedidos";
if ($filtro) $sql .= " WHERE status = '" . $conn->real_escape_string($filtro) . "'";
$sql .= " ORDER BY id DESC LIMIT 100";
$pedidos = $conn->query($sql);

$statusLabel = [
    'novo' => 'Novo', 'em_preparo' => 'Em preparo', 'saiu_entrega' => 'Saiu p/ entrega',
    'concluido' => 'Concluído', 'cancelado' => 'Cancelado'
];

function itens_do_pedido($conn, $pedidoId) {
    $stmt = $conn->prepare("SELECT nome_produto, tamanho, quantidade, preco_unitario, subtotal, produto_id_2 FROM pedido_itens WHERE pedido_id = ?");
    $stmt->bind_param('i', $pedidoId);
    $stmt->execute();
    return $stmt->get_result();
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Pedidos recebidos</h1></div>

<div class="card-box">
  <form method="get" style="margin-bottom:12px;">
    <select name="status" onchange="this.form.submit()" style="border:1px solid var(--line);border-radius:8px;padding:7px 10px;">
      <option value="">Todos os status</option>
      <?php foreach ($statusLabel as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <table>
    <tr><th></th><th>#</th><th>Cliente</th><th>Tipo</th><th>Pagamento</th><th>Total</th><th>Status</th><th>Data</th></tr>
    <?php while ($p = $pedidos->fetch_assoc()): ?>
      <tr>
        <td>
          <button type="button" class="btn btn-outline btn-sm" onclick="var d=document.getElementById('itens-<?= $p['id'] ?>'); d.style.display = d.style.display==='table-row' ? 'none' : 'table-row';">Itens</button>
          <a class="btn btn-outline btn-sm" href="imprimir_pedido.php?id=<?= $p['id'] ?>" target="_blank" title="Reimprimir este pedido">🖨️</a>
        </td>
        <td>#<?= $p['id'] ?></td>
        <td>
          <strong><?= htmlspecialchars($p['nome_cliente']) ?></strong><br>
          <span style="color:var(--ink-soft);font-size:12px;"><?= htmlspecialchars($p['telefone']) ?></span>
          <?php if ($p['tipo_entrega'] === 'entrega'): ?>
            <br><span style="color:var(--ink-soft);font-size:12px;"><?= htmlspecialchars($p['rua'].', '.$p['numero'].' - '.$p['bairro']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= $p['tipo_entrega'] === 'entrega' ? '🛵 Entrega' : '🏪 Retirada' ?></td>
        <td>
          <?= ['dinheiro'=>'💵 Dinheiro','cartao'=>'💳 Cartão','pix'=>'🔑 Pix'][$p['forma_pagamento']] ?? $p['forma_pagamento'] ?>
          <?php if ($p['forma_pagamento'] === 'dinheiro' && $p['precisa_troco']): ?><br><small>Troco p/ R$ <?= number_format($p['troco_para'],2,',','.') ?></small><?php endif; ?>
        </td>
        <td><strong>R$ <?= number_format($p['total'],2,',','.') ?></strong></td>
        <td>
          <form method="post" style="display:flex;gap:4px;">
            <input type="hidden" name="acao" value="status">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <select name="status" onchange="this.form.submit()" style="border:1px solid var(--line);border-radius:8px;padding:5px 6px;font-size:12px;">
              <?php foreach ($statusLabel as $k => $v): ?>
                <option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><?= date('d/m H:i', strtotime($p['criado_em'])) ?></td>
      </tr>
      <tr id="itens-<?= $p['id'] ?>" style="display:none;">
        <td></td>
        <td colspan="7" style="background:#fafaf6;">
          <?php if ($p['observacoes']): ?><div style="margin-bottom:6px;"><strong>Obs:</strong> <?= htmlspecialchars($p['observacoes']) ?></div><?php endif; ?>
          <table style="width:100%;">
            <tr><th>Item</th><th>Tam.</th><th>Qtd</th><th>Preço</th><th>Subtotal</th></tr>
            <?php $itens = itens_do_pedido($conn, $p['id']); while ($it = $itens->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($it['nome_produto']) ?> <?= $it['produto_id_2'] ? '<span class="badge badge--on">meio a meio</span>' : '' ?></td>
                <td><?= $it['tamanho'] !== 'Único' ? htmlspecialchars($it['tamanho']) : '—' ?></td>
                <td><?= (int)$it['quantidade'] ?></td>
                <td>R$ <?= number_format($it['preco_unitario'],2,',','.') ?></td>
                <td>R$ <?= number_format($it['subtotal'],2,',','.') ?></td>
              </tr>
            <?php endwhile; ?>
          </table>
          <div style="text-align:right;margin-top:8px;font-size:13px;">
            Subtotal: R$ <?= number_format($p['subtotal'],2,',','.') ?>
            <?php if ($p['taxa_entrega'] > 0): ?> &nbsp;+&nbsp; Taxa de entrega: R$ <?= number_format($p['taxa_entrega'],2,',','.') ?><?php endif; ?>
            &nbsp;=&nbsp; <strong>Total: R$ <?= number_format($p['total'],2,',','.') ?></strong>
          </div>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  <?php if ($pedidos->num_rows === 0): ?><p style="color:var(--ink-soft);">Nenhum pedido encontrado.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
