<?php
// Espera que $pageTitle e $activeMenu estejam definidos antes do include
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Painel Top Pizzaria</title>
<link rel="icon" href="../images/logo.png">
<link rel="stylesheet" href="includes/admin.css?v=<?= @filemtime(__DIR__ . '/includes/admin.css') ?: time() ?>">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <div class="admin-side__logo">
      <img src="../images/logo.png" alt="Top Pizzaria">
      <b>Painel Top Pizzaria</b>
    </div>
    <nav class="admin-nav">
      <a href="dashboard.php" class="<?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">📊 Início</a>
      <a href="categorias.php" class="<?= ($activeMenu ?? '') === 'categorias' ? 'active' : '' ?>">🗂️ Categorias</a>
      <a href="produtos.php" class="<?= ($activeMenu ?? '') === 'produtos' ? 'active' : '' ?>">🍕 Produtos</a>
      <a href="bairros.php" class="<?= ($activeMenu ?? '') === 'bairros' ? 'active' : '' ?>">🏘️ Bairros</a>
      <a href="pedidos.php" class="<?= ($activeMenu ?? '') === 'pedidos' ? 'active' : '' ?>">🧾 Pedidos</a>
      <a href="agente_impressao.php" class="<?= ($activeMenu ?? '') === 'impressao' ? 'active' : '' ?>">🖨️ Agente de Impressão</a>
      <a href="configuracoes.php" class="<?= ($activeMenu ?? '') === 'config' ? 'active' : '' ?>">⚙️ Configurações</a>
      <a href="logout.php" class="logout">🚪 Sair</a>
    </nav>
  </aside>
  <main class="admin-main">
