(function(){
  const KEY='metalcolor_cart_v2';let syncTimer;
  function read(){try{const x=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(x)?x:[]}catch{return[]}}
  function sync(cart){clearTimeout(syncTimer);syncTimer=setTimeout(()=>fetch('/api/account/cart',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({cart})}).catch(()=>{}),250)}
  function write(cart){localStorage.setItem(KEY,JSON.stringify(cart));window.dispatchEvent(new CustomEvent('metalcolor:cart',{detail:cart}));sync(cart);}
  function add(item){const cart=read(),key=`${item.id}::${item.option||''}`,found=cart.find(x=>`${x.id}::${x.option||''}`===key);if(found)found.quantity=Math.min(100,(Number(found.quantity)||1)+(Number(item.quantity)||1));else cart.push({id:item.id,option:item.option||'ÚNICO',quantity:Math.max(1,Number(item.quantity)||1)});write(cart);return cart;}
  function clear(){write([])} function count(){return read().reduce((s,x)=>s+(Number(x.quantity)||1),0)}
  async function restore(){if(read().length)return read();try{const r=await fetch('/api/account/cart');if(!r.ok)return read();const d=await window.METAL_API.parse(r);if(Array.isArray(d.cart)&&d.cart.length){localStorage.setItem(KEY,JSON.stringify(d.cart));window.dispatchEvent(new CustomEvent('metalcolor:cart',{detail:d.cart}));return d.cart}}catch{}return read()}
  window.METAL_CART={read,write,add,clear,count,restore};
})();
