<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers_meio.php';
garantir_coluna_fatias($conn);
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Produtos';
$activeMenu = 'produtos';
$msg = ''; $msgTipo = '';

$categorias = $conn->query("SELECT id, nome FROM categorias ORDER BY ordem ASC");
$categoriasArr = [];
while ($c = $categorias->fetch_assoc()) $categoriasArr[] = $c;

function upload_imagem($conn, $file) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) return null;
    $novoNome = 'prod_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destino = __DIR__ . '/../images/products/' . $novoNome;
    if (move_uploaded_file($file['tmp_name'], $destino)) {
        return $novoNome;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $temTamanhos = isset($_POST['tem_tamanhos']) ? 1 : 0;

        if ($nome === '' || $categoriaId === 0) {
            $msg = 'Preencha nome e categoria.'; $msgTipo = 'err';
        } else {
            $imagemNome = upload_imagem($conn, $_FILES['imagem'] ?? null);

            if ($id) {
                if ($imagemNome) {
                    $stmt = $conn->prepare("UPDATE produtos SET categoria_id=?, nome=?, descricao=?, tem_tamanhos=?, imagem=? WHERE id=?");
                    $stmt->bind_param('issssi', $categoriaId, $nome, $descricao, $temTamanhos, $imagemNome, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE produtos SET categoria_id=?, nome=?, descricao=?, tem_tamanhos=? WHERE id=?");
                    $stmt->bind_param('isssi', $categoriaId, $nome, $descricao, $temTamanhos, $id);
                }
                $stmt->execute();
                $produtoId = $id;
            } else {
                $maxOrdem = $conn->query("SELECT COALESCE(MAX(ordem),0) m FROM produtos WHERE categoria_id = $categoriaId")->fetch_assoc()['m'];
                $novaOrdem = $maxOrdem + 1;
                $stmt = $conn->prepare("INSERT INTO produtos (categoria_id, nome, descricao, imagem, ordem, tem_tamanhos, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param('isssii', $categoriaId, $nome, $descricao, $imagemNome, $novaOrdem, $temTamanhos);
                $stmt->execute();
                $produtoId = $conn->insert_id;
            }

            // Preços
            $conn->query("DELETE FROM precos WHERE produto_id = $produtoId");
            if ($temTamanhos) {
                foreach (['P', 'M', 'G'] as $tam) {
                    $preco = isset($_POST['preco_' . $tam]) ? (float)str_replace(',', '.', $_POST['preco_' . $tam]) : 0;
                    $fatiasTam = (int)($_POST['fatias_' . $tam] ?? 0);
                    $fatiasTam = $fatiasTam > 0 ? min($fatiasTam, 99) : null; // vazio = usa o padrão das Configurações
                    if ($preco > 0) {
                        $stmt = $conn->prepare("INSERT INTO precos (produto_id, tamanho, preco, fatias) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('isdi', $produtoId, $tam, $preco, $fatiasTam);
                        $stmt->execute();
                    }
                }
            } else {
                $preco = isset($_POST['preco_unico']) ? (float)str_replace(',', '.', $_POST['preco_unico']) : 0;
                $tamanho = 'Único';
                $stmt = $conn->prepare("INSERT INTO precos (produto_id, tamanho, preco) VALUES (?, ?, ?)");
                $stmt->bind_param('isd', $produtoId, $tamanho, $preco);
                $stmt->execute();
            }
            $msg = 'Produto salvo com sucesso.'; $msgTipo = 'ok';
        }
    } elseif ($acao === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE produtos SET ativo = 1 - ativo WHERE id = " . $id);
        $msg = 'Status atualizado.'; $msgTipo = 'ok';
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM produtos WHERE id = " . $id);
        $msg = 'Produto excluído.'; $msgTipo = 'ok';
    } elseif ($acao === 'mover') {
        $id = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        $atual = $conn->query("SELECT id, categoria_id, ordem FROM produtos WHERE id = $id")->fetch_assoc();
        if ($atual) {
            $catId = $atual['categoria_id'];
            $vizinho = $dir === 'up'
                ? $conn->query("SELECT id, ordem FROM produtos WHERE categoria_id = $catId AND ordem < {$atual['ordem']} ORDER BY ordem DESC LIMIT 1")->fetch_assoc()
                : $conn->query("SELECT id, ordem FROM produtos WHERE categoria_id = $catId AND ordem > {$atual['ordem']} ORDER BY ordem ASC LIMIT 1")->fetch_assoc();
            if ($vizinho) {
                $conn->query("UPDATE produtos SET ordem = {$vizinho['ordem']} WHERE id = {$atual['id']}");
                $conn->query("UPDATE produtos SET ordem = {$atual['ordem']} WHERE id = {$vizinho['id']}");
            }
        }
    }
}

// Produto em edição (se veio ?editar=ID)
$editando = null;
$editandoPrecos = [];
$editandoFatias = [];
if (isset($_GET['editar'])) {
    $eid = (int)$_GET['editar'];
    $editando = $conn->query("SELECT * FROM produtos WHERE id = $eid")->fetch_assoc();
    if ($editando) {
        $pr = $conn->query("SELECT tamanho, preco, fatias FROM precos WHERE produto_id = $eid");
        while ($p = $pr->fetch_assoc()) { $editandoPrecos[$p['tamanho']] = $p['preco']; $editandoFatias[$p['tamanho']] = $p['fatias']; }
    }
}

$filtroCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$sql = "SELECT p.*, c.nome AS categoria_nome FROM produtos p JOIN categorias c ON c.id = p.categoria_id";
if ($filtroCat) $sql .= " WHERE p.categoria_id = $filtroCat";
$sql .= " ORDER BY p.categoria_id, p.ordem ASC";
$produtos = $conn->query($sql);

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Produtos do cardápio</h1></div>
<?php if ($msg): ?><div class="alert alert--<?= $msgTipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card-box">
  <h3 style="margin-bottom:14px;"><?= $editando ? 'Editar produto' : 'Novo produto' ?></h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="acao" value="salvar">
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>

    <div class="field-row">
      <div class="field">
        <label>Categoria</label>
        <select name="categoria_id" required>
          <option value="">Selecione...</option>
          <?php foreach ($categoriasArr as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($editando && $editando['categoria_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Nome do produto</label>
        <input type="text" name="nome" required value="<?= htmlspecialchars($editando['nome'] ?? '') ?>">
      </div>
    </div>

    <div class="field">
      <label>Descrição / ingredientes</label>
      <textarea name="descricao" rows="2"><?= htmlspecialchars($editando['descricao'] ?? '') ?></textarea>
    </div>

    <div class="field">
      <label><input type="checkbox" id="temTamanhos" name="tem_tamanhos" onchange="togglePrecos()" <?= (!$editando || $editando['tem_tamanhos']) ? 'checked' : '' ?>> Este produto tem tamanhos (P / M / G) — desmarque para produtos com preço único, como bebidas</label>
    </div>

    <div id="precosTamanhos" class="field-row">
      <div class="field"><label>Preço Pequena (P)</label><input type="text" name="preco_P" value="<?= isset($editandoPrecos['P']) ? number_format($editandoPrecos['P'],2,',','') : '' ?>" placeholder="0,00">
        <label style="margin-top:8px;">Fatias Pequena</label><input type="number" min="1" max="99" name="fatias_P" value="<?= !empty($editandoFatias['P']) ? (int)$editandoFatias['P'] : '' ?>" placeholder="padrão"></div>
      <div class="field"><label>Preço Média (M)</label><input type="text" name="preco_M" value="<?= isset($editandoPrecos['M']) ? number_format($editandoPrecos['M'],2,',','') : '' ?>" placeholder="0,00">
        <label style="margin-top:8px;">Fatias Média</label><input type="number" min="1" max="99" name="fatias_M" value="<?= !empty($editandoFatias['M']) ? (int)$editandoFatias['M'] : '' ?>" placeholder="padrão"></div>
      <div class="field"><label>Preço Grande (G)</label><input type="text" name="preco_G" value="<?= isset($editandoPrecos['G']) ? number_format($editandoPrecos['G'],2,',','') : '' ?>" placeholder="0,00">
        <label style="margin-top:8px;">Fatias Grande</label><input type="number" min="1" max="99" name="fatias_G" value="<?= !empty($editandoFatias['G']) ? (int)$editandoFatias['G'] : '' ?>" placeholder="padrão"></div>
    </div>
    <div id="precoUnico" class="field" style="display:none;">
      <label>Preço único</label>
      <input type="text" name="preco_unico" value="<?= isset($editandoPrecos['Único']) ? number_format($editandoPrecos['Único'],2,',','') : '' ?>" placeholder="0,00">
    </div>

    <div class="field">
      <label>Foto do produto <?= $editando && $editando['imagem'] ? '(atual: ' . htmlspecialchars($editando['imagem']) . ')' : '' ?></label>
      <input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp">
    </div>

    <button class="btn btn-gold" type="submit"><?= $editando ? 'Salvar alterações' : '+ Adicionar produto' ?></button>
    <?php if ($editando): ?><a class="btn btn-outline" href="produtos.php">Cancelar edição</a><?php endif; ?>
  </form>
</div>

<div class="card-box">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
    <h3>Lista de produtos</h3>
    <form method="get" style="display:flex;gap:8px;">
      <select name="cat" onchange="this.form.submit()" style="border:1px solid var(--line);border-radius:8px;padding:6px 8px;">
        <option value="0">Todas as categorias</option>
        <?php foreach ($categoriasArr as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filtroCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <table>
    <tr><th>Ordem</th><th>Foto</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr>
    <?php while ($p = $produtos->fetch_assoc()):
        $precosProd = $conn->query("SELECT tamanho, preco, fatias FROM precos WHERE produto_id = {$p['id']} ORDER BY id");
        $precosStr = [];
        while ($pr = $precosProd->fetch_assoc()) $precosStr[] = ($pr['tamanho'] !== 'Único' ? $pr['tamanho'].': ' : '') . 'R$ ' . number_format($pr['preco'],2,',','.') . (!empty($pr['fatias']) ? ' · ' . (int)$pr['fatias'] . ' fatias' : '');
    ?>
    <tr>
      <td>
        <div class="move-btns">
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="dir" value="up"><button>▲</button></form>
          <form method="post"><input type="hidden" name="acao" value="mover"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="dir" value="down"><button>▼</button></form>
        </div>
      </td>
      <td>
        <?php if ($p['imagem']): ?>
          <img class="thumb" src="../images/products/<?= htmlspecialchars($p['imagem']) ?>">
        <?php else: ?>
          <div class="thumb thumb--empty">sem foto</div>
        <?php endif; ?>
      </td>
      <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
      <td><?= htmlspecialchars($p['categoria_nome']) ?></td>
      <td style="font-size:12px;"><?= implode('<br>', $precosStr) ?></td>
      <td><span class="badge <?= $p['ativo'] ? 'badge--on' : 'badge--off' ?>"><?= $p['ativo'] ? 'Visível' : 'Oculto' ?></span></td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <a class="btn btn-outline btn-sm" href="?editar=<?= $p['id'] ?>">Editar</a>
        <form method="post"><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $p['ativo'] ? 'Ocultar' : 'Exibir' ?></button></form>
        <form method="post" onsubmit="return confirm('Excluir este produto?');"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-danger btn-sm" type="submit">Excluir</button></form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<script>
function togglePrecos(){
  var comTamanho = document.getElementById('temTamanhos').checked;
  document.getElementById('precosTamanhos').style.display = comTamanho ? 'flex' : 'none';
  document.getElementById('precoUnico').style.display = comTamanho ? 'none' : 'block';
}
togglePrecos();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
