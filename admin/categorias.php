<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers_meio.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

// cria a coluna "meio_a_meio" automaticamente na primeira vez (se o banco permitir)
$temColunaMeio = garantir_coluna_meio_a_meio($conn);

$pageTitle = 'Categorias';
$activeMenu = 'categorias';
$msg = ''; $msgTipo = '';

// ---- Ações ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome !== '') {
            $maxOrdem = $conn->query("SELECT COALESCE(MAX(ordem),0) m FROM categorias")->fetch_assoc()['m'];
            $novaOrdem = $maxOrdem + 1;
            $stmt = $conn->prepare("INSERT INTO categorias (nome, ordem, ativo) VALUES (?, ?, 1)");
            $stmt->bind_param('si', $nome, $novaOrdem);
            $stmt->execute();
            $msg = 'Categoria criada.'; $msgTipo = 'ok';
        }
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id && $nome !== '') {
            $stmt = $conn->prepare("UPDATE categorias SET nome = ? WHERE id = ?");
            $stmt->bind_param('si', $nome, $id);
            $stmt->execute();
            $msg = 'Categoria atualizada.'; $msgTipo = 'ok';
        }
    } elseif ($acao === 'meio' && $temColunaMeio) {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE categorias SET meio_a_meio = 1 - meio_a_meio WHERE id = " . $id);
        $msg = 'Meio a meio atualizado.'; $msgTipo = 'ok';
    } elseif ($acao === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE categorias SET ativo = 1 - ativo WHERE id = " . $id);
        $msg = 'Status atualizado.'; $msgTipo = 'ok';
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM categorias WHERE id = " . $id);
        $msg = 'Categoria excluída.'; $msgTipo = 'ok';
    } elseif ($acao === 'mover') {
        $id = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $atual = $conn->query("SELECT id, ordem FROM categorias WHERE id = $id")->fetch_assoc();
        if ($atual) {
            $vizinho = $dir === 'up'
                ? $conn->query("SELECT id, ordem FROM categorias WHERE ordem < {$atual['ordem']} ORDER BY ordem DESC LIMIT 1")->fetch_assoc()
                : $conn->query("SELECT id, ordem FROM categorias WHERE ordem > {$atual['ordem']} ORDER BY ordem ASC LIMIT 1")->fetch_assoc();
            if ($vizinho) {
                $conn->query("UPDATE categorias SET ordem = {$vizinho['ordem']} WHERE id = {$atual['id']}");
                $conn->query("UPDATE categorias SET ordem = {$atual['ordem']} WHERE id = {$vizinho['id']}");
            }
        }
    }
}

$categorias = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM produtos p WHERE p.categoria_id = c.id) AS total_produtos FROM categorias c ORDER BY ordem ASC");

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header">
  <h1>Categorias do cardápio</h1>
</div>

<?php if ($msg): ?><div class="alert alert--<?= $msgTipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (!$temColunaMeio): ?>
<div class="alert alert--err">Para liberar o meio a meio entre categorias, rode este comando no phpMyAdmin (aba SQL) e recarregue a página:<br><code>ALTER TABLE categorias ADD COLUMN meio_a_meio TINYINT(1) NOT NULL DEFAULT 0;</code></div>
<?php endif; ?>

<div class="card-box">
  <h3 style="margin-bottom:10px;">Nova categoria</h3>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="hidden" name="acao" value="criar">
    <input type="text" name="nome" placeholder="Ex: Sobremesas" required style="flex:1;min-width:200px;border:1.5px solid var(--line);border-radius:10px;padding:10px 12px;">
    <button class="btn btn-gold" type="submit">+ Adicionar categoria</button>
  </form>
</div>

<div class="card-box">
  <table>
    <tr><th>Ordem</th><th>Categoria</th><th>Produtos</th><th>Status</th><?php if ($temColunaMeio): ?><th>Meio a meio</th><?php endif; ?><th>Ações</th></tr>
    <?php while ($c = $categorias->fetch_assoc()): ?>
    <tr>
      <td>
        <div class="move-btns">
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="up"><button title="Subir">▲</button></form>
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="dir" value="down"><button title="Descer">▼</button></form>
        </div>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px;">
          <input type="hidden" name="acao" value="editar">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <input type="text" name="nome" value="<?= htmlspecialchars($c['nome']) ?>" style="border:1px solid var(--line);border-radius:8px;padding:5px 8px;">
          <button class="btn btn-outline btn-sm" type="submit">Salvar</button>
        </form>
      </td>
      <td><?= (int)$c['total_produtos'] ?></td>
      <td><span class="badge <?= $c['ativo'] ? 'badge--on' : 'badge--off' ?>"><?= $c['ativo'] ? 'Visível' : 'Oculta' ?></span></td>
      <?php if ($temColunaMeio): ?>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center;">
          <input type="hidden" name="acao" value="meio"><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <span class="badge <?= !empty($c['meio_a_meio']) ? 'badge--on' : 'badge--off' ?>"><?= !empty($c['meio_a_meio']) ? 'Liberado' : 'Não' ?></span>
          <button class="btn btn-outline btn-sm" type="submit"><?= !empty($c['meio_a_meio']) ? 'Bloquear' : 'Liberar' ?></button>
        </form>
      </td>
      <?php endif; ?>
      <td style="display:flex;gap:6px;">
        <form method="post"><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $c['ativo'] ? 'Ocultar' : 'Exibir' ?></button></form>
        <form method="post" onsubmit="return confirm('Excluir esta categoria e todos os seus produtos?');"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Excluir</button></form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <p class="hint" style="color:var(--ink-soft);font-size:12.5px;margin-top:10px;">A ordem definida aqui é a mesma ordem das abas do cardápio do cliente.</p>
  <?php if ($temColunaMeio): ?>
  <p class="hint" style="color:var(--ink-soft);font-size:12.5px;margin-top:6px;">Meio a meio: libere as categorias de pizza (ex.: Pizzas Salgadas e Pizzas Doces) para o cliente poder misturar sabores entre elas. Categorias não liberadas só combinam sabores da própria categoria.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
