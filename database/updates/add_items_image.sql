-- Adiciona imagem de produto para uso no site
ALTER TABLE items
  ADD COLUMN image_path VARCHAR(255) NULL AFTER colors;
