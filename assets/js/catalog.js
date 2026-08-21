(function(){
 const cfg=window.PAYMENT_CONFIG||{}; const products=cfg.products||{};
 const grid=document.getElementById('product-grid'); if(!grid) return;
 const filter=grid.dataset.category||'all';
 const labels={tecidos:'Tecidos',dbv:'Material de Uniforme DBV',avt:'Material de Uniforme AVT'};
 const list=Object.entries(products).filter(([id,p])=>filter==='all'||p.category===filter);
 if(!list.length){grid.innerHTML='<div class="empty">Nenhum produto cadastrado nesta categoria.</div>';return;}
 grid.innerHTML=list.map(([id,p])=>`<article class="product-card"><img src="${p.image}" alt="${p.name}"><div class="product-info"><div class="product-cat">${labels[p.category]||''}</div><div class="product-name">${p.name}</div><div class="product-price">${(p.priceCents/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</div><a class="product-btn" href="/produtos/produto1/?id=${encodeURIComponent(id)}">COMPRAR</a></div></article>`).join('');
})();
