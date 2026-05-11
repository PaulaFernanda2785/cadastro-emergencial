CREATE TABLE IF NOT EXISTS recomecar_analise_atribuicoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    familia_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    atribuido_por BIGINT UNSIGNED NULL,
    estrategia VARCHAR(40) NOT NULL DEFAULT 'periodo',
    filtros_hash CHAR(64) NULL,
    filtros_json TEXT NULL,
    concluido_em DATETIME NULL,
    concluido_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_recomecar_atribuicao_familia FOREIGN KEY (familia_id) REFERENCES familias(id),
    CONSTRAINT fk_recomecar_atribuicao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_recomecar_atribuicao_autor FOREIGN KEY (atribuido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_recomecar_atribuicao_concluido_por FOREIGN KEY (concluido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_recomecar_atribuicao_familia (familia_id),
    KEY idx_recomecar_atribuicao_usuario (usuario_id),
    KEY idx_recomecar_atribuicao_autor_usuario (atribuido_por, usuario_id),
    KEY idx_recomecar_atribuicao_concluido (concluido_em),
    KEY idx_recomecar_atribuicao_estrategia (estrategia),
    KEY idx_recomecar_atribuicao_atualizado (atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE recomecar_analise_atribuicoes
    ADD COLUMN IF NOT EXISTS concluido_em DATETIME NULL AFTER filtros_json,
    ADD COLUMN IF NOT EXISTS concluido_por BIGINT UNSIGNED NULL AFTER concluido_em,
    ADD INDEX IF NOT EXISTS idx_recomecar_atribuicao_autor_usuario (atribuido_por, usuario_id),
    ADD INDEX IF NOT EXISTS idx_recomecar_atribuicao_concluido (concluido_em);
