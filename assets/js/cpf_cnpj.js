/**
 * Validação e Máscara Automática de CPF/CNPJ
 */

// Valida CPF
function validarCPF(cpf) {
  cpf = cpf.replace(/[^\d]/g, '');
  
  if (cpf.length !== 11) return false;
  
  // Verifica sequências iguais
  if (/^(\d)\1{10}$/.test(cpf)) return false;
  
  // Valida primeiro dígito
  let soma = 0;
  for (let i = 0; i < 9; i++) {
    soma += parseInt(cpf.charAt(i)) * (10 - i);
  }
  let resto = 11 - (soma % 11);
  let digito1 = resto >= 10 ? 0 : resto;
  
  if (digito1 !== parseInt(cpf.charAt(9))) return false;
  
  // Valida segundo dígito
  soma = 0;
  for (let i = 0; i < 10; i++) {
    soma += parseInt(cpf.charAt(i)) * (11 - i);
  }
  resto = 11 - (soma % 11);
  let digito2 = resto >= 10 ? 0 : resto;
  
  return digito2 === parseInt(cpf.charAt(10));
}

// Valida CNPJ
function validarCNPJ(cnpj) {
  cnpj = cnpj.replace(/[^\d]/g, '');
  
  if (cnpj.length !== 14) return false;
  
  // Verifica sequências iguais
  if (/^(\d)\1{13}$/.test(cnpj)) return false;
  
  // Valida primeiro dígito
  let tamanho = cnpj.length - 2;
  let numeros = cnpj.substring(0, tamanho);
  let digitos = cnpj.substring(tamanho);
  let soma = 0;
  let pos = tamanho - 7;
  
  for (let i = tamanho; i >= 1; i--) {
    soma += numeros.charAt(tamanho - i) * pos--;
    if (pos < 2) pos = 9;
  }
  
  let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  if (resultado != digitos.charAt(0)) return false;
  
  // Valida segundo dígito
  tamanho = tamanho + 1;
  numeros = cnpj.substring(0, tamanho);
  soma = 0;
  pos = tamanho - 7;
  
  for (let i = tamanho; i >= 1; i--) {
    soma += numeros.charAt(tamanho - i) * pos--;
    if (pos < 2) pos = 9;
  }
  
  resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  return resultado == digitos.charAt(1);
}

// Aplica máscara CPF
function mascararCPF(valor) {
  valor = valor.replace(/\D/g, '');
  valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
  valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
  valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  return valor;
}

// Aplica máscara CNPJ
function mascararCNPJ(valor) {
  valor = valor.replace(/\D/g, '');
  valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
  valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
  valor = valor.replace(/\.(\d{3})(\d)/, '.$1/$2');
  valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
  return valor;
}

// Detecta automaticamente CPF ou CNPJ e aplica máscara
function aplicarMascaraCpfCnpj(input) {
  let valor = input.value.replace(/\D/g, '');
  
  if (valor.length <= 11) {
    // CPF
    input.value = mascararCPF(valor);
    input.setAttribute('data-type', 'cpf');
  } else {
    // CNPJ
    input.value = mascararCNPJ(valor);
    input.setAttribute('data-type', 'cnpj');
  }
  
  // Adiciona validação visual
  validarCampo(input);
}

// Valida campo e mostra feedback visual
function validarCampo(input) {
  let valor = input.value.replace(/\D/g, '');
  let isValid = false;
  let feedbackEl = input.nextElementSibling;
  
  if (!feedbackEl || !feedbackEl.classList.contains('cpf-cnpj-feedback')) {
    feedbackEl = document.createElement('small');
    feedbackEl.className = 'cpf-cnpj-feedback';
    input.parentNode.insertBefore(feedbackEl, input.nextSibling);
  }
  
  if (valor.length === 0) {
    input.classList.remove('is-valid', 'is-invalid');
    feedbackEl.textContent = '';
    return;
  }
  
  if (valor.length === 11) {
    isValid = validarCPF(valor);
    feedbackEl.textContent = isValid ? '✓ CPF válido' : '✗ CPF inválido';
  } else if (valor.length === 14) {
    isValid = validarCNPJ(valor);
    feedbackEl.textContent = isValid ? '✓ CNPJ válido' : '✗ CNPJ inválido';
  } else {
    feedbackEl.textContent = 'Digite CPF (11 dígitos) ou CNPJ (14 dígitos)';
  }
  
  if (isValid) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    feedbackEl.className = 'cpf-cnpj-feedback text-success small';
  } else if (valor.length >= 11) {
    input.classList.remove('is-valid');
    input.classList.add('is-invalid');
    feedbackEl.className = 'cpf-cnpj-feedback text-danger small';
  } else {
    input.classList.remove('is-valid', 'is-invalid');
    feedbackEl.className = 'cpf-cnpj-feedback text-muted small';
  }
}

// Inicializa campos CPF/CNPJ
function initCpfCnpjFields() {
  document.querySelectorAll('input[data-cpf-cnpj]').forEach(input => {
    input.setAttribute('maxlength', '18'); // 14 dígitos + 4 caracteres de formatação
    input.setAttribute('placeholder', 'CPF ou CNPJ');
    
    input.addEventListener('input', function(e) {
      aplicarMascaraCpfCnpj(this);
    });
    
    input.addEventListener('blur', function(e) {
      validarCampo(this);
    });
    
    // Aplica máscara no valor inicial se houver
    if (input.value) {
      aplicarMascaraCpfCnpj(input);
    }
  });
}

// Inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCpfCnpjFields);
} else {
  initCpfCnpjFields();
}

// Exporta funções para uso global
window.validarCPF = validarCPF;
window.validarCNPJ = validarCNPJ;
window.aplicarMascaraCpfCnpj = aplicarMascaraCpfCnpj;
