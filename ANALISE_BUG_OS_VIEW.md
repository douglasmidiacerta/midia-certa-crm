# 🐛 Análise do Bug - Tela Branca em os_view.php

**Data:** 17/01/2026  
**URL Afetada:** https://graficamidiacerta.com.br/app.php?page=os_view&id=1  
**Status:** 🔴 Em Investigação

---

## 📋 Sintomas

- ✅ Correção aplicada: 38 `exit;` adicionados após `redirect()`
- ❌ Problema persiste: Tela branca após deploy
- ✅ Deploy realizado via Git Push

---

## 🔍 Possíveis Causas

### 1. **JOIN falhando (MAIS PROVÁVEL)**

**Causa:** Linhas 5-16 usam `JOIN` (INNER JOIN) que falha se:
- Cliente `client_id` foi deletado da tabela `clients`
- Vendedor `seller_user_id` foi deletado da tabela `users`

**Sintoma:** Query retorna vazio (`$os = false`), mas o código não trata isso adequadamente.

**Código problemático:**
```php
$st = $pdo->prepare("SELECT o.*, c.name client_name, ... 
                     FROM os o
                     JOIN clients c ON c.id=o.client_id        // ❌ INNER JOIN
                     JOIN users u ON u.id=o.seller_user_id     // ❌ INNER JOIN
                     WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ 
    flash_set('danger','O.S não encontrada'); 
    redirect($base.'/app.php?page=os'); 
    exit; 
}
```

**Problema:** Se o JOIN falhar, `$os = false`, mas mesmo com o redirect+exit, se houver erro ANTES do redirect (output buffer, headers), pode causar tela branca.

**Solução:**
```php
// Usar LEFT JOIN ao invés de JOIN
FROM os o
LEFT JOIN clients c ON c.id=o.client_id
LEFT JOIN users u ON u.id=o.seller_user_id
```

---

### 2. **Headers Already Sent**

**Causa:** Se há QUALQUER saída (echo, espaço, BOM UTF-8) antes do `redirect()`, o header Location: falha silenciosamente.

**Verificar:**
- Arquivo tem BOM UTF-8? (3 bytes invisíveis no início)
- Há espaços antes do `<?php`?
- Há `echo` ou `print` antes do redirect?

**Solução:**
- Salvar arquivo como UTF-8 sem BOM
- Garantir que não há espaços antes de `<?php`
- Adicionar `ob_start()` no início

---

### 3. **Erro em require_once**

**Linha 105 e 517:** 
```php
require_once __DIR__ . '/../config/os_tokens.php';
```

**Problema:** Se o arquivo não existe ou tem erro de sintaxe, causa Fatal Error = tela branca.

**Verificar:**
- Arquivo existe em `config/os_tokens.php`?
- Arquivo tem sintaxe válida?

---

### 4. **Erro na query de os_lines (linha 18)**

```php
$lines = $pdo->prepare("SELECT l.*, i.name item_name, i.type item_type 
                        FROM os_lines l 
                        JOIN items i ON i.id=l.item_id    // ❌ Pode falhar se item deletado
                        WHERE l.os_id=? ORDER BY l.id");
```

**Problema:** Se algum item foi deletado, o JOIN falha e não retorna linhas.

**Solução:** Usar LEFT JOIN

---

### 5. **Variáveis não definidas**

**Linha 29:** Acessa `$os['status']` mas se `$os` for array vazio...

**Linhas críticas:**
- Linha 500: `$os['code']` - se não existir = Notice/Warning
- Linha 486: `$os['client_phone']` - pode não existir

---

## 🧪 Plano de Testes

### Teste 1: Verificar se OS existe e JOIN funciona
```bash
# Upload tmp_rovodev_test_os_simple.php
# Acessar: https://graficamidiacerta.com.br/tmp_rovodev_test_os_simple.php
```

**Resultado esperado:**
- ✅ Se OS carrega = problema está em outra parte do código
- ❌ Se JOIN falha = problema é cliente/vendedor deletado

### Teste 2: Verificar logs do servidor
```bash
# cPanel → Metrics → Errors
# Procurar por erros em 17/01/2026 11:19 ou posterior
```

### Teste 3: Adicionar error_reporting no topo de os_view.php
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_login();
```

---

## ✅ Correções Recomendadas

### Correção #1: Usar LEFT JOIN (CRÍTICO)

```php
$st = $pdo->prepare("SELECT o.*,
                            c.name client_name,
                            COALESCE(NULLIF(c.whatsapp,''), c.phone) client_phone,
                            c.address_street, c.address_number, c.address_neighborhood, 
                            c.address_city, c.address_state, c.address_complement,
                            u.name seller_name
                     FROM os o
                     LEFT JOIN clients c ON c.id=o.client_id          // ✅ LEFT JOIN
                     LEFT JOIN users u ON u.id=o.seller_user_id       // ✅ LEFT JOIN
                     WHERE o.id=?");
```

### Correção #2: Tratar cliente/vendedor NULL

```php
$os = $st->fetch();
if(!$os){ 
    flash_set('danger','O.S não encontrada'); 
    redirect($base.'/app.php?page=os'); 
    exit; 
}

// Adicionar após fetch:
if(empty($os['client_name'])){
    $os['client_name'] = '(Cliente não encontrado)';
}
if(empty($os['seller_name'])){
    $os['seller_name'] = '(Vendedor não encontrado)';
}
```

### Correção #3: Adicionar try-catch global

```php
<?php
try {
    require_login();
    // ... todo o código ...
} catch (Throwable $e) {
    error_log('ERRO os_view.php: ' . $e->getMessage());
    flash_set('danger', 'Erro ao carregar O.S: ' . $e->getMessage());
    redirect($base.'/app.php?page=os');
    exit;
}
```

### Correção #4: LEFT JOIN nos os_lines

```php
$lines = $pdo->prepare("SELECT l.*, 
                               COALESCE(i.name, '(Item removido)') as item_name, 
                               i.type item_type 
                        FROM os_lines l 
                        LEFT JOIN items i ON i.id=l.item_id    // ✅ LEFT JOIN
                        WHERE l.os_id=? 
                        ORDER BY l.id");
```

---

## 📊 Prioridade de Ações

1. **🔴 URGENTE:** Executar `tmp_rovodev_test_os_simple.php` no servidor
2. **🔴 URGENTE:** Verificar logs de erro do servidor
3. **🟡 ALTA:** Aplicar correção LEFT JOIN
4. **🟡 ALTA:** Adicionar tratamento de cliente/vendedor NULL
5. **🟢 MÉDIA:** Adicionar try-catch global
6. **🟢 MÉDIA:** Adicionar error_reporting temporário

---

## 🎯 Próximo Passo

**Aguardando:** Resultado do teste `tmp_rovodev_test_os_simple.php` para confirmar diagnóstico.

**Se JOIN falhar:** Aplicar correção #1 (LEFT JOIN)  
**Se JOIN funcionar:** Investigar outras causas (requires, variáveis)

---

**Atualizado:** 17/01/2026 11:35
