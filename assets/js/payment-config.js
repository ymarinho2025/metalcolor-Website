(function(){
  const runtime = window.METALCOLOR_RUNTIME || {};
  window.PAYMENT_CONFIG = {
    storeName: runtime.storeName || 'METAL COLOR',
    whatsappNumber: runtime.whatsappNumber || '',
    products: runtime.products || {}
  };
})();
