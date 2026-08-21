(function(){
 const cfg=window.PAYMENT_CONFIG||{}, products=cfg.products||{};
 const grid=document.getElementById('product-grid'); if(!grid) return;
 const filter=grid.dataset.category||'all';
 const labels={tecidos:'Tecidos',dbv:'Uniforme DBV',avt:'Uniforme AVT'};
 const input=document.getElementById('catalog-search');
 const base=Object.entries(products).filter(([_,p])=>filter==='all'||p.category===filter);
 function render(query=''){
   const q=query.trim().toLocaleLowerCase('pt-BR');
   const list=base.filter(([_,p])=>!q||`${p.name} ${labels[p.category]||''}`.toLocaleLowerCase('pt-BR').includes(q));
   if(!list.length){grid.innerHTML='<div class="empty">Nenhum produto encontrado para esta busca.</div>';return;}
   grid.innerHTML=list.map(([id,p])=>`<article class="product-card"><a class="product-image-wrap" href="/produtos/produto1/?id=${encodeURIComponent(id)}"><img loading="lazy" src="${p.image}" alt="${p.name}"><span class="product-badge">${labels[p.category]||''}</span></a><div class="product-info"><div class="product-cat">${labels[p.category]||''}</div><div class="product-name">${p.name}</div><div class="product-price">${(p.priceCents/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</div><a class="product-btn" href="/produtos/produto1/?id=${encodeURIComponent(id)}">VER PRODUTO</a></div></article>`).join('');
 }
 render(); if(input) input.addEventListener('input',e=>render(e.target.value));
})();
