<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Início';
$activeMenu = 'dashboard';

$hoje = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(total),0) AS soma FROM pedidos WHERE DATE(criado_em) = ?");
$stmt->bind_param('s', $hoje);
$stmt->execute();
$hojeInfo = $stmt->get_result()->fetch_assoc();

$totalProdutos = $conn->query("SELECT COUNT(*) c FROM produtos")->fetch_assoc()['c'];
$totalCategorias = $conn->query("SELECT COUNT(*) c FROM categorias")->fetch_assoc()['c'];
$pedidosNovos = $conn->query("SELECT COUNT(*) c FROM pedidos WHERE status = 'novo'")->fetch_assoc()['c'];

$ultimosPedidos = $conn->query("SELECT id, nome_cliente, tipo_entrega, total, status, criado_em FROM pedidos ORDER BY id DESC LIMIT 8");

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Olá! Aqui está o resumo de hoje</h1></div>

<div class="card-box" style="display:flex;gap:24px;flex-wrap:wrap;">
  <div><div style="font-size:12px;color:var(--ink-soft);font-weight:700;">Pedidos hoje</div><div style="font-size:26px;font-weight:800;color:var(--green-dark);"><?= (int)$hojeInfo['qtd'] ?></div></div>
  <div><div style="font-size:12px;color:var(--ink-soft);font-weight:700;">Faturado hoje</div><div style="font-size:26px;font-weight:800;color:var(--green-dark);">R$ <?= number_format($hojeInfo['soma'], 2, ',', '.') ?></div></div>
  <div><div style="font-size:12px;color:var(--ink-soft);font-weight:700;">Pedidos novos</div><div style="font-size:26px;font-weight:800;color:var(--tomato);"><?= (int)$pedidosNovos ?></div></div>
  <div><div style="font-size:12px;color:var(--ink-soft);font-weight:700;">Produtos cadastrados</div><div style="font-size:26px;font-weight:800;color:var(--green-dark);"><?= (int)$totalProdutos ?></div></div>
  <div><div style="font-size:12px;color:var(--ink-soft);font-weight:700;">Categorias</div><div style="font-size:26px;font-weight:800;color:var(--green-dark);"><?= (int)$totalCategorias ?></div></div>
</div>

<div class="card-box">
  <h3 style="margin-bottom:12px;">Últimos pedidos</h3>
  <table>
    <tr><th>#</th><th>Cliente</th><th>Tipo</th><th>Total</th><th>Status</th><th>Data</th></tr>
    <?php while ($p = $ultimosPedidos->fetch_assoc()): ?>
      <tr>
        <td>#<?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['nome_cliente']) ?></td>
        <td><?= $p['tipo_entrega'] === 'entrega' ? 'Entrega' : 'Retirada' ?></td>
        <td>R$ <?= number_format($p['total'], 2, ',', '.') ?></td>
        <td><span class="badge badge--on"><?= htmlspecialchars($p['status']) ?></span></td>
        <td><?= date('d/m H:i', strtotime($p['criado_em'])) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
  <?php if ($ultimosPedidos->num_rows === 0): ?><p style="color:var(--ink-soft);">Nenhum pedido ainda.</p><?php endif; ?>
  <div style="margin-top:12px;"><a class="btn btn-outline" href="pedidos.php">Ver todos os pedidos →</a></div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
