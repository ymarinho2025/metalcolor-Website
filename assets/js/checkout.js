window.addEventListener('DOMContentLoaded',()=>{
 const cfg=window.PAYMENT_CONFIG||{}; const cart=window.METAL_CART.read(); const products=cfg.products||{};
 const $=id=>document.getElementById(id); const esc=s=>String(s??'').replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'}[c])); const money=c=>(Number(c||0)/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
 let shipping=null, preview=null;
 if(!cart.length){$('checkout-root').innerHTML='<div class="pay-card"><h1>Seu carrinho está vazio</h1><p class="pay-sub">Escolha um produto para continuar.</p><a class="btn-primary" href="/produtos/todos-os-itens/">VER CATÁLOGO</a></div>';return;}
 function renderCart(){
   $('cart-items').innerHTML=cart.map((item,i)=>{const p=products[item.id];if(!p)return'';return `<div class="checkout-item"><img src="${esc(p.image)}" alt="${esc(p.name)}"><div><b>${esc(p.name)}</b><small>${esc(item.option||'ÚNICO')} · ${item.quantity} un.</small></div><strong>${money(p.priceCents*item.quantity)}</strong><button type="button" class="mini-remove" data-i="${i}" aria-label="Remover">×</button></div>`}).join('');
   document.querySelectorAll('.mini-remove').forEach(b=>b.onclick=()=>{cart.splice(Number(b.dataset.i),1);window.METAL_CART.write(cart);location.reload()});
 }
 renderCart();
 // Se estiver logado, associa o pedido à conta e reaproveita dados básicos.
 fetch('/api/auth/me').then(r=>r.json()).then(d=>{if(d.user){const c=document.getElementById('login-callout');if(c)c.innerHTML=`<span>Comprando como</span><b>${esc(d.user.name)}</b> · <a href="/conta/">Minha conta</a>`; if(!$('name').value)$('name').value=d.user.name||''; if(!$('email').value)$('email').value=d.user.email||'';}}).catch(()=>{});

 async function lookupCep(){
   const cep=$('postalCode').value.replace(/\D/g,''); if(cep.length!==8)return;
   $('shipping-status').textContent='Buscando CEP...';
   try{const r=await fetch(`https://viacep.com.br/ws/${cep}/json/`);const d=await r.json();if(d.erro)throw new Error('CEP não encontrado');
     $('address').value=d.logradouro||'';$('province').value=d.bairro||'';$('cityName').value=d.localidade||'';$('cityIbge').value=d.ibge||'';$('uf').value=d.uf||'';
     $('shipping-status').textContent='CEP encontrado. Clique em calcular frete.';
   }catch(e){$('shipping-status').textContent='Não foi possível localizar esse CEP. Você pode preencher o endereço manualmente.';}
 }
 $('postalCode').addEventListener('blur',lookupCep);

 $('quote-btn').onclick=async()=>{
   const cep=$('postalCode').value.replace(/\D/g,''); if(cep.length!==8){$('shipping-status').textContent='Informe um CEP válido.';return;}
   $('shipping-status').textContent='Calculando frete...'; $('shipping-options').innerHTML=''; shipping=null;
   try{const r=await fetch('/api/shipping-quote',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({cart,cep})});const d=await r.json();if(!r.ok)throw new Error(d.error||'Erro ao calcular frete');
     $('shipping-options').innerHTML=d.options.map((x,i)=>`<label class="shipping-option"><input type="radio" name="shipping" value="${i}"><span><b>${esc(x.name)}</b><small>${x.deliveryDays?`Prazo estimado: ${x.deliveryDays} dia(s)`:''}</small></span><strong>${money(x.priceCents)}</strong></label>`).join('');
     document.querySelectorAll('input[name=shipping]').forEach(input=>input.onchange=()=>{shipping=d.options[Number(input.value)];refreshPreview();});
     $('shipping-status').textContent=d.mode==='demo'?'Frete em modo DEMO. Configure Melhor Envio antes de produção.':'Escolha uma opção de frete.';
   }catch(e){$('shipping-status').textContent=e.message;}
 };

 async function refreshPreview(){
   if(!shipping)return;
   try{const r=await fetch('/api/price-preview',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({cart,cep:$('postalCode').value.replace(/\D/g,''),shippingId:shipping.id})});const d=await r.json();if(!r.ok)throw new Error(d.error);preview=d;
     $('subtotal').textContent=money(d.subtotalCents);$('freight').textContent=money(d.shippingCents);$('pix-total').textContent=money(d.pixTotalCents);$('card-total').textContent=money(d.cardTotalCents);$('pix-fee-note').textContent=d.pixFeeCents?`Inclui ${money(d.pixFeeCents)} de taxa Pix configurada.`:'Sem repasse adicional de taxa Pix.';
     $('card-fee-note').textContent=d.cardFeeCents?`Inclui ${money(d.cardFeeCents)} de taxa de cartão configurada.`:'Sem repasse adicional de taxa de cartão.';
   }catch(e){$('shipping-status').textContent=e.message;}
 }

 function customerPayload(){return{
   cart,shipping,
   customer:{name:$('name').value.trim(),cpfCnpj:$('cpfCnpj').value,phone:$('phone').value,email:$('email').value.trim()},
   address:{postalCode:$('postalCode').value,address:$('address').value.trim(),addressNumber:$('addressNumber').value.trim(),complement:$('complement').value.trim(),province:$('province').value.trim(),cityName:$('cityName').value.trim(),cityIbge:$('cityIbge').value,uf:$('uf').value}
 }}
 function formOk(){
   if(!shipping){$('checkout-status').textContent='Calcule e escolha o frete antes de pagar.';return false;}
   const p=customerPayload(); if(!p.customer.name||!p.customer.cpfCnpj||!p.address.postalCode||!p.address.address||!p.address.addressNumber||!p.address.province){$('checkout-status').textContent='Preencha os dados obrigatórios do cliente e endereço.';return false;}return true;
 }
 async function pay(method){
   if(!formOk())return; const btn=method==='PIX'?$('pay-pix'):$('pay-card'); $('pay-pix').disabled=true;$('pay-card').disabled=true; $('checkout-status').textContent='Criando checkout seguro...';
   try{const body={...customerPayload(),paymentMethod:method};const r=await fetch('/api/create-checkout',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(body)});const d=await r.json();if(!r.ok)throw new Error(d.error||'Não foi possível criar o checkout.');sessionStorage.setItem('metalcolor_last_order',d.orderId);sessionStorage.setItem('metalcolor_last_order_token',d.orderAccessToken||'');location.href=d.checkoutUrl;}
   catch(e){$('checkout-status').textContent=e.message;$('pay-pix').disabled=false;$('pay-card').disabled=false;}
 }
 $('pay-pix').onclick=()=>pay('PIX'); $('pay-card').onclick=()=>pay('CREDIT_CARD');
});
