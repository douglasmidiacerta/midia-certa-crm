-- Mídia Certa CRM - Upgrade v3.4
-- Esta versão não exige mudanças obrigatórias no banco.
-- (Inclui apenas índices recomendados para relatórios/DRE e consultas do dashboard.)

ALTER TABLE os
  ADD INDEX idx_os_created_at (created_at),
  ADD INDEX idx_os_seller (seller_user_id);

ALTER TABLE ar_titles
  ADD INDEX idx_ar_status_due (status, due_date),
  ADD INDEX idx_ar_received_at (received_at);

ALTER TABLE ap_titles
  ADD INDEX idx_ap_status_due (status, due_date),
  ADD INDEX idx_ap_paid_at (paid_at);
