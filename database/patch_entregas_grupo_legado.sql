SET NAMES utf8mb4;

-- Agrupa entregas antigas registradas no mesmo momento para a mesma familia.
-- Mantem os codigos de item existentes, mas define um unico codigo publico de ticket.
UPDATE entregas_ajuda e
INNER JOIN (
    SELECT
        familia_id,
        entregue_por,
        data_entrega,
        COALESCE(observacao, '') AS observacao_chave,
        MIN(comprovante_codigo) AS codigo_grupo,
        COUNT(*) AS total_itens
    FROM entregas_ajuda
    WHERE deleted_at IS NULL
      AND (grupo_comprovante_codigo IS NULL OR grupo_comprovante_codigo = '')
    GROUP BY familia_id, entregue_por, data_entrega, COALESCE(observacao, '')
    HAVING COUNT(*) > 1
) grupos ON grupos.familia_id = e.familia_id
    AND grupos.entregue_por = e.entregue_por
    AND grupos.data_entrega = e.data_entrega
    AND grupos.observacao_chave = COALESCE(e.observacao, '')
SET e.grupo_comprovante_codigo = grupos.codigo_grupo
WHERE e.deleted_at IS NULL
  AND (e.grupo_comprovante_codigo IS NULL OR e.grupo_comprovante_codigo = '');
