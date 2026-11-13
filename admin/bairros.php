<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Bairros';
$activeMenu = 'bairros';
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $taxa = (float)str_replace(',', '.', trim($_POST['taxa'] ?? '0'));
        if ($nome !== '') {
            $maxOrdem = $conn->query("SELECT COALESCE(MAX(ordem),0) m FROM bairros_entrega")->fetch_assoc()['m'];
            $novaOrdem = $maxOrdem + 1;
            $stmt = $conn->prepare("INSERT INTO bairros_entrega (nome, taxa, ordem, ativo) VALUES (?, ?, ?, 1)");
            $stmt->bind_param('sdi', $nome, $taxa, $novaOrdem);
            $stmt->execute();
            $msg = 'Bairro adicionado.'; $msgTipo = 'ok';
        }
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $taxa = (float)str_replace(',', '.', trim($_POST['taxa'] ?? '0'));
        if ($id && $nome !== '') {
            $stmt = $conn->prepare("UPDATE bairros_entrega SET nome = ?, taxa = ? WHERE id = ?");
            $stmt->bind_param('sdi', $nome, $taxa, $id);
            $stmt->execute();
            $msg = 'Bairro atualizado.'; $msgTipo = 'ok';
        }
    } elseif ($acao === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE bairros_entrega SET ativo = 1 - ativo WHERE id = " . $id);
        $msg = 'Status atualizado.'; $msgTipo = 'ok';
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM bairros_entrega WHERE id = " . $id);
        $msg = 'Bairro excluído.'; $msgTipo = 'ok';
    } elseif ($acao === 'mover') {
        $id = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $atual = $conn->query("SELECT id, ordem FROM bairros_entrega WHERE id = $id")->fetch_assoc();
        if ($atual) {
            $vizinho = $dir === 'up'
                ? $conn->query("SELECT id, ordem FROM bairros_entrega WHERE ordem < {$atual['ordem']} ORDER BY ordem DESC LIMIT 1")->fetch_assoc()
                : $conn->query("SELECT id, ordem FROM bairros_entrega WHERE ordem > {$atual['ordem']} ORDER BY ordem ASC LIMIT 1")->fetch_assoc();
            if ($vizinho) {
                $conn->query("UPDATE bairros_entrega SET ordem = {$vizinho['ordem']} WHERE id = {$atual['id']}");
                $conn->query("UPDATE bairros_entrega SET ordem = {$atual['ordem']} WHERE id = {$vizinho['id']}");
            }
        }
    }
}

$bairros = $conn->query("SELECT * FROM bairros_entrega ORDER BY ordem ASC");
$taxaPadrao = get_config($conn, 'taxa_entrega', '0');

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Bairros e taxas de entrega</h1></div>
<?php if ($msg): ?><div class="alert alert--<?= $msgTipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card-box">
  <p style="font-size:13.5px;color:var(--ink-soft);margin-top:0;">
    Cadastre aqui os bairros que você atende, cada um com sua própria taxa de entrega.
    No cardápio, o cliente escolhe o bairro numa lista e o valor da entrega é
    calculado automaticamente. Se o bairro do cliente não estiver na lista, ele
    pode escolher "Outro bairro" — nesse caso vale a
    <a href="configuracoes.php">taxa de entrega padrão</a> (atualmente R$ <?= number_format((float)$taxaPadrao,2,',','.') ?>).
  </p>
</div>

<div class="card-box">
  <h3 style="margin-bottom:10px;">Novo bairro</h3>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="hidden" name="acao" value="criar">
    <input type="text" name="nome" placeholder="Ex: Centro" required style="flex:2;min-width:200px;border:1.5px solid var(--line);border-radius:10px;padding:10px 12px;">
    <input type="text" name="taxa" placeholder="Taxa (ex: 6,00)" required style="flex:1;min-width:120px;border:1.5px solid var(--line);border-radius:10px;padding:10px 12px;">
    <button class="btn btn-gold" type="submit">+ Adicionar bairro</button>
  </form>
</div>

<div class="card-box">
  <table>
    <tr><th>Ordem</th><th>Bairro</th><th>Taxa de entrega</th><th>Status</th><th>Ações</th></tr>
    <?php while ($b = $bairros->fetch_assoc()): ?>
    <tr>
      <td>
        <div class="move-btns">
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="dir" value="up"><button title="Subir">▲</button></form>
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="dir" value="down"><button title="Descer">▼</button></form>
        </div>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center;">
          <input type="hidden" name="acao" value="editar">
          <input type="hidden" name="id" value="<?= $b['id'] ?>">
          <input type="text" name="nome" value="<?= htmlspecialchars($b['nome']) ?>" style="border:1px solid var(--line);border-radius:8px;padding:5px 8px;flex:1;">
          <span style="color:var(--ink-soft);">R$</span>
          <input type="text" name="taxa" value="<?= number_format($b['taxa'],2,',','') ?>" style="border:1px solid var(--line);border-radius:8px;padding:5px 8px;width:80px;">
          <button class="btn btn-outline btn-sm" type="submit">Salvar</button>
        </form>
      </td>
      <td><strong>R$ <?= number_format($b['taxa'],2,',','.') ?></strong></td>
      <td><span class="badge <?= $b['ativo'] ? 'badge--on' : 'badge--off' ?>"><?= $b['ativo'] ? 'Visível' : 'Oculto' ?></span></td>
      <td style="display:flex;gap:6px;">
        <form method="post"><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $b['ativo'] ? 'Ocultar' : 'Exibir' ?></button></form>
        <form method="post" onsubmit="return confirm('Excluir este bairro?');"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Excluir</button></form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <?php if ($bairros->num_rows === 0): ?><p style="color:var(--ink-soft);">Nenhum bairro cadastrado ainda — enquanto isso, o cardápio mostra só a opção "Outro bairro" com a taxa padrão.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
