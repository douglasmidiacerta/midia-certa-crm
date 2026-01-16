-- Adicionar permissões para Marketing
-- Permite controlar quem pode acessar o painel de Marketing

-- Verificar se já existem e adicionar se não
INSERT INTO permissions (resource, action, description, created_at) VALUES
('marketing', 'view', 'Visualizar painel de Marketing', NOW()),
('marketing', 'edit', 'Editar configurações do site', NOW()),
('marketing', 'upload', 'Upload de imagens (Hero/Banner)', NOW()),
('marketing', 'manage_featured', 'Gerenciar produtos em destaque', NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Por padrão, dar acesso ao admin
-- (assumindo que existe um usuário admin com role='admin')

SELECT 'Permissões de Marketing adicionadas!' as resultado;
