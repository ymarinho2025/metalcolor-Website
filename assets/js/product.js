window.addEventListener('DOMContentLoaded',()=>{
 const cfg=window.PAYMENT_CONFIG||{}; const id=new URLSearchParams(location.search).get('id'); const p=cfg.products?.[id];
 const box=document.getElementById('product-root');
 if(!p){box.innerHTML='<div class="product-panel"><h1>Produto não encontrado</h1><a class="back-link" href="/produtos/todos-os-itens/">Voltar ao catálogo</a></div>';return;}
 const money=(p.priceCents/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
 const opts=(p.options||['ÚNICO']).map(v=>`<option value="${v}">${v}</option>`).join('');
 box.innerHTML=`<div><img src="${p.image}" alt="${p.name}"></div><section class="product-panel"><div class="product-cat">${p.category==='tecidos'?'Tecidos':p.category==='avt'?'Material de Uniforme AVT':'Material de Uniforme DBV'}</div><h1>${p.name}</h1><div class="price">${money}</div><form id="buy-form"><div class="field"><label for="option">Opção / tamanho</label><select id="option" name="option">${opts}</select></div><div class="field"><label for="quantity">Quantidade</label><input id="quantity" name="quantity" type="number" min="1" max="100" value="1"></div><button class="buy-main" type="submit">ADICIONAR E IR AO CHECKOUT</button><button class="pay-button alt" type="button" id="add-only">ADICIONAR AO CARRINHO</button><p id="form-message" class="notice-small"></p></form><p class="notice-small">No checkout você informa o CEP, escolhe o frete e paga com Pix ou cartão pelo ambiente seguro do Asaas.</p><a class="back-link" href="/produtos/todos-os-itens/">← Voltar ao catálogo</a></section>`;
 const getItem=()=>({id,option:document.getElementById('option').value,quantity:Math.max(1,Math.min(100,Number(document.getElementById('quantity').value)||1))});
 document.getElementById('buy-form').addEventListener('submit',e=>{e.preventDefault();window.METAL_CART.add(getItem());location.href='/pagamento/';});
 document.getElementById('add-only').onclick=()=>{window.METAL_CART.add(getItem());document.getElementById('form-message').textContent='Produto adicionado ao carrinho.';};
});
