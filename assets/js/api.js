(function(){
  async function parse(response){
    const text=await response.text();
    if(!text) return {};
    try{return JSON.parse(text);}catch{
      const requestId=response.headers.get('x-vercel-id')||'';
      const suffix=requestId?` (ref. ${requestId})`:'';
      throw new Error(`O servidor respondeu em formato inválido${suffix}. Consulte os logs da Vercel.`);
    }
  }
  async function request(url,options){
    const response=await fetch(url,options);
    const data=await parse(response);
    if(!response.ok) throw new Error(data?.error||`Erro HTTP ${response.status}`);
    return {response,data};
  }
  window.METAL_API={parse,request};
})();
