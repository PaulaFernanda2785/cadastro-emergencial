CREATE DATABASE IF NOT EXISTS u696029111_cedec

representante_nome VARCHAR(180) NULL,

representante_cpf VARCHAR(14) NULL,

representante_rg VARCHAR(30) NULL,

representante_telefone VARCHAR(30) NULL,

criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

deleted_at DATETIME NULL,

CONSTRAINT fk_familias_residencia FOREIGN KEY (residencia_id) REFERENCES residencias(id),

INDEX idx_familias_cpf (responsavel_cpf),

INDEX idx_familias_residencia (residencia_id),

INDEX idx_familias_deleted (deleted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

CREATE TABLE documentos_anexos (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

familia_id BIGINT UNSIGNED NULL,

residencia_id BIGINT UNSIGNED NULL,

tipo_documento VARCHAR(80) NOT NULL,

caminho_arquivo VARCHAR(255) NOT NULL,

mime_type VARCHAR(100) NOT NULL,

tamanho_bytes BIGINT UNSIGNED NOT NULL,

enviado_por BIGINT UNSIGNED NOT NULL,

criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

CONSTRAINT fk_docs_familia FOREIGN KEY (familia_id) REFERENCES familias(id),

CONSTRAINT fk_docs_residencia FOREIGN KEY (residencia_id) REFERENCES residencias(id),

CONSTRAINT fk_docs_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

CREATE TABLE tipos_ajuda (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

nome VARCHAR(180) NOT NULL UNIQUE,

unidade_medida VARCHAR(50) NOT NULL,

ativo TINYINT(1) NOT NULL DEFAULT 1,

criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

CREATE TABLE entregas_ajuda (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

familia_id BIGINT UNSIGNED NOT NULL,

tipo_ajuda_id BIGINT UNSIGNED NOT NULL,

quantidade DECIMAL(10,2) NOT NULL DEFAULT 1.00,

data_entrega DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

entregue_por BIGINT UNSIGNED NOT NULL,

comprovante_codigo VARCHAR(80) NOT NULL UNIQUE,

observacao TEXT NULL,

CONSTRAINT fk_entregas_familia FOREIGN KEY (familia_id) REFERENCES familias(id),

CONSTRAINT fk_entregas_tipo FOREIGN KEY (tipo_ajuda_id) REFERENCES tipos_ajuda(id),

CONSTRAINT fk_entregas_usuario FOREIGN KEY (entregue_por) REFERENCES usuarios(id),

INDEX idx_entregas_tipo_data (tipo_ajuda_id, data_entrega),

INDEX idx_entregas_familia (familia_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

CREATE TABLE tokens_idempotencia (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

token VARCHAR(100) NOT NULL UNIQUE,

usuario_id BIGINT UNSIGNED NULL,

rota VARCHAR(180) NOT NULL,

processado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

ip_origem VARCHAR(45) NULL,

CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),

INDEX idx_tokens_rota_data (rota, processado_em)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  

CREATE TABLE logs_sistema (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

usuario_id BIGINT UNSIGNED NULL,

acao VARCHAR(100) NOT NULL,

entidade VARCHAR(100) NOT NULL,

entidade_id BIGINT UNSIGNED NULL,

descricao TEXT NULL,

ip_origem VARCHAR(45) NULL,

user_agent VARCHAR(255) NULL,

criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),

INDEX idx_logs_usuario_data (usuario_id, criado_em),

INDEX idx_logs_entidade (entidade, entidade_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;