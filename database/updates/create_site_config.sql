-- Tabela para configurações do site (gerenciadas pelo Marketing)

CREATE TABLE IF NOT EXISTS site_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(100) UNIQUE NOT NULL,
  config_value TEXT,
  config_type ENUM('text', 'textarea', 'image', 'number', 'boolean') DEFAULT 'text',
  description VARCHAR(255),
  category VARCHAR(50) DEFAULT 'geral',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para imagens do Hero
CREATE TABLE IF NOT EXISTS site_hero_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image_path VARCHAR(255) NOT NULL,
  title VARCHAR(255),
  subtitle TEXT,
  button_text VARCHAR(100),
  button_link VARCHAR(255),
  order_num INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para produtos em destaque no site
CREATE TABLE IF NOT EXISTS site_featured_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL COMMENT 'Nome do grupo, ex: Cartão de Visita',
  image_path VARCHAR(255) COMMENT 'Imagem representativa da categoria',
  description TEXT,
  order_num INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relação entre produtos destaque e produtos reais
CREATE TABLE IF NOT EXISTS site_featured_products_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  featured_id INT NOT NULL,
  item_id INT NOT NULL,
  order_num INT DEFAULT 0,
  FOREIGN KEY (featured_id) REFERENCES site_featured_products(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  UNIQUE KEY unique_featured_item (featured_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir configurações padrão do site
INSERT INTO site_config (config_key, config_value, config_type, description, category) VALUES
('hero_title', 'Impressão Profissional com Qualidade', 'text', 'Título principal do Hero', 'hero'),
('hero_subtitle', 'Transforme suas ideias em realidade. Gráfica rápida, moderna e com os melhores preços.', 'textarea', 'Subtítulo do Hero', 'hero'),
('hero_button_text', 'Ver Produtos', 'text', 'Texto do botão do Hero', 'hero'),
('stats_clientes', '1000+', 'text', 'Número de clientes satisfeitos', 'estatisticas'),
('stats_prazo', '48h', 'text', 'Prazo de entrega', 'estatisticas'),
('stats_experiencia', '15', 'text', 'Anos de experiência', 'estatisticas'),
('diferencial_1_titulo', 'Entrega Rápida', 'text', 'Título diferencial 1', 'diferenciais'),
('diferencial_1_texto', 'Produção e entrega em até 48 horas. Pedidos urgentes são nossa especialidade!', 'textarea', 'Texto diferencial 1', 'diferenciais'),
('diferencial_2_titulo', 'Qualidade Premium', 'text', 'Título diferencial 2', 'diferenciais'),
('diferencial_2_texto', 'Equipamentos modernos e materiais de primeira linha para resultados impecáveis.', 'textarea', 'Texto diferencial 2', 'diferenciais'),
('diferencial_3_titulo', 'Melhor Preço', 'text', 'Título diferencial 3', 'diferenciais'),
('diferencial_3_texto', 'Preços competitivos sem comprometer a qualidade. Consulte nossos pacotes!', 'textarea', 'Texto diferencial 3', 'diferenciais'),
('footer_telefone', '(11) 9999-9999', 'text', 'Telefone do rodapé', 'contato'),
('footer_email', 'contato@midiacerta.com.br', 'text', 'Email do rodapé', 'contato'),
('footer_endereco', 'Endereço da gráfica', 'textarea', 'Endereço do rodapé', 'contato')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value);

-- Inserir produtos destaque padrão (Cartão de Visita e Panfletos)
INSERT INTO site_featured_products (category_name, description, order_num, active) VALUES
('Cartão de Visita', 'Impressão profissional de cartões de visita em diversos acabamentos', 1, 1),
('Panfletos', 'Panfletos de qualidade para divulgar seu negócio', 2, 1)
ON DUPLICATE KEY UPDATE description=VALUES(description);

SELECT 'Tabelas de configuração do site criadas com sucesso!' as resultado;
