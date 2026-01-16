<?php
ob_start();
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/utils.php';
require __DIR__ . '/config/company.php'; // Dados da empresa
$config = require __DIR__ . '/config/config.php';
$base = $config['base_path'];
$appName = $config['app_name'] ?? 'Mídia Certa';

require_login();

// Debug visível apenas para ADMIN (ajuda a evitar "tela branca" quando algo quebra)
if(function_exists('current_user')){
  $u = current_user();
  if(($u['role'] ?? '') === 'admin'){
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting(E_ALL);
  }
}

$page = $_GET['page'] ?? 'dashboard';

require __DIR__ . '/partials/layout_top.php';

switch($page){
  case 'dashboard':
    // todos os perfis
    require __DIR__ . '/pages/dashboard.php';
    break;

  // Vendas / O.S
  case 'os':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os.php';
    break;
  case 'os_new':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_new.php';
    break;
  case 'os_view':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_view.php';
    break;
  case 'os_print':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_print.php';
    break;
  case 'os_print_professional':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_print_professional.php';
    break;
  case 'os_label':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_label.php';
    break;
  case 'os_status':
    require_role(['admin','financeiro']); // status operacional/financeiro
    require __DIR__ . '/pages/os_status.php';
    break;
  case 'os_requests':
    require_role(['admin']);
    require __DIR__ . '/pages/os_requests.php';
    break;
  case 'os_kanban':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/os_kanban.php';
    break;
  case 'os_edit':
    require_role(['admin','vendas']);
    require __DIR__ . '/pages/os_edit.php';
    break;

  // cadastros
  case 'clients':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/clients.php';
    break;
  case 'clients_new':
    require_role(['admin','vendas']);
    require __DIR__ . '/pages/clients_new.php';
    break;
  case 'clients_edit':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/clients_edit.php';
    break;
  case 'suppliers':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/suppliers.php';
    break;
  case 'suppliers_edit':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/suppliers_edit.php';
    break;
  case 'suppliers_edit':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/suppliers_edit.php';
    break;
  case 'employees':
    require_role(['admin']);
    require __DIR__ . '/pages/employees.php';
    break;
  case 'user_permissions':
    require_role(['admin']);
    require __DIR__ . '/pages/user_permissions.php';
    break;
  case 'items':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/items.php';
    break;
  case 'items_edit':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/items_edit.php';
    break;
  case 'items_edit':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/items_edit.php';
    break;
  case 'accounts':
    require_role(['admin']);
    require __DIR__ . '/pages/accounts.php';
    break;
  case 'card_acquirers':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/card_acquirers.php';
    break;

  // financeiro
  case 'purchases':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/purchases.php';
    break;
  case 'finance':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/finance.php';
    break;
  case 'cash':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/cash.php';
    break;
  case 'cash_movements':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/cash_movements.php';
    break;

  case 'fin_receber':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/fin_receber.php';
    break;
  case 'fin_pagar':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/fin_pagar.php';
    break;
  case 'fin_baixa':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/fin_baixa.php';
    break;
  case 'transfer':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/transfer.php';
    break;
  case 'dre':
    // Verificação de acesso feita dentro da página dre.php
    require __DIR__ . '/pages/dre.php';
    break;
  case 'dre_professional':
    require_role(['admin']);
    require __DIR__ . '/pages/dre_professional.php';
    break;
  case 'dre_export':
    require_role(['admin']);
    require __DIR__ . '/pages/dre_export.php';
    break;

  case 'expedicao':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/expedicao.php';
    break;

  // Relatórios
  case 'reports_sales':
    require_role(['admin','vendas','financeiro']);
    require __DIR__ . '/pages/reports_sales.php';
    break;
  case 'reports_sales_advanced':
    require_role(['admin','vendas']);
    require __DIR__ . '/pages/reports_sales_advanced.php';
    break;
  case 'reports_finance':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/reports_finance.php';
    break;
  case 'reports_compras':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/reports_compras.php';
    break;
  case 'reports_compras_advanced':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/reports_compras_advanced.php';
    break;
  case 'reports_finance_advanced':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/reports_finance_advanced.php';
    break;
  case 'oc':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/oc.php';
    break;

  // Produção
  case 'producao_gestao':
    require_login();
    require __DIR__ . '/pages/producao_gestao.php';
    break;
  case 'producao_conferencia':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/producao_conferencia.php';
    break;
  case 'producao_producao':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/producao_producao.php';
    break;
  case 'producao_expedicao':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/producao_expedicao.php';
    break;

  case 'clients_import':
    require_role(['admin','financeiro']);
    require __DIR__ . '/pages/clients_import.php';
    break;

  // Marketing
  case 'carousel_slides':
    require_role(['admin']);
    require __DIR__ . '/pages/carousel_slides.php';
    break;
  case 'marketing_hero':
    require_role(['admin']);
    require __DIR__ . '/pages/marketing_hero.php';
    break;
  case 'marketing_site':
    require_role(['admin']);
    require __DIR__ . '/pages/marketing_site.php';
    break;
  case 'marketing_produtos':
    require_role(['admin']);
    require __DIR__ . '/pages/marketing_produtos.php';
    break;
  case 'marketing_artigos':
    require_role(['admin']);
    require __DIR__ . '/pages/marketing_artigos.php';
    break;

  // DRE (Master Only - Último)
  case 'dre_professional':
    require_role(['admin']);
    require __DIR__ . '/pages/dre_professional.php';
    break;
  case 'dre_enhanced':
    require_role(['admin']);
    require __DIR__ . '/pages/dre_enhanced.php';
    break;
  case 'dre_export':
    require_role(['admin']);
    require __DIR__ . '/pages/dre_export.php';
    break;

  default:
    echo "<div class='card p-3'><h5>Página não encontrada</h5><div class='text-muted'>page=".h($page)."</div></div>";
}

require __DIR__ . '/partials/layout_bottom.php';
ob_end_flush();
