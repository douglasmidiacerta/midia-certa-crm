-- ============================================
-- LIMPAR SLIDES DUPLICADOS DO CAROUSEL
-- Execute este SQL no phpMyAdmin
-- ============================================

-- Deletar todos os slides duplicados, mantendo apenas os 3 primeiros
DELETE FROM `carousel_slides` 
WHERE `id` NOT IN (
    SELECT * FROM (
        SELECT MIN(`id`) 
        FROM `carousel_slides` 
        GROUP BY `title`, `order_num`
    ) as temp
);

-- Verificar resultado
SELECT 
    'LIMPEZA CONCLUÍDA! Slides restantes:' as Mensagem, 
    COUNT(*) as Total 
FROM `carousel_slides`;

-- Listar os slides que sobraram
SELECT 
    `id`, 
    `order_num` as Ordem, 
    `title` as Título, 
    CASE WHEN `active` = 1 THEN 'Ativo' ELSE 'Inativo' END as Status
FROM `carousel_slides` 
ORDER BY `order_num`;
