window.addEventListener('DOMContentLoaded',()=>{
  const toggle=document.getElementById('mobile-toggle');
  const mobile=document.getElementById('mobile-menu');
  if(toggle&&mobile){toggle.addEventListener('click',()=>{mobile.classList.toggle('open');toggle.setAttribute('aria-expanded',mobile.classList.contains('open')?'true':'false')})}
  const legacy=document.getElementById('btn-menu'), legacyMenu=document.getElementById('menu');
  if(legacy&&legacyMenu){legacy.addEventListener('click',()=>legacyMenu.classList.toggle('menu-ativo'))}
});
