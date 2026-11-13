(function () {
  "use strict";

  var MENU = window.MENU_DATA;
  var CFG = window.LOJA_CONFIG;
  var BAIRROS = window.BAIRROS_ENTREGA || [];
  var cart = []; // { productId, nome, tamanho, preco, qtd }
  var pendingProduct = null; // produto aguardando escolha de tamanho
  var checkoutStep = 1; // 1 = tipo entrega, 2 = dados, 3 = pagamento, 4 = confirmação
  var checkoutData = {
    tipo: null, nome: "", telefone: "", rua: "", numero: "",
    bairroId: null, bairroNome: "", bairroOutroTexto: "",
    pagamento: null, precisaTroco: null, trocoPara: "", obs: ""
  };

  var fmt = function (v) {
    return "R$ " + v.toFixed(2).replace(".", ",");
  };
  function produtoPorId(id) {
    return MENU.produtos.find(function (p) { return p.id === id; });
  }
  function categoriaNomePorId(catId) {
    var c = MENU.categorias.find(function (c) { return c.id === catId; });
    return c ? c.nome : "";
  }

  // ---------------- Render do cardápio ----------------
  function renderMenu() {
    var nav = document.getElementById("catNav");
    var container = document.getElementById("menuContainer");
    nav.innerHTML = "";
    container.innerHTML = "";

    MENU.categorias.forEach(function (cat, idx) {
      var chip = document.createElement("button");
      chip.className = "cat-chip" + (idx === 0 ? " active" : "");
      chip.textContent = cat.nome;
      chip.dataset.cat = cat.id;
      chip.addEventListener("click", function () {
        document.getElementById("sec-" + cat.id).scrollIntoView({ behavior: "smooth", block: "start" });
      });
      nav.appendChild(chip);

      var ids = MENU.porCategoria[cat.id] || [];
      if (!ids.length) return;

      var section = document.createElement("section");
      section.id = "sec-" + cat.id;

      var title = document.createElement("div");
      title.className = "section-title";
      title.innerHTML = '<span>' + escapeHtml(cat.nome) + '</span><small>' + ids.length + ' ' + (ids.length === 1 ? 'item' : 'itens') + '</small>';
      section.appendChild(title);

      var grid = document.createElement("div");
      grid.className = "grid";

      ids.forEach(function (pid) {
        var prod = MENU.produtos.find(function (p) { return p.id === pid; });
        if (!prod) return;
        grid.appendChild(renderCard(prod));
      });

      section.appendChild(grid);
      container.appendChild(section);
    });

    setupScrollSpy();
  }

  function menorPreco(prod) {
    var min = null;
    prod.precos.forEach(function (pr) {
      if (min === null || pr.preco < min) min = pr.preco;
    });
    return min;
  }

  function renderCard(prod) {
    var card = document.createElement("div");
    card.className = "card";

    var img;
    if (prod.imagem) {
      img = document.createElement("img");
      img.className = "card__img";
      img.src = "images/products/" + prod.imagem;
      img.alt = prod.nome;
      img.loading = "lazy";
    } else {
      img = document.createElement("div");
      img.className = "card__img card__img--empty";
      img.textContent = "Top Pizzaria";
    }
    card.appendChild(img);

    var body = document.createElement("div");
    body.className = "card__body";

    var name = document.createElement("div");
    name.className = "card__name";
    name.textContent = prod.nome;
    body.appendChild(name);

    if (prod.descricao) {
      var desc = document.createElement("div");
      desc.className = "card__desc";
      desc.textContent = prod.descricao;
      body.appendChild(desc);
    }

    var foot = document.createElement("div");
    foot.className = "card__foot";

    var price = document.createElement("div");
    price.className = "card__price";
    if (prod.tem_tamanhos) {
      price.innerHTML = "A partir de<br>" + fmt(menorPreco(prod));
    } else {
      price.textContent = fmt(prod.precos[0] ? prod.precos[0].preco : 0);
    }
    foot.appendChild(price);

    var btn = document.createElement("button");
    btn.className = "btn-add";
    btn.textContent = "Adicionar";
    btn.addEventListener("click", function () { handleAddClick(prod); });
    foot.appendChild(btn);

    body.appendChild(foot);
    card.appendChild(body);
    return card;
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  // ---------------- Adicionar ao carrinho ----------------
  var sizeSelection = { tipo: "unico", tamanhoIdx: 0, segundoId: null };

  function handleAddClick(prod) {
    if (prod.tem_tamanhos && prod.precos.length > 1) {
      openSizeDrawer(prod);
    } else {
      addItemToCart({
        productId: prod.id, productId2: null,
        nome: prod.nome, tamanho: prod.precos[0].tamanho, preco: prod.precos[0].preco, qtd: 1
      });
      showToast(prod.nome + " adicionado");
    }
  }

  function categoriaPorId(catId) {
    return MENU.categorias.find(function (c) { return c.id === catId; });
  }

  // Sabores que podem ser combinados com "prod" no meio a meio, no tamanho informado.
  // - Se a categoria do produto estiver liberada no admin ("permite meio a meio"), aceita sabores de
  //   QUALQUER categoria também liberada (ex.: pizza salgada + pizza doce).
  // - Caso contrário, só aceita sabores da mesma categoria (comportamento anterior).
  function outrosSaboresDaCategoria(prod, tamanho) {
    var catAtual = categoriaPorId(prod.categoria_id);
    var liberada = !!catAtual && Number(catAtual.meio_a_meio) === 1;
    return MENU.produtos.filter(function (p) {
      if (p.id === prod.id || !p.tem_tamanhos) return false;
      if (tamanho && !p.precos.some(function (pr) { return pr.tamanho === tamanho; })) return false;
      if (p.categoria_id === prod.categoria_id) return true;
      if (!liberada) return false;
      var c = categoriaPorId(p.categoria_id);
      return !!c && Number(c.meio_a_meio) === 1;
    });
  }

  function openSizeDrawer(prod) {
    pendingProduct = prod;
    sizeSelection = { tipo: "unico", tamanhoIdx: 0, segundoId: null };
    document.getElementById("sizeTitle").textContent = prod.nome;
    renderSizeDrawerContent();
    openDrawer("sizeDrawer");
  }

  function precoPorTamanho(prod, tamanho) {
    var pr = prod.precos.find(function (p) { return p.tamanho === tamanho; });
    return pr ? pr.preco : 0;
  }

  function renderSizeDrawerContent() {
    var prod = pendingProduct;
    var wrap = document.getElementById("sizeOptions");
    var tamanhoAtual = prod.precos[sizeSelection.tamanhoIdx] ? prod.precos[sizeSelection.tamanhoIdx].tamanho : prod.precos[0].tamanho;
    var outros = outrosSaboresDaCategoria(prod, tamanhoAtual);
    var temMeio = prod.precos.some(function (pr) { return outrosSaboresDaCategoria(prod, pr.tamanho).length > 0; });

    // Se o segundo sabor escolhido não existe neste tamanho, passa para o primeiro disponível
    if (sizeSelection.tipo === "meio") {
      var aindaValido = outros.some(function (p) { return String(p.id) === String(sizeSelection.segundoId); });
      if (!aindaValido) sizeSelection.segundoId = outros.length ? outros[0].id : null;
    }

    var html = "";

    // Sabor único / Meio a meio
    if (temMeio) {
      html += '<div class="flavor-toggle seg">' +
        '<button type="button" data-flavor-tipo="unico" class="' + (sizeSelection.tipo === "unico" ? "active" : "") + '">🍕<br>Sabor único</button>' +
        '<button type="button" data-flavor-tipo="meio" class="' + (sizeSelection.tipo === "meio" ? "active" : "") + '">🍕🍕<br>Meio a meio</button>' +
        '</div>';
    }

    // Tamanho
    prod.precos.forEach(function (pr, idx) {
      html += '<div class="size-row">' +
        '<label><input type="radio" name="tamanho" value="' + idx + '"' + (idx === sizeSelection.tamanhoIdx ? " checked" : "") + '> ' + tamanhoLabel(pr.tamanho) + (pr.fatias ? ' <span class="size-slices">' + pr.fatias + (pr.fatias === 1 ? ' fatia' : ' fatias') + '</span>' : '') + '</label>' +
        '<span class="price">' + fmt(pr.preco) + '</span>' +
        '</div>';
    });

    // Segundo sabor (se meio a meio)
    if (sizeSelection.tipo === "meio") {
      if (!outros.length) {
        html += '<div class="half-hint">Nenhum outro sabor disponível para meio a meio neste tamanho.</div>';
      } else {
        // agrupa as opções por categoria (na ordem das abas do cardápio)
        var grupos = MENU.categorias.map(function (c) {
          return { nome: c.nome, itens: outros.filter(function (p) { return p.categoria_id === c.id; }) };
        }).filter(function (g) { return g.itens.length > 0; });
        var optHtml = function (p) {
          return '<option value="' + p.id + '"' + (String(sizeSelection.segundoId) === String(p.id) ? " selected" : "") + '>' + escapeHtml(p.nome) + '</option>';
        };
        var optionsHtml = grupos.length > 1
          ? grupos.map(function (g) { return '<optgroup label="' + escapeAttr(g.nome) + '">' + g.itens.map(optHtml).join("") + '</optgroup>'; }).join("")
          : outros.map(optHtml).join("");

        html += '<div class="half-picker">' +
          '<label>Segundo sabor (metade)</label>' +
          '<select id="segundoSaborSelect">' + optionsHtml + '</select></div>';

        var segundoProd = outros.find(function (p) { return String(p.id) === String(sizeSelection.segundoId); });
        var precoBase = precoPorTamanho(prod, tamanhoAtual);
        var precoSegundo = segundoProd ? precoPorTamanho(segundoProd, tamanhoAtual) : precoBase;
        var precoFinal = Math.max(precoBase, precoSegundo);

        html += '<div class="half-price-box"><span class="lbl">Preço da pizza (metade + metade)</span><span class="val">' + fmt(precoFinal) + '</span></div>' +
          '<div class="half-hint">Vale sempre o preço do sabor mais caro entre os dois escolhidos.</div>';
      }
    }

    wrap.innerHTML = html;

    // listeners
    wrap.querySelectorAll("[data-flavor-tipo]").forEach(function (b) {
      b.addEventListener("click", function () {
        sizeSelection.tipo = b.dataset.flavorTipo;
        renderSizeDrawerContent();
      });
    });
    wrap.querySelectorAll('input[name="tamanho"]').forEach(function (r) {
      r.addEventListener("change", function () {
        sizeSelection.tamanhoIdx = parseInt(r.value, 10);
        renderSizeDrawerContent();
      });
    });
    var segSelect = document.getElementById("segundoSaborSelect");
    if (segSelect) {
      segSelect.addEventListener("change", function () {
        var escolhido = outros.find(function (p) { return String(p.id) === segSelect.value; });
        sizeSelection.segundoId = escolhido ? escolhido.id : null;
        renderSizeDrawerContent();
      });
    }
  }

  document.getElementById("btnConfirmSize").addEventListener("click", function () {
    if (!pendingProduct) return;
    var prod = pendingProduct;
    var pr = prod.precos[sizeSelection.tamanhoIdx];

    if (sizeSelection.tipo === "meio") {
      var outros = outrosSaboresDaCategoria(prod, pr.tamanho);
      var segundoProd = outros.find(function (p) { return String(p.id) === String(sizeSelection.segundoId); });
      if (!segundoProd) { showToast("Escolha o segundo sabor"); return; }
      var precoFinal = Math.max(pr.preco, precoPorTamanho(segundoProd, pr.tamanho));
      var nomeCombo = "1/2 " + prod.nome + " + 1/2 " + segundoProd.nome;

      addItemToCart({
        productId: prod.id, productId2: segundoProd.id,
        nome: nomeCombo, tamanho: pr.tamanho, preco: precoFinal, qtd: 1
      });
      showToast("Pizza meio a meio adicionada");
    } else {
      addItemToCart({
        productId: prod.id, productId2: null,
        nome: prod.nome, tamanho: pr.tamanho, preco: pr.preco, qtd: 1
      });
      showToast(prod.nome + " adicionado");
    }

    closeDrawer("sizeDrawer");
    pendingProduct = null;
  });

  function tamanhoLabel(t) {
    if (t === "P") return "Pequena";
    if (t === "M") return "Média";
    if (t === "G") return "Grande";
    return t;
  }

  function addItemToCart(item) {
    var existing = cart.find(function (i) {
      return i.productId === item.productId && i.productId2 === item.productId2 && i.tamanho === item.tamanho;
    });
    if (existing) {
      existing.qtd++;
    } else {
      cart.push(item);
    }
    updateCartBar();
  }

  function cartTotal() {
    return cart.reduce(function (sum, i) { return sum + i.preco * i.qtd; }, 0);
  }
  function cartCount() {
    return cart.reduce(function (sum, i) { return sum + i.qtd; }, 0);
  }
  function valorFreteConfigurado() {
    return parseFloat(CFG.taxaEntrega) || 0;
  }
  function valorFrete() {
    if (checkoutData.tipo !== "entrega") return 0;
    if (checkoutData.bairroId) {
      var b = BAIRROS.find(function (x) { return x.id === checkoutData.bairroId; });
      return b ? parseFloat(b.taxa) || 0 : valorFreteConfigurado();
    }
    return valorFreteConfigurado(); // "Outro bairro" ou nenhum bairro cadastrado ainda
  }
  function totalComFrete() {
    return cartTotal() + valorFrete();
  }

  function updateCartBar() {
    var bar = document.getElementById("cartBar");
    var count = cartCount();
    document.getElementById("cartCount").textContent = count;
    document.getElementById("cartBarTotal").textContent = fmt(cartTotal());
    if (count > 0) bar.classList.remove("hide");
    else bar.classList.add("hide");
    renderCartBody();
  }

  // ---------------- Carrinho (drawer) ----------------
  function renderCartBody() {
    var body = document.getElementById("cartBody");
    var foot = document.getElementById("cartFoot");
    if (!cart.length) {
      body.innerHTML = '<div class="cart-empty">Seu carrinho está vazio.<br>Adicione itens do cardápio para começar.</div>';
      foot.innerHTML = "";
      return;
    }
    body.innerHTML = "";
    cart.forEach(function (item, idx) {
      var row = document.createElement("div");
      row.className = "cart-item";
      row.innerHTML =
        '<div class="cart-item__info">' +
          '<div class="cart-item__name">' + escapeHtml(item.nome) + '</div>' +
          '<div class="cart-item__size">' +
            (item.tamanho && item.tamanho !== "Único" ? tamanhoLabel(item.tamanho) : "") +
            (item.productId2 ? (item.tamanho && item.tamanho !== "Único" ? " · " : "") + "Meio a meio" : "") +
          '</div>' +
          '<div class="cart-item__price">' + fmt(item.preco * item.qtd) + '</div>' +
          '<button class="cart-remove" data-idx="' + idx + '">Remover</button>' +
        '</div>' +
        '<div class="qty">' +
          '<button data-act="dec" data-idx="' + idx + '">−</button>' +
          '<span>' + item.qtd + '</span>' +
          '<button data-act="inc" data-idx="' + idx + '">+</button>' +
        '</div>';
      body.appendChild(row);
    });

    body.querySelectorAll("[data-act]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var idx = parseInt(btn.dataset.idx, 10);
        if (btn.dataset.act === "inc") cart[idx].qtd++;
        else { cart[idx].qtd--; if (cart[idx].qtd <= 0) cart.splice(idx, 1); }
        updateCartBar();
      });
    });
    body.querySelectorAll(".cart-remove").forEach(function (btn) {
      btn.addEventListener("click", function () {
        cart.splice(parseInt(btn.dataset.idx, 10), 1);
        updateCartBar();
      });
    });

    foot.innerHTML =
      '<div class="summary-row total"><span>Subtotal</span><span>' + fmt(cartTotal()) + '</span></div>' +
      '<button class="btn-primary" id="btnGoCheckout" style="margin-top:10px;">Continuar para entrega</button>';
    document.getElementById("btnGoCheckout").addEventListener("click", function () {
      closeDrawer("cartDrawer");
      checkoutStep = 1;
      openCheckout();
    });
  }

  document.getElementById("cartBar").addEventListener("click", function () {
    renderCartBody();
    openDrawer("cartDrawer");
  });

  // ---------------- Checkout ----------------
  function openCheckout() {
    renderCheckoutStep();
    openDrawer("checkoutDrawer");
  }

  function dots(step, total) {
    var html = '<div class="step-dots">';
    for (var i = 1; i <= total; i++) html += '<span class="' + (i === step ? "active" : "") + '"></span>';
    html += '</div>';
    return html;
  }

  function renderCheckoutStep() {
    var body = document.getElementById("checkoutBody");
    var foot = document.getElementById("checkoutFoot");
    document.getElementById("checkoutTitle").textContent =
      checkoutStep === 1 ? "Entrega ou retirada?" :
      checkoutStep === 2 ? "Seus dados" :
      checkoutStep === 3 ? "Forma de pagamento" : "Pedido enviado";

    if (checkoutStep === 1) {
      var freteInfo = "";
      if (BAIRROS.length > 0) {
        freteInfo = '<div class="hint" style="text-align:center;margin-top:10px;">A taxa de entrega varia de acordo com o bairro (você escolhe na próxima etapa)</div>';
      } else if (valorFreteConfigurado() > 0) {
        freteInfo = '<div class="hint" style="text-align:center;margin-top:10px;">Taxa de entrega: ' + fmt(valorFreteConfigurado()) + ' (não se aplica na retirada)</div>';
      }
      body.innerHTML =
        dots(1, 4) +
        '<div class="seg" style="margin-top:6px;">' +
          '<button data-tipo="entrega" class="' + (checkoutData.tipo === "entrega" ? "active" : "") + '">🛵<br>Entrega</button>' +
          '<button data-tipo="retirada" class="' + (checkoutData.tipo === "retirada" ? "active" : "") + '">🏪<br>Retirada</button>' +
        '</div>' + freteInfo;
      body.querySelectorAll("[data-tipo]").forEach(function (b) {
        b.addEventListener("click", function () {
          checkoutData.tipo = b.dataset.tipo;
          renderCheckoutStep();
        });
      });
      foot.innerHTML = '<button class="btn-primary" id="ckNext" ' + (checkoutData.tipo ? "" : "disabled") + '>Continuar</button>';
      var nextBtn = document.getElementById("ckNext");
      if (nextBtn) nextBtn.addEventListener("click", function () { checkoutStep = 2; renderCheckoutStep(); });
    }

    else if (checkoutStep === 2) {
      var enderecoFields = "";
      if (checkoutData.tipo === "entrega") {
        var bairroField = "";
        if (BAIRROS.length > 0) {
          var mostrarOutro = checkoutData.bairroId === "outro";
          bairroField =
            '<div class="field"><label>Bairro</label><select id="fBairroSelect">' +
              '<option value="">Selecione...</option>' +
              BAIRROS.map(function (b) {
                return '<option value="' + b.id + '"' + (checkoutData.bairroId === b.id ? " selected" : "") + '>' + escapeHtml(b.nome) + ' — ' + fmt(parseFloat(b.taxa)) + '</option>';
              }).join("") +
              '<option value="outro"' + (mostrarOutro ? " selected" : "") + '>Outro bairro (a combinar)</option>' +
            '</select></div>';
          if (mostrarOutro) {
            bairroField += '<div class="field"><label>Qual bairro?</label><input type="text" id="fBairroOutroTexto" value="' + escapeAttr(checkoutData.bairroOutroTexto) + '" placeholder="Digite o nome do seu bairro"></div>';
          }
        } else {
          bairroField = '<div class="field"><label>Bairro</label><input type="text" id="fBairroOutroTexto" value="' + escapeAttr(checkoutData.bairroOutroTexto) + '"></div>';
        }
        enderecoFields =
          '<div class="field"><label>Rua / Avenida</label><input type="text" id="fRua" value="' + escapeAttr(checkoutData.rua) + '"></div>' +
          '<div class="field-row">' +
            '<div class="field"><label>Número</label><input type="text" id="fNumero" value="' + escapeAttr(checkoutData.numero) + '"></div>' +
          '</div>' +
          bairroField;
      } else {
        enderecoFields =
          '<div class="pickup-box">' +
            '<p><strong>Retirar no local:</strong><br>' + escapeHtml(CFG.enderecoRetirada) + '</p>' +
            '<a href="' + CFG.mapsLink + '" target="_blank" rel="noopener">📍 Ver no Google Maps</a>' +
          '</div>';
      }
      body.innerHTML =
        dots(2, 4) +
        '<div class="field"><label>Nome completo</label><input type="text" id="fNome" value="' + escapeAttr(checkoutData.nome) + '"></div>' +
        '<div class="field"><label>Telefone / WhatsApp</label><input type="tel" id="fTelefone" placeholder="(87) 9xxxx-xxxx" value="' + escapeAttr(checkoutData.telefone) + '"></div>' +
        enderecoFields +
        '<div class="field"><label>Observações (opcional)</label><textarea id="fObs" rows="2" placeholder="Ex: sem cebola, ponto da campainha...">' + escapeHtml(checkoutData.obs) + '</textarea></div>';

      var bairroSelectEl = document.getElementById("fBairroSelect");
      if (bairroSelectEl) {
        bairroSelectEl.addEventListener("change", function () {
          // salva o que já foi digitado antes de redesenhar, para não perder os dados
          checkoutData.nome = val("fNome");
          checkoutData.telefone = val("fTelefone");
          checkoutData.rua = val("fRua");
          checkoutData.numero = val("fNumero");
          checkoutData.obs = val("fObs");
          checkoutData.bairroId = bairroSelectEl.value || null;
          renderCheckoutStep();
        });
      }

      foot.innerHTML =
        '<button class="btn-primary" id="ckNext">Continuar</button>' +
        '<button class="btn-secondary" id="ckBack">Voltar</button>';
      document.getElementById("ckBack").addEventListener("click", function () { checkoutStep = 1; renderCheckoutStep(); });
      document.getElementById("ckNext").addEventListener("click", function () {
        checkoutData.nome = val("fNome");
        checkoutData.telefone = val("fTelefone");
        if (checkoutData.tipo === "entrega") {
          checkoutData.rua = val("fRua");
          checkoutData.numero = val("fNumero");

          if (BAIRROS.length > 0) {
            if (!checkoutData.bairroId) { showToast("Escolha o bairro"); return; }
            if (checkoutData.bairroId === "outro") {
              checkoutData.bairroOutroTexto = val("fBairroOutroTexto");
              if (!checkoutData.bairroOutroTexto) { showToast("Digite o nome do bairro"); return; }
              checkoutData.bairroNome = checkoutData.bairroOutroTexto;
            } else {
              var bSel = BAIRROS.find(function (x) { return x.id === checkoutData.bairroId; });
              checkoutData.bairroNome = bSel ? bSel.nome : "";
            }
          } else {
            checkoutData.bairroOutroTexto = val("fBairroOutroTexto");
            checkoutData.bairroNome = checkoutData.bairroOutroTexto;
          }
        }
        checkoutData.obs = val("fObs");
        if (!checkoutData.nome || !checkoutData.telefone) { showToast("Preencha nome e telefone"); return; }
        if (checkoutData.tipo === "entrega" && (!checkoutData.rua || !checkoutData.numero || !checkoutData.bairroNome)) {
          showToast("Preencha o endereço completo"); return;
        }
        checkoutStep = 3;
        renderCheckoutStep();
      });
    }

    else if (checkoutStep === 3) {
      var resumoHtml =
        '<div class="card-box-like" style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:14px;">' +
          '<div class="summary-row"><span>Subtotal</span><span>' + fmt(cartTotal()) + '</span></div>' +
          (valorFrete() > 0 ? '<div class="summary-row"><span>Taxa de entrega' + (checkoutData.bairroNome ? ' (' + escapeHtml(checkoutData.bairroNome) + ')' : '') + '</span><span>' + fmt(valorFrete()) + '</span></div>' : '') +
          '<div class="summary-row total" style="border-top:1px solid var(--line);margin-top:4px;padding-top:8px;"><span>Total</span><span>' + fmt(totalComFrete()) + '</span></div>' +
        '</div>';
      body.innerHTML =
        dots(3, 4) +
        resumoHtml +
        '<div class="seg" style="margin-top:6px;">' +
          '<button data-pg="dinheiro" class="' + (checkoutData.pagamento === "dinheiro" ? "active" : "") + '">💵<br>Dinheiro</button>' +
          '<button data-pg="cartao" class="' + (checkoutData.pagamento === "cartao" ? "active" : "") + '">💳<br>Cartão</button>' +
          '<button data-pg="pix" class="' + (checkoutData.pagamento === "pix" ? "active" : "") + '">🔑<br>Pix</button>' +
        '</div>' +
        '<div id="pgExtra"></div>';
      renderPagamentoExtra();

      body.querySelectorAll("[data-pg]").forEach(function (b) {
        b.addEventListener("click", function () {
          checkoutData.pagamento = b.dataset.pg;
          renderCheckoutStep();
        });
      });

      foot.innerHTML =
        '<button class="btn-primary" id="ckFinalizar" ' + (checkoutData.pagamento ? "" : "disabled") + '>Finalizar pedido</button>' +
        '<button class="btn-secondary" id="ckBack">Voltar</button>';
      document.getElementById("ckBack").addEventListener("click", function () { checkoutStep = 2; renderCheckoutStep(); });
      var finBtn = document.getElementById("ckFinalizar");
      if (finBtn) finBtn.addEventListener("click", finalizarPedido);
    }

    else if (checkoutStep === 4) {
      // conteúdo definido dinamicamente em finalizarPedido()
    }
  }

  function renderPagamentoExtra() {
    var box = document.getElementById("pgExtra");
    if (!box) return;
    if (checkoutData.pagamento === "dinheiro") {
      box.innerHTML =
        '<div class="field" style="margin-top:14px;"><label>Precisa de troco?</label>' +
        '<div class="seg">' +
          '<button data-troco="nao" class="' + (checkoutData.precisaTroco === false ? "active" : "") + '">Não</button>' +
          '<button data-troco="sim" class="' + (checkoutData.precisaTroco === true ? "active" : "") + '">Sim</button>' +
        '</div></div>' +
        (checkoutData.precisaTroco ? '<div class="field"><label>Troco para quanto?</label><input type="number" step="0.01" id="fTroco" value="' + escapeAttr(checkoutData.trocoPara) + '" placeholder="Ex: 50,00"></div>' : '');
      box.querySelectorAll("[data-troco]").forEach(function (b) {
        b.addEventListener("click", function () {
          checkoutData.precisaTroco = b.dataset.troco === "sim";
          renderPagamentoExtra();
        });
      });
      var tInput = document.getElementById("fTroco");
      if (tInput) tInput.addEventListener("input", function () { checkoutData.trocoPara = tInput.value; });
    } else if (checkoutData.pagamento === "pix") {
      box.innerHTML =
        '<div class="pix-box" style="margin-top:14px;">' +
          '<div>Chave Pix (celular)<br><code>' + escapeHtml(CFG.chavePix) + '</code></div>' +
          '<button class="copy-btn" id="btnCopyPix">Copiar chave</button>' +
        '</div>' +
        '<div class="hint">Copie a chave, faça o pagamento no app do seu banco e envie o comprovante pelo WhatsApp ao finalizar.</div>';
      var copyBtn = document.getElementById("btnCopyPix");
      if (copyBtn) copyBtn.addEventListener("click", function () {
        copyToClipboard(CFG.chavePix);
        showToast("Chave Pix copiada!");
      });
    } else if (checkoutData.pagamento === "cartao") {
      box.innerHTML = '<div class="hint" style="margin-top:14px;">Pagamento na maquininha, na entrega ou na retirada.</div>';
    } else {
      box.innerHTML = "";
    }
  }

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : "";
  }
  function escapeAttr(s) {
    return (s || "").toString().replace(/"/g, "&quot;");
  }
  function copyToClipboard(text) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).catch(function () { fallbackCopy(text); });
    } else {
      fallbackCopy(text);
    }
  }
  function fallbackCopy(text) {
    var ta = document.createElement("textarea");
    ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand("copy"); } catch (e) {}
    document.body.removeChild(ta);
  }

  // ---------------- Finalizar pedido ----------------
  function finalizarPedido() {
    var finBtn = document.getElementById("ckFinalizar");
    if (finBtn) { finBtn.disabled = true; finBtn.textContent = "Enviando..."; }

    var payload = {
      tipo_entrega: checkoutData.tipo,
      nome_cliente: checkoutData.nome,
      telefone: checkoutData.telefone,
      rua: checkoutData.rua,
      numero: checkoutData.numero,
      bairro_id: (checkoutData.bairroId && checkoutData.bairroId !== "outro") ? checkoutData.bairroId : null,
      bairro: checkoutData.bairroNome,
      forma_pagamento: checkoutData.pagamento,
      precisa_troco: checkoutData.pagamento === "dinheiro" ? !!checkoutData.precisaTroco : false,
      troco_para: checkoutData.trocoPara || null,
      observacoes: checkoutData.obs,
      itens: cart.map(function (i) {
        var prod = produtoPorId(i.productId);
        var categoria = prod ? categoriaNomePorId(prod.categoria_id) : "";
        if (i.productId2) {
          var prod2 = produtoPorId(i.productId2);
          var categoria2 = prod2 ? categoriaNomePorId(prod2.categoria_id) : "";
          if (categoria2 && categoria2 !== categoria) categoria = "Meio a meio";
        }
        return { produto_id: i.productId, produto_id_2: i.productId2 || null, nome: i.nome, tamanho: i.tamanho, quantidade: i.qtd, preco_unitario: i.preco, categoria: categoria };
      }),
      subtotal: cartTotal()
    };

    fetch("process_order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || "Erro ao enviar pedido");
        payload.subtotal = data.subtotal;
        payload.taxa_entrega = data.taxa_entrega;
        payload.total = data.total;
        mostrarConfirmacao(data.pedido_id, payload);
      })
      .catch(function (err) {
        showToast("Não foi possível enviar o pedido. Tente novamente.");
        if (finBtn) { finBtn.disabled = false; finBtn.textContent = "Finalizar pedido"; }
      });
  }

  function montarMensagemWhatsapp(pedidoId, payload) {
    var linhas = [];
    linhas.push("*Novo pedido #" + pedidoId + " - " + CFG.nomeLoja + "*");
    linhas.push("");

    var porCategoria = {};
    var ordemCategorias = [];
    payload.itens.forEach(function (i) {
      var cat = i.categoria || "Itens";
      if (!porCategoria[cat]) { porCategoria[cat] = []; ordemCategorias.push(cat); }
      porCategoria[cat].push(i);
    });
    ordemCategorias.forEach(function (cat) {
      linhas.push("*" + cat + "*");
      porCategoria[cat].forEach(function (i) {
        var tam = i.tamanho && i.tamanho !== "Único" ? " (" + tamanhoLabel(i.tamanho) + ")" : "";
        linhas.push(i.quantidade + "x " + i.nome + tam + " - " + fmt(i.preco_unitario * i.quantidade));
      });
      linhas.push("");
    });

    linhas.push("Subtotal: " + fmt(payload.subtotal));
    if (payload.taxa_entrega > 0) linhas.push("Taxa de entrega: " + fmt(payload.taxa_entrega));
    linhas.push("*Total: " + fmt(payload.total) + "*");
    linhas.push("");
    linhas.push("Cliente: " + payload.nome_cliente);
    linhas.push("Telefone: " + payload.telefone);
    if (payload.tipo_entrega === "entrega") {
      linhas.push("Entrega: " + payload.rua + ", " + payload.numero + " - " + payload.bairro);
    } else {
      linhas.push("Retirada no local: " + CFG.enderecoRetirada);
    }
    var pg = payload.forma_pagamento === "dinheiro" ? "Dinheiro" + (payload.precisa_troco ? " (troco para " + fmt(parseFloat(payload.troco_para || 0)) + ")" : " (sem troco)") :
              payload.forma_pagamento === "cartao" ? "Cartão" : "Pix";
    linhas.push("Pagamento: " + pg);
    if (payload.observacoes) linhas.push("Obs: " + payload.observacoes);
    return encodeURIComponent(linhas.join("\n"));
  }

  function mostrarConfirmacao(pedidoId, payload) {
    checkoutStep = 4;
    var body = document.getElementById("checkoutBody");
    var foot = document.getElementById("checkoutFoot");
    document.getElementById("checkoutTitle").textContent = "Pedido enviado";
    body.innerHTML =
      '<div class="confirm-box">' +
        '<div class="icon">🍕</div>' +
        '<h3>Pedido recebido!</h3>' +
        '<p>Seu pedido <span class="order-number">#' + pedidoId + '</span> foi enviado para a pizzaria.</p>' +
        '<p>' + (payload.tipo_entrega === "entrega" ? "Em breve chega até você." : "Você poderá retirar no local em breve.") + '</p>' +
        (payload.taxa_entrega > 0 ? '<p>Subtotal ' + fmt(payload.subtotal) + ' + taxa de entrega ' + fmt(payload.taxa_entrega) + '</p>' : '') +
        '<p><strong>Total: ' + fmt(payload.total) + '</strong></p>' +
      '</div>';
    var waLink = "https://wa.me/" + CFG.whatsapp + "?text=" + montarMensagemWhatsapp(pedidoId, payload);
    foot.innerHTML =
      '<a class="btn-primary" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;" href="' + waLink + '" target="_blank" rel="noopener">Confirmar pelo WhatsApp</a>' +
      '<button class="btn-secondary" id="btnNovoPedido">Fazer novo pedido</button>';
    document.getElementById("btnNovoPedido").addEventListener("click", function () {
      cart = [];
      checkoutData = {
        tipo: null, nome: "", telefone: "", rua: "", numero: "",
        bairroId: null, bairroNome: "", bairroOutroTexto: "",
        pagamento: null, precisaTroco: null, trocoPara: "", obs: ""
      };
      updateCartBar();
      closeDrawer("checkoutDrawer");
    });
  }

  // ---------------- Drawers / overlay ----------------
  function openDrawer(id) {
    document.getElementById("overlay").classList.add("open");
    document.getElementById(id).classList.add("open");
    document.body.style.overflow = "hidden";
  }
  function closeDrawer(id) {
    document.getElementById(id).classList.remove("open");
    document.getElementById("overlay").classList.remove("open");
    document.body.style.overflow = "";
  }
  document.getElementById("overlay").addEventListener("click", function () {
    ["sizeDrawer", "cartDrawer", "checkoutDrawer"].forEach(function (id) { closeDrawer(id); });
  });
  document.querySelectorAll("[data-close]").forEach(function (btn) {
    btn.addEventListener("click", function () { closeDrawer(btn.dataset.close); });
  });

  function showToast(msg) {
    var t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(t._timer);
    t._timer = setTimeout(function () { t.classList.remove("show"); }, 1800);
  }

  // ---------------- Scroll spy nas categorias ----------------
  function setupScrollSpy() {
    var sections = MENU.categorias.map(function (c) { return document.getElementById("sec-" + c.id); }).filter(Boolean);
    var chips = document.querySelectorAll(".cat-chip");
    window.addEventListener("scroll", function () {
      var top = window.scrollY + 130;
      var activeIdx = 0;
      sections.forEach(function (sec, idx) {
        if (sec.offsetTop <= top) activeIdx = idx;
      });
      chips.forEach(function (c, idx) { c.classList.toggle("active", idx === activeIdx); });
    }, { passive: true });
  }

  renderMenu();
  updateCartBar();
})();
