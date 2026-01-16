<?php
// NOVA ORDEM DE COMPRA
require_login();
require_role(['admin','financeiro']);

// Visualização de O.C
$view_id = (int)($_GET['view'] ?? 0);
if ($view_id) {
    $st = $pdo->prepare("
        SELECT p.*, s.name AS supplier_name, o.code AS os_code
        FROM purchases p
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        LEFT JOIN os o ON o.id = p.os_id
        WHERE p.id = ?
    ");
    $st->execute([$view_id]);
    $purchase = $st->fetch();
    if (!$purchase) {
        flash_set('danger', 'O.C não encontrada.');
        redirect($base . '/app.php?page=oc');
    }

    $lines_st = $pdo->prepare("SELECT * FROM purchase_lines WHERE purchase_id = ? ORDER BY id");
    $lines_st->execute([$view_id]);
    $lines = $lines_st->fetchAll();

    $ap_st = $pdo->prepare("SELECT * FROM ap_titles WHERE purchase_id = ? ORDER BY id");
    $ap_st->execute([$view_id]);
    $aps = $ap_st->fetchAll();

    $total_lines = 0;
    foreach ($lines as $l) {
        $total_lines += ((float)$l['qty']) * ((float)$l['unit_price']);
    }
    ?>
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 style="font-weight:900"><?= h($purchase['code']) ?></h5>
                <div class="text-muted small">
                    Fornecedor: <?= h($purchase['supplier_name'] ?? '-') ?><?= $purchase['os_code'] ? ' • OS ' . h($purchase['os_code']) : '' ?>
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Total itens</div>
                <div style="font-size:1.2rem;font-weight:900"><?= h(money($total_lines)) ?></div>
                <a class="btn btn-sm btn-outline-primary mt-2" href="<?= h($base) ?>/app.php?page=fin_pagar&oc=<?= h($view_id) ?>">Ir para pagar</a>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <h6 style="font-weight:900">Itens da O.C</h6>
        <?php if (empty($lines)): ?>
            <div class="text-muted">Nenhum item cadastrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Descrição</th><th>Qtd</th><th>Valor</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <?php
                            $qty = (float)($l['qty'] ?? 0);
                            $unit = (float)($l['unit_price'] ?? 0);
                        ?>
                        <tr>
                            <td><?= h($l['description'] ?? '') ?></td>
                            <td><?= h($qty) ?></td>
                            <td><?= h(money($unit)) ?></td>
                            <td><?= h(money($qty * $unit)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card p-3">
        <h6 style="font-weight:900">Títulos a Pagar</h6>
        <?php if (empty($aps)): ?>
            <div class="text-muted">Nenhum título vinculado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Descrição</th><th>Venc.</th><th>Valor</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($aps as $ap): ?>
                        <tr>
                            <td><?= h($ap['description'] ?? '') ?></td>
                            <td><?= h(date_br($ap['due_date'] ?? '')) ?></td>
                            <td><?= h(money($ap['amount'] ?? 0)) ?></td>
                            <td><span class="badge bg-light text-dark"><?= h($ap['status'] ?? '') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-3">
        <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=oc">Voltar</a>
    </div>
    <?php
    return;
}

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_purchase') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $items = $_POST['items'] ?? [];
        
        if ($supplier_id && !empty($items)) {
            try {
                $pdo->beginTransaction();
                
                // Gera código da O.C (a partir de OC#001700)
                $stCodes = $pdo->query("SELECT code FROM purchases ORDER BY id DESC LIMIT 200");
                $max = 0;
                foreach ($stCodes->fetchAll() as $row) {
                    $digits = preg_replace('/\D+/', '', (string)($row['code'] ?? ''));
                    if ($digits !== '') {
                        $num = (int)$digits;
                        if ($num > $max) $max = $num;
                    }
                }
                if ($max < 1700) $max = 1699;
                $code = 'OC#' . str_pad((string)($max + 1), 6, '0', STR_PAD_LEFT);
                
                // Cria a O.C
                $st = $pdo->prepare("INSERT INTO purchases (code, supplier_id, notes, status, created_at) VALUES (?, ?, ?, 'pendente', NOW())");
                $st->execute([$code, $supplier_id, $notes]);
                $purchase_id = $pdo->lastInsertId();
                
                // Adiciona itens
                $stLine = $pdo->prepare("INSERT INTO purchase_lines (purchase_id, item_id, qty, unit_price) VALUES (?, ?, ?, ?)");
                foreach ($items as $item) {
                    if (!empty($item['item_id']) && !empty($item['qty']) && !empty($item['unit_price'])) {
                        $stLine->execute([
                            $purchase_id,
                            (int)$item['item_id'],
                            (float)$item['qty'],
                            (float)$item['unit_price']
                        ]);
                    }
                }
                
                $pdo->commit();
                flash_set('success', "O.C $code criada com sucesso!");
                redirect($base . '/app.php?page=oc');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('danger', 'Erro ao criar O.C: ' . $e->getMessage());
            }
        } else {
            flash_set('danger', 'Preencha todos os campos obrigatórios');
        }
    }
}

