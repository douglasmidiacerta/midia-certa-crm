-- =====================================================
-- ADICIONA NOVO STATUS: AGUARDANDO APROVAÇÃO
-- =====================================================

-- Atualiza a estrutura para suportar o novo status
-- O fluxo agora será:
-- 1. Criar venda -> status: aguardando_aprovacao
-- 2. Cliente aprovar arte -> status: conferencia
-- 3. Continua fluxo normal -> producao -> disponivel -> finalizada

-- Não precisa alterar a tabela pois o campo status já aceita qualquer string
-- Mas vamos garantir que os status estão padronizados

-- Atualiza O.S antigas que estão em 'atendimento' e já foram convertidas em venda
-- para o novo status 'aguardando_aprovacao' (apenas se tiverem token de aprovação pendente)
UPDATE os o
SET o.status = 'aguardando_aprovacao'
WHERE o.status = 'atendimento' 
  AND o.doc_kind = 'sale'
  AND o.sales_locked = 1
  AND EXISTS (
    SELECT 1 FROM os_approval_tokens t 
    WHERE t.os_id = o.id 
    AND t.used_at IS NULL
  );

-- Documentação dos status do sistema:
-- 'orcamento' / 'budget' (doc_kind) - Orçamento, não entra no fluxo
-- 'atendimento' - Venda em criação, ainda não enviada
-- 'aguardando_aprovacao' - Venda criada, aguardando cliente aprovar arte
-- 'conferencia' - Arte aprovada, em análise/conferência
-- 'producao' - Em produção
-- 'disponivel' - Pronto para retirada/entrega
-- 'finalizada' - Entregue e finalizado
-- 'refugado' - Refugado (problemas)
-- 'cancelada' - Cancelado
