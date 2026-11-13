<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';
exigir_login();

$pageTitle = 'Agente de Impressão';
$activeMenu = 'impressao';

require __DIR__ . '/includes/layout_top.php';
?>
<div class="admin-header"><h1>Agente de impressão automática (58mm)</h1></div>

<div class="card-box">
  <h3 style="margin-bottom:10px;">Como configurar (só precisa fazer isso uma vez)</h3>
  <ol style="font-size:13.5px;line-height:1.8;color:var(--ink);padding-left:20px;">
    <li>No computador ligado à impressora térmica 58mm, instale o driver da impressora e deixe ela definida como <strong>impressora padrão do Windows</strong>.</li>
    <li>
      Crie um arquivo de texto na Área de Trabalho, cole o conteúdo abaixo, e salve como
      <code>abrir_impressao.bat</code> (o final precisa ser <code>.bat</code>, não <code>.txt</code>):
      <pre style="background:#f2efe4;padding:10px 12px;border-radius:8px;margin-top:6px;white-space:pre-wrap;word-break:break-all;font-size:12px;">@echo off
taskkill /F /IM chrome.exe /T >nul 2>&1
timeout /t 2 /nobreak >nul
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing "<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) ?>/admin/agente_impressao.php"</pre>
    </li>
    <li>
      <strong>Isso é importante:</strong> se o Google Chrome já estiver aberto quando você usar o atalho comum, o Windows
      só abre uma aba nova na janela que já existe — e a impressão silenciosa <strong>não funciona</strong> nesse caso.
      Por isso o arquivo acima fecha à força qualquer Chrome aberto antes de abrir de novo, já com a impressão automática ativada.
    </li>
    <li>Sempre que for usar o computador do caixa, abra a impressão automática clicando duas vezes nesse arquivo <code>.bat</code> (não abra o Chrome do jeito normal). Faça login no admin e deixe essa aba aberta o dia todo.</li>
    <li>
      Se o caminho <code>C:\Program Files\Google\Chrome\Application\chrome.exe</code> não existir no seu computador,
      o Chrome provavelmente foi instalado só para o seu usuário — troque essa linha por:
      <code style="background:#f2efe4;padding:4px 8px;border-radius:6px;display:inline-block;margin-top:4px;word-break:break-all;">start "" "%LocalAppData%\Google\Chrome\Application\chrome.exe" --kiosk-printing "<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) ?>/admin/agente_impressao.php"</code>
    </li>
  </ol>
  <p style="font-size:12.5px;color:var(--ink-soft);">
    Como saber se funcionou: quando um pedido novo chegar, ele deve sair na impressora sozinho, sem aparecer
    NENHUMA janela de impressão na tela. Se ainda aparecer a janela de impressão do Chrome pedindo pra você clicar
    em "Imprimir", é sinal de que o Chrome não foi aberto pelo arquivo .bat (ou ainda havia uma janela dele aberta em segundo plano).
  </p>
</div>

<div class="card-box">
  <h3 style="margin-bottom:6px;">🔍 Diagnóstico rápido</h3>
  <p style="font-size:13px;color:var(--ink-soft);">
    Clique no botão "Imprimir pedido de teste" abaixo. Se aparecer a janela de impressão do Chrome
    (com botões Cancelar/Imprimir), a impressão silenciosa <strong>ainda não está ativa</strong> — revise os passos
    acima, principalmente fechar todo o Chrome antes de abrir pelo .bat. Se o recibo sair direto na impressora sem
    nenhuma janela aparecer, está tudo certo.
  </p>
</div>

<div class="card-box">
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div id="statusDot" style="width:14px;height:14px;border-radius:50%;background:#ccc;"></div>
    <div id="statusText" style="font-weight:800;">Monitoramento pausado</div>
    <button class="btn btn-green" id="btnToggle">Iniciar monitoramento</button>
    <button class="btn btn-outline" id="btnTeste">🖨️ Imprimir pedido de teste</button>
  </div>
  <p id="lastCheck" style="font-size:12px;color:var(--ink-soft);margin-top:10px;">Ainda não verificado.</p>
