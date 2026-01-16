<?php
require_role(['admin']);

$user_id = (int)($_GET['id'] ?? 0);

if(!$user_id){
  flash_set('danger', 'Usuário não especificado.');
  redirect($base.'/app.php?page=employees');
}

// Busca usuário
$st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$user_id]);
$user = $st->fetch();

if(!$user){
  flash_set('danger', 'Usuário não encontrado.');
  redirect($base.'/app.php?page=employees');
}

// Atualizar permissões
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
  $action = $_POST['action'];
  
  if($action === 'update_permissions'){
    $permissions = [
      'perm_os_view', 'perm_os_create', 'perm_os_edit', 'perm_os_delete',
      'perm_clients_view', 'perm_clients_create', 'perm_clients_edit', 'perm_clients_delete',
      'perm_finance_view', 'perm_finance_receive', 'perm_finance_pay',
      'perm_cash_view', 'perm_cash_move',
      'perm_production_view', 'perm_production_manage',
      'perm_purchases_view', 'perm_purchases_create',
      'perm_reports_sales', 'perm_reports_finance', 'perm_dre_view',
      'perm_items_manage', 'perm_suppliers_manage', 'perm_users_manage',
      'perm_marketing_view', 'perm_marketing_edit', 'perm_marketing_upload'
    ];
    
    $updates = [];
    $values = [];
    foreach($permissions as $perm){
      $updates[] = "$perm = ?";
      $values[] = isset($_POST[$perm]) ? 1 : 0;
    }
    $values[] = $user_id;
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $st = $pdo->prepare($sql);
    $st->execute($values);
    
    flash_set('success', 'Permissões atualizadas com sucesso!');
    redirect($base.'/app.php?page=user_permissions&id='.$user_id);
  }
  
  if($action === 'change_password'){
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if(strlen($new_password) < 6){
      flash_set('danger', 'A senha deve ter no mínimo 6 caracteres.');
      redirect($base.'/app.php?page=user_permissions&id='.$user_id);
    }
    
    if($new_password !== $confirm_password){
      flash_set('danger', 'As senhas não conferem.');
      redirect($base.'/app.php?page=user_permissions&id='.$user_id);
    }
    
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $st = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $st->execute([$password_hash, $user_id]);
    
    flash_set('success', 'Senha alterada com sucesso!');
    redirect($base.'/app.php?page=user_permissions&id='.$user_id);
  }
}
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1" style="font-weight:900">🔐 Permissões de <?= h($user['name']) ?></h5>
          <div class="text-muted small">
            Perfil: <span class="badge bg-<?= $user['role']==='admin'?'danger':($user['role']==='vendas'?'primary':'success') ?>">
              <?= strtoupper(h($user['role'])) ?>
            </span>
          </div>
        </div>
        <a href="<?= h($base) ?>/app.php?page=employees" class="btn btn-outline-secondary">← Voltar</a>
      </div>

      <form method="post">
        <input type="hidden" name="action" value="update_permissions">
        
        <div class="alert alert-info">
          <strong>💡 Dica:</strong> Marque as caixas para liberar acesso a cada funcionalidade específica. Isso permite movimentar colaboradores entre setores sem alterar o perfil.
        </div>

        <!-- Vendas e O.S -->
        <div class="card mb-3">
          <div class="card-header bg-primary text-white">
            <strong>📋 Ordens de Serviço (O.S)</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_os_view" id="perm_os_view" <?= $user['perm_os_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_os_view">Ver O.S</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_os_create" id="perm_os_create" <?= $user['perm_os_create']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_os_create">Criar O.S</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_os_edit" id="perm_os_edit" <?= $user['perm_os_edit']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_os_edit">Editar O.S</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_os_delete" id="perm_os_delete" <?= $user['perm_os_delete']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_os_delete">Excluir O.S</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Clientes -->
        <div class="card mb-3">
          <div class="card-header bg-success text-white">
            <strong>👥 Clientes</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_clients_view" id="perm_clients_view" <?= $user['perm_clients_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_clients_view">Ver clientes</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_clients_create" id="perm_clients_create" <?= $user['perm_clients_create']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_clients_create">Criar clientes</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_clients_edit" id="perm_clients_edit" <?= $user['perm_clients_edit']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_clients_edit">Editar clientes</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_clients_delete" id="perm_clients_delete" <?= $user['perm_clients_delete']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_clients_delete">Excluir clientes</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Financeiro -->
        <div class="card mb-3">
          <div class="card-header bg-warning text-dark">
            <strong>💰 Financeiro</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_finance_view" id="perm_finance_view" <?= $user['perm_finance_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_finance_view">Ver financeiro</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_finance_receive" id="perm_finance_receive" <?= $user['perm_finance_receive']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_finance_receive">Dar baixa em recebimentos</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_finance_pay" id="perm_finance_pay" <?= $user['perm_finance_pay']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_finance_pay">Dar baixa em pagamentos</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_cash_view" id="perm_cash_view" <?= $user['perm_cash_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_cash_view">Ver caixa</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_cash_move" id="perm_cash_move" <?= $user['perm_cash_move']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_cash_move">Movimentar caixa</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Produção -->
        <div class="card mb-3">
          <div class="card-header bg-info text-white">
            <strong>🏭 Produção</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_production_view" id="perm_production_view" <?= $user['perm_production_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_production_view">Ver produção</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_production_manage" id="perm_production_manage" <?= $user['perm_production_manage']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_production_manage">Gerenciar produção</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Compras -->
        <div class="card mb-3">
          <div class="card-header bg-secondary text-white">
            <strong>🛒 Compras</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_purchases_view" id="perm_purchases_view" <?= $user['perm_purchases_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_purchases_view">Ver compras</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_purchases_create" id="perm_purchases_create" <?= $user['perm_purchases_create']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_purchases_create">Criar compras</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Relatórios -->
        <div class="card mb-3">
          <div class="card-header bg-dark text-white">
            <strong>📊 Relatórios</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_reports_sales" id="perm_reports_sales" <?= $user['perm_reports_sales']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_reports_sales">Relatórios de vendas</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_reports_finance" id="perm_reports_finance" <?= $user['perm_reports_finance']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_reports_finance">Relatórios financeiros</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_dre_view" id="perm_dre_view" <?= $user['perm_dre_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_dre_view">Ver DRE</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Cadastros -->
        <div class="card mb-3">
          <div class="card-header bg-danger text-white">
            <strong>⚙️ Cadastros e Configurações</strong>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_items_manage" id="perm_items_manage" <?= $user['perm_items_manage']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_items_manage">Gerenciar produtos/serviços</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_suppliers_manage" id="perm_suppliers_manage" <?= $user['perm_suppliers_manage']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_suppliers_manage">Gerenciar fornecedores</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_users_manage" id="perm_users_manage" <?= $user['perm_users_manage']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_users_manage">Gerenciar usuários</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Marketing -->
        <div class="card mb-3">
          <div class="card-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
            <h6 class="mb-0" style="font-weight:900;">🎨 Marketing</h6>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_marketing_view" id="perm_marketing_view" <?= $user['perm_marketing_view']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_marketing_view">Ver painel Marketing</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_marketing_edit" id="perm_marketing_edit" <?= $user['perm_marketing_edit']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_marketing_edit">Editar site</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="perm_marketing_upload" id="perm_marketing_upload" <?= $user['perm_marketing_upload']?'checked':'' ?>>
                  <label class="form-check-label" for="perm_marketing_upload">Upload imagens</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">💾 Salvar Permissões</button>
          <a href="<?= h($base) ?>/app.php?page=employees" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Sidebar: Alterar Senha -->
  <div class="col-lg-4">
    <div class="card p-4">
      <h6 class="mb-3" style="font-weight:900">🔑 Alterar Senha</h6>
      
      <form method="post">
        <input type="hidden" name="action" value="change_password">
        
        <div class="mb-3">
          <label class="form-label">Nova Senha</label>
          <input type="password" class="form-control" name="new_password" required minlength="6" placeholder="Mínimo 6 caracteres">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Confirmar Senha</label>
          <input type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="Digite novamente">
        </div>
        
        <button type="submit" class="btn btn-warning w-100">🔐 Alterar Senha</button>
      </form>
      
      <hr class="my-3">
      
      <div class="text-muted small">
        <strong>Dica:</strong> Use uma senha forte com letras, números e símbolos.
      </div>
    </div>
  </div>
</div>
