window.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('[data-auth-form]'),status=document.getElementById('auth-status'); if(!form)return;
 form.addEventListener('submit',async e=>{e.preventDefault();status.textContent='Processando...';const data=Object.fromEntries(new FormData(form).entries());
  try{const r=await fetch(form.dataset.endpoint,{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(data)});const d=await window.METAL_API.parse(r);if(!r.ok)throw new Error(d.error||'Não foi possível continuar.');location.href=d.user?.role==='ADMIN'?'/admin/':'/conta/';}catch(err){status.textContent=err.message;}
 });
});