</div>

<div class="card-box">
  <h3 style="margin-bottom:10px;">Pedidos impressos nesta sessão</h3>
  <table id="logTable">
    <tr><th>#</th><th>Cliente</th><th>Total</th><th>Horário</th></tr>
  </table>
  <p id="logEmpty" style="color:var(--ink-soft);font-size:13px;">Nenhum pedido impresso ainda nesta sessão.</p>
</div>

<iframe id="printFrame" style="position:absolute;left:-9999px;top:-9999px;width:302px;height:600px;border:0;"></iframe>

<script>
(function () {
  var monitorando = false;
  var timer = null;
  var INTERVALO_MS = 7000;

  var statusDot = document.getElementById('statusDot');
  var statusText = document.getElementById('statusText');
  var btnToggle = document.getElementById('btnToggle');
  var btnTeste = document.getElementById('btnTeste');
  var lastCheck = document.getElementById('lastCheck');
  var logTable = document.getElementById('logTable');
  var logEmpty = document.getElementById('logEmpty');
  var printFrame = document.getElementById('printFrame');

  function beep() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      osc.start();
      osc.stop(ctx.currentTime + 0.18);
    } catch (e) { /* navegador sem suporte a áudio, ignora */ }
  }

  function fmt(v) {
    return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ',');
  }

  function adicionarAoLog(pedido) {
    logEmpty.style.display = 'none';
    var row = document.createElement('tr');
    var agora = new Date();
    var hora = agora.getHours().toString().padStart(2, '0') + ':' + agora.getMinutes().toString().padStart(2, '0');
    row.innerHTML = '<td>#' + pedido.id + '</td><td>' + pedido.nome_cliente + '</td><td>' + fmt(pedido.total) + '</td><td>' + hora + '</td>';
    logTable.insertBefore(row, logTable.children[1] || null);
  }

  function imprimirPedido(id, callback) {
    printFrame.onload = function () {
      // dá um tempo para o print() interno da página disparar antes de seguir
      setTimeout(function () { if (callback) callback(); }, 800);
    };
    printFrame.src = 'imprimir_pedido.php?id=' + id;
  }

  function marcarImpresso(id) {
    var body = new URLSearchParams();
    body.append('id', id);
    return fetch('marcar_impresso.php', { method: 'POST', body: body });
  }

  function processarFila(pedidos, index) {
    if (index >= pedidos.length) return;
    var pedido = pedidos[index];
    marcarImpresso(pedido.id).then(function () {
      beep();
      adicionarAoLog(pedido);
      imprimirPedido(pedido.id, function () {
        processarFila(pedidos, index + 1);
      });
    });
  }

  function verificarNovosPedidos() {
    lastCheck.textContent = 'Última verificação: ' + new Date().toLocaleTimeString('pt-BR');
    fetch('api_pedidos_novos.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok && data.pedidos && data.pedidos.length > 0) {
          processarFila(data.pedidos, 0);
        }
      })
      .catch(function () { /* falha de rede momentânea, tenta de novo no próximo ciclo */ });
  }

  function iniciar() {
    monitorando = true;
    statusDot.style.background = '#2e9e4f';
    statusText.textContent = 'Monitorando pedidos...';
    btnToggle.textContent = 'Pausar monitoramento';
    verificarNovosPedidos();
    timer = setInterval(verificarNovosPedidos, INTERVALO_MS);
  }

  function pausar() {
    monitorando = false;
    statusDot.style.background = '#ccc';
    statusText.textContent = 'Monitoramento pausado';
    btnToggle.textContent = 'Iniciar monitoramento';
    clearInterval(timer);
  }

  btnToggle.addEventListener('click', function () {
    if (monitorando) pausar(); else iniciar();
  });

  btnTeste.addEventListener('click', function () {
    printFrame.onload = null;
    printFrame.src = 'imprimir_pedido.php?teste=1';
  });

  // Inicia automaticamente ao abrir a página (ideal para o modo kiosk)
  iniciar();
})();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
