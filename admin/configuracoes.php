<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Configurações';
$activeMenu = 'config';
$msg = ''; $msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_loja') {
        set_config($conn, 'nome_loja', trim($_POST['nome_loja'] ?? ''));
        set_config($conn, 'telefone_whatsapp', preg_replace('/\D/', '', $_POST['telefone_whatsapp'] ?? ''));
        set_config($conn, 'chave_pix', trim($_POST['chave_pix'] ?? ''));
        set_config($conn, 'endereco_retirada', trim($_POST['endereco_retirada'] ?? ''));
        set_config($conn, 'maps_link_retirada', trim($_POST['maps_link_retirada'] ?? ''));
        set_config($conn, 'loja_aberta', isset($_POST['loja_aberta']) ? '1' : '0');
        $taxaEntregaPost = str_replace(',', '.', trim($_POST['taxa_entrega'] ?? '0'));
        set_config($conn, 'taxa_entrega', number_format((float)$taxaEntregaPost, 2, '.', ''));
        $tempoMin = max(0, (int)($_POST['tempo_entrega_min'] ?? 0));
        $tempoMax = max(0, (int)($_POST['tempo_entrega_max'] ?? 0));
        if ($tempoMax > 0 && $tempoMax < $tempoMin) { $tempoMax = $tempoMin; }
        set_config($conn, 'tempo_entrega_min', (string)$tempoMin);
        set_config($conn, 'tempo_entrega_max', (string)$tempoMax);
        $msg = 'Configurações salvas.'; $msgTipo = 'ok';
    } elseif ($acao === 'salvar_fatias') {
        foreach (['P', 'M', 'G'] as $t) {
            $n = (int)($_POST['fatias_' . $t] ?? 0);
            set_config($conn, 'fatias_' . $t, (string)max(0, min($n, 99)));
        }
        $msg = 'Fatias padrão salvas.'; $msgTipo = 'ok';
    } elseif ($acao === 'trocar_senha') {
        $nova = $_POST['nova_senha'] ?? '';
        if (strlen($nova) < 6) {
            $msg = 'A nova senha deve ter pelo menos 6 caracteres.'; $msgTipo = 'err';
        } else {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_usuarios SET senha_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $_SESSION['admin_id']);
            $stmt->execute();
            $msg = 'Senha alterada com sucesso.'; $msgTipo = 'ok';
        }
    }
}

$cfg = [];
foreach (['nome_loja','telefone_whatsapp','chave_pix','endereco_retirada','maps_link_retirada','loja_aberta','taxa_entrega','tempo_entrega_min','tempo_entrega_max'] as $k) {
    $cfg[$k] = get_config($conn, $k, '');
}

foreach (['fatias_P','fatias_M','fatias_G'] as $k) {
    $cfg[$k] = get_config($conn, $k, '');
}

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Configurações da loja</h1></div>
<?php if ($msg): ?><div class="alert alert--<?= $msgTipo ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card-box">
  <h3 style="margin-bottom:14px;">Dados gerais</h3>
  <form method="post">
    <input type="hidden" name="acao" value="salvar_loja">
    <div class="field"><label>Nome da loja</label><input type="text" name="nome_loja" value="<?= htmlspecialchars($cfg['nome_loja']) ?>"></div>
    <div class="field"><label>WhatsApp para receber pedidos (com DDI+DDD, só números — ex: 5587991361950)</label><input type="text" name="telefone_whatsapp" value="<?= htmlspecialchars($cfg['telefone_whatsapp']) ?>"></div>
    <div class="field"><label>Chave Pix (exibida no checkout)</label><input type="text" name="chave_pix" value="<?= htmlspecialchars($cfg['chave_pix']) ?>"></div>
    <div class="field"><label>Endereço para retirada</label><input type="text" name="endereco_retirada" value="<?= htmlspecialchars($cfg['endereco_retirada']) ?>"></div>
    <div class="field"><label>Link do Google Maps para retirada</label><input type="text" name="maps_link_retirada" value="<?= htmlspecialchars($cfg['maps_link_retirada']) ?>"></div>
    <div class="field"><label>Taxa de entrega / frete (cobrada só quando o cliente escolhe "Entrega" — não se aplica na retirada)</label><input type="text" name="taxa_entrega" value="<?= htmlspecialchars(number_format((float)($cfg['taxa_entrega'] ?: 0), 2, ',', '')) ?>" placeholder="0,00"></div>
    <div class="field"><label><input type="checkbox" name="loja_aberta" <?= $cfg['loja_aberta'] === '1' ? 'checked' : '' ?>> Loja aberta (aparece "Aberto" no cardápio do cliente)</label></div>
    <div class="field"><label>Previsão de entrega — tempo mínimo (minutos)</label><input type="number" min="0" name="tempo_entrega_min" value="<?= htmlspecialchars($cfg['tempo_entrega_min'] ?: '30') ?>"></div>
    <div class="field"><label>Previsão de entrega — tempo máximo (minutos, opcional)</label><input type="number" min="0" name="tempo_entrega_max" value="<?= htmlspecialchars($cfg['tempo_entrega_max'] ?: '50') ?>"></div>
    <button class="btn btn-gold" type="submit">Salvar configurações</button>
  </form>
</div>

<div class="card-box">
  <h3 style="margin-bottom:6px;">Fatias por tamanho (padrão)</h3>
  <p class="hint" style="margin:0 0 12px;">Aparece no cardápio ao lado de Pequena / Média / Grande. Deixe 0 para não mostrar. Em cada produto você pode definir um número diferente.</p>
  <form method="post" style="max-width:420px;">
    <input type="hidden" name="acao" value="salvar_fatias">
    <div class="field-row">
      <div class="field"><label>Pequena (P)</label><input type="number" min="0" max="99" name="fatias_P" value="<?= (int)$cfg['fatias_P'] ?>"></div>
      <div class="field"><label>Média (M)</label><input type="number" min="0" max="99" name="fatias_M" value="<?= (int)$cfg['fatias_M'] ?>"></div>
      <div class="field"><label>Grande (G)</label><input type="number" min="0" max="99" name="fatias_G" value="<?= (int)$cfg['fatias_G'] ?>"></div>
    </div>
    <button class="btn btn-gold" type="submit">Salvar fatias</button>
  </form>
</div>

<div class="card-box">
  <h3 style="margin-bottom:14px;">Alterar senha do admin</h3>
  <form method="post" style="max-width:320px;">
    <input type="hidden" name="acao" value="trocar_senha">
    <div class="field"><label>Nova senha</label><input type="password" name="nova_senha" minlength="6" required></div>
    <button class="btn btn-outline" type="submit">Alterar senha</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