// Buscar fornecedores
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

// Buscar produtos
$products = $pdo->query("SELECT id, name, type FROM items ORDER BY name")->fetchAll();
?>

<div class="card p-4 mb-4">
    <h4 style="font-weight: 900;">➕ Nova Ordem de Compra (O.C)</h4>
    <p class="text-muted small">Crie uma nova ordem de compra para seus fornecedores</p>
</div>

<form method="post" id="formPurchase">
    <input type="hidden" name="action" value="create_purchase">
    
    <div class="row g-3">
        <!-- Dados da O.C -->
        <div class="col-md-12">
            <div class="card p-3">
                <h6 style="font-weight: 900;">📋 Informações Gerais</h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Fornecedor <span class="text-danger">*</span></label>
                        <select class="form-select" name="supplier_id" required>
                            <option value="">Selecione um fornecedor</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= h($sup['id']) ?>"><?= h($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Observações</label>
                        <input type="text" class="form-control" name="notes" placeholder="Ex: Entrega em 10 dias">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Itens da Compra -->
        <div class="col-md-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 style="font-weight: 900;">📦 Itens da Compra</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addItem()">
                        ➕ Adicionar Item
                    </button>
                </div>
                
                <div id="itemsContainer">
                    <!-- Itens serão adicionados aqui via JavaScript -->
                </div>
                
                <div class="mt-3 p-3" style="background: #f9fafb; border-radius: 6px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>TOTAL DA O.C:</strong>
                        <strong id="totalValue" style="font-size: 1.5rem; color: #059669;">R$ 0,00</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botões -->
        <div class="col-md-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success btn-lg">
                    ✅ Criar Ordem de Compra
                </button>
                <a href="<?= h($base) ?>/app.php?page=oc" class="btn btn-secondary btn-lg">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
</form>

<script>
let itemIndex = 0;
const products = <?= json_encode($products) ?>;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const item = document.createElement('div');
    item.className = 'row g-2 mb-2 align-items-end';
    item.id = 'item' + itemIndex;
    item.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small">Produto <span class="text-danger">*</span></label>
            <select class="form-select" name="items[${itemIndex}][item_id]" required onchange="calculateTotal()">
                <option value="">Selecione</option>
                ${products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Quantidade <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="items[${itemIndex}][qty]" step="0.01" min="0.01" required onchange="calculateTotal()">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Preço Unitário <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="items[${itemIndex}][unit_price]" step="0.01" min="0.01" required onchange="calculateTotal()">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(${itemIndex})">
                🗑️ Remover
            </button>
        </div>
    `;
    container.appendChild(item);
    itemIndex++;
}

function removeItem(index) {
    document.getElementById('item' + index).remove();
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('#itemsContainer .row').forEach(row => {
        const qty = parseFloat(row.querySelector('[name*="[qty]"]')?.value || 0);
        const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value || 0);
        total += qty * price;
    });
    document.getElementById('totalValue').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Adiciona primeiro item ao carregar
addItem();
</script>

<style>
.form-label .text-danger {
    font-weight: bold;
}
</style>
