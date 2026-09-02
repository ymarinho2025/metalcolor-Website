(function(){
 const cfg=window.PAYMENT_CONFIG||{}, products=cfg.products||{};
 const grid=document.getElementById('product-grid'); if(!grid) return;
 const filter=grid.dataset.category||'all';
 const labels={tecidos:'Tecidos',dbv:'Uniforme DBV',avt:'Uniforme AVT'};
 const input=document.getElementById('catalog-search');
 const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
 const safeImage=v=>{const x=String(v||'');return /^\/assets\/images\/[A-Za-z0-9._-]+$/.test(x)?x:'/assets/images/logo.png'};
 const base=Object.entries(products).filter(([_,p])=>filter==='all'||p.category===filter);
 function render(query=''){
   const q=query.trim().toLocaleLowerCase('pt-BR');
   const list=base.filter(([_,p])=>!q||`${p.name} ${labels[p.category]||''}`.toLocaleLowerCase('pt-BR').includes(q));
   if(!list.length){grid.innerHTML='<div class="empty">Nenhum produto encontrado para esta busca.</div>';return;}
   grid.innerHTML=list.map(([id,p])=>{const name=esc(p.name),cat=esc(labels[p.category]||''),url=`/produtos/produto1/?id=${encodeURIComponent(id)}`;return `<article class="product-card"><a class="product-image-wrap" href="${url}"><img loading="lazy" src="${safeImage(p.image)}" alt="${name}"><span class="product-badge">${cat}</span></a><div class="product-info"><div class="product-cat">${cat}</div><div class="product-name">${name}</div><div class="product-price">${(Number(p.priceCents||0)/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}</div><a class="product-btn" href="${url}">VER PRODUTO</a></div></article>`}).join('');
 }
 render(); if(input) input.addEventListener('input',e=>render(e.target.value));
})();
