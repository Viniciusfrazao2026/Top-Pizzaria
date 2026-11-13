<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

if (admin_logado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $stmt = $conn->prepare("SELECT id, senha_hash FROM admin_usuarios WHERE usuario = ? LIMIT 1");
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row && password_verify($senha, $row['senha_hash'])) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_usuario'] = $usuario;
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Painel Top Pizzaria</title>
<link rel="stylesheet" href="includes/admin.css?v=<?= @filemtime(__DIR__ . '/includes/admin.css') ?: time() ?>">
</head>
<body>
<div class="login-wrap">
  <form class="login-box" method="post">
    <img src="../images/logo.png" alt="Top Pizzaria">
    <h1>Painel Top Pizzaria</h1>
    <?php if ($erro): ?><div class="alert alert--err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <div class="field" style="text-align:left;">
      <label>Usuário</label>
      <input type="text" name="usuario" required autofocus>
    </div>
    <div class="field" style="text-align:left;">
      <label>Senha</label>
      <input type="password" name="senha" required>
    </div>
    <button class="btn btn-green" style="width:100%;justify-content:center;" type="submit">Entrar</button>
  </form>
</div>
</body>
</html>
