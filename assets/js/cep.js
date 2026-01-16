// CEP auto-fill via ViaCEP
// - Add data-cep="1" to a form/container
// - Required input names:
//   cep, address_street, address_neighborhood, address_city, address_state
// - Optional: address_complement
(function(){
  const onlyDigits = (v) => (v || '').toString().replace(/\D+/g, '');

  async function lookupCep(cep){
    const c = onlyDigits(cep);
    if (c.length !== 8) return null;
    try {
      const res = await fetch('https://viacep.com.br/ws/' + c + '/json/');
      if (!res.ok) return null;
      const data = await res.json();
      if (data && data.erro) return null;
      return data;
    } catch(e){
      return null;
    }
  }

  function findInput(container, name){
    return container.querySelector('[name="' + name + '"]');
  }

  function setVal(inp, val){
    if (!inp) return;
    if (inp.value && inp.value.trim() !== '') return;
    inp.value = val || '';
  }

  function bind(container){
    const cepInp = findInput(container, 'cep');
    if (!cepInp) return;

    async function apply(){
      const data = await lookupCep(cepInp.value);
      if (!data) return;

      setVal(findInput(container, 'address_street'), data.logradouro);
      setVal(findInput(container, 'address_neighborhood'), data.bairro);
      setVal(findInput(container, 'address_city'), data.localidade);
      setVal(findInput(container, 'address_state'), data.uf);
      setVal(findInput(container, 'address_complement'), data.complemento);
    }

    cepInp.addEventListener('blur', apply);
    cepInp.addEventListener('change', apply);
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('[data-cep="1"]').forEach(bind);
  });
})();
