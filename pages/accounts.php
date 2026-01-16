<?php
// GERENCIAMENTO DE PERMISSÕES E USUÁRIOS
require_login();
require_role(['admin']);

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_role') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_role = trim($_POST['role'] ?? '');
        
        if ($user_id && in_array($new_role, ['admin', 'financeiro', 'vendas'])) {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
            flash_set('success', 'Permissão atualizada com sucesso!');
        } else {
            flash_set('danger', 'Dados inválidos');
        }
        redirect($base . '/app.php?page=accounts');
    }
    
    if ($action === 'toggle_active') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            $current = $pdo->prepare("SELECT active FROM users WHERE id = ?")->execute([$user_id]);
            $pdo->prepare("UPDATE users SET active = NOT active WHERE id = ?")->execute([$user_id]);
            flash_set('success', 'Status atualizado!');
        }
        redirect($base . '/app.php?page=accounts');
    }
    
    if ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_password = bin2hex(random_bytes(5)); // Gera senha de 10 caracteres
        
        if ($user_id) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
            $user = $pdo->prepare("SELECT email FROM users WHERE id = ?")->execute([$user_id]);
            $user = $pdo->query("SELECT email FROM users WHERE id = $user_id")->fetch();
            flash_set('success', "Senha resetada! Nova senha para {$user['email']}: <strong>$new_password</strong>");
        }
        redirect($base . '/app.php?page=accounts');
    }
}

// Buscar todos os usuários
$users = $pdo->query("
    SELECT 
        u.*,
        e.full_name AS employee_name,
        e.department
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    ORDER BY u.active DESC, u.name
")->fetchAll();

// Estatísticas
$stats = $pdo->query("
    SELECT 
        role,
        COUNT(*) as total,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as ativos
    FROM users
    GROUP BY role
")->fetchAll();
?>

<div class="card p-4 mb-4">
    <h4 style="font-weight: 900;">🔐 Gerenciamento de Permissões</h4>
    <p class="text-muted small">Gerencie usuários, permissões e acessos ao sistema</p>
</div>

<!-- Estatísticas -->
<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
    <div class="col-md-4">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div style="font-size: 0.875rem; color: #6b7280; text-transform: uppercase; font-weight: 600;">
                        <?php
                        $roleNames = [
                            'admin' => '👑 Administradores',
                            'financeiro' => '💼 Financeiro',
                            'vendas' => '📊 Vendas'
                        ];
                        echo $roleNames[$stat['role']] ?? $stat['role'];
                        ?>
                    </div>
                    <div style="font-size: 2rem; font-weight: 900; color: #059669;">
                        <?= h($stat['ativos']) ?>/<?= h($stat['total']) ?>
                    </div>
                    <small class="text-muted">ativos / total</small>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Lista de Usuários -->
<div class="card p-4">
    <h5 style="font-weight: 900; margin-bottom: 1rem;">👥 Usuários do Sistema</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead style="background: #f9fafb;">
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Permissão</th>
                    <th>Departamento</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight: 700;">
                        <?= h($u['employee_name'] ?? $u['name']) ?>
                        <?php if ($u['id'] == user()['id']): ?>
                            <span class="badge bg-info small">Você</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($u['email']) ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                            <select name="role" class="form-select form-select-sm" 
                                    onchange="if(confirm('Deseja alterar a permissão deste usuário?')) this.form.submit();"
                                    <?= $u['id'] == user()['id'] ? 'disabled' : '' ?>>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>👑 Admin</option>
                                <option value="financeiro" <?= $u['role'] === 'financeiro' ? 'selected' : '' ?>>💼 Financeiro</option>
                                <option value="vendas" <?= $u['role'] === 'vendas' ? 'selected' : '' ?>>📊 Vendas</option>
                            </select>
                        </form>
                    </td>
                    <td class="small text-muted"><?= h($u['department'] ?? '-') ?></td>
                    <td class="text-center">
                        <?php if ($u['active']): ?>
                            <span class="badge bg-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <?php if ($u['id'] != user()['id']): ?>
                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPass<?= h($u['id']) ?>">
                                🔑
                            </button>
                            <button type="button" class="btn btn-outline-<?= $u['active'] ? 'danger' : 'success' ?>" data-bs-toggle="modal" data-bs-target="#toggleActive<?= h($u['id']) ?>">
                                <?= $u['active'] ? '🚫' : '✅' ?>
                            </button>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <!-- Modal Reset Senha -->
                <div class="modal fade" id="resetPass<?= h($u['id']) ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title">🔑 Resetar Senha</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <p>Deseja resetar a senha do usuário <strong><?= h($u['name']) ?></strong>?</p>
                                    <div class="alert alert-warning small mb-0">
                                        Uma nova senha aleatória será gerada e exibida para você informar ao usuário.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning">🔑 Resetar Senha</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Ativar/Desativar -->
                <div class="modal fade" id="toggleActive<?= h($u['id']) ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header <?= $u['active'] ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                                <h5 class="modal-title"><?= $u['active'] ? '🚫 Desativar' : '✅ Ativar' ?> Usuário</h5>
                                <button type="button" class="btn-close <?= $u['active'] ? 'btn-close-white' : '' ?>" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <p>Deseja <?= $u['active'] ? 'desativar' : 'ativar' ?> o usuário <strong><?= h($u['name']) ?></strong>?</p>
                                    <?php if ($u['active']): ?>
                                    <div class="alert alert-danger small mb-0">
                                        <strong>⚠️ Atenção:</strong> O usuário não conseguirá mais fazer login no sistema.
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-success small mb-0">
                                        <strong>✅ Confirmação:</strong> O usuário poderá fazer login normalmente.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= h($u['id']) ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn <?= $u['active'] ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $u['active'] ? '🚫 Desativar' : '✅ Ativar' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3 mt-4" style="background: #f0f9ff; border-left: 4px solid #3b82f6;">
    <h6 style="font-weight: 900;">💡 Sobre as Permissões</h6>
    <ul class="mb-0 small">
        <li><strong>👑 Admin:</strong> Acesso total ao sistema, incluindo DRE e configurações</li>
        <li><strong>💼 Financeiro:</strong> Acesso a vendas, financeiro, compras, produção e relatórios</li>
        <li><strong>📊 Vendas:</strong> Acesso apenas a vendas e relatórios de vendas (sem ver lucro)</li>
    </ul>
</div>